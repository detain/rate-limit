# detain/rate-limit

PHP rate-limiting library implementing Token Bucket (`src/RateLimit.php`) and Leaky Bucket (`src/LeakyBucket.php`) algorithms with pluggable storage adapters.

## Commands

```bash
composer build          # full pipeline: lint + format-check + psalm + phpstan + test
composer test           # PHPUnit only (phpunit.xml.dist)
composer lint           # parallel-lint syntax check
composer check-format   # php-cs-fixer dry-run (PSR-2)
composer format         # php-cs-fixer apply
composer psalm          # psalm static analysis (psalm.xml)
composer phpstan        # phpstan level 6 on src/
```

## Architecture

- **Algorithms**: `src/RateLimit.php` (Token Bucket — `check($id)`, `getAllowance($id)`, `purge($id)`) · `src/LeakyBucket.php` (Leaky Bucket — `fill()`, `spill()`, `leak()`, `isFull()`, `save()`, `reset()`)
- **Adapter base**: `src/Adapter.php` — abstract with `set($key, $value, $ttl)`, `get($key)`, `exists($key)`, `del($key)`
- **Adapters** (`src/Adapter/`): `APCu.php` · `Redis.php` · `Predis.php` · `Memcached.php` · `Stash.php`
- **Tests** (`tests/`): `RateLimitTest.php` · `LeakyBucketTest.php` · bootstrap `tests/bootstrap.php` · `tests/php.ini` enables `redis`, `apcu`, `memcached` extensions
- **Namespace**: `Detain\RateLimit\` → `src/` · `Detain\RateLimit\Tests\` → `tests/`

## Key Patterns

**Token Bucket storage keys** (in `src/RateLimit.php`):
```php
$t_key = "{$name}:{$id}:time";
$a_key = "{$name}:{$id}:allow";
```

**Leaky Bucket storage key** (in `src/LeakyBucket.php`):
```php
LEAKY_BUCKET_KEY_PREFIX . $key . LEAKY_BUCKET_KEY_POSTFIX
// = 'leakybucket:v1:' . $key . ':bucket'
```

**New adapter** — extend `\Detain\RateLimit\Adapter`, implement `set/get/exists/del`, place in `src/Adapter/`.

**Test extension skipping** — use `$this->markTestSkipped()` when required PHP extension is absent (see `tests/RateLimitTest.php::testCheckAPCu`).

## Conventions

- PSR-2 code style enforced via `.php-cs-fixer.dist.php` (run `composer format` before committing)
- Psalm config in `psalm.xml` — `UndefinedClass` suppressed for `src/Adapter/Memcached.php` due to optional ext
- PHPStan level 6 on `src/` only
- CI services needed for full test suite: `redis-server`, `memcached` (see `.travis.yml`)
- `tests/php.ini` must be loaded via `phpenv config-add` for CLI APCu support (`apc.enable_cli=1`)

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
