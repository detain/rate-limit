<?php

namespace Detain\RateLimit\Adapter;

use Detain\RateLimit\Adapter;

/**
 * Stash adapter for rate limiting storage.
 *
 * tedivm/stash is an optional suggestion; PHPStan cannot resolve its
 * internal classes without the package being installed. Errors from this
 * adapter are suppressed.
 */
class Stash extends Adapter
{
    // phpcs:disable PHPStan
    /** @var \Stash\Pool */
    private $pool;
    // phpcs:enable

    /**
     * @param \Stash\Pool $pool
     *
     * @phpstan-ignore-next-line
     */
    public function __construct($pool)
    {
        $this->pool = $pool;
    }

    /**
     * @param string $key
     *
     * @return mixed
     *
     * @phpstan-ignore-next-line
     */
    public function get($key): mixed
    {
        $item = $this->pool->getItem($key);
        // phpstan-ignore-next-line
        $item->setInvalidationMethod(\Stash\Invalidation::OLD);

        if ($item->isHit()) {
            /** @var mixed */
            return $item->get();
        }
        return 0.0;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     *
     * @return bool
     *
     * @phpstan-ignore-next-line
     */
    public function set($key, $value, int $ttl): bool
    {
        $item = $this->pool->getItem($key);
        $item->set($value);
        $item->expiresAfter($ttl);
        // phpstan-ignore-next-line
        return $item->save() === true;
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @phpstan-ignore-next-line
     */
    public function exists($key): bool
    {
        $item = $this->pool->getItem($key);
        // phpstan-ignore-next-line
        $item->setInvalidationMethod(\Stash\Invalidation::OLD);
        // phpstan-ignore-next-line
        return $item->isHit() === true;
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @phpstan-ignore-next-line
     */
    public function del($key): bool
    {
        // phpstan-ignore-next-line
        return $this->pool->deleteItem($key) === true;
    }
}
