# DTC EMS UI/UX baseline audit

**Status:** Partial baseline; not a release certification. **Date:** 2026-11-28 (Asia/Manila).

## Scope and safety

Discovery reviewed Laravel routes, Blade layouts, AdminLTE configuration, CSS/JS, shared components, and installed dependencies. Browser coverage: **6 public pages**, each at five requested viewports and two theme preferences, for **60 completed renders and 60 screenshots**. Screens: `/`, `/login`, `/apply`, `/apply/status`, `/forgot-password`, `/request-activation`. Artifacts and machine-readable measurements are in [`ui-ux-evidence/baseline`](ui-ux-evidence/baseline/).

The initial server used `.env`'s `dtc_enrollment`: `artisan serve --env=ui-testing` did not pass `--env` to its child server. An empty `dtc_ui_testing` schema was created and migrated additively. The actual HTTP process was verified through `GET /__ui-test/database`, returning `ui-testing` and `dtc_ui_testing`. The Playwright harness fails closed unless that exact identity is returned. MySQL could not create a restricted account because its grant metadata table is corrupt; the ignored `.env.ui-testing` uses the existing root account. No role accounts or synthetic business records were created. Only public GET routes were visited; no user data or workflow state was intentionally changed. The earlier 37 screenshots were preserved and the current matrix has 60 screenshot files.

## Browser measurements

- HTTP result: 60/60 responses were 200.
- Document horizontal overflow: 0/60 combinations.
- Light/dark class expectation: 60/60 matched requested preference.
- Axe ran 60 scans with WCAG 2.0/2.1/2.2 A/AA tags. It reported **94 repeated violation instances across 11 route/rule combinations**. Findings include critical `button-name` on `/apply` step controls and serious contrast, target-size, and label-name issues on public pages.
- Axe did not test keyboard traversal, actual focus movement, modal focus management, reduced motion, validation/error/success/rejection, disabled, or confirmation states. Role pages were not scanned.
- The harness recorded 300 failed external-resource requests in the axe run across repeated browser contexts. The initial 60-render run identified Google Fonts CSS, Lucide UMD from unpkg, and Bootstrap 5.3.3 CSS from jsDelivr. These blocked resources limit font/icon fidelity.
- The earlier unlabeled-control count was a heuristic and is not an accessibility result.

## Discovery notes

- Runtime reports Laravel 13.44.1 / PHP 8.4.28, while repository guidance states Laravel 9 / PHP `^8.0.2`; verify dependency drift separately.
- Authenticated layout: `resources/views/vendor/adminlte/master.blade.php`; public layout: `resources/views/layouts/public.blade.php`.
- `public/css/dtc-theme.css` contains DTC color, radius, shadow, transition, and dark overrides. Poppins is used by public layout/shared CSS; AdminLTE may load Source Sans Pro.
- Lucide is loaded from unpkg; Font Awesome assets remain installed. Rendered icon parity was not tested.
- Dark mode uses `dtc-ems-theme` local storage and `dtc-dark`; the public layout has a preload path. The browser matrix checked the class state, not OS preference equivalence.
- Shared DTC Blade components include page header, stat card, status badge, empty state, toast, confirm modal, student welcome, avatar, and password guidance.

## Role route inventory

Laravel reported **122 GET route templates** (not a count of distinct UI screens):

- **Admin:** `/admin/dashboard`, `/admin/programs`, `/admin/reports/*`, `/admin/settings/*`, `/admin/users`, `/admin/transactions`.
- **Registrar:** `/registrar/dashboard`, `/registrar/applications`, `/registrar/appointments`, `/registrar/claiming-appointments`, `/registrar/documents`, `/registrar/enrollments`, `/registrar/imports`, `/registrar/students`, `/registrar/transactions`.
- **Cashier:** `/cashier/dashboard`, `/cashier/payments`, `/cashier/document-payments`, `/reports/payments`.
- **Student:** enrollment, appointment, COR, profile, payment, and transaction routes.
- **Alumni:** portal dashboard, document requests, profile/payment history routes.
- **Applicant:** public application/status and authenticated new-applicant portal routes.
- **Unauthenticated pages rendered:** the six public pages above. Authenticated role pages and parameterized application results were skipped because no isolated role fixtures exist.

These route groups include API, redirect, download, print, and parameterized endpoints and must not be treated as 122 independent screens.

## Findings

**Critical accessibility finding:** the seven application wizard step buttons on `/apply` have no discernible accessible names (`button-name`), source `resources/views/public/application/create.blade.php`, captured in `application-light-360x800.png` and repeated across the sampled application renders. **Serious contrast finding:** axe flags low-contrast hint/footer copy on `/`, `/login`, `/apply`, `/apply/status`, `/forgot-password`, and `/request-activation`; the measured selectors and contrast ratios are in `results.json`. **Serious target-size/label finding:** axe flags service carousel cards/dots on `/` and `/login`. These are the pages/source locations and reproducible evidence; no remediation has been applied yet.

