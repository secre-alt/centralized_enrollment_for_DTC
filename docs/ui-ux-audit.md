# DTC EMS UI/UX baseline audit

**Status:** Partial baseline, not a release certification. **Date:** 2026-11-28 (Asia/Manila).

## Scope and method

Read-only discovery covered Laravel routes, Blade layouts, AdminLTE configuration, shared CSS/JS, shared DTC components, and Playwright availability. Browser screenshots were collected from `http://127.0.0.1:8000` for public home, login, application, and application-status pages using Chromium at 360×800, 390×844, 768×1024, 1366×768, and 1920×1080 in light and dark preference. Evidence is in [`ui-ux-evidence/baseline`](ui-ux-evidence/baseline/).

The browser run was stopped after 40 screenshots when it became clear `artisan serve` was rendering with `.env`'s `dtc_enrollment` database rather than the dedicated `dtc_testing` database. Only GET requests were made; no form was submitted and no records were intentionally changed. The run did not finish, so no aggregate layout/console report was produced. A generated untracked `debug.log` was noticed and left untouched. Browser screenshots establish rendered appearance only; they do not certify production-safe test data.

Playwright is installed and Chromium launches. `axe-core` and `@axe-core/playwright` are not installed. Network access is restricted, so automated axe scans were not run. No authenticated role credentials or isolated role fixtures were available, and role pages were not visited. The system has many more routes than the six public screens sampled; the actual number of distinct screens fully audited is **0**. Four pages have partial screenshot evidence, with incomplete viewport/theme coverage for application status.

## Discovery notes

- Framework reports Laravel 13.44.1 and PHP 8.4.28 in this checkout; this differs from the repository guidance's Laravel 9 expectation and merits dependency/config confirmation.
- Authenticated pages use the `adminlte::page` layout. Public pages use `layouts.public`.
- `public/css/dtc-theme.css` defines DTC color, radius, shadow, and transition tokens and extensive light/dark styles. It imports Poppins. Public layout separately loads Poppins and Bootstrap 5; AdminLTE configuration allows Source Sans Pro. This is a potential font inconsistency, not a rendered role comparison.
- Lucide is loaded from unpkg in layouts and used through `data-lucide`; AdminLTE assets also include Font Awesome. No icon rendering consistency check completed.
- Dark theme uses `dtc-ems-theme` local storage and `dtc-dark` classes; public layout preloads dark mode. Screenshots were attempted for both preferences, not verified for every route.
- Shared DTC components include page header, stat card, status badge, empty state, toast, confirm modal, and student welcome.

## Route inventory by role

The complete route inventory is available from `php artisan route:list --json`; this audit's browser sample did not exercise role routes. Role prefixes and page groups include:

- **Admin:** `/admin/dashboard`, `/admin/programs`, `/admin/reports/*`, `/admin/settings/*`, `/admin/users`, `/admin/transactions`.
- **Registrar:** `/registrar/dashboard`, `/registrar/applications`, `/registrar/appointments`, `/registrar/claiming-appointments`, `/registrar/documents`, `/registrar/enrollments`, `/registrar/imports`, `/registrar/students`, `/registrar/transactions`.
- **Cashier:** `/cashier/dashboard`, `/cashier/payments`, `/cashier/document-payments`, `/reports/payments`.
- **Student:** student enrollment, appointment, COR, profile, payment and transaction routes.
- **Alumni:** portal dashboard, document request, profile/payment history routes.
- **Unauthenticated:** `/`, `/login`, `/apply`, `/apply/status`, `/forgot-password`, `/request-activation`, plus activation and password flows.

These are route groups, not a count of distinct screens; parameterized and modal states need separate review.

## Evidence limits

The baseline Playwright harness is [`../tests/browser/ui-ux-baseline.cjs`](../tests/browser/ui-ux-baseline.cjs). It was interrupted before writing `results.json`; it is not a completed test suite and its exit result must not be represented as a pass. The captured PNGs are the only browser artifacts from this run. Do not infer success for missing files or states.

## Scorecard

| Criterion | Result | Evidence and limitation |
|---|---|---|
| 1. Visual consistency | NOT TESTED | Only four public page templates have partial screenshots; no cross-role comparison. |
| 2. Typography | PARTIAL | Source shows Poppins and AdminLTE Source Sans Pro configuration; role-rendered fonts were not compared. |
| 3. Color and contrast | NOT TESTED | Theme tokens and dark-mode CSS exist; no full rendered contrast sampling or axe results. |
| 4. Spacing and layout | NOT TESTED | Partial screenshots exist, but viewport matrix did not finish and no measurements were persisted. |
| 5. Component consistency | NOT TESTED | Shared component definitions exist; repeated rendered instances were not compared. |
| 6. Iconography | NOT TESTED | Lucide and Font Awesome are present; rendered glyphs were not audited. |
| 7. Responsive UI | NOT TESTED | Some viewport screenshots exist; no complete overflow/clipping evaluation was produced. |
| 8. Accessibility | NOT TESTED | No axe-core installed; keyboard and assistive-technology tests were not run. |
| 9. UI states | NOT TESTED | No isolated test data; loading, success, rejection, disabled, validation and confirmation states were not exercised. |
| 10. Design system architecture | PARTIAL | Shared tokens/components and dark theme exist in source; adoption and consistency across screens are unverified. |

**Totals:** PASS 0 · PARTIAL 2 · FAIL 0 · NOT TESTED 8. These are evidence labels, not a claim that tested behavior passed or failed.

## Findings and follow-up

No severity-rated visual defect is asserted from the incomplete baseline. The observed setup blocker is **High** for audit completeness: the local server used the operational `.env` database despite a process-level DB override attempt. Stop using that server for further UI audit traffic. Configure a genuinely isolated database using an explicit Laravel environment file or a verified separate checkout, check the effective connection before starting the server, and provision disposable accounts there. Then complete all role routes, state coverage, axe scans, screenshots, and reproducible issue records before assigning visual defect severity.
