# Dependency compatibility matrix

November 27, 2026 (Asia/Manila). Current versions and declared constraints come from the original lockfile. Proposed target versions come from a separate Laravel 13 dry run; they are not installed or tested. Declared constraints do not establish lifecycle support.

## Required compatibility work

- Framework: validate installed 10/11/12/13 checkpoints; preserve kernels and providers.
- Sanctum 3 to 4: review middleware config and retain existing token migration.
- Permission 5 to 7: review middleware namespaces, provider/configuration changes and role boundaries.
- DomPDF 2 to 3: verify fonts, local/remote resource restrictions, receipts and exports.
- Nette schema 1.2.5 excludes PHP 8.4; its replacement must pass the actual platform check.
- Carbon 2 to 3: review signed/fractional differences and academic/report date boundaries.
- PHPUnit 9 to 12: migrate XML/metadata and check test discovery; coordinate Collision and Ignition.
- Symfony: framework-selected changes require HTTP/session/mail/process regression tests.
- AdminLTE: retain integration 3.16.0 and AdminLTE 3.2 assets; verify behavior in browsers.
- Frontend: npm audit reports one High, one Moderate and one Low package-level finding. Vite references absent CSS. Review compatible tooling; do not force major updates.

## Complete original Composer inventory

Advisory counts describe the original lock, not application-specific exploitability. Absent target packages require a removal review.

