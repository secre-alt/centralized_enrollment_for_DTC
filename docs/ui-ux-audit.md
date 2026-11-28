# DTC EMS UI/UX baseline audit

**Status:** Partial public-page baseline and remediation; not a release certification. **Date:** 2026-11-28 (Asia/Manila).

## Scope and safety

Discovery reviewed Laravel routes, Blade layouts, AdminLTE configuration, CSS/JS, shared components, and installed dependencies. Browser coverage: **6 public pages**, each at five requested viewports and two theme preferences, for **60 completed renders and 60 screenshots**. Screens: `/`, `/login`, `/apply`, `/apply/status`, `/forgot-password`, `/request-activation`. Before evidence and measurements are in [`ui-ux-evidence/baseline`](ui-ux-evidence/baseline/); intermediate evidence is in [`ui-ux-evidence/after-final`](ui-ux-evidence/after-final/); the latest complete pass is in [`ui-ux-evidence/final`](ui-ux-evidence/final/). The original 120 baseline/intermediate screenshots were preserved; 60 final screenshots were added.

The initial server used `.env`'s `dtc_enrollment`: `artisan serve --env=ui-testing` did not pass `--env` to its child server. An empty `dtc_ui_testing` schema was created and migrated additively. The actual HTTP process was verified through `GET /__ui-test/database`, returning `ui-testing` and `dtc_ui_testing`. The Playwright harness fails closed unless that exact identity is returned. MySQL could not create a restricted account because its grant metadata table is corrupt; the ignored `.env.ui-testing` uses the existing root account. No role accounts or synthetic business records were created. Only public GET routes were visited; no user data or workflow state was intentionally changed. The earlier 37 screenshots were preserved and the current matrix has 60 screenshot files.

## Browser measurements

- HTTP result: 60/60 responses were 200.
- Document horizontal overflow: 0/60 combinations.
- Light/dark class expectation: 60/60 matched requested preference.
- Axe ran 60 scans with WCAG 2.0/2.1/2.2 A/AA tags. Baseline reported **94 repeated violation instances**: `button-name` 6, `color-contrast` 48, `label-content-name-mismatch` 20, and `target-size` 20. After remediation, **4 repeated violation records** remain: `target-size`, two carousel arrows at 768 x 1024 across `/` and `/login` in light/dark (8 reported nodes total). Wizard-name, label-name, and contrast findings no longer reproduce.
- Axe did not test keyboard traversal, actual focus movement, modal focus management, reduced motion, validation/error/success/rejection, disabled, or confirmation states. Role pages were not scanned.
- Each pass recorded 300 denied external-resource requests across repeated browser contexts. The initial 60-render run identified Google Fonts CSS, Lucide UMD from unpkg, and Bootstrap 5.3.3 CSS from jsDelivr. These blocked resources limit font/icon fidelity.
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

**Resolved public findings:** seven unnamed admission wizard step buttons (`resources/views/public/application/create.blade.php`), hint/footer and dark form label/CTA/nav contrast, and carousel card name issues. After axe no longer reports button-name, label-name, or contrast failures. **Remaining medium target-size finding:** service carousel previous/next buttons at 768 x 1024 on `/` and `/login`, all theme preferences; see `ui-ux-evidence/final/results.json`. Changes were limited to public page controls/styles; no business logic changed.

**High environment finding (for stateful coverage):** `mysql.global_priv` and `mysql.db` are corrupt, so a least-privilege database user could not be established. The separate schema is verified, but current root credentials are unrestricted and stateful test operations are unsafe. **Medium environment finding:** remote CDN requests are blocked, limiting typography/icon fidelity and producing browser network errors.

## Ten-criterion scorecard

| Criterion | Result | Evidence / limit |
|---|---|---|
| 1. Visual consistency | PARTIAL | Six public screens captured; no role-to-role comparison; remote assets blocked. |
| 2. Typography | PARTIAL | Public computed family is Poppins; remote font failed; AdminLTE pages not tested. |
| 3. Color and contrast | PARTIAL | Baseline 48 instances; after 0 contrast findings in 60 public renders. Authenticated pages untested. |
| 4. Spacing and layout | PARTIAL | 0/60 document overflow; overlap and clipping were not comprehensively checked. |
| 5. Component consistency | NOT TESTED | Shared components exist; cross-page rendered comparison unavailable. |
| 6. Iconography | NOT TESTED | Lucide/Font Awesome identified; external icon fetch blocked. |
| 7. Responsive UI | PARTIAL | Six public pages at five sizes/two themes; role pages untested. |
| 8. Accessibility | PARTIAL | Final public matrix: 0 axe violations across 60 scans; manual keyboard/assistive checks and authenticated role routes remain untested. Manual keyboard/assistive checks and roles untested. |
| 9. UI states | NOT TESTED | No disposable records; only static/public GET states viewed. |
| 10. Design system architecture | PARTIAL | Tokens/components/dark-mode mechanism found; adoption consistency unmeasured. |

