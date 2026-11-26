# Paid enrollment workflow: implementation and acceptance

## Registrar review presentation — November 26, 2026

Enrollment review now uses a compact program/year/semester/classification summary, with submission time displayed in Asia/Manila. Each subject and its section selector share one row group; labels retain the subject code for screen readers. Shared section guidance appears once, and the prepayment hold explains that sections come before Cashier verification and enrollment approval. Its header reads “Sections required.” The same template serves the modal and standalone page; presentation rules are scoped to enrollment review. Existing form associations, actions, permissions and server validation are preserved. No migration or asset build is required.

Verification: 33 focused automatic-assignment, Cashier-first and subject-replacement tests passed (373 assertions). The updated browser fixture passed 42 cases across seven dialog families, three widths and two themes, including section/form association, neutral notice styling, one-column instructions, footer visibility and scrolling the final selector clear of the footer. The sandboxed Edge launch timed out; the fixture passed outside the sandbox and its screenshot was inspected. Targeted Pint, PHP/JavaScript syntax and whitespace checks passed. This is presentation verification, not full regression acceptance; the full PHP suite was not rerun for this UI-only follow-up. Authenticated manual role acceptance remains outstanding.

## New submission follow-up: sections before payment

The user approved automatic complete-group assignment from published timetables and chose a prepayment Registrar section-assignment hold when no complete group fits. See [draft timetable generation](draft-timetable-generation.md#automatic-assignment-and-prepayment-section-hold) for the implementation and limits. This supersedes unconditional cashier-first instructions for new unassigned submissions; final academic approval still requires verified payment. Existing records are not backfilled or newly held.

Manual acceptance must cover a successful whole-group assignment, a full group and a mixed-year load waiting for Registrar assignment, payment blocked through direct requests while held, complete manual assignment releasing payment without approving/promoting the student, and the existing paid review/replacement/refund paths. An explicit unpaid hold reserves no seats; assigned pending enrollments do occupy seats. Legacy unknown assignments remain reconciliation blockers. No seat expiry is invented.

Automatic-assignment follow-up verification (November 18, 2026): the final full suite passed **623 tests, 7,346 assertions**. The focused automatic-assignment/concurrency/refund suite passed **18 tests, 214 assertions**. Both first-time empty-index and returning-student older-snapshot concurrency cases passed; the existing paid-replacement offering-lock proof and timetable-publication proof remain covered. Forty-two shared modal fixtures and the enrollment revision DOM fixture passed. Authenticated manual acceptance above remains unperformed. These results supersede earlier follow-up counts for the current implementation; older counts below describe their respective historical changes.

## Repository audit and implementation map

### Additional concurrency regression checks (November 19, 2026)

`EnrollmentTransitionConcurrencyTest` runs real independent PHP controller workers against committed fixtures in `dtc_testing`. Before releasing a parent-held row lock, every case requires both worker connection IDs to appear in `information_schema.innodb_trx` with `LOCK WAIT`. It covers competing manual prepayment section assignments for one seat, duplicate approval, duplicate rejection recovery, recovery versus new same-term submission, recovery versus full-refund confirmation, and duplicate full-refund confirmation. Assertions check the surviving academic state, assignment/revision counts, and original verified payment identity and collection details. These are controlled simultaneous requests, not exhaustive proofs of every possible interleaving or HTTP middleware acceptance checks.

The payment-preservation assertion exposed legacy MySQL implicit `ON UPDATE` behavior on `payments.paid_at`. Migration `2026_11_19_000001_preserve_payment_collection_timestamp.php` explicitly retains a creation default without automatic updates, preserving stored dates. Apply it with `php artisan migrate` before using the fix outside the test database. Its rollback deliberately keeps this protection. No existing collection date is reconstructed or guessed.

Verification: the full sequential MySQL suite passed **629 tests, 7,465 assertions**, including all six new two-process cases and the existing replacement, automatic-assignment and timetable-publication concurrency tests. Targeted Pint and PHP syntax checks passed for the new files. No operational migration or authenticated browser acceptance was performed for this follow-up.

The existing application already has paid-enrollment revision requests, directed equivalency approvals, term-specific class offerings, section assignments, refund reconciliation, notifications, and COR rendering. The implementation extends those paths rather than adding a second enrollment or payment workflow.

| Area | Existing implementation and reuse |
| --- | --- |
| Enrollment creation | Student EnrollmentController; configured school year/semester; regular and irregular curriculum selection |
| Academic identity | CourseSubject belongs to the official program; EnrollmentSubjectOffering maps that curriculum selection to an actual section |
| Equivalency | Directed CourseSubjectEquivalency records; explicit approver, reason, units and load treatment |
| Revision | Registrar return action; EnrollmentReplacementRequest and items; Student revision action |
| Capacity | ClassSchedule term, activity and capacity; pending/approved assignments occupy seats; unknown active assignments require reconciliation |
| Financial integrity | Existing verified Payment rows retained; replacement does not create payments or collection events |
| Refund | Existing Cashier full-refund reconciliation with exact amount/reference, row locks and confirmer/timestamp |
| Presentation | Existing student status/dashboard, Registrar review, Cashier queue, Admin reports and COR |

## Follow-up plan and changes

1. Keep recorded unavailable sections out of both the picker and server submissions.
2. Recalculate Irregular treatment from the complete current load and its original baseline, including unaffected offerings and equivalencies.
3. Require student confirmation server-side; show original and proposed selections before submission and clear confirmation when a selection changes.
4. Reject duplicate academic subjects across both replacements and unaffected selections.
5. Exercise simultaneous submissions using two PHP processes against MySQL, with both transactions blocked on the same offering lock before release. Use a current locking read for occupied seats so a pre-lock repeatable-read snapshot cannot overbook the section, and retry resubmission transactions a bounded three times on deadlocks.
6. Run the complete PHPUnit suite against `dtc_testing`; compare with the pre-change run. Perform focused browser checks and retain explicit manual acceptance steps.

The broader specification takes precedence over the original plan where they differ. However, it explicitly forbids inventing institutional policy. The original plan's full-refund-only, fixed-fee and manual academic-policy boundaries remain limits of the existing application, not claims that all conceptual workflows in the specification exist.

## State transitions

| Trigger | Academic enrollment | Revision request | Payment |
| --- | --- | --- | --- |
| Registrar returns paid load | pending | open | verified, no refund opened |
| Student confirms valid replacement | pending | submitted | original verified payment preserved |
| Registrar approves | approved | approved | preserved |
| Registrar requests another revision | pending | new open request | preserved |
| Student confirms refund request | rejected/non-active | refund_requested | verified, refund pending |
| Cashier records actual full refund | rejected/non-active | refunded after all refunds confirmed | verified original collection, separately confirmed refund |

Repeat or conflicting actions are guarded by ownership, enrollment/request states, and transactions. The page's Cancel link navigates away; it does not withdraw the request. Abandoned revisions remain open with no invented seat expiry. There is no configured withdrawal, reduced-load, deferral or refund-denial workflow.

## Files in this follow-up

- `app/Http/Controllers/Student/EnrollmentController.php`: explicit confirmation and final-load duplicate validation; complete-load classification call.
- `app/Services/EnrollmentSubjectReplacementService.php`: server rejection of unavailable section and complete-load classification (from the preceding audit fixes), current locking seat count, and option-specific load/equivalency details.
- `resources/views/student/enrollment/revise.blade.php`: original/new selection review, confirmation, accurate explanation when any affected subject lacks alternatives, and policy notice.
- `public/js/enrollment-revision.js`: safe text rendering of selection review; reset confirmation after changes.
- `tests/Feature/EnrollmentSubjectReplacementWorkflowTest.php`: confirmation, duplicate-load, unavailable-section and repeated-revision coverage.
- `tests/Feature/EnrollmentReplacementConcurrencyTest.php` and `tests/Support/enrollment-replacement-worker.php`: simultaneous real-controller submissions on separate database connections.
- `tests/browser/enrollment-revision.cjs`: isolated Edge check for review text, load previews, confirmation reset and safe rendering. This fixture does not replace manual checks of the authenticated portals.
- This acceptance document and the existing implementation plan.

## Migrations and operation

The follow-up requires no new schema or package. Existing additive workflow migrations are:

- `2026_11_11_000001_create_enrollment_subject_replacement_workflow.php`
- `2026_11_11_000002_add_baseline_load_classification_to_replacement_requests.php`

Normal deployment applies pending migrations with `php artisan migrate --force` after the institution's normal backup/deployment procedure. This work does not run migrations against the application's main database. Existing ambiguous section assignments are not backfilled.

Admins must configure subject classification, current-term sections/capacity and explicit cross-program load treatment. Authorized Registrar staff record directed equivalencies. Resolve unknown active section assignments before offering seats; missing section/capacity data does not imply unlimited availability.

## Policy decisions and limits

Gap corrections (15 November 2026): the Registrar can now explicitly record missing sections for reconciliation using the existing review form without approving the enrollment. Known committed capacity, term, academic identity, duplicates and schedule checks remain enforced; ordinary approval/replacement still blocks on other unknown active assignments. Recovery takes the same user lock as creation and refuses another active enrollment for the recovered term. Returning students with a recorded institutional program are restricted to that program in both the form and POST. No program is inferred for a missing profile and no shifting policy is invented. See `enrollment-flow-audit-2026-11-15.md` and `EnrollmentGapRegressionTest` for the two-full-subject scenario and coverage limits.

### Recovering an incorrect paid rejection

Recovery verification: the recovery-specific test passed (18 assertions). The full run executed 590 tests with 6,873 assertions and reported one error in `DocumentRequestPaymentTrackingTest::test_outbox_retry_after_acknowledgement_failure_creates_only_one_notification` (`TestResponse` called `getMessage()` on a string). That outbox test passed alone (8 assertions); a clean full-suite pass is not claimed. Blade compilation and targeted whitespace checks passed. Authenticated recovery/refund race acceptance remains outstanding; no operational record was reopened during implementation.

Registrar staff can use the existing subject-replacement form on a rejected paid enrollment to correct a rejection caused by unavailable subjects. They must select affected subjects, enter a correction reason and explicitly confirm that they checked with the Cashier that no external refund was issued. The server locks the enrollment and payment records, rejects recorded refund evidence or an existing student refund request, restores pending academic status and cancels only uncompleted refund-follow-up flags. Original verified collections, receipts, official program, subject selections, assignments and rejection remarks remain preserved. The audit entry `enrollment.rejection_recovered` records the actor, original reason, correction reason, affected subjects and prior refund flags; the replacement request records original sections and its actor/time. All recovery changes roll back if subject or offering validation fails. Repeat submissions cannot create another open request. The student then uses the existing replacement/refund decision flow. This action does not infer a correction from free-text rejection reasons, issue a refund, or reopen approved historical enrollments. No migration is needed.

Full-class review guidance: the paid review notice links directly to the existing subject-replacement form and explains that only affected subjects should be returned. The rejection panel and confirmation explicitly distinguish ending the paid enrollment (Cashier reconciliation) from returning subjects (payment preserved, student replacement choice). A section change alone does not imply Irregular treatment. Existing rejected records are not reopened or rewritten by this presentation change.

Paid-rejection guidance follow-up: the student's status card and enrollment payment details now explain the next Cashier-office step, identify the enrollment number and documents/payment evidence to bring, distinguish unconfirmed refunds from completed reconciliation, and say not to pay again while reconciliation is pending. After recorded full-refund confirmation, a new-enrollment link leads to the existing form and its term/eligibility checks. These are guidance and navigation changes only: they neither issue a refund nor reopen a rejected enrollment for subject replacement. Academic questions remain with the Registrar. No office hours, contact information, refund entitlement or processing deadline is invented.

### Approval validation and optional equivalencies (2026-11-14)

The existing review dialog now keeps academic-equivalency setup collapsed and optional. A single notice identifies unclassified subjects. Ordinary same-subject offerings do not require an equivalency or inferred subject classification. Matching-unit target choices omit existing directed mappings; the recording endpoint rejects mismatched units as well as self/duplicate mappings. The subject-management picker uses the same target exclusions. No classifications are backfilled.

Every pending paid enrollment must have a complete recorded offering load before approval. Existing assignments cannot be changed through approval; use the existing replacement flow. For missing assignments, the Registrar selects recorded same-subject offerings beside the subjects in the same dialog. Empty pickers require offering/term/capacity reconciliation in existing subject management. Legacy enrollments with no section assignments are not silently assigned a section or granted unlimited seats. Unknown active assignments can also block capacity confirmation; reconcile those records before approval.

Approval locks the enrollment, curriculum rows, assignments and offering rows, checks the latest payment is verified without a refund, and validates all subjects, directed equivalencies, matching units, offering activity/term/capacity, duplicate target subjects, schedules and previously unavailable sections. It uses the existing current locking occupancy count and three bounded transaction attempts. Assignments, load treatment and approval save together; failures roll back and leave verified payment records unchanged. Existing Irregular treatment is preserved, and required offering/equivalency treatment is added. Notifications remain outside the retried transaction. Unknown schedule text is rejected rather than partially parsed.

This does not introduce prerequisite, overload, reduced-load or refund policy. Registrar academic review still applies the institution's actual approved rules. No new page, migration or dependency is required. Manual acceptance must exercise missing-section selection, its association with the existing approval form, stale/offering errors, optional equivalency disclosure, historical-term offerings, long names and phone layout. Shared modal fixtures alone do not establish authenticated manual acceptance.

Verification for this follow-up: the full isolated MySQL suite passed (588 tests, 6,845 assertions), including the existing two-process replacement lock test. The new approval-load cases passed (6 tests, 63 assertions); 42 shared modal browser fixtures and the subject-management target-picker JavaScript check passed. PHP syntax, Blade compilation and whitespace checks passed. These results do not establish a separate two-process approval-capacity proof or authenticated manual acceptance.

The user identified these governing authorities. This identifies where approved rules must come from; it does not supply numeric limits, prerequisite mappings, waiver permissions, refund percentages or effective dates. No regulatory entitlement or threshold is inferred from this list.

| Matter | Authority identified by the user |
| --- | --- |
| Prerequisites | Approved curriculum, course catalog, Registrar rules for passing grades, equivalencies and waivers |
| Maximum units | School academic-load policy and applicable CHED program standards; prescribed overload approvals |
| Reduced load or deferral | School enrollment, leave-of-absence and withdrawal policies; applicable scholarship conditions |
| Refunds | Published school refund schedule and applicable CHED rules; public-institution free-higher-education rules where applicable |

Before enabling automatic enforcement or new financial options, record the actual approved rules, scope/program, effective term, approving authority and exceptions. The repository does not establish the institution's regulatory eligibility or a student's refund entitlement.

- There is no prerequisite graph, passing-grade policy or maximum-unit configuration. Automatic prerequisite/load-limit validation cannot be claimed. Registrar review must apply the institution's academic rules before approval.
- Reduced load and deferral need institutional approval rules and fee treatment. They remain unavailable; the interface explains this and offers existing refund follow-up.
- The existing fixed enrollment fee requires no adjustment during a same-credit revision. There is no variable-unit fee or additional-balance engine.
- Cashier currently reconciles exact full refunds only. Partial amounts, denial/appeal, failed external transfers and supporting-evidence policies need an explicit school decision before extending financial behavior. A request is not an entitlement or proof of a completed refund.
- No external GCash refund is issued. Refunds remain manual reconciliation of an actual transfer, with the original verified collection preserved in reports/history.
- No seat-expiry duration, automatic withdrawal or abandoned-revision cleanup is invented.

## Verification commands

Run database tests sequentially because they share the dedicated test database. The concurrency test migrates only that database and uses independent PHP processes; never point PHPUnit at operational data.

```text
php vendor/phpunit/phpunit/phpunit --no-coverage
php vendor/phpunit/phpunit/phpunit --filter=EnrollmentReplacementConcurrencyTest --no-coverage
node --check public/js/enrollment-revision.js
node tests/browser/enrollment-revision.cjs
```

## Automated verification record

- Pre-change full suite: 567 tests, 6,607 assertions passed. No baseline failures were reported.
- Final full suite: 571 tests, 6,652 assertions passed, including the new two-process MySQL concurrency test. No regressions were reported. JUnit/output artifacts are under ignored `storage/framework/testing/enrollment-{baseline,final}.*`.
- Focused revised workflow and actual concurrent submissions: 21 tests, 232 assertions passed.
- Edge DOM fixture passed original/replacement review, Regular/Irregular previews, retained Irregular treatment, confirmation reset and safe rendering of markup as text. Edge required execution outside the restricted renderer sandbox; the fixture does not use the application database.
- PHP formatting, targeted syntax checks and JavaScript syntax checks passed.
- The concurrency test initially reproduced both a deadlock response and a stale-snapshot overbooking case. The bounded retry and current locking capacity read corrected those failures; the successful run verifies two simultaneous lock waiters, one accepted revision and one validation rejection.

## Manual browser acceptance

Use disposable local test accounts and fixtures, with configured current-term offerings.

- Student: open the exact returned enrollment from its notification/dashboard. Check reason, affected subjects and verified payment. Select permitted sections; check original/new comparison, host, time, units and seats. Confirm and change a choice; confirmation must clear. Confirm and submit; verify one enrollment/payment and Registrar-review state. Repeat at narrow widths and with keyboard navigation.
- Student: request refund with a reason and explicit confirmation. Verify non-active academic status and pending financial refund, not completed refund. Another student must not access or submit these actions. Approved enrollments remain immutable.
- Registrar: return one and multiple subjects with a reason; verify payment remains intact and no refund opens. Review submitted offerings/history and approve or return again. Check same-major transfer, permitted cross-program minor and directed equivalent major. Final COR must show actual assigned sections and the original degree program.
- Cashier: confirm replacement-only cases stay out of the refund queue. Open student-requested refund; check original payment and reason. Wrong amount/reference and repeat confirmation must fail. Only an actual confirmed refund changes the financial refund status.
- Admin: inspect existing academic and payment oversight, original collections, pending refunds and confirmed refunds. A replacement must not create revenue. Verify unapproved equivalencies and absent capacity remain unavailable.

Manual checks must be reported separately from automated PHP and browser fixture checks. Passing unit/feature tests is not evidence that every role has been manually exercised.
