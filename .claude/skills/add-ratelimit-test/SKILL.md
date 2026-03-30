---
name: add-ratelimit-test
description: Adds PHPUnit tests following `tests/RateLimitTest.php` patterns: extension checks with `markTestSkipped()`, `purge()` before each run, loop assertions against `getAllowance()`. Use when user says 'write test', 'add test for adapter', 'test the rate limiter', or creates files in `tests/`. Do NOT use for non-rate-limit test files.
---
# add-ratelimit-test

## Critical

- Every adapter test method MUST call `$rateLimit->purge($label)` before assertions — stale storage state from prior runs causes false failures.
- Every test method for an extension-backed adapter (APCu, Redis, Memcached) MUST guard with `$this->markTestSkipped()` when the extension is absent — never let a missing extension cause a hard failure.
- Use `uniqid('label', true)` for the label to prevent parallel-run key collisions.
- Call `composer test` (`phpunit.xml.dist`) to verify — not a custom runner.

## Instructions

1. **Create the test class** in `tests/` under namespace `Detain\RateLimit\Tests`, extending `PHPUnit\Framework\TestCase`. Name it `{AdapterName}Test.php` for new adapters, or add methods to `tests/RateLimitTest.php` for existing adapters.
   ```php
   namespace Detain\RateLimit\Tests;
   use Detain\RateLimit\Adapter;
   use Detain\RateLimit\RateLimit;
   use PHPUnit\Framework\TestCase;
   class RateLimitTest extends TestCase {
       public const NAME = "RateLimitTest";
       public const MAX_REQUESTS = 10;
       public const PERIOD = 2;
   }
   ```
   Verify the file is inside `./tests/` (scanned by `<directory>./tests</directory>` in `phpunit.xml.dist`).

2. **Add a `testCheck{Adapter}()` method** for each adapter. Pattern:
   - Add `@requires extension {ext}` docblock for extension-backed adapters.
   - Check `extension_loaded('{ext}')` and call `$this->markTestSkipped(...)` if absent.
   - For APCu also check `ini_get('apc.enable_cli') == 0` and skip.
   - For network services (Redis, Memcached): read host from `getenv('REDIS_HOST')` / `getenv('MEMCACHE_HOST')`, fallback to `'localhost'`; wrap connection in `try/catch` and call `$this->markTestSkipped()` on failure.
   - Flush/clear the backend before constructing the adapter (`$redis->flushDB()`, `$predis->flushdb()`, `$stash->clear()`).
   - Construct the adapter, pass to `$this->check($adapter)`.

3. **Implement the shared `check(Adapter $adapter)` private method** — do NOT duplicate assertion logic per adapter:
   ```php
   private function check($adapter) {
       $label = uniqid('label', true);
       $rateLimit = $this->getRateLimit($adapter);
       $rateLimit->purge($label);                    // Step 1: purge stale state
       $this->assertEquals(self::MAX_REQUESTS, $rateLimit->getAllowance($label));
       for ($i = 0; $i < self::MAX_REQUESTS; $i++) {
           $this->assertEquals(self::MAX_REQUESTS - $i, $rateLimit->getAllowance($label));
           $this->assertTrue($rateLimit->check($label));
       }
       $this->assertFalse($rateLimit->check($label), 'Bucket should be empty');
       $this->assertEquals(0, $rateLimit->getAllowance($label), 'Bucket should be empty');
       sleep(self::PERIOD);                          // wait for token refill
       $this->assertEquals(self::MAX_REQUESTS, $rateLimit->getAllowance($label));
       $this->assertTrue($rateLimit->check($label));
   }
   private function getRateLimit(Adapter $adapter) {
       return new RateLimit(self::NAME . uniqid(), self::MAX_REQUESTS, self::PERIOD, $adapter);
   }
   ```
   Verify `check()` and `getRateLimit()` are `private`, not `public`.

4. **Run the suite** and confirm the new test appears and passes (or skips cleanly):
   ```bash
   composer test
   ```
   A skip is acceptable; a hard error (`E`) or failure (`F`) is not.

## Examples

**User says:** "Add a test for the new ArrayAdapter"

**Actions taken:**
- Add method to `tests/RateLimitTest.php` (no extension required, no skip guard needed):
```php
public function testCheckArray() {
    $adapter = new Adapter\ArrayAdapter();
    $this->check($adapter);
}
```
- Reuse existing `check()` and `getRateLimit()` — no changes needed there.
- Run `composer test` — new test executes and passes.

**User says:** "Write a test for the Memcached adapter"

```php
public function testCheckMemcached() {
    if (!extension_loaded('memcached')) {
        $this->markTestSkipped('memcached extension not installed');
    }
    $memcache_host = getenv('MEMCACHE_HOST') ?: 'localhost';
    $m = new \Memcached();
    $m->addServer($memcache_host, 11211);
    $adapter = new Adapter\Memcached($m);
    $this->check($adapter);
}
```

## Common Issues

- **`Error: Class 'Redis' not found`** — extension not loaded; the test must have called `markTestSkipped()` before constructing `new \Redis()`. Move the skip check above the instantiation.
- **`AssertionError: Bucket should be empty` on first run** — missing `$rateLimit->purge($label)` at the top of `check()`. Add it immediately after constructing `$rateLimit`.
- **`apc.enable_cli` skip not triggering** — `ini_get('apc.enable_cli')` returns `'0'` (string), so use `== 0` not `=== 0` or `=== false`.
- **Test not discovered** — file is outside `./tests/` or class does not extend `TestCase`. Confirm placement and `use PHPUnit\Framework\TestCase`.
- **Parallel test key collisions** — label must use `uniqid('label', true)` (second arg `true` adds entropy); plain `uniqid()` can collide under fast parallel runs.