**Totals:** PASS 0 · PARTIAL 7 · FAIL 0 · NOT TESTED 3. This score reflects six sampled public pages, not the full system.

## MySQL infrastructure investigation

The server is MariaDB 10.4.32 (XAMPP Win64), using Aria for privilege tables. Read-only `CHECK TABLE` reports `mysql.global_priv` corrupt (“from another system”) and `mysql.db` marked crashed with a data/index size mismatch and Aria error 176. The error log records an Aria log checkpoint mismatch and a missing `aria_log.00000001` during failed startup. This confirms Aria system-table/log corruption or incompatible on-disk state; it does not establish the initiating cause. Windows denied disk free-space queries, so disk exhaustion is not ruled out. Configuration points to `C:/xampp/mysql/data` and `C:/xampp/tmp`; `ibdata1` uses `10M:autoextend`; no Aria per-table size cap was found. Timestamped full data-directory backup folders exist from prior activity; their provenance must be reviewed before recovery.

No repair was run. MariaDB documents that `aria_chk` must not run while the server is active and recommends backing up all databases before `mariadb-upgrade`. Recovery requires a verified full offline datadir backup, confirmation of backup compatibility/provenance, and DBA review. Do not restore individual `mysql` files, remove Aria logs, or run repair as a trial.

**DBA recovery sequence (recommendation only; not executed):** stop MariaDB cleanly through XAMPP; confirm no `mysqld.exe` remains; make and verify a full offline copy of `C:\xampp\mysql\data` plus `C:\xampp\mysql\bin\my.ini` to a separate volume with sufficient free space; preserve the existing dated backups. Run read-only checks against the offline copy with the matching XAMPP binary: `C:\xampp\mysql\bin\aria_chk.exe --check --read-only <backup>\mysql\global_priv.MAI <backup>\mysql\db.MAI`. Only after confirming matching MariaDB version and backup integrity, a DBA may repair a *working copy* with `aria_chk.exe --recover --backup <working-copy>\mysql\global_priv.MAI <working-copy>\mysql\db.MAI`; recovery can lose unrecoverable privilege rows and change login/grant behavior. Do not run against a live datadir. If system-table format/version is incompatible, use the DBA-approved MariaDB 10.4-compatible system-table upgrade/recovery procedure after full backup; `mariadb-upgrade --upgrade-system-tables` also modifies system tables and is not a substitute for repairing Aria corruption. Once stable, create the scoped account, then verify `SHOW GRANTS` and confirm a connection to `dtc_enrollment` is denied before configuring the browser server.

## Isolation and dependencies

`.env.ui-testing` uses array cache/session, synchronous queue, array mail, and `local` filesystem. Local filesystem storage is not separately rooted, so state-changing uploads are not isolated. The corrupt grant metadata prevents a least-privilege account; the app uses unrestricted root. The HTTP identity guard blocks browser testing against a non-allowlisted database, but does not make root safe for mutations. Authenticated and stateful tests remain blocked.

`@axe-core/playwright` 4.14.1 is installed alongside Playwright 1.65.1. Local hosting appears technically feasible for Bootstrap (MIT), Lucide (ISC), and approved Google Font files, subject to retaining license notices, pinning versions, and checking existing local vendor assets first. No asset migration was made.

## Reproduction

Use ignored `.env.ui-testing` and launch with `APP_ENV=ui-testing` in the server process (not only `artisan --env`). Set `UI_AUDIT_URL=http://127.0.0.1:8011` and run `node tests/browser/ui-ux-baseline.cjs`. The harness verifies the HTTP-serving process's environment and database before visiting a page. Both passes had 0 status/layout/theme failures. Set `UI_AUDIT_EVIDENCE_DIR=docs/ui-ux-evidence/after-final` for after reruns; default output goes to baseline. Each `results.json` contains node targets and axe check data.

## Latest continuation: target-size and server cleanup

The remaining axe issue was caused by the 3D-transformed side service cards overlapping the arrow hit regions in the stacking order. At 768 x 1024 the arrows measured 48×48 CSS px and were laid out with a 36px flex gap; making the arrows positioned with `z-index: 6` placed the controls above transformed cards without changing icons or carousel behavior. Focused scans passed for `/` and `/login` in light/dark. The final 60-render matrix reports 0 axe violation records, 0 bad runs, 0 horizontal overflow and correct theme state in all cases. Evidence is `ui-ux-evidence/final/` (60 PNGs plus node-capable `results.json`). Earlier baseline and intermediate evidence remains intact.

