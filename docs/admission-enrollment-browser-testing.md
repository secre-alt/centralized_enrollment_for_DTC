# Admission and enrollment browser checks

Run the database suites sequentially. The browser harness resets only the dedicated `dtc_testing` database, creates committed disposable fixtures, starts a temporary local PHP server, and stops it after the run. Do not run another database suite alongside it.

```text
php vendor/phpunit/phpunit/phpunit --filter="Admission|Application|Enrollment|StudentGcashPaymentTest|PortalPaymentPagesTest|PortalCorEnrollmentSelectionTest" --no-coverage
php vendor/phpunit/phpunit/phpunit tests/browser/AdmissionEnrollmentBrowserTest.php --no-coverage
```

The browser check requires the installed `@playwright/test` package. It is deliberately outside the default Feature/Unit suite. The harness uses Playwright's managed headless launch with installed Microsoft Edge on Windows and downloaded Chromium elsewhere (`npx.cmd playwright install chromium`). Set `BROWSER_CHANNEL` to override that selection. GPU flags preserve the working headless configuration on this host. The former custom TCP debugging launcher failed with connection refusals and has been removed. Startup retains Playwright's browser diagnostics; action/navigation waits and failure artifact capture are bounded. No browser plugin or production login shortcut is used.

## Enrollment consistency and form recovery (November 25, 2026)

The existing enrollment form now displays the configured school year and semester instead of offering semesters the server will reject. Missing term settings show Registrar guidance and disable submission; server validation remains authoritative. Applicant dashboard overview actions reuse the main next-action guidance, including section holds and subject replacement. Enrollment validation displays all returned errors and messages beside relevant fields, and restores regular/irregular subject choices (including an empty selection) on return. Changing program, year or selection mode resets the restored choices to that selection's normal defaults. No migration, dependency or asset build is required.

Regression coverage: `EnrollmentFormRecoveryTest` checks current/stale term input, missing configuration, validation recovery and applicant dashboard states. `tests/js/regular-enrollment-auto-selection.test.cjs` executes the form script for fresh and restored selections. These checks do not establish manual keyboard or phone acceptance.

Verification for this follow-up: the full sequential `dtc_testing` PHP suite passed **753 tests, 9,099 assertions** (`storage/framework/testing/enrollment-recovery-full.log`). Admission/enrollment JavaScript checks passed **20 tests**, including six enrollment selection checks. The admission and section-hold browser journeys passed **2 tests, 32 PHP assertions**, plus browser assertions (`storage/framework/testing/enrollment-recovery-browser-retry.log`); the hold journey now submits an empty load, verifies its selection stays empty after validation, then corrects it and continues. The enrollment creation mobile screenshot was visually reviewed. The initial sandbox browser run failed all five journeys with a hidden-modal click timeout and missing `Chart` errors; only admission and hold were rerun outside the sandbox and passed. Other browser journeys were not rerun outside the sandbox. Syntax and targeted whitespace checks passed. No operational migration or manual role/keyboard acceptance was performed.

## Actual browser coverage

- Required applicant-type validation, the seven-step admission wizard, review and explicit submission confirmation.
- Registrar login, starting admission review and approving the submitted application.
- Password setup and applicant login. The helper mints a normal Laravel password-broker token for the newly approved test account; external email delivery is not tested.
- Enrollment subject selection and automatic assignment from one published group, with no premature payment or approval.
- Cashier login and explicit cash-payment confirmation; enrollment remains pending until Registrar approval.
- Final Registrar approval, student status, one verified payment, one section assignment and student-role promotion.
- Rendered mobile overflow checks and screenshots at 390 × 844 for admission review, enrollment creation, pending status, Registrar review and approved status. Browser JavaScript errors fail the check.
- Full-group hold: a student submits against an occupied published group; cash collection is absent and a crafted collection request creates no payment. Registrar assignment of the full offering fails; a valid alternative enables payment while leaving the enrollment pending and unpaid.
- GCash: upload generated test proof, reject it with a visible reason, submit corrected proof, verify it and approve the enrollment. The rejected attempt remains recorded alongside the verified payment.
- Paid replacement: Registrar returns the assigned section, student cannot reselect that recorded unavailable offering, changing a choice clears confirmation, and resubmission plus approval preserves the complete original payment record.
- Refund: explicit student confirmation is required, the request remains pending until Cashier reconciliation, an incorrect amount fails, and exact full confirmation records the reference and confirmer while preserving the original receipt and collection date.

