<?php

namespace Detain\RateLimit\Tests;

use Detain\RateLimit\Adapter;
use Detain\RateLimit\RateLimit;
use PHPUnit\Framework\TestCase;

/**
 * @author Peter Chung <touhonoob@gmail.com>
 * @date May 16, 2015
 */
class RateLimitTest extends TestCase
{
    public const NAME = "RateLimitTest";
    public const MAX_REQUESTS = 10;
    public const PERIOD = 2;

    /**
     * @requires extension apcu
     */
    public function testCheckAPCu()
    {
        if (!extension_loaded('apcu')) {
            $this->markTestSkipped("apcu extension not installed");
        }
        if (ini_get('apc.enable_cli') == 0) {
            $this->markTestSkipped("apc.enable_cli != 1; can't change at runtime");
        }
        $adapter = new Adapter\APCu();
        $this->check($adapter);
    }

    /**
     * @requires extension redis
     */
    public function testCheckRedis()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped("redis extension not installed");
        }

        $redis_host = getenv('REDIS_HOST');

        if ($redis_host === false) {
            $redis_host = 'localhost';
        }

        try {
            $redis = new \Redis();
            $redis->connect($redis_host);
            $redis->flushDB(); // clear redis db
        } catch (\RedisException $e) {
            error_log("Failed to connect to redis? " . $e->getMessage());
            $this->markTestSkipped("Failed to connect to redis? " . $e->getMessage());
        }

        $adapter = new Adapter\Redis($redis);
        $this->check($adapter);
    }

    public function testCheckPredis()
    {
        $redis_host = getenv('REDIS_HOST');

        if ($redis_host === false) {
            $redis_host = 'localhost';
        }

        try {
            $predis = new \Predis\Client(
                [
                'scheme' => 'tcp',
                    'host' => $redis_host,
                'port' => 6379,
                'cluster' => false,
                'database' => 1
            ]
            );
            $predis->flushdb(); // clear redis db.
            $adapter = new Adapter\Predis($predis);
        } catch (\Predis\Connection\ConnectionException $e) {
            error_log("Failed to connect to (p)redis : " . $e->getMessage());
            $this->markTestSkipped("Could not connect to (p)redis");
        }
        $this->check($adapter);
    }

    public function testCheckStash()
    {
        $stash = new \Stash\Pool(); // ephermeral driver by default
        $stash->clear();
        $adapter = new Adapter\Stash($stash);
        $this->check($adapter);
    }

    public function testCheckMemcached()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped("memcached extension not installed");
        }

        $memcache_host = getenv('MEMCACHE_HOST') ?: 'localhost';
        $m = new \Memcached();
        $m->addServer($memcache_host, 11211);

        // Verify the server is actually reachable before running assertions
        $m->set('_ratelimit_ping', 1, 1);
        if ($m->getResultCode() !== \Memcached::RES_SUCCESS) {
            $this->markTestSkipped("Could not connect to Memcached at {$memcache_host}:11211");
        }

        $adapter = new Adapter\Memcached($m);
        $this->check($adapter);
    }


    private function check($adapter)
    {
        $label = uniqid("label", true); // should stop storage conflicts if tests are running in parallel.
        $rateLimit = $this->getRateLimit($adapter);

        $rateLimit->purge($label); // make sure a previous failed test doesn't mess up this one .

        $this->assertEquals(self::MAX_REQUESTS, $rateLimit->getAllowance($label));

        // All should work, but bucket will be empty at the end.
        for ($i = 0; $i < self::MAX_REQUESTS; $i++) {
            // Calling check reduces the counter each time.
            $this->assertEquals(self::MAX_REQUESTS - $i, $rateLimit->getAllowance($label));
            $this->assertTrue($rateLimit->check($label));
        }

        // bucket empty.
        $this->assertFalse($rateLimit->check($label), "Bucket should be empty");
        $this->assertEquals(0, $rateLimit->getAllowance($label), "Bucket should be empty");

        //Wait for PERIOD seconds, bucket should refill.
        sleep(self::PERIOD);
        $this->assertEquals(self::MAX_REQUESTS, $rateLimit->getAllowance($label));
        $this->assertTrue($rateLimit->check($label));
    }

    private function getRateLimit(Adapter $adapter)
    {
        return new RateLimit(self::NAME . uniqid(), self::MAX_REQUESTS, self::PERIOD, $adapter);
    }
}
