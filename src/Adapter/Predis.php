<?php

namespace Detain\RateLimit\Adapter;

/**
 * Predis adapter for rate limiting storage.
 */
class Predis extends \Detain\RateLimit\Adapter
{
    /** @var \Predis\ClientInterface */
    protected \Predis\ClientInterface $redis;

    public function __construct(\Predis\ClientInterface $client)
    {
        $this->redis = $client;
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
        return $this->redis->set($key, (string) $value, 'ex', $ttl) instanceof \Predis\Response\Status;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get($key): mixed
    {
        /** @var string|null $val */
        $val = $this->redis->get($key);
        if ($val === null) {
            return 0.0;
        }
        /** @var float|false $parsed */
        $parsed = filter_var($val, FILTER_VALIDATE_FLOAT);
        /** @phpstan-ignore-next-line */
        return $parsed !== false ? $parsed : 0.0;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists($key): bool
    {
        /** @phpstan-ignore-next-line */
        return (bool) $this->redis->exists($key);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function del($key): bool
    {
        /** @phpstan-ignore-next-line */
        return (bool) $this->redis->del([$key]);
    }
}
