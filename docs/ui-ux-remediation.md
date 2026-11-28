# UI/UX remediation plan

## Current status

No application remediation has been performed. The request requires a complete baseline before changes, and this baseline could not safely reach authenticated roles or stateful screens. Remediation without that evidence could alter role workflows or mask existing defects.

## Blockers to resolve before implementation

1. Start the browser server against a verified isolated database. `.env` currently selects `dtc_enrollment`; the attempted process environment override did not change Laravel's effective connection. Do not run Playwright against that server for stateful workflows.
2. Provision disposable Admin, Registrar, Cashier, Student, and Alumni accounts and minimal fixtures in that isolated database. Preserve the operational database and existing user records.
3. Install or otherwise provide axe-core in a way approved for the repository, then rerun the complete five viewport × light/dark matrix for all selected screens.
4. Capture reproducible defects with route, role, screenshot, actual/expected behavior, severity, and a test before choosing targeted fixes.

## Candidate architecture checks (not approved fixes)

- Compare Poppins in DTC styles with Source Sans Pro enabled by AdminLTE; standardize only if browser evidence confirms unintended divergence and the change can be scoped safely.
- Check actual Lucide and Font Awesome usage before removing either icon source.
- Measure duplicate page CSS and token adoption before consolidating styles.

## Regression gate after remediation

Run the same browser matrix and compare screenshots; run axe; verify each affected state and role; run the full PHPUnit suite against `dtc_testing` sequentially; preserve business logic and authorization. Record actual counts and all unresolved findings. No gate has run as part of this baseline.
