# Security audit report

November 27, 2026 (Asia/Manila). Status: baseline findings recorded; modernization and application-wide security acceptance remain incomplete.

## Baseline evidence

- composer audit --locked --no-ansi --format=json succeeded in retrieving advisories with network-enabled execution; its nonzero exit indicates findings.
- 59 advisories across 13 packages. Severity: {"medium":23,"low":9,"high":16,"critical":1,"unknown":10}. Unknown values are not downgraded.
- npm audit --json reports 3 affected packages: one High, one Moderate and one Low; these include development tooling.
- See evidence/composer-baseline.json and evidence/npm-baseline.json for identifiers, affected ranges and primary advisory links.
- These results identify affected dependency versions; application-specific reachability and exploitability have not been established.
- Laravel 9 is outside standard security support. Actual local PHP 8.4.27 fails the baseline Nette schema constraint. Production PHP and web configuration remain unverified.
- Composer security settings and platform checks have not been disabled. No advisory ignore list has been added.

## Original lockfile findings

| Package | Severity | Advisory | Description |
| --- | --- | --- | --- |
| dompdf/dompdf | medium | [PKSA-cv56-2228-pzr6](https://github.com/advisories/GHSA-j8qw-6jw8-r297) | Dompdf: Embedded SVG images can leak existence of files and directories within the filesystem |
| dompdf/dompdf | medium | [PKSA-6r8f-nxsb-67bq](https://github.com/advisories/GHSA-f5gf-2cj8-52g2) | Dompdf: Denial of Service (DoS) via Resource Exhaustion using Oversized Image Bitmaps |
| dompdf/dompdf | medium | [PKSA-gh7h-hhy4-byg7](https://github.com/advisories/GHSA-8hg6-c449-896m) | Dompdf: Uncontrolled resource consumption based on declared BMP dimensions |
| dompdf/dompdf | medium | [PKSA-mwt3-h9tv-kx78](https://github.com/advisories/GHSA-cx96-42px-69fm) | Dompdf: Local file read due to improper file path validation in SVG images encoded as data-URI |
| dompdf/dompdf | low | [PKSA-hp6n-n4kz-21wk](https://github.com/advisories/GHSA-7x2p-4jvh-6384) | Dompdf: File existence oracle via font-face stylesheet declaration |
| dompdf/dompdf | low | [PKSA-mckv-s5hg-868k](https://github.com/advisories/GHSA-wvh6-f5jh-8gw4) | Dompdf: Chroot Validation Bypass |
| guzzlehttp/guzzle | high | [PKSA-gcrk-3vtt-1r14](https://github.com/advisories/GHSA-v5mv-p594-2x33) | Guzzle: Noncanonical host can bypass host-based checks |
| guzzlehttp/guzzle | medium | [PKSA-cnw1-2ytm-cgr8](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r) | Guzzle: Noncanonical cookie domain keeps subdomain scope |
| guzzlehttp/guzzle | medium | [PKSA-fy2t-3c5f-827y](https://github.com/advisories/GHSA-h95v-h523-3mw8) | Guzzle: URI fragments disclosed in redirect Referer headers |
| guzzlehttp/guzzle | medium | [PKSA-qxvb-2bpp-dnk6](https://github.com/advisories/GHSA-wm3w-8rrp-j577) | Guzzle: Host-only cookie scope is not preserved |
| guzzlehttp/guzzle | medium | [PKSA-bbs6-q5q9-f3t4](https://github.com/advisories/GHSA-f283-ghqc-fg79) | Guzzle: Unbounded response cookies risk denial of service |
| guzzlehttp/guzzle | medium | [PKSA-bcdd-5xc7-gwfb](https://github.com/advisories/GHSA-g446-98w2-8p5w) | Guzzle: Cookie Disclosure and Injection via IP-Address Domains |
| guzzlehttp/guzzle | medium | [PKSA-pwsk-hy21-4gby](https://github.com/advisories/GHSA-94pj-82f3-465w) | Guzzle: Proxy-Authorization headers can be sent to origin servers |
| guzzlehttp/guzzle | medium | [PKSA-93qv-9n9h-6k6p](https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx) | Dot-only cookie domains match all hosts |
| guzzlehttp/guzzle | medium | [PKSA-k22t-f949-t9g6](https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3) | Silent HTTPS proxy downgrade to cleartext |
| guzzlehttp/psr7 | medium | [PKSA-vznr-tgp9-fd7d](https://github.com/advisories/GHSA-c2w2-prh8-qm98) | guzzlehttp/psr7: Host Confusion via Weak URI Host Validation |
| guzzlehttp/psr7 | medium | [PKSA-7qs6-zvnz-h66r](https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432) | CRLF injection in HTTP start-line serialization |
| laravel/framework | low | [PKSA-yxbx-3c9s-cjt9](https://github.com/advisories/GHSA-63hm-g96r-2m2q) | Laravel: Task Output Injection - Concurrency Process |
| laravel/framework | critical | [PKSA-b41t-wbzk-9q51](https://github.com/advisories/GHSA-f9x3-7vq9-cph6) | Laravel TokenGuard authentication bypass through non-string API credentials |
| laravel/framework | low | [PKSA-d5tc-s1qs-h781](https://github.com/advisories/GHSA-jh5r-qr3c-85q8) | Laravel: XSS in Debug Page Information |
| laravel/framework | medium | [PKSA-m5cs-t1y6-qpcs](https://github.com/advisories/GHSA-crmm-hgp2-wgrp) | Laravel Framework: Temporary Signed URL Path Confusion |
| laravel/framework | high | [PKSA-3r5d-mb8f-1qw9](https://github.com/advisories/GHSA-5vg9-5847-vvmq) | Laravel Framework: CRLF injection in default email rule  |
| laravel/framework | unknown | [PKSA-mdq4-51ck-6kdq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq) | Laravel CRLF injection in default email rule |
| laravel/framework | medium | [PKSA-8qx3-n5y5-vvnd](https://github.com/advisories/GHSA-78fx-h6xr-vch4) | Laravel has a File Validation Bypass |
| league/commonmark | medium | [PKSA-m2dq-1fhr-29b1](https://github.com/advisories/GHSA-97jj-33gv-5xf9) | league/commonmark: DisallowedRawHtml bypassed when a disallowed tag name ends the raw-HTML literal |
| league/commonmark | high | [PKSA-m4t9-vsgq-8khn](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8) | league/commonmark: Quadratic-time denial of service in the GitHub Flavored Markdown Table extension block-start scan |
| league/commonmark | high | [PKSA-zyf5-hrxv-hrd7](https://github.com/advisories/GHSA-8rr7-cvq3-gmfh) | league/commonmark: Denial of service via distinctly-named attributes in the Attributes extension |
| league/commonmark | high | [PKSA-nv44-1b4d-6gjg](https://github.com/advisories/GHSA-jjv6-8j6v-6j52) | league/commonmark: Denial of service in the SmartPunct and Attributes extensions |
| league/commonmark | high | [PKSA-kr3s-894t-g5w2](https://github.com/advisories/GHSA-f8fg-pg57-v4j8) | league/commonmark XSS: `on*` event-handler filter in `AttributesExtension` bypassed with a U+000C form feed |
| league/commonmark | high | [PKSA-9q1p-3s19-bp1q](https://github.com/advisories/GHSA-j8pm-gj4c-rq4x) | league/commonmark: Denial of service via crafted code fences, reference links, and emphasis delimiters |
| league/commonmark | medium | [PKSA-5mzr-szzf-z6cn](https://github.com/advisories/GHSA-mj63-m3rc-8ppr) | league/commonmark: Denial of service via deeply nested XML output |
| league/commonmark | high | [PKSA-cqd6-fg4n-nxpf](https://github.com/advisories/GHSA-mh25-x5hq-wrqp) | league/commonmark: Denial of service via colliding heading slugs |
| league/commonmark | high | [PKSA-1q6p-sqkj-8mmj](https://github.com/advisories/GHSA-jfm3-95jq-q3rf) | league/commonmark:  Denial of service via duplicate footnote definitions |
| league/commonmark | high | [PKSA-mc58-w91n-f5gv](https://github.com/advisories/GHSA-g2gp-3wwq-f4ph) | league/commonmark: Denial of service via adjacent inline attribute blocks |
| league/commonmark | high | [PKSA-t21r-vtr5-3mdz](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv) | league/commonmark: Quadratic-time denial of service when parsing crafted Markdown |
| league/commonmark | medium | [PKSA-scnn-p8mm-jbft](https://github.com/advisories/GHSA-29pj-957v-52mc) | league/commonmark: AttributesExtension href/src unsafe-link filter bypass via embedded control bytes |
| league/flysystem | low | [PKSA-w9tt-7782-78jx](https://github.com/advisories/GHSA-cxf4-7mrp-vvpr) | Flysystem: WhitespacePathNormalizer's control-character (CorruptedPathDetected) check is bypassed by malformed UTF-8 in the path, affecting every adapter |
| symfony/http-foundation | unknown | [PKSA-7b9s-mkdg-qfhs](https://symfony.com/cve-2026-133748) | CVE-2026-133748: NoPrivateNetworkHttpClient With Custom Subnets Lets IPv4-Mapped Addresses Through |
| symfony/http-foundation | unknown | [PKSA-qgp8-6q73-5w6n](https://symfony.com/cve-2026-135802) | CVE-2026-135802: Generated URLs Can Point to a Host Chosen by the Client |
| symfony/http-foundation | unknown | [PKSA-24ph-xkrh-y79j](https://symfony.com/cve-2026-118514) | CVE-2026-118514: HEAD Requests Bypass the methods Option of security.access_control Rules and Firewalls: Incomplete Fix for CVE-2026-45075 |
| symfony/http-foundation | high | [PKSA-xmwy-rmx5-m7h4](https://symfony.com/cve-2026-108082) | CVE-2026-108082: ReDoS in HeaderUtils::split() Reachable Through the Cache-Control Request Header |
| symfony/http-foundation | high | [PKSA-365x-2zjk-pt47](https://symfony.com/blog/cve-2025-64500-incorrect-parsing-of-path-info-can-lead-to-limited-authorization-bypass) | CVE-2025-64500: Incorrect parsing of PATH_INFO can lead to limited authorization bypass |
| symfony/http-foundation | low | [PKSA-b35n-565h-rs4q](https://symfony.com/cve-2024-50345) | CVE-2024-50345: Open redirect via browser-sanitized URLs |
| symfony/http-kernel | unknown | [PKSA-k1tg-4k6j-w4xf](https://symfony.com/cve-2026-133743) | CVE-2026-133743: Inline Fragments Let Untrusted Forwarded Headers Change the URLs of asset() and absolute_url(): Incomplete Fix for CVE-2026-123339 |
| symfony/http-kernel | unknown | [PKSA-3gxv-7xhq-mgmz](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9) | Inline Fragments Let Untrusted Forwarded Headers Set the Host, Scheme and Port of the URLs Generated for the Main Request |
| symfony/mailer | medium | [PKSA-28rh-rzzn-djk4](https://symfony.com/cve-2026-45068) | CVE-2026-45068: Argument Injection in SendmailTransport via Dash-Prefixed Recipient Address |
| symfony/mime | medium | [PKSA-wtxr-p26d-nn42](https://symfony.com/cve-2026-45070) | CVE-2026-45070: Email Header Injection via Non-Token Characters in Mime Parameter Names |
| symfony/mime | high | [PKSA-2n2k-66v2-bwg3](https://symfony.com/cve-2026-45067) | CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address |
| symfony/process | high | [PKSA-wws7-mr54-jsny](https://symfony.com/cve-2024-51736) | CVE-2024-51736: Command execution hijack on Windows with Process class |
| symfony/routing | unknown | [PKSA-pbtx-4qgh-mmkh](https://symfony.com/cve-2026-133746) | CVE-2026-133746: UrlGenerator Can Still Generate URLs to Another Host: Incomplete Fix for CVE-2026-45065 |
| symfony/routing | unknown | [PKSA-2vy6-qchn-f13r](https://symfony.com/cve-2026-133754) | CVE-2026-133754: UrlGenerator Lets Host Placeholder Values Send Generated URLs to Another Host |
| symfony/routing | unknown | [PKSA-y8c5-1b7f-r1gj](https://symfony.com/cve-2026-118516) | CVE-2026-118516: Host Placeholders Can Match the Start of the Path in Compiled URL Matchers, Bypassing Path-Based Access Control |
| symfony/routing | medium | [PKSA-bf7t-jnpz-492k](https://symfony.com/cve-2026-48784) | CVE-2026-48784: UrlGenerator Dot-Segment Encoding Skips Every Other Chained `../` or `./` → Generated URL Collapses Off-Route Under RFC 3986 Normalization |
| symfony/routing | medium | [PKSA-yc7t-91v9-99xs](https://symfony.com/cve-2026-45065) | CVE-2026-45065: UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation → Off-Site //host URL Injection |
| symfony/yaml | unknown | [PKSA-nktx-f1n1-b4yd](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx) | Exponential and Quadratic Parse Times in the YAML Parser Let a Single Document Hold a Process |
| symfony/yaml | high | [PKSA-xz3r-9gp4-4m1z](https://symfony.com/cve-2026-109413) | CVE-2026-109413: Unbounded Recursion in the YAML Inline Lexer Lets a Single Document Crash the Process |
| symfony/yaml | low | [PKSA-v5yj-8nmz-sk2q](https://symfony.com/cve-2026-45304) | CVE-2026-45304: YAML Parser Exponential Memory Allocation via Recursive Collection-Alias Expansion ("Billion Laughs") |
| symfony/yaml | low | [PKSA-ft77-7h5f-p3r6](https://symfony.com/cve-2026-45305) | CVE-2026-45305: YAML Parser ReDoS via Catastrophic Backtracking in Parser::cleanup() Regex |
| symfony/yaml | low | [PKSA-b14r-zh1d-vdrc](https://symfony.com/cve-2026-45133) | CVE-2026-45133: YAML Parser Stack Exhaustion via Unbounded Recursion in Nested Blocks, Sequences, and Mappings |

## Application and infrastructure scope

### Record-preservation paths under investigation

Synthetic integration reproduced account deletion accepting a paid enrollment owner and a financial verifier. `Admin/UserController::destroy` now locks the account within a retried transaction and checks actual foreign-key references to users before deletion. Recorded owners and nullable audit actors block removal with validation feedback; unused staff removal remains available. The transaction also protects role cleanup if deletion fails. Regression checks compare complete stored payment attributes, retained enrollment identity and role membership. This addresses destructive authorized administration, not a demonstrated unauthorized-access exploit.

Synthetic integration also reproduced deletion accepting a subject referenced only by legacy enrollment JSON and deletion of credited-subject documentation through its cascading foreign key. Subject and cascading program removal now check integer/string JSON identities and credited records under subject locks. Student enrollment creation locks subjects in ID order and rechecks existence/program identity before storing JSON references. Regression checks cover both JSON representations, program removal, credited records and successful unused-subject removal. Existing offering concurrency tests remain required; these sequential history checks alone do not establish the new deletion/enrollment race behavior.

`RoleSeeder` previously created a development administrator using a fixed public password without an environment guard. It now creates that account only in local/testing environments while retaining role creation everywhere. A production-environment test on the guarded disposable database proves six roles and no account; local repeated seeding remains idempotent. No operational account or password was inspected or changed; this does not establish whether an older deployment already contains a development credential.

Search input previously accepted arrays into `trim()`, producing a reproducible server error. It now validates a nullable string of at most 150 characters. Malformed/oversized JSON requests receive 422, while blank and ordinary authorized searches remain usable.

The complete Laravel 11 repeat passed 775 tests / 9,374 assertions, including all nine new security/data-preservation tests.

Additional malformed password inputs reproduced errors in reset/setup link rendering and password hash validation. The link pages now validate bounded string email values, reset tokens require strings, and password-change inputs reject arrays before hashing. The required-change validator stops on a type failure before applying its hash rule. Focused history/input/help verification passes 18 tests / 142 assertions, preserving passwords and required-change state after rejection. These later changes still require the next complete checkpoint suite; malformed-input errors alone do not establish account takeover or credential disclosure.

The README also claims CAPTCHA is required and fails closed in production, while the actual login controller has no CAPTCHA integration and `LoginSecurityRemediationTest` explicitly checks login without an external challenge. Correct the documentation to the current behavior; do not claim nonexistent enforcement or silently introduce a new login flow.

### Browser assets outside lockfile audits

The isolated test connection reports MariaDB 10.4.32. [MariaDB's release documentation](https://mariadb.com/docs/release-notes/community-server/old-releases/10.4) states that 10.4 maintenance ended in June 2024. These tests establish behavior on that actual server, not acceptance on a maintained production database. Production's server version remains unknown. Use an independently backed-up staging instance to rehearse a vendor-supported upgrade path to a maintained release, preserving collations, SQL modes, indexes, decimal precision and transaction behavior; do not upgrade or replace the shared operational database as part of dependency installation.

The authenticated master serves copied Bootstrap 4.6.1 and jQuery 3.6.0, with AdminLTE 3.2.0 and OverlayScrollbars 1.13.0. Font Awesome headers identify 5.15.4. These are outside npm's audited lockfile. Both layouts also load floating `lucide@latest`; the public layout references Bootstrap 5.3.3 CSS. Optional older plugin CDNs are declared but disabled by default; do not infer active exposure without per-page tracing.

[Bootstrap's lifecycle notice](https://getbootstrap.com/docs/4.6/end-of-life/) confirms Bootstrap 4 has been unsupported since January 1, 2023. This is an unresolved production lifecycle risk. No application-specific Bootstrap exploit has been established, and a zero npm audit cannot clear these copied assets. Preserve AdminLTE 3 while evaluating an approved support/backport path or a separate verified UI migration; do not claim the Laravel upgrade resolves this risk.

The [official PHP 8.4.28 release announcement](https://www.php.net/releases/8_4_28.php) identifies a newer security update than installed 8.4.27. A supported PHP branch alone does not establish that the installed patch is current. Final acceptance requires an approved patched runtime and re-verification of actual web/worker/scheduler processes.

The upgrade must preserve account/session revocation, password reset, role/ownership checks, CSRF, private financial responses, file upload validation and offering/appointment transaction locks. Existing security and concurrency suites are validation inputs, not proof that all routes have been audited.

The exhaustive route/controller/file security review, authenticated cross-role acceptance, production HTTPS/cookies/proxies/debug/worker checks and backup restoration are not complete. No claim of legal compliance or absolute security is made. Re-run both dependency audits against the final installed lockfiles and record remaining findings before any production decision.
