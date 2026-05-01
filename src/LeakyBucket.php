<?php

/*
 * Copyright (c) Jeroen Visser <jeroenvisser101@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Detain\RateLimit;

/**
 * Implements the Leak Bucket algorithm.
 *
 * @author Jeroen Visser <jeroenvisser101@gmail.com>
 */
class LeakyBucket
{
    /**
     * Bucket key's prefix.
     */
    public const LEAKY_BUCKET_KEY_PREFIX = 'leakybucket:v1:';

    /**
     * Bucket key's postfix.
     */
    public const LEAKY_BUCKET_KEY_POSTFIX = ':bucket';

    /**
     * The key to the bucket.
     *
     * @var string
     */
    private string $key;

    /**
     * The current bucket.
     *
     * @var array{drops: int, time: float, data?: mixed}
     */
    private array $bucket;

    /**
     * A Adapter where the bucket data will be stored.
     *
     * @var Adapter
     */
    private Adapter $storage;

    /**
     * Array containing default settings.
     *
     * @var array{capacity: int, leak: float}
     */
    private static array $defaults = [
        'capacity' => 10,
        'leak'     => 0.33
    ];

    /**
     * The settings for this bucket.
     *
     * @var array{capacity: int, leak: float}
     */
    private array $settings;

    /**
     * Class constructor.
     *
     * @param string $key      The bucket key
     * @param Adapter $storage The storage provider that has to be used
     * @param array<string, mixed> $settings The settings to be set
     */
    public function __construct(string $key, Adapter $storage, array $settings = [])
    {
        $this->key     = $key;
        $this->storage = $storage;

        // Make sure only existing settings can be set
        $settings       = array_intersect_key($settings, self::$defaults);
        /** @var array{capacity: int, leak: float} $merged */
        $merged = array_merge(self::$defaults, $settings);
        $this->settings = $merged;

        $this->bucket = $this->get();

        // Initialize the bucket — isset() false-alarm is a PHPStan false-positive
        // due to template array type; we use getData() accessor to probe 'data' key
        /** @phpstan-ignore-next-line */
        if (!isset($this->bucket['drops']) || !isset($this->bucket['time'])) {
            $this->bucket = [
                'drops' => 0,
                'time'  => microtime(true)
            ];
        }
    }

