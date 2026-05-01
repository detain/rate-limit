<?php

namespace Detain\RateLimit\Adapter;

/**
 * Native ext-redis adapter for rate limiting storage.
 *
 * @author Peter Chung <touhonoob@gmail.com>
 */
class Redis extends \Detain\RateLimit\Adapter
{
    /** @var \Redis */
    protected \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     *
     * @return bool
     */
    public function set($key, $value, int $ttl): bool
    {
        /** @phpstan-ignore-next-line */
        return $this->redis->set($key, (string) $value, $ttl) !== false;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get($key): mixed
    {
        /** @var string|false $val */
        $val = $this->redis->get($key);
        /** @phpstan-ignore-next-line */
        return $val !== false ? (float) $val : 0.0;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists($key): bool
    {
        /** @var int $n */
        $n = $this->redis->exists($key);
        // @phpstan-ignore-next-line
        return $n > 0;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function del($key): bool
    {
        /** @var int|false $result */
        $result = $this->redis->del($key);
        /** @phpstan-ignore-next-line */
        return (int) ($result ?? 0) > 0;
    }
}
