# UI/UX remediation plan

## Current status

No product UI remediation has been performed. The six-page public baseline now has 60 viewport/theme renders and 60 axe scans; axe found a critical unnamed wizard control and serious contrast/target-size/label-name violations. Role pages, stateful flows, keyboard/manual accessibility checks, and connected CDN rendering remain untested. MySQL system-table corruption prevents creating least-privilege test credentials, so stateful testing is not safe yet.

## Blockers to resolve before implementation

1. Have a DBA diagnose/recover the corrupt Aria privilege tables. `CHECK TABLE` reports `mysql.global_priv` and `mysql.db` corrupt; logs show Aria log mismatch/missing log. No repair was run. Windows denied disk-space inspection. Then create an account restricted to `dtc_ui_testing`; current ignored test environment file uses root.
2. Provision deterministic disposable Admin, Registrar, Cashier, Student, Alumni, and Applicant fixtures in that isolated schema. No existing production records were copied or modified.
3. `@axe-core/playwright` 4.14.1 is installed and scanned the public baseline. Rerun scans for all role pages when fixtures are safely available and add manual keyboard/focus/modal checks.
4. Restore external asset access or mirror approved dependencies locally, then recapture font/icon-dependent pages.
5. Capture reproducible defects with route, role, screenshot, actual/expected behavior, severity, and a test before choosing targeted fixes.

## Candidate architecture checks (not approved fixes)

- Compare Poppins in DTC styles with Source Sans Pro enabled by AdminLTE; standardize only if browser evidence confirms unintended divergence and the change can be scoped safely.
- Check actual Lucide and Font Awesome usage before removing either icon source.
- Measure duplicate page CSS and token adoption before consolidating styles.

## Regression gate after remediation

Run the same browser matrix and compare screenshots; run axe; verify each affected state and role; run the full PHPUnit suite against `dtc_testing` sequentially; preserve business logic and authorization. Record actual counts and all unresolved findings. No product-code remediation or regression gate has run as part of this audit.
