<?php

namespace Detain\RateLimit;

/**
 * Token Bucket rate limiter.
 *
 * @author Peter Chung <touhonoob@gmail.com>
 * @date May 16, 2015
 */
class RateLimit
{
    /** @var string */
    private string $name;

    /** @var int */
    private int $maxRequests;

    /** @var int */
    private int $period;

    /** @var Adapter */
    private Adapter $adapter;

    /**
     * @param string  $name        Prefix used in storage keys
     * @param int     $maxRequests Maximum requests per period
     * @param int     $period      Seconds in the rate limit window
     * @param Adapter $adapter     Storage adapter
     */
    public function __construct(string $name, int $maxRequests, int $period, Adapter $adapter)
    {
        $this->name = $name;
        $this->maxRequests = $maxRequests;
        $this->period = $period;
        $this->adapter = $adapter;
    }

    /**
     * Check if a request with given $id should be allowed.
     *
     * @param string $id  Unique identifier for the caller
     * @param float  $use Number of tokens to consume (default 1.0)
     *
     * @return bool True if allowed, false if rate limited
     *
     * @see https://stackoverflow.com/a/668327/670662
     */
    public function check(string $id, float $use = 1.0): bool
    {
        if ($use < 0) {
            throw new \InvalidArgumentException('$use must be >= 0');
        }

        /** @var float $rate Tokens per second */
        $rate = $this->maxRequests / $this->period;

        $t_key = $this->keyTime($id);
        $a_key = $this->keyAllow($id);

        if (!$this->adapter->exists($t_key)) {
            // First hit: set up storage; allow.
            /** @var float $now */
            $now = microtime(true);
            $this->adapter->set($t_key, $now, $this->period);
            $this->adapter->set($a_key, ($this->maxRequests - $use), $this->period);
            return true;
        }

        /** @var float $cTime */
        $cTime = microtime(true);

        /** @var float $tStored */
        $tStored = $this->adapter->get($t_key);
        $time_passed = $cTime - $tStored;
        $this->adapter->set($t_key, $cTime, $this->period);

        /** @var float $allowance */
        $allowance = $this->adapter->get($a_key);
        $allowance = $allowance + $time_passed * $rate;

        if ($allowance > $this->maxRequests) {
            $allowance = (float) $this->maxRequests;
        }

        if ($allowance < $use) {
            // Need to wait for more tokens to be available.
            $this->adapter->set($a_key, $allowance, $this->period);
            return false;
        }

        $this->adapter->set($a_key, $allowance - $use, $this->period);
        return true;
    }

    /**
     * Get the number of requests that can still be made before the limit is hit.
     *
     * @param string $id Unique identifier for the caller
     *
     * @return int Number of requests allowed before rate limit
     */
    public function getAllowance(string $id): int
    {
        $t_key = $this->keyTime($id);
        $a_key = $this->keyAllow($id);

        if (!$this->adapter->exists($t_key)) {
            return $this->maxRequests;
        }

        /** @var float $rate Tokens per second */
        $rate = $this->maxRequests / $this->period;

        /** @var float $tStored */
        $tStored = $this->adapter->get($t_key);
        $time_passed = microtime(true) - $tStored;

        /** @var float $allowance */
        $allowance = $this->adapter->get($a_key);
        $allowance = $allowance + $time_passed * $rate;

        return (int) max(0, floor(min($allowance, (float) $this->maxRequests)));
    }

    /**
     * @deprecated use getAllowance() instead.
     *
     * @param string $id
     *
     * @return int
     */
    public function getAllow(string $id): int
    {
        return $this->getAllowance($id);
    }

    /**
     * Purge rate limit record for $id.
     *
     * @param string $id
     *
     * @return void
     */
    public function purge(string $id): void
    {
        $this->adapter->del($this->keyTime($id));
        $this->adapter->del($this->keyAllow($id));
    }

    /**
     * @param string $id
     *
     * @return string
     */
    private function keyTime(string $id): string
    {
        return $this->name . ':' . $id . ':time';
    }

    /**
     * @param string $id
     *
     * @return string
     */
    private function keyAllow(string $id): string
    {
        return $this->name . ':' . $id . ':allow';
    }

    // ─────────────────────────────────────────────────────────────
    // Mutators (for testing / dynamic configuration)
    // ─────────────────────────────────────────────────────────────

    /** @return void */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** @return void */
    public function setMaxRequests(int $maxRequests): void
    {
        $this->maxRequests = $maxRequests;
    }

    /** @return void */
    public function setPeriod(int $period): void
    {
        $this->period = $period;
    }

    /** @return void */
    public function setAdapter(Adapter $adapter): void
    {
        $this->adapter = $adapter;
    }
}
