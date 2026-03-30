---
name: leaky-bucket-usage
description: Demonstrates LeakyBucket construction and usage: instantiate with key/adapter/settings, call fill(), leak(), isFull(), save(), reset(). Shows capacity/leak settings and LEAKY_BUCKET_KEY_PREFIX/POSTFIX constants. Use when user says 'use leaky bucket', 'leaky bucket example', 'implement throttle with leaky bucket', 'rate limit with leaky bucket'. Do NOT trigger for Token Bucket (RateLimit class) usage.
---
# Leaky Bucket Usage

## Critical

- Always call `leak()` before `isFull()` to drain elapsed drops first — skipping this causes stale full-bucket reads.
- Always call `save()` after mutating the bucket or the state is lost between requests.
- `capacity` must be an integer > 0; `leak` is drops-per-second as a float (default `0.33`).
- Only `capacity` and `leak` are valid settings keys — others are silently dropped via `array_intersect_key`.
- Storage key format is fixed: `leakybucket:v1:{$key}:bucket` — never construct this manually; let the class handle it.

## Instructions

1. **Choose and instantiate a storage adapter** from `src/Adapter/`. Verify the required PHP extension is available before constructing.
   ```php
   // Redis
   $redis = new \Redis();
   $redis->connect('127.0.0.1', 6379);
   $adapter = new \Detain\RateLimit\Adapter\Redis($redis);

   // Memcached
   $m = new \Memcached();
   $m->addServer('localhost', 11211);
   $adapter = new \Detain\RateLimit\Adapter\Memcached($m);

   // APCu (CLI: requires apc.enable_cli=1 in tests/php.ini)
   $adapter = new \Detain\RateLimit\Adapter\APCu();
   ```

2. **Instantiate `LeakyBucket`** with a unique string key, the adapter, and optional settings. Verify `$key` uniquely identifies the rate-limited resource (e.g., user ID, IP).
   ```php
   use Detain\RateLimit\LeakyBucket;

   $bucket = new LeakyBucket(
       'user:42:upload',   // unique key — no prefix needed, class adds leakybucket:v1: prefix
       $adapter,
       [
           'capacity' => 10,   // max drops before full
           'leak'     => 0.33, // drops leaked per second
       ]
   );
   ```

3. **Apply leakage for elapsed time**, then check fullness, then fill on each incoming request:
   ```php
   $bucket->leak();          // drain drops based on time since last save()

   if ($bucket->isFull()) {
       // reject / throttle the request
       http_response_code(429);
       exit;
   }

   $bucket->fill();          // add 1 drop (pass int > 0 for weighted cost)
   $bucket->save();          // persist state + update timestamp
   ```

4. **Inspect bucket state** for diagnostics or response headers:
   ```php
   $bucket->getCapacity();      // total capacity (float)
   $bucket->getCapacityUsed();  // current drops (float)
   $bucket->getCapacityLeft();  // remaining capacity (float)
   $bucket->getLeak();          // configured leak rate (float)
   $bucket->getLastTimestamp(); // microtime(true) of last save()
   ```

5. **Reset** the bucket (e.g., after ban lifted or in tests):
   ```php
   $bucket->reset(); // deletes key from storage
   ```

6. **Verify** with `composer test` — add `$this->markTestSkipped()` if the required extension is absent (see `tests/RateLimitTest.php` pattern).

## Examples

**User says:** "Add leaky bucket throttling for API uploads, 10 requests max, leaking 1/sec"

**Actions taken:**
```php
use Detain\RateLimit\LeakyBucket;
use Detain\RateLimit\Adapter\Redis;

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$adapter = new Redis($redis);

$bucket = new LeakyBucket(
    "api:upload:{$userId}",
    $adapter,
    ['capacity' => 10, 'leak' => 1]
);

$bucket->leak();

if ($bucket->isFull()) {
    http_response_code(429);
    header('X-RateLimit-Remaining: 0');
    exit;
}

$bucket->fill()->save();
header('X-RateLimit-Remaining: ' . floor($bucket->getCapacityLeft()));
```

**Result:** Bucket key stored as `leakybucket:v1:api:upload:42:bucket`, capacity 10, drains 1 drop/sec, rejects when full.

## Common Issues

- **`isFull()` always returns `true` on every request`:** You forgot to call `leak()` before `isFull()`. Leakage is not automatic — call `$bucket->leak()` at the start of each request.
- **State not persisting between requests:** You called `fill()` but not `save()`. `save()` is required to write state to storage and update the timestamp.
- **`InvalidArgumentException: The parameter "$drops" has to be an integer greater than 0`:** Passed `0` or a negative number to `fill()`. Always pass a positive integer.
- **APCu not working in CLI/tests:** Add `apc.enable_cli=1` to `tests/php.ini` and load it with `phpenv config-add tests/php.ini` (see `.travis.yml`).
- **Setting `'rate'` or other unknown key has no effect:** Only `capacity` and `leak` are accepted. Other keys are silently dropped by `array_intersect_key`. Check spelling.
- **`Could not save "$key" to storage provider`:** The storage adapter threw an exception. Verify the backend service is running (`redis-cli ping`, `memcached-tool localhost:11211 stats`).