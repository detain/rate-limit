<?php

namespace Detain\RateLimit;

/**
 * @author Peter Chung <touhonoob@gmail.com>
 * @date May 16, 2015
 */
abstract class Adapter
{
    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl seconds after which this entry will expire
     *
     * @return bool
     */
    abstract public function set($key, $value, int $ttl): bool;

    /**
     * @param string $key
     *
     * @return mixed
     */
    abstract public function get($key);

    /**
     * @param string $key
     *
     * @return bool
     */
    abstract public function exists($key): bool;

    /**
     * @param string $key
     *
     * @return bool
     */
    abstract public function del($key): bool;
}
