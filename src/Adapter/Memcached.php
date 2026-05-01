<?php

namespace Detain\RateLimit\Adapter;

/**
 * Memcached adapter for rate limiting storage.
 */
class Memcached extends \Detain\RateLimit\Adapter
{
    /** @var \Memcached */
    protected \Memcached $memcached;

    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
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
        return $this->memcached->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get($key): mixed
    {
        $val = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }
        // Memcached can return false for non-existent keys (before getResultCode check above)
        // and various scalar values for existing keys.
        /** @var mixed */
        return $val;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists($key): bool
    {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function del($key): bool
    {
        /** @phpstan-ignore-next-line */
        return $this->memcached->delete($key);
    }
}
