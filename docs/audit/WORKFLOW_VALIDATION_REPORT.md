# Workflow validation

November 27, 2026 (Asia/Manila).

The Laravel 9 baseline passed the complete default Unit/Feature suite: 766 tests / 9,334 assertions. This establishes observed baseline behavior and does not establish target-framework acceptance.

| Workflow | Relevant existing verification | Upgrade acceptance |
| --- | --- | --- |
| Login, reset, activation and account sessions | LoginSecurityRemediationTest, AccountSessionSecurityTest, email/activation suites | Pending checkpoint completion and authenticated browser checks |
| Applicant submission and Registrar review | PublicApplication and admission/enrollment suites | Pending |
| Enrollment, replacements and refunds | EnrollmentSubjectReplacementWorkflowTest and enrollment transition suites | Pending; verified payments and official program identity must be preserved |
| Offering capacity | EnrollmentReplacementConcurrencyTest, automatic section and transition concurrency suites | Pending; retain two-process lock-wait assertions |
| Cashier payment/GCash/receipts | StudentGcashPaymentTest and payment/refund/reconciliation suites | Pending |
| Financial reports and personal history | PaymentReportsTest, personal history/export/ownership suites | Pending; preserve centavos, identity deduplication and privacy |
| Student/alumni document requests | Document workflow/payment/release suites | Pending |
| Appointments and claiming | Appointment lifecycle, claiming and booking concurrency suites | Pending |
| Administration and academic records | AcademicIdentityTest, AcademicRecordManagementTest and administration suites | Pending |
| UI interactions | Edge enrollment-revision fixture passed after sandbox retry | Limited fixture evidence only |

Additional disposable Edge fixtures passed for timetables and 42 modal variants. The initial modal fixture incorrectly required 12px corners for the existing 16px welcome dialog. The user explicitly selected preserving 16px and correcting that expectation; only the fixture changed, retaining the 12px assertion for other dialogs. Existing styles and appearance were preserved.

Default PHPUnit configuration includes `tests/Unit` and `tests/Feature`. The authenticated browser journey harness under `tests/browser` requires separate execution against its guarded disposable test environment; it is not included in the 766-test result.

Cross-role desktop/mobile acceptance, representative before/after screenshots, console/network inspection and production-like staging acceptance have not yet been completed. Preserve the per-role checklist in [enrollment workflow acceptance](../enrollment-workflow-acceptance.md). Do not substitute a route inventory or passing HTML fixture for those checks.

## Current checkpoint evidence

Laravel 11's complete repeat passed 775 tests / 9,374 assertions. Its separate role-browser repeat passed 60 page/viewport checks across Admin, Registrar, Cashier, Student, Alumni and Applicant, plus three expected portal-to-admin denials. There were no uncaught JS errors or unexpected failed requests; the console recorded the intentionally denied requests. Sanitized evidence is in `evidence/role-pages-laravel11.json`. Screenshots are local ignored artifacts under `storage/framework/testing/browser-flow-0cc5cb8dc04e/`. This covers dashboard/list/form navigation, not the separate five transactional browser journeys or the final Laravel 13 comparison.

## Additional audit candidates requiring reproduction

- Cashier dashboard month counts currently filter month without year/status, its walk-in count does not filter method, and recent attempts are displayed using a fixed Paid badge. Compare crafted verified/pending/rejected and cross-year records before correcting these read-only statistics; preserve the established enrollment scope unless the existing reporting specification establishes a wider scope.
- `StudentProfile::generateStudentNumber` reads the last calendar-year ID without a shared allocation lock. Review all three profile-creation callers and reproduce simultaneous first profiles for different students before claiming concurrency safety or changing allocation. Keep the existing number format, official program and original payment unchanged; do not invent academic-year identity rules.
- Manual slot overlap checks occur outside the write transaction and do not share the automatic generator's cache mutex. Existing booking-capacity proofs do not establish concurrent slot-creation safety. Reproduce the overlap race before choosing a shared serialization mechanism.
- Admin enrollment PDF exports load all records. Export behavior at realistic large volumes remains unverified; avoid claiming production-scale acceptance from small synthetic fixtures.
