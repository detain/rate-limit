<?php

namespace Detain\RateLimit\Adapter;

/**
 * APCu adapter for rate limiting storage.
 */
class APCu extends \Detain\RateLimit\Adapter
{
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
        return apcu_store($key, $value, $ttl) !== false;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get($key): mixed
    {
        /** @phpstan-ignore-next-line */
        return apcu_fetch($key);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists($key): bool
    {
        /** @phpstan-ignore-next-line */
        return apcu_exists($key) !== false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function del($key): bool
    {
        /** @phpstan-ignore-next-line */
        return apcu_delete($key) !== false;
    }
}