**High environment finding (for stateful coverage):** `mysql.global_priv` and `mysql.db` are corrupt, so a least-privilege database user could not be established. The separate schema is verified, but current root credentials are unrestricted and stateful test operations are unsafe. **Medium environment finding:** remote CDN requests are blocked, limiting typography/icon fidelity and producing browser network errors.

## Ten-criterion scorecard

| Criterion | Result | Evidence / limit |
|---|---|---|
| 1. Visual consistency | PARTIAL | Six public screens captured; no role-to-role comparison; remote assets blocked. |
| 2. Typography | PARTIAL | Public computed family is Poppins; remote font failed; AdminLTE pages not tested. |
| 3. Color and contrast | FAIL | Axe reports serious contrast violations on all six public pages in sampled light renders. |
| 4. Spacing and layout | PARTIAL | 0/60 document overflow; overlap and clipping were not comprehensively checked. |
| 5. Component consistency | NOT TESTED | Shared components exist; cross-page rendered comparison unavailable. |
| 6. Iconography | NOT TESTED | Lucide/Font Awesome identified; external icon fetch blocked. |
| 7. Responsive UI | PARTIAL | Six public pages at five sizes/two themes; role pages untested. |
| 8. Accessibility | FAIL | Critical unnamed wizard buttons and serious touch-target/label-name violations; manual keyboard/assistive checks untested. |
| 9. UI states | NOT TESTED | No disposable records; only static/public GET states viewed. |
| 10. Design system architecture | PARTIAL | Tokens/components/dark-mode mechanism found; adoption consistency unmeasured. |

**Totals:** PASS 0 · PARTIAL 5 · FAIL 2 · NOT TESTED 3. This score reflects six sampled public pages, not the full system.

## MySQL infrastructure investigation

The server is MariaDB 10.4.32 (XAMPP Win64), using Aria for privilege tables. Read-only `CHECK TABLE` reports `mysql.global_priv` corrupt (“from another system”) and `mysql.db` marked crashed with a data/index size mismatch and Aria error 176. The error log records an Aria log checkpoint mismatch and a missing `aria_log.00000001` during failed startup. This confirms Aria system-table/log corruption or incompatible on-disk state; it does not establish the initiating cause. Windows denied disk free-space queries, so disk exhaustion is not ruled out. Configuration points to `C:/xampp/mysql/data` and `C:/xampp/tmp`; `ibdata1` uses `10M:autoextend`; no Aria per-table size cap was found. Timestamped full data-directory backup folders exist from prior activity; their provenance must be reviewed before recovery.

No repair was run. MariaDB documents that `aria_chk` must not run while the server is active and recommends backing up all databases before `mariadb-upgrade`. Recovery requires a verified full offline datadir backup, confirmation of backup compatibility/provenance, and DBA review. Do not restore individual `mysql` files, remove Aria logs, or run repair as a trial.

**DBA recovery sequence (recommendation only; not executed):** stop MariaDB cleanly through XAMPP; confirm no `mysqld.exe` remains; make and verify a full offline copy of `C:\xampp\mysql\data` plus `C:\xampp\mysql\bin\my.ini` to a separate volume with sufficient free space; preserve the existing dated backups. Run read-only checks against the offline copy with the matching XAMPP binary: `C:\xampp\mysql\bin\aria_chk.exe --check --read-only <backup>\mysql\global_priv.MAI <backup>\mysql\db.MAI`. Only after confirming matching MariaDB version and backup integrity, a DBA may repair a *working copy* with `aria_chk.exe --recover --backup <working-copy>\mysql\global_priv.MAI <working-copy>\mysql\db.MAI`; recovery can lose unrecoverable privilege rows and change login/grant behavior. Do not run against a live datadir. If system-table format/version is incompatible, use the DBA-approved MariaDB 10.4-compatible system-table upgrade/recovery procedure after full backup; `mariadb-upgrade --upgrade-system-tables` also modifies system tables and is not a substitute for repairing Aria corruption. Once stable, create the scoped account, then verify `SHOW GRANTS` and confirm a connection to `dtc_enrollment` is denied before configuring the browser server.

## Isolation and dependencies

`.env.ui-testing` uses array cache/session, synchronous queue, array mail, and `local` filesystem. Local filesystem storage is not separately rooted, so state-changing uploads are not isolated. The corrupt grant metadata prevents a least-privilege account; the app uses unrestricted root. The HTTP identity guard blocks browser testing against a non-allowlisted database, but does not make root safe for mutations. Authenticated and stateful tests remain blocked.

`@axe-core/playwright` 4.14.1 is installed alongside Playwright 1.65.1. Local hosting appears technically feasible for Bootstrap (MIT), Lucide (ISC), and approved Google Font files, subject to retaining license notices, pinning versions, and checking existing local vendor assets first. No asset migration was made.

## Reproduction

Use ignored `.env.ui-testing` and launch with `APP_ENV=ui-testing` in the server process (not only `artisan --env`). Set `UI_AUDIT_URL=http://127.0.0.1:8011` and run `node tests/browser/ui-ux-baseline.cjs`. The harness verifies the HTTP-serving process's environment and database before visiting a page. The completed run had 0 status/layout/theme failures; axe violations are preserved in `results.json`.