| Package | Kind | Original | Declared PHP | Declared Laravel/Illuminate | Proposed target (dry run) | Original advisories |
| --- | --- | --- | --- | --- | --- | --- |
| almasaeed2010/adminlte | Transitive production | v3.2.0 | Not declared | Not declared | v3.2.0 | 0 |
| barryvdh/laravel-dompdf | Direct production | v2.2.0 | ^7.2 \|\| ^8.0 | illuminate/support: ^6\|^7\|^8\|^9\|^10\|^11 | v3.1.2 | 0 |
| brick/math | Transitive production | 0.11.0 | ^8.0 | Not declared | 1.0.0 | 0 |
| carbonphp/carbon-doctrine-types | Transitive production | 2.1.0 | ^7.4 \|\| ^8.0 | Not declared | 2.1.1 | 0 |
| dflydev/dot-access-data | Transitive production | v3.0.3 | ^7.1 \|\| ^8.0 | Not declared | v3.0.3 | 0 |
| doctrine/dbal | Direct production | 3.10.6 | ^7.4 \|\| ^8.0 | Not declared | 3.10.7 | 0 |
| doctrine/deprecations | Transitive production | 1.1.6 | ^7.1 \|\| ^8.0 | Not declared | 1.2.0 | 0 |
| doctrine/event-manager | Transitive production | 1.2.0 | ^7.1 \|\| ^8.0 | Not declared | 2.1.1 | 0 |
| doctrine/inflector | Transitive production | 2.1.0 | ^7.2 \|\| ^8.0 | Not declared | 2.1.1 | 0 |
| doctrine/instantiator | Transitive development | 1.5.0 | ^7.1 \|\| ^8.0 | Not declared | Absent; review removal | 0 |
| doctrine/lexer | Transitive production | 2.1.1 | ^7.1 \|\| ^8.0 | Not declared | 3.0.3 | 0 |
| dompdf/dompdf | Transitive production | v2.0.8 | ^7.1 \|\| ^8.0 | Not declared | v3.1.6 | 6 |
| dragonmantank/cron-expression | Transitive production | v3.5.0 | ^7.2\|^8.0 | Not declared | v3.6.1 | 0 |
| egulias/email-validator | Transitive production | 3.2.6 | >=7.2 | Not declared | 4.0.5 | 0 |
| fakerphp/faker | Direct development | v1.24.1 | ^7.4 \|\| ^8.0 | Not declared | v1.24.1 | 0 |
| filp/whoops | Transitive development | 2.18.4 | ^7.1 \|\| ^8.0 | Not declared | 2.19.3 | 0 |
| fruitcake/php-cors | Transitive production | v1.3.0 | ^7.4\|^8.0 | Not declared | v1.4.0 | 0 |
| graham-campbell/result-type | Transitive production | v1.1.4 | ^7.2.5 \|\| ^8.0 | Not declared | v1.2.0 | 0 |
| guzzlehttp/guzzle | Direct production | 7.12.0 | ^7.2.5 \|\| ^8.0 | Not declared | 7.17.0 | 9 |
| guzzlehttp/promises | Transitive production | 2.5.0 | ^7.2.5 \|\| ^8.0 | Not declared | 2.6.2 | 0 |
| guzzlehttp/psr7 | Transitive production | 2.12.0 | ^7.2.5 \|\| ^8.0 | Not declared | 2.13.6 | 2 |
| guzzlehttp/uri-template | Transitive production | v1.0.7 | ^7.2.5 \|\| ^8.0 | Not declared | v2.0.1 | 0 |
| hamcrest/hamcrest-php | Transitive development | v2.1.1 | ^7.4\|^8.0 | Not declared | v3.0.0 | 0 |
| jeroennoten/laravel-adminlte | Direct production | v3.16.0 | >=7.3 | laravel/framework: >=8.0 | v3.16.0 | 0 |
| laravel/framework | Direct production | v9.52.21 | ^8.0.2 | Not declared | v13.44.1 | 7 |
| laravel/pint | Direct development | v1.5.0 | ^8.0 | Not declared | v1.32.3 | 0 |
| laravel/sail | Direct development | v1.62.0 | ^8.0 | illuminate/console: ^9.52.16\|^10.0\|^11.0\|^12.0\|^13.0; illuminate/contracts: ^9.52.16\|^10.0\|^11.0\|^12.0\|^13.0; illuminate/support: ^9.52.16\|^10.0\|^11.0\|^12.0\|^13.0 | v1.71.0 | 0 |
| laravel/sanctum | Direct production | v3.3.3 | ^8.0.2 | illuminate/console: ^9.21\|^10.0; illuminate/contracts: ^9.21\|^10.0; illuminate/database: ^9.21\|^10.0; illuminate/support: ^9.21\|^10.0 | v4.5.0 | 0 |
| laravel/serializable-closure | Transitive production | v1.3.7 | ^7.3\|^8.0 | Not declared | v2.2.0 | 0 |
| laravel/tinker | Direct production | v2.11.1 | ^7.2.5\|^8.0 | illuminate/console: ^6.0\|^7.0\|^8.0\|^9.0\|^10.0\|^11.0\|^12.0; illuminate/contracts: ^6.0\|^7.0\|^8.0\|^9.0\|^10.0\|^11.0\|^12.0; illuminate/support: ^6.0\|^7.0\|^8.0\|^9.0\|^10.0\|^11.0\|^12.0 | v3.2.0 | 0 |
| league/commonmark | Transitive production | 2.8.2 | ^7.4 \|\| ^8.0 | Not declared | 2.10.10 | 12 |
| league/config | Transitive production | v1.2.0 | ^7.4 \|\| ^8.0 | Not declared | v1.2.0 | 0 |
| league/flysystem | Transitive production | 3.34.0 | ^8.0.2 | Not declared | 3.36.0 | 1 |
| league/flysystem-local | Transitive production | 3.31.0 | ^8.0.2 | Not declared | 3.35.3 | 0 |
| league/mime-type-detection | Transitive production | 1.16.0 | ^7.4 \|\| ^8.0 | Not declared | 1.17.0 | 0 |
| masterminds/html5 | Transitive production | 2.10.1 | >=5.3.0 | Not declared | 2.12.0 | 0 |
| mockery/mockery | Direct development | 1.6.12 | >=7.3 | Not declared | 1.6.15 | 0 |
| monolog/monolog | Transitive production | 2.11.0 | >=7.2 | Not declared | 3.13.0 | 0 |
| myclabs/deep-copy | Transitive development | 1.13.4 | ^7.1 \|\| ^8.0 | Not declared | 1.14.0 | 0 |
| nesbot/carbon | Transitive production | 2.73.0 | ^7.1.8 \|\| ^8.0 | Not declared | 3.15.1 | 0 |
| nette/schema | Transitive production | v1.2.5 | 7.1 - 8.3 | Not declared | v1.4.0 | 0 |
| nette/utils | Transitive production | v4.0.10 | 8.0 - 8.5 | Not declared | v4.1.6 | 0 |
| nikic/php-parser | Transitive production | v5.7.0 | >=7.4 | Not declared | v5.9.0 | 0 |
| nunomaduro/collision | Direct development | v6.4.0 | ^8.0.0 | Not declared | v8.9.6 | 0 |
| nunomaduro/termwind | Transitive production | v1.15.1 | ^8.0 | Not declared | v2.4.1 | 0 |
| phar-io/manifest | Transitive development | 2.0.4 | ^7.2 \|\| ^8.0 | Not declared | 2.0.4 | 0 |
| phar-io/version | Transitive development | 3.2.1 | ^7.2 \|\| ^8.0 | Not declared | 3.2.1 | 0 |
| phenx/php-font-lib | Transitive production | 0.5.6 | Not declared | Not declared | Absent; review removal | 0 |
| phenx/php-svg-lib | Transitive production | 0.5.4 | ^7.1 \|\| ^8.0 | Not declared | Absent; review removal | 0 |
| phpoption/phpoption | Transitive production | 1.9.5 | ^7.2.5 \|\| ^8.0 | Not declared | 1.10.0 | 0 |
| phpunit/php-code-coverage | Transitive development | 9.2.32 | >=7.3 | Not declared | 12.5.8 | 0 |
| phpunit/php-file-iterator | Transitive development | 3.0.6 | >=7.3 | Not declared | 6.0.2 | 0 |
| phpunit/php-invoker | Transitive development | 3.1.1 | >=7.3 | Not declared | 6.0.0 | 0 |
| phpunit/php-text-template | Transitive development | 2.0.4 | >=7.3 | Not declared | 5.0.1 | 0 |
| phpunit/php-timer | Transitive development | 5.0.3 | >=7.3 | Not declared | 8.0.0 | 0 |
| phpunit/phpunit | Direct development | 9.6.34 | >=7.3 | Not declared | 12.5.45 | 0 |
| psr/cache | Transitive production | 3.0.0 | >=8.0.0 | Not declared | 3.0.0 | 0 |
| psr/clock | Transitive production | 1.0.0 | ^7.0 \|\| ^8.0 | Not declared | 1.0.0 | 0 |
| psr/container | Transitive production | 2.0.2 | >=7.4.0 | Not declared | 2.0.2 | 0 |
| psr/event-dispatcher | Transitive production | 1.0.0 | >=7.2.0 | Not declared | 1.0.0 | 0 |
| psr/http-client | Transitive production | 1.0.3 | ^7.0 \|\| ^8.0 | Not declared | 1.0.3 | 0 |
| psr/http-factory | Transitive production | 1.1.0 | >=7.1 | Not declared | 1.1.0 | 0 |
| psr/http-message | Transitive production | 2.0 | ^7.2 \|\| ^8.0 | Not declared | 2.0 | 0 |
| psr/log | Transitive production | 3.0.2 | >=8.0.0 | Not declared | 3.0.2 | 0 |
| psr/simple-cache | Transitive production | 3.0.0 | >=8.0.0 | Not declared | 3.0.0 | 0 |
| psy/psysh | Transitive production | v0.12.23 | ^8.0 \|\| ^7.4 | Not declared | v0.12.24 | 0 |
| ralouphie/getallheaders | Transitive production | 3.0.3 | >=5.6 | Not declared | 3.0.3 | 0 |
| ramsey/collection | Transitive production | 1.3.0 | ^7.4 \|\| ^8.0 | Not declared | 2.1.1 | 0 |
| ramsey/uuid | Transitive production | 4.9.3 | ^8.0 | Not declared | 4.10.0 | 0 |
| sabberworm/php-css-parser | Transitive production | v8.9.0 | ^5.6.20 \|\| ^7.0.0 \|\| ~8.0.0 \|\| ~8.1.0 \|\| ~8.2.0 \|\| ~8.3.0 \|\| ~8.4.0 | Not declared | v9.5.0 | 0 |
| sebastian/cli-parser | Transitive development | 1.0.2 | >=7.3 | Not declared | 4.2.1 | 0 |
| sebastian/code-unit | Transitive development | 1.0.8 | >=7.3 | Not declared | Absent; review removal | 0 |
| sebastian/code-unit-reverse-lookup | Transitive development | 2.0.3 | >=7.3 | Not declared | Absent; review removal | 0 |
| sebastian/comparator | Transitive development | 4.0.10 | >=7.3 | Not declared | 7.1.8 | 0 |
| sebastian/complexity | Transitive development | 2.0.3 | >=7.3 | Not declared | 5.0.0 | 0 |
| sebastian/diff | Transitive development | 4.0.6 | >=7.3 | Not declared | 7.0.1 | 0 |
| sebastian/environment | Transitive development | 5.1.5 | >=7.3 | Not declared | 8.1.2 | 0 |
| sebastian/exporter | Transitive development | 4.0.8 | >=7.3 | Not declared | 7.0.3 | 0 |
| sebastian/global-state | Transitive development | 5.0.8 | >=7.3 | Not declared | 8.0.3 | 0 |
| sebastian/lines-of-code | Transitive development | 1.0.4 | >=7.3 | Not declared | 4.0.1 | 0 |
| sebastian/object-enumerator | Transitive development | 4.0.4 | >=7.3 | Not declared | 7.0.0 | 0 |
| sebastian/object-reflector | Transitive development | 2.0.4 | >=7.3 | Not declared | 5.0.0 | 0 |
| sebastian/recursion-context | Transitive development | 4.0.6 | >=7.3 | Not declared | 7.0.1 | 0 |
| sebastian/resource-operations | Transitive development | 3.0.4 | >=7.3 | Not declared | Absent; review removal | 0 |
| sebastian/type | Transitive development | 3.2.1 | >=7.3 | Not declared | 6.0.4 | 0 |
| sebastian/version | Transitive development | 3.0.2 | >=7.3 | Not declared | 6.0.2 | 0 |
| spatie/backtrace | Transitive development | 1.8.2 | ^7.3 \|\| ^8.0 | Not declared | 1.8.2 | 0 |
| spatie/flare-client-php | Transitive development | 1.11.1 | ^8.0 | illuminate/pipeline: ^8.0\|^9.0\|^10.0\|^11.0\|^12.0\|^13.0 | 1.11.2 | 0 |
| spatie/ignition | Transitive development | 1.14.2 | ^8.0 | Not declared | 1.16.0 | 0 |
| spatie/laravel-ignition | Direct development | 1.7.2 | ^8.0 | illuminate/support: ^8.77\|^9.27 | 2.13.0 | 0 |
| spatie/laravel-permission | Direct production | 5.10.0 | ^7.3\|^8.0 | illuminate/auth: ^7.0\|^8.0\|^9.0\|^10.0; illuminate/container: ^7.0\|^8.0\|^9.0\|^10.0; illuminate/contracts: ^7.0\|^8.0\|^9.0\|^10.0; illuminate/database: ^7.0\|^8.0\|^9.0\|^10.0 | 7.4.2 | 0 |
| symfony/console | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 0 |
| symfony/css-selector | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.10 | 0 |
| symfony/deprecation-contracts | Transitive production | v3.0.2 | >=8.0.2 | Not declared | v3.8.1 | 0 |
| symfony/error-handler | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.10 | 0 |
| symfony/event-dispatcher | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.5 | 0 |
| symfony/event-dispatcher-contracts | Transitive production | v3.0.2 | >=8.0.2 | Not declared | v3.8.1 | 0 |
| symfony/finder | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 0 |
| symfony/http-foundation | Transitive production | v6.0.20 | >=8.0.2 | Not declared | v7.4.24 | 6 |
| symfony/http-kernel | Transitive production | v6.0.20 | >=8.0.2 | Not declared | v8.1.12 | 2 |
| symfony/mailer | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 1 |
| symfony/mime | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 2 |
| symfony/polyfill-ctype | Transitive production | v1.37.0 | >=7.2 | Not declared | v1.37.0 | 0 |
| symfony/polyfill-intl-grapheme | Transitive production | v1.38.1 | >=7.2 | Not declared | v1.43.3 | 0 |
| symfony/polyfill-intl-idn | Transitive production | v1.38.1 | >=7.2 | Not declared | v1.43.0 | 0 |
| symfony/polyfill-intl-normalizer | Transitive production | v1.38.0 | >=7.2 | Not declared | v1.43.0 | 0 |
| symfony/polyfill-mbstring | Transitive production | v1.38.2 | >=7.2 | Not declared | v1.43.3 | 0 |
| symfony/polyfill-php80 | Transitive production | v1.37.0 | >=7.2 | Not declared | v1.43.0 | 0 |
| symfony/polyfill-php81 | Transitive production | v1.38.1 | >=7.2 | Not declared | Absent; review removal | 0 |
| symfony/polyfill-uuid | Transitive production | v1.37.0 | >=7.2 | Not declared | v1.43.0 | 0 |
| symfony/process | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 1 |
| symfony/routing | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 5 |
| symfony/service-contracts | Transitive production | v3.0.2 | >=8.0.2 | Not declared | v3.8.1 | 0 |
| symfony/string | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 0 |
| symfony/translation | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 0 |
| symfony/translation-contracts | Transitive production | v3.0.2 | >=8.0.2 | Not declared | v3.8.1 | 0 |
| symfony/uid | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.9 | 0 |
| symfony/var-dumper | Transitive production | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 0 |
| symfony/yaml | Transitive development | v6.0.19 | >=8.0.2 | Not declared | v8.1.12 | 5 |
| theseer/tokenizer | Transitive development | 1.3.1 | ^7.2 \|\| ^8.0 | Not declared | 2.0.1 | 0 |
| tijsverkoyen/css-to-inline-styles | Transitive production | v2.4.0 | ^7.4 \|\| ^8.0 | Not declared | v2.4.0 | 0 |
| vlucas/phpdotenv | Transitive production | v5.6.3 | ^7.2.5 \|\| ^8.0 | Not declared | v5.8.1 | 0 |
| voku/portable-ascii | Transitive production | 2.1.1 | >=7.1.0 | Not declared | 2.1.1 | 0 |