Server cleanup is **not verified**. Port 8011's endpoint still responded with `{"environment":"ui-testing","database":"dtc_ui_testing"}`. Earlier `netstat` attributed port 8011 to PID 15680, but process inspection (`tasklist`, CIM) was denied; the current environment also returns virtual/zero PIDs for listener queries. Executable path and command line could not be verified, so no process was terminated. Port 8012, used for the final pass, also continued to respond after its parent session ended. No broad PHP process kill was attempted.

In an elevated PowerShell, identify and inspect each listener before stopping only the confirmed audit server:

```powershell
$listener = Get-NetTCPConnection -LocalPort 8011,8012 -State Listen
$listener | Select-Object LocalPort, OwningProcess
$listener | ForEach-Object {
  Get-CimInstance Win32_Process -Filter "ProcessId = $($_.OwningProcess)" |
    Select-Object ProcessId, ExecutablePath, CommandLine
}
```

Only if the inspected command line identifies the isolated Laravel audit server and its executable is the expected PHP binary, stop that exact PID with `Stop-Process -Id <verified PID>`. Then verify each port returns no listener and `Invoke-WebRequest http://127.0.0.1:<port>/__ui-test/database` fails to connect. Do not stop unrelated PHP or MariaDB processes.

## Database option evaluation

No system table repair, destructive command, or authenticated workflow test was run. The diagnosed server is XAMPP MariaDB 10.4.32, with Aria corruption reported for `mysql.global_priv` and `mysql.db`; matching tools are under `C:\xampp\mysql\bin` (`aria_chk.exe` reports version 1.2). `mysqld.exe` and `mysql_upgrade.exe` exist. A standalone `mariadb-upgrade.exe` was not found. Docker was not available on PATH in this environment; a second instance was therefore not provisioned or run.

**Version-specific DBA procedure (recommendation only):**

1. Confirm the exact running server version with `C:\xampp\mysql\bin\mysqld.exe --version` and inspect `C:\xampp\mysql\bin\my.ini`; do not assume another MariaDB major/minor version's system-table format is interchangeable.
2. Schedule maintenance, stop MariaDB cleanly from XAMPP, verify no `mysqld.exe` remains, then make a byte-verified offline copy of the complete `C:\xampp\mysql\data` directory and `my.ini` to separate storage. Preserve all prior backup folders and record source/target paths, timestamps, and hashes. Ensure sufficient destination space.
3. On that offline copy only, run matching `aria_chk.exe --check --read-only` against `mysql\global_priv.MAI` and `mysql\db.MAI`. Do not run `aria_chk` on live tables.
4. If recoverable and approved by the DBA, clone the verified offline copy to a separate working copy and run the matching MariaDB 10.4 `aria_chk.exe --recover --backup` on the affected Aria tables in that working copy. Keep the original untouched. Recovery may lose rows or alter grants.
5. Start only the working copy using an isolated config, separate port and loopback binding. Test startup, verify `CHECK TABLE`, confirm critical users/grants exist, run `SHOW GRANTS`, then create a least-privilege audit user. Verify that it can access only the test schema and is denied access to `dtc_enrollment` before any browser state changes.
6. If recovery fails or changes are unacceptable, stop that test instance and discard only the working copy after DBA review; restore by switching back to the untouched verified full datadir copy during a controlled maintenance window. Never restore individual `mysql` system-table files into a live datadir.
7. Only after clean table checks and verified backup, run the matching 10.4 `mysql_upgrade.exe` against the isolated instance if its output indicates upgrade work; review changes and repeat checks. It modifies system metadata and is not a repair substitute.

**Separate disposable instance alternative:** provision an isolated MariaDB 10.4.32 instance (or approved supported version after compatibility review) with its own empty datadir, config, loopback-only bind and unused port; initialize only that datadir, create `dtc_ui_testing` and a narrowly scoped account, then verify denied access to all operational schemas. Use an isolated app storage root and array/sync test services. This avoids the damaged shared privilege tables. It remains a setup recommendation, not an executed environment; no alternate server was available here.

## Authenticated coverage status

Authenticated screens and workflow states remain **not tested**. No deterministic fixtures or disposable role accounts were created because neither the shared unrestricted root setup nor the corrupted privilege tables provide safe account isolation. No PHPUnit suite was run. The 122 GET route templates inventoried earlier are route definitions, not distinct UI screens. A follow-up must enumerate rendered pages per role and state only after a safe isolated database, app storage, mail, queue and session environment exists.