The four enrollment-specific journeys start from separate committed fixtures rather than repeating admission in each test. Each test resets `dtc_testing`; no browser scenario runs in parallel with another database test. Test GCash proofs and refund references are fictional and exercise local application records only.

The fixture helpers require testing environment and the actual database name `dtc_testing`. The HTTP server uses file sessions and proof-upload storage in its unique artifact directory, an array mailer and array cache. No `.env` changes, operational migrations or live payments are performed. The browser uses real forms, confirmation dialogs, sessions and application routes; it does not bypass HTTP authentication or CSRF middleware.

Artifacts are retained in ignored `storage/framework/testing/browser-flow-*`: browser/server logs, mobile screenshots and failure HTML/screenshots when available. Do not commit browser profiles or session files.

## Cashier control correction found by these tests

The GCash journey reproduced a broken Reject button on the direct Cashier payment page: the reason-panel handlers were registered only inside the list modal. Both surfaces now load `public/js/cashier-payment-actions.js`, which scopes Reject/Cancel to the current payment content, shows or hides its reason panel, switches its footer and restores focus. The modal retains its existing lazy loading. The GCash browser journey exercises Reject/Cancel on both surfaces before verifying the resubmitted proof. Server-side payment and refund rules are unchanged; no migration or asset build is needed for this correction.

## Limits

Coverage comprises the admission/cash-payment journey and four focused enrollment journeys. Other admission types, broader invalid/unauthorized transitions, complex multi-subject loads, directed equivalencies, rejection recovery and concurrency cases remain primarily covered by the PHP suites. These checks do not establish external email delivery, actual GCash transfers/refunds, every browser/device layout, keyboard-only usability or institutional policy acceptance. No automated test result should be described as exhaustive scenario coverage.

## Initial browser verification on November 19, 2026

- Full sequential MySQL PHPUnit suite: **629 tests, 7,465 assertions passed**.
- Admission/enrollment-focused PHP run: **233 tests, 2,763 assertions passed**; these are a subset of the full suite, not additional unique coverage.
- Separate actual Playwright/Chromium journey: **1 harness test, 14 PHP assertions passed**, with additional browser assertions for the steps above. Final screenshots wait for the loading placeholder to disappear. Artifacts: `storage/framework/testing/browser-flow-798cbffbedf6/`.
- Relevant JavaScript tests: **16 passed**, covering admission validation, education profiles, submission confirmation, draft preservation/clearing, text normalization and regular/irregular subject selection.
- Existing Edge revision fixture: passed original/new selection review, confirmation reset, retained Irregular treatment and safe text rendering. This fixture is separate from the authenticated application journey.
- PHP syntax, targeted Pint, JavaScript syntax and whitespace checks passed. No production application logic or environment files were changed for this follow-up.

## Browser verification on November 21, 2026

The final combined run passed **5 tests, 90 PHP assertions**, plus the browser assertions for all five journeys. Output: `storage/framework/testing/browser-final-stable-nov21.log`. The earlier custom-launch run failed to connect; the first Edge run then had two timing failures. Those are retained as failed runs, not counted as passes. The final run uses managed Edge with GPU flags, 30-second action waits, 60-second navigation waits, bounded failure screenshots and a 330-second per-case harness limit. No workflow assertion was removed to obtain the pass.

Final per-journey artifacts:

- Admission: `storage/framework/testing/browser-flow-be70c57ee60b/`.
- Section hold: `storage/framework/testing/browser-flow-cfb08a364e99/`.
- GCash: `storage/framework/testing/browser-flow-5e65ef1e9393/`.
- Replacement: `storage/framework/testing/browser-flow-c8997d67dafe/`.
- Refund: `storage/framework/testing/browser-flow-3d6ce9fd5bef/`.

The section-hold and GCash resubmission mobile screenshots were visually reviewed in addition to the automated overflow checks. These results establish a successful combined run on this host, not a guarantee against future browser/environment timing failures.

After the final browser run completed, the full sequential PHP regression suite passed **629 tests, 7,465 assertions** in 9 minutes 31 seconds (`storage/framework/testing/regression-final-nov21.log`). This is the current regression result after the Cashier control correction. Targeted Pint, PHP/JavaScript syntax and whitespace checks passed. No operational migration, environment-file change, external email delivery or real payment/refund was performed.