## Proposed additions

Resolver-selected transitive packages, not installed.

| Package | Proposed version |
| --- | --- |
| dompdf/php-font-lib | 1.0.2 |
| dompdf/php-svg-lib | 1.0.2 |
| laravel/prompts | v0.3.28 |
| league/uri | 7.10.0 |
| league/uri-interfaces | 7.10.0 |
| spatie/error-solutions | 1.1.3 |
| spatie/laravel-package-tools | 1.93.3 |
| staabm/side-effects-detector | 1.0.5 |
| symfony/clock | v8.1.9 |
| symfony/polyfill-php84 | v1.43.0 |
| symfony/polyfill-php85 | v1.44.0 |
| symfony/polyfill-php86 | v1.43.0 |
| symfony/polyfill-time | v1.42.0 |

## Sources and limits

- Original composer.json, composer.lock and installed package manifests.
- Network-enabled composer audit --locked --format=json --no-ansi.
- Separate-manifest composer update --dry-run --no-scripts --no-plugins --no-interaction --no-ansi, without platform overrides or security suppression.
- [Laravel upgrade](https://laravel.com/docs/13.x/upgrade), [Spatie prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites), [Spatie upgrading](https://spatie.be/docs/laravel-permission/v8/upgrading), [DomPDF metadata](https://github.com/barryvdh/laravel-dompdf/blob/master/composer.json).
- Dry-run audit output does not establish security of a hypothetical target. Audit the installed final lock.

## npm lockfile inventory

These are locked entries, including optional packages for other platforms; they are not a claim that every optional binary is installed on Windows. Installed npm audit reports zero findings. Copied/CDN assets below are outside that audit.

| Lockfile path | Original version | Upgraded lock version | Declared Node (upgraded) |
| --- | --- | --- | --- |
| node_modules/@esbuild/aix-ppc64 | Not present | 0.28.2 | >=18 |
| node_modules/@esbuild/android-arm | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/android-arm64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/android-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/darwin-arm64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/darwin-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/freebsd-arm64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/freebsd-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-arm | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-arm64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-ia32 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-loong64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-mips64el | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-ppc64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-riscv64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-s390x | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/linux-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/netbsd-arm64 | Not present | 0.28.2 | >=18 |
| node_modules/@esbuild/netbsd-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/openbsd-arm64 | Not present | 0.28.2 | >=18 |
| node_modules/@esbuild/openbsd-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/openharmony-arm64 | Not present | 0.28.2 | >=18 |
| node_modules/@esbuild/sunos-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/win32-arm64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/win32-ia32 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@esbuild/win32-x64 | 0.18.20 | 0.28.2 | >=18 |
| node_modules/@playwright/test | 1.65.1 | 1.65.1 | >=20 |
| node_modules/@rollup/rollup-android-arm-eabi | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-android-arm64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-android-x64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-darwin-arm64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-darwin-x64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-freebsd-arm64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-freebsd-x64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-arm-gnueabihf | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-arm-musleabihf | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-arm64-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-arm64-musl | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-loong64-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-loong64-musl | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-ppc64-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-ppc64-musl | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-riscv64-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-riscv64-musl | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-s390x-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-x64-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-linux-x64-musl | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-openbsd-x64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-openharmony-arm64 | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-win32-arm64-msvc | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-win32-ia32-msvc | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-win32-x64-gnu | Not present | 4.68.8 | Not declared |
| node_modules/@rollup/rollup-win32-x64-msvc | Not present | 4.68.8 | Not declared |
| node_modules/@types/estree | Not present | 1.0.11 | Not declared |
| node_modules/agent-base | 6.0.2 | 6.0.2 | >= 6.0.0 |
| node_modules/asynckit | 0.4.0 | 0.4.0 | Not declared |
| node_modules/axios | 1.21.1 | 1.21.1 | Not declared |
| node_modules/call-bind-apply-helpers | 1.0.2 | 1.0.2 | >= 0.4 |
| node_modules/combined-stream | 1.0.8 | 1.0.8 | >= 0.8 |
| node_modules/debug | 4.4.3 | 4.4.3 | >=6.0 |
| node_modules/delayed-stream | 1.0.0 | 1.0.0 | >=0.4.0 |
| node_modules/dunder-proto | 1.0.1 | 1.0.1 | >= 0.4 |
| node_modules/es-define-property | 1.0.1 | 1.0.1 | >= 0.4 |
| node_modules/es-errors | 1.3.0 | 1.3.0 | >= 0.4 |
| node_modules/es-object-atoms | 1.1.2 | 1.1.2 | >= 0.4 |
| node_modules/es-set-tostringtag | 2.1.0 | 2.1.0 | >= 0.4 |
| node_modules/esbuild | 0.18.20 | 0.28.2 | >=18 |
| node_modules/follow-redirects | 1.16.3 | 1.16.3 | >=4.0 |
| node_modules/form-data | 4.0.6 | 4.0.6 | >= 6 |
| node_modules/fsevents | 2.3.3 | 2.3.3 | ^8.16.0 \|\| ^10.6.0 \|\| >=11.0.0 |
| node_modules/function-bind | 1.1.2 | 1.1.2 | Not declared |
| node_modules/get-intrinsic | 1.3.0 | 1.3.0 | >= 0.4 |
| node_modules/get-proto | 1.0.1 | 1.0.1 | >= 0.4 |
| node_modules/gopd | 1.2.0 | 1.2.0 | >= 0.4 |
| node_modules/has-symbols | 1.1.0 | 1.1.0 | >= 0.4 |
| node_modules/has-tostringtag | 1.0.2 | 1.0.2 | >= 0.4 |
| node_modules/hasown | 2.0.4 | 2.0.4 | >= 0.4 |
| node_modules/https-proxy-agent | 5.0.1 | 5.0.1 | >= 6 |
| node_modules/laravel-vite-plugin | 0.7.8 | 2.1.0 | ^20.19.0 \|\| >=22.12.0 |
| node_modules/lodash | 4.18.1 | 4.18.1 | Not declared |
| node_modules/math-intrinsics | 1.1.0 | 1.1.0 | >= 0.4 |
| node_modules/mime-db | 1.52.0 | 1.52.0 | >= 0.6 |
| node_modules/mime-types | 2.1.35 | 2.1.35 | >= 0.6 |
| node_modules/ms | 2.1.3 | 2.1.3 | Not declared |
| node_modules/nanoid | 3.3.20 | 3.3.20 | ^10 \|\| ^12 \|\| ^13.7 \|\| ^14 \|\| >=15.0.1 |
| node_modules/picocolors | 1.1.1 | 1.1.1 | Not declared |
| node_modules/picomatch | 2.3.2 | 2.3.2 | >=8.6 |
| node_modules/playwright | 1.65.1 | 1.65.1 | >=20 |
| node_modules/playwright-core | 1.65.1 | 1.65.1 | >=20 |
| node_modules/postcss | 8.5.33 | 8.5.33 | ^10 \|\| ^12 \|\| >=14 |
| node_modules/proxy-from-env | 2.1.0 | 2.1.0 | >=10 |
| node_modules/rollup | 3.30.0 | 4.68.8 | >=18.0.0 |
| node_modules/source-map-js | 1.2.3 | 1.2.3 | >=0.10.0 |
| node_modules/tinyglobby | Not present | 0.2.17 | >=12.0.0 |
| node_modules/tinyglobby/node_modules/fdir | Not present | 6.5.0 | >=12.0.0 |
| node_modules/tinyglobby/node_modules/picomatch | Not present | 4.0.7 | >=12 |
| node_modules/vite | 4.5.14 | 7.3.8 | ^20.19.0 \|\| >=22.12.0 |
| node_modules/vite-plugin-full-reload | 1.2.0 | 1.2.0 | Not declared |
| node_modules/vite/node_modules/fdir | Not present | 6.5.0 | >=12.0.0 |
| node_modules/vite/node_modules/picomatch | Not present | 4.0.7 | >=12 |

## Copied and CDN-served browser assets

| Asset | Observed version/reference | Verification limit |
| --- | --- | --- |
| AdminLTE JavaScript | 3.2.0, public/vendor/adminlte | Header inspected; preserve current UI integration |
| Bootstrap bundle | 4.6.1, public/vendor/bootstrap | Served by the authenticated master; Bootstrap 4 is EOL |
| jQuery | 3.6.0, public/vendor/jquery | Header inspected; separate from npm lock audit |
| OverlayScrollbars | 1.13.0 | Header inspected; separate from npm lock audit |
| Font Awesome Free | 5.15.4 | Header inspected; fonts/CSS separate from npm lock audit |
| Standalone Popper | Copied public/vendor/popper | Exact version and active standalone usage not verified |
| Public Bootstrap CSS | CDN 5.3.3 | Public layout reference; separate from authenticated Bootstrap 4 |
| Lucide | unpkg lucide@latest in both layouts | Floating version, not reproducible or covered by npm lock audit |
| DataTables / Select2 / Chart.js / SweetAlert2 / Pace | Config references 1.10.19 / 4.0.3 / 3.9.1 / 8 / 1.0.2 | Disabled by default; per-page activations require tracing before claiming runtime exposure |

Bootstrap 4 reached upstream end of life January 1, 2023. Keeping AdminLTE 3 appearance does not resolve that lifecycle risk. A separately verified compatibility-preserving support/backport path or an approved frontend migration is required for production acceptance; no license purchase, unsupported patch or major UI migration has been made. [Bootstrap lifecycle](https://getbootstrap.com/docs/4.6/end-of-life/).
