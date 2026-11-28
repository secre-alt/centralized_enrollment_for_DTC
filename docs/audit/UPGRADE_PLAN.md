# Controlled Laravel modernization

Started November 27, 2026 (Asia/Manila). This is an execution record, not production approval.

## Isolation and baseline

- Original branch: `nov-26`; original commit: `d1c8386b86f964527b4108bd0587505b3a3c9a48`.
- Working branch: `upgrade/laravel-13-controlled`; initial working tree clean.
- No commits, pushes, deployment, production migrations, or production data changes authorized by this plan.
- Preserve the existing application structure, AdminLTE 3.2 assets, directly served CSS/JS, payment records and institutional rules.
- PHPUnit forces MySQL `dtc_testing`, including connection URL and socket overrides. Operational database name differs. `bootstrap/cache/config.php` was absent. Concurrency workers independently assert the testing database.
- Run database suites sequentially. The baseline command is `php vendor/phpunit/phpunit/phpunit --no-coverage`; results belong in `TEST_RESULTS.md` after completion.

## Runtime constraint

Installed CLI/Composer PHP is `C:\Program Files\PHP\current\php.exe`, version 8.4.27. Composer is 2.8.8. Initial platform check fails because `nette/schema` 1.2.5 requires PHP 7.1–8.3.

The [official PHP 8.4.28 announcement](https://www.php.net/releases/8_4_28.php) identifies a newer security release, published November 19, 2026. Final runtime acceptance requires 8.4.28 or a later maintained security patch; passing checks on 8.4.27 does not satisfy that requirement.

An official isolated PHP 8.2.36 archive was downloaded into disposable testing storage and checked against the SHA256 supplied by `https://windows.php.net/downloads/releases/releases.json`. Windows Application Control rejected execution: `An Application Control policy has blocked this file`. Do not bypass this control. The initial baseline therefore measures the installed PHP 8.4 environment and cannot establish compatibility with the framework's historical supported PHP range.

The same official download-and-hash-verification procedure subsequently produced an executable PHP 8.4.28 runtime at `storage/framework/testing/php-8.4.28/php.exe`. It boots Laravel 10, runs Composer 2.8.8 and passes installed platform requirements. Required extensions including PDO MySQL, GD, mbstring, curl and OpenSSL load. An optional ZIP DLL probe was blocked by Application Control; no application/test ZIP API usage was found, and ZIP is not configured in the accepted isolated runtime. Do not claim extension parity with system PHP or bypass that block.

Use the explicit patched executable for subsequent checkpoint tests and final checks; invoking bare `php` or `composer` still selects system PHP 8.4.27. Composer can use the existing trusted Git `usr/bin/unzip.exe` for archive extraction when native ZIP is unavailable. Temporary runtimes are ignored local artifacts, not checked-in deployment dependencies. Production requires its own approved patched runtime and extension verification.

## Checkpoints and gates

1. Establish the Laravel 9 baseline and distinguish existing failures from upgrade regressions.
2. Laravel 10: update framework, Ignition and Collision constraints as required; resolve actual PHP constraints without platform overrides. Review Monolog and database expression changes.
3. Laravel 11: review Sanctum, permission middleware, column modifications, password rehashing and provider behavior. Retain the existing kernels and providers.
4. Laravel 12: adopt Carbon 3, checking signed/fractional date differences and academic/report boundaries; update test tooling.
5. Laravel 13: review request-forgery protection, sessions/cache configuration, Eloquent scopes and SQL changes. Bootstrap 3 pagination names changed; existing explicit Bootstrap 4 names do not require replacement.

At every installed checkpoint, run strict Composer validation, actual platform checks, the complete PHPUnit suite, route inventory comparison and relevant workflow/browser checks. Review the diff and record package changes. Do not advance past unexplained regressions or incompatible dependencies. Intermediate unsupported versions are testing checkpoints only.

Resolve first with dry runs and keep Composer security configuration intact. A successful resolver is not evidence of application compatibility or a clean security audit. Do not add optional tooling mentioned in upstream guides unless this application needs it.

## Validation and preservation

- Preserve ownership, role boundaries, CSRF, account/session revocation, private proof delivery and receipt/export privacy.
- Preserve original verified payments, exact full-refund reconciliation, revision history and offering-lock concurrency behavior.
- Check fresh schema installation and representative synthetic existing-record preservation on disposable MySQL only.
- Keep functional, security and visual acceptance distinct. Disposable HTML fixtures do not establish authenticated role acceptance.
- Preserve explicit cache/Redis prefixes, session cookie naming and storage roots. Do not sync in new skeleton defaults that rename cookies, move private files or invalidate active sessions without a separate decision.
- Retry dependency audits with network access; record advisories rather than suppressing them.
- The initial asset build fails because Vite references absent `resources/css/app.css`; application asset bundling is disabled. Repair only the build entry configuration required for existing assets.
- After modernization passes its gates, complete the route/page/workflow audit. Mark unperformed reviews explicitly.

## Sources

- [Laravel 10 upgrade](https://laravel.com/docs/10.x/upgrade)
- [Laravel 11 upgrade](https://laravel.com/docs/11.x/upgrade)
- [Laravel 12 upgrade](https://laravel.com/docs/12.x/upgrade)
- [Laravel 13 upgrade](https://laravel.com/docs/13.x/upgrade)
- [Laravel support policy](https://laravel.com/docs/13.x/releases)
- [PHP support policy](https://www.php.net/supported-versions.php)

Laravel 13 is the intended target, subject to verified dependencies and tests. Production web/worker/scheduler PHP versions, hosting configuration, signed backup recovery and staging rollback remain separate mandatory deployment gates.
