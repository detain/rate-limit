---
name: new-adapter
description: Creates a new storage adapter in `src/Adapter/` by extending `src/Adapter.php`. Implements `set()`, `get()`, `exists()`, `del()` following patterns from existing adapters. Use when user says 'add adapter', 'new backend', 'support X storage', or needs a new file in `src/Adapter/`. Do NOT use for modifying existing adapters.
---
# New Storage Adapter

## Critical

- All four abstract methods **must** be implemented: `set($key, $value, $ttl)`, `get($key)`, `exists($key)`, `del($key)` — missing any will cause a fatal error at runtime.
- `get()` **must** return `(float)` cast value so Token Bucket math works correctly.
- Class must extend `\Detain\RateLimit\Adapter` (not the interface — it is abstract).
- If the backing extension is optional, guard the test method with `$this->markTestSkipped()` (see Step 4).
- Run `composer check-format` before committing; run `composer format` to auto-fix PSR-2 violations.

## Instructions

1. **Create the adapter class** at `src/Adapter/ClassName.php`. Use the namespace `Detain\RateLimit\Adapter` and extend `\Detain\RateLimit\Adapter`:

```php
<?php

namespace Detain\RateLimit\Adapter;

class MyBackend extends \Detain\RateLimit\Adapter
{
    /**
     * @var \MyBackendClient
     */
    protected $client;

    public function __construct(\MyBackendClient $client)
    {
        $this->client = $client;
    }

    public function set($key, $value, $ttl)
    {
        return (bool) $this->client->set($key, (string) $value, $ttl);
    }

    public function get($key)
    {
        return (float) $this->client->get($key);
    }

    public function exists($key)
    {
        return $this->client->exists($key) == true;
    }

    public function del($key)
    {
        return $this->client->del($key) > 0;
    }
}
```

   Verify: file saved at `src/Adapter/MyBackend.php`, class name matches filename.

2. **Check the backing client's API** — map its methods to the four adapter methods:
   - `set`: pass TTL as seconds (integer); cast `$value` to `string` if the client requires it (see `Redis.php:26`).
   - `get`: always cast return to `(float)`. If the client can return `false`, cast `false` → `0.0` (acceptable).
   - `exists`: reduce to a `bool` expression (`== true`, `!== false`, `> 0`).
   - `del`: reduce to a `bool` expression (check `> 0` or return the client bool directly).

   Verify: all four methods return the types declared in `src/Adapter.php` (`bool`/`float`/`bool`/`bool`).

3. **If the extension is optional** (like Memcached), suppress Psalm errors in `psalm.xml`:

```xml
<issueHandlers>
    <UndefinedClass>
        <errorLevel type="suppress">
            <referencedClass name="MyBackendClient"/>
        </errorLevel>
    </UndefinedClass>
</issueHandlers>
```

   Verify: `composer psalm` passes without new errors.

4. **Add a test method** in `tests/RateLimitTest.php` following the extension-guard pattern:

```php
/**
 * @requires extension mybackend
 */
public function testCheckMyBackend()
{
    if (!extension_loaded('mybackend')) {
        $this->markTestSkipped("mybackend extension not installed");
    }
    $client = new \MyBackendClient();
    $client->connect('localhost');
    $adapter = new Adapter\MyBackend($client);
    $this->check($adapter);
}
```

   Verify: `composer test` runs without fatal errors (skipped is acceptable if extension absent).

5. **Run the full pipeline**: `composer build` — this runs lint, format-check, psalm, phpstan, and tests in sequence.

## Examples

**User says:** "Add an APCu adapter" → already exists at `src/Adapter/APCu.php`; no constructor needed since APCu uses global functions:

```php
class APCu extends \Detain\RateLimit\Adapter
{
    public function set($key, $value, $ttl) { return apcu_store($key, $value, $ttl); }
    public function get($key)              { return apcu_fetch($key); }
    public function exists($key)           { return apcu_exists($key); }
    public function del($key)              { return apcu_delete($key); }
}
```

**User says:** "Add a Redis adapter" → inject `\Redis` via constructor, cast `get()` to float, `del()` returns `> 0` (see `src/Adapter/Redis.php`).

## Common Issues

- **`Cannot instantiate abstract class Detain\RateLimit\Adapter`** — you named your class `Adapter` or forgot `extends \Detain\RateLimit\Adapter`. Rename and re-check.
- **Token Bucket always allows / blocks incorrectly** — `get()` is not returning a `float`. Add `(float)` cast explicitly.
- **`composer psalm` reports `UndefinedClass`** — add a `<referencedClass>` suppress entry in `psalm.xml` (Step 3).
- **`composer check-format` fails** — run `composer format` to auto-apply PSR-2 fixes, then re-run `composer check-format`.
- **Test not skipped when extension absent, causes fatal** — ensure `extension_loaded()` check with `markTestSkipped()` comes before any `new \ExtensionClass()` instantiation.