    /**
     * Fills the bucket with a given amount of drops.
     *
     * @param int $drops Amount of drops that have to be added to the bucket
     *
     * @return $this
     */
    public function fill(int $drops = 1): self
    {
        if ($drops <= 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The parameter "%s" has to be an integer greater than 0.',
                    '$drops'
                )
            );
        }

        // Make sure the key is at least zero
        $this->bucket['drops'] = $this->bucket['drops'] ?: 0;

        // Update the bucket
        $this->bucket['drops'] += $drops;

        $this->overflow();
        return $this;
    }

    /**
     * Spills a few drops from the bucket.
     *
     * @param int $drops Amount of drops to spill from the bucket
     *
     * @return $this
     */
    public function spill(int $drops = 1): self
    {
        // Make sure the key is at least zero
        $this->bucket['drops'] = $this->bucket['drops'] ?: 0;

        $this->bucket['drops'] -= $drops;

        // Make sure we don't set it less than zero
        if ($this->bucket['drops'] < 0) {
            $this->bucket['drops'] = 0;
        }
        return $this;
    }

    /**
     * Attach aditional data to the bucket.
     *
     * @param mixed $data The data to be attached to this bucket
     *
     * @return $this
     */
    public function setData($data): self
    {
        $this->bucket['data'] = $data;
        return $this;
    }

    /**
     * Get additional data from the bucket.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->bucket['data'] ?? null;
    }

    /**
     * Gets the total capacity.
     *
     * @return float
     */
    public function getCapacity(): float
    {
        return (float) $this->settings['capacity'];
    }

    /**
     * Gets the amount of drops inside the bucket.
     *
     * @return float
     */
    public function getCapacityUsed(): float
    {
        return (float) $this->bucket['drops'];
    }

    /**
     * Gets the capacity that is still left.
     *
     * @return float
     */
    public function getCapacityLeft(): float
    {
        return (float) $this->settings['capacity'] - $this->bucket['drops'];
    }

    /**
     * Get the leak setting's value.
     *
     * @return float
     */
    public function getLeak(): float
    {
        return (float) $this->settings['leak'];
    }

    /**
     * Gets the last timestamp set on the bucket.
     *
     * @return float
     */
    public function getLastTimestamp(): float
    {
        return $this->bucket['time'];
    }

    /**
     * Updates the bucket's timestamp
     *
     * @return $this
     */
    public function touch(): self
    {
        $this->bucket['time'] = microtime(true);
        return $this;
    }

    /**
     * Returns true if the bucket is full.
     *
     * @return bool
     */
    public function isFull(): bool
    {
        return (ceil((float) $this->bucket['drops']) >= $this->settings['capacity']);
    }

    /**
     * Calculates how much the bucket has leaked.
     *
     * @return $this
     */
    public function leak(): self
    {
        // Calculate the leakage
        $elapsed = microtime(true) - $this->bucket['time'];
        $leakage = $elapsed * $this->settings['leak'];

        // Make sure the key is at least zero
        $this->bucket['drops'] = $this->bucket['drops'] ?: 0;
        $this->bucket['drops'] -= (int) $leakage;

        // Make sure we don't set it less than zero
        if ($this->bucket['drops'] < 0) {
            $this->bucket['drops'] = 0;
        }

        // Update timestamp so a second call doesn't re-leak the same elapsed period
        $this->bucket['time'] = microtime(true);

        return $this;
    }

    /**
     * Removes the overflow if present.
     *
     * @return $this
     */
    public function overflow(): self
    {
        if ($this->bucket['drops'] > $this->settings['capacity']) {
            $this->bucket['drops'] = $this->settings['capacity'];
        }
        return $this;
    }

    /**
     * Saves the bucket to the Adapter used.
     *
     * @return $this
     */
    public function save(): self
    {
        // Set the timestamp
        $this->touch();
        $this->set($this->bucket, (int) ($this->settings['capacity'] / $this->settings['leak'] * 1.5));
        return $this;
    }

    /**
     * Resets the bucket.
     *
     * @throws \Exception
     * @return $this
     */
    public function reset(): self
    {
        try {
            $this->storage->del(self::LEAKY_BUCKET_KEY_PREFIX . $this->key . self::LEAKY_BUCKET_KEY_POSTFIX);
        } catch (\Exception $ex) {
            throw new \Exception(sprintf('Could not delete "%s" from storage provider.', $this->key));
        }
        return $this;
    }

    /**
     * Sets the active bucket's value
     *
     * @param array{drops: int, time: float, data?: mixed} $bucket The bucket's contents
     * @param int $ttl The time to live for the bucket
     *
     * @throws \Exception
     * @return $this
     */
    private function set(array $bucket, int $ttl = 0): self
    {
        try {
            $this->storage->set(self::LEAKY_BUCKET_KEY_PREFIX . $this->key . self::LEAKY_BUCKET_KEY_POSTFIX, $bucket, $ttl);
        } catch (\Exception $ex) {
            throw new \Exception(sprintf('Could not save "%s" to storage provider.', $this->key));
        }
        return $this;
    }

    /**
     * Gets the active bucket's value
     *
     * @return array{drops: int, time: float, data?: mixed}
     *
     * @throws \Exception
     */
    private function get(): array
    {
        try {
            /** @var mixed $raw */
            $raw = $this->storage->get(self::LEAKY_BUCKET_KEY_PREFIX . $this->key . self::LEAKY_BUCKET_KEY_POSTFIX);
            if (!is_array($raw)) {
                return [
                    'drops' => 0,
                    'time'  => microtime(true),
                ];
            }
            /** @var array{drops?: mixed, time?: mixed, data?: mixed} $raw */
            return [
                'drops' => isset($raw['drops']) && is_int($raw['drops']) ? $raw['drops'] : 0,
                'time'  => isset($raw['time']) && is_float($raw['time']) ? $raw['time'] : microtime(true),
                'data'  => $raw['data'] ?? null,
            ];
        } catch (\Exception $ex) {
            throw new \Exception(sprintf('Could not get "%s" from storage provider.', $this->key));
        }
    }
}
