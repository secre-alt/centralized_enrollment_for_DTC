# Enrollment flow audit — 15 November 2026

Scope: approved application → term enrollment → Cashier payment → Registrar review → section assignment/approval → subject replacement → refund or rejection recovery → COR/history. This is an audit of the current working tree, including the five pre-existing modified files. No production logic or operational data was changed. Test execution uses the forced MySQL `dtc_testing` configuration in `phpunit.xml`.

## Implementation follow-up

The findings below describe the audit baseline. Subsequent authorized implementation now provides:

- **Section reconciliation in the same review dialog:** “Record sections” uses the same complete-load validation and deterministic offering locks, retains pending status, records an audit entry, and checks current committed assignments against capacity. It records explicit assignments; it does not approve enrollment or certify unknown occupancy. Ordinary approval and student replacement still refuse unknown active assignments. Staff reconcile each affected record before approval. Existing assignments cannot be rewritten through this operation.
- **Duplicate recovery protection:** the return/recovery transaction acquires the user lock used by enrollment creation, then checks for another pending/approved enrollment for the same student and the recovered record's own term. Recovery refuses to modify either record if one exists.
- **Recorded-program enforcement:** returning students with an existing institutional program see that locked program on the form. The POST checks the same program under the user/profile locks. Profiles with no recorded program retain the existing workflow; no ambiguous program is inferred or backfilled. A shift must first be approved and recorded through institutional procedures.
- **Two-full-subject student scenario:** `EnrollmentGapRegressionTest` exercises Registrar return, student view and submission of a same-subject alternative plus directed GE equivalent, preserved unaffected assignment and curriculum IDs, recorded Irregular treatment, final approval and unchanged original payment/receipt. It also tests both students completing reconciliation/approval, full-section rollback, role boundaries, program mismatch denial, ordinary official-program submission, and duplicate recovery denial.

The new focused tests passed: 5 tests, 41 assertions. Existing affected suites passed: 46 tests, 367 assertions. These counts are separate executions, not an additive unique-coverage total. Full-suite results are recorded after the final run. Separate two-process reconciliation and recovery/create race proofs remain beyond the existing replacement concurrency test.

Final implementation verification: **595 tests, 6,915 assertions passed** in the full isolated MySQL suite, including the existing two-process replacement lock test and notification-outbox retry test. The outbox warning now uses `exception_type` for its class-name string instead of Laravel's reserved `exception` context key; the logged diagnostic remains a class name and no sensitive exception payload is added. The enrollment-revision browser fixture and 42 shared modal fixtures passed. PHP syntax, Blade compilation and targeted whitespace checks passed. The baseline error described below is historical to the audit and did not recur in this final run. Authenticated manual acceptance and separate concurrency proofs for the new operations remain outstanding. No migration, new dependency or asset build is required.

## Reproduced findings

### High: ordinary unassigned students can prevent one another from enrolling

`EnrollmentSubjectReplacementService::hasUnassignedActiveEnrollmentsForSchedule` treats another pending/approved enrollment without a section assignment as requiring reconciliation. This is conservative for seat safety, but ordinary creation does not record sections and `validateApprovalLoad` saves the first assignments only after that check succeeds. With two paid pending students taking the same subject, selecting an otherwise empty 40-seat section fails for both. Neither approval saves an assignment. The existing ordinary flow therefore has a reconciliation dead end; adding section capacity alone does not solve it.

Reproduction: two paid pending enrollments with the same curriculum subject/term, no assignments, and one active 40-seat section. Both approval POSTs return `replacements` errors and both assignment counts remain zero.

Recommended fix: let staff explicitly reconcile/assign missing sections through one supported operation under offering locks, before the normal approval capacity check. Distinguish fresh unassigned requests from genuinely ambiguous legacy occupancy using an explicit model/policy, not assumptions that unknown seats are available. Preserve pending/approved occupancy rules and the existing current locking count. Do not bypass the unknown-assignment guard or silently backfill sections.

### High: rejection recovery can create two active same-term enrollments

`Registrar/EnrollmentController::returnForSubjectReplacement` locks the recovered enrollment and its payments, but does not reject another active same-user/same-term enrollment or acquire the user lock used by ordinary creation. The ordinary flow allows another request after rejection. Recovery can then restore the old request to pending while the new request is still pending.

Reproduction: rejected paid enrollment A, new pending enrollment B for the same student/term, explicit recovery confirmation on A. Recovery succeeds and both are pending. This is reproducible sequentially; the create/recover race is an additional untested concurrency risk.

Recommended fix: acquire the same user lock as creation before enrollment/payment locks, check for other pending/approved enrollments in the recovered record's term, and refuse recovery with a clear reconciliation instruction. Preserve both records and original payments; do not cancel the newer record automatically. Add a real two-process create/recover test.

### High: returning students can request a different official degree program

`Student/EnrollmentController::store` checks subjects against the submitted program and locks program selection for `new_applicant`, but does not compare an existing student's submitted program with their institutional `StudentProfile.program_id`. The create page offers all programs to students/alumni. `StudentProfile::createForEnrollmentIfMissing` retains the old profile, so the two records can disagree.

Reproduction: student profile in program A; submit an otherwise valid standard load from program B. Enrollment B is created while the official profile remains A. No approved shift decision is required by this endpoint.

Recommended fix: keep ordinary enrollment in the recorded official program; use approved institutional shifting/admission rules for an actual degree change. Do not infer authorization from subject availability or change the profile automatically. The school must define the authoritative program for students with missing/ambiguous profiles and approved shiftees before an ambiguous backfill.

## Test method and interpretation

Three isolated diagnostic probes are saved under ignored `storage/framework/testing/EnrollmentAuditProbeTest.php`. All three reproduced the observed behavior (3 tests, 13 assertions). Their passing assertions confirm the defects exist; they are not regression acceptance or approval to preserve those behaviors. These probes were run only after the full suite completed because both use `dtc_testing`.

The full current suite executed 590 tests and 6,873 assertions with one error in `DocumentRequestPaymentTrackingTest::test_outbox_retry_after_acknowledgement_failure_creates_only_one_notification`: Laravel `TestResponse` attempted `getMessage()` on a string. This is the same failure reported before this audit; the audit made no application changes. A clean full-suite pass is not claimed. Current focused and individual rerun results are recorded below when completed.

The enrollment-revision Edge fixture passed original/new selection review, confirmation-reset and safe-text checks. It does not establish authenticated end-to-end acceptance for every role, device or network condition.

Current focused run: `--filter='Enrollment|StudentGcashPaymentTest|PortalCorEnrollmentSelectionTest|PortalPaymentPagesTest'` passed **127 tests, 1,125 assertions**, including the actual two-process replacement concurrency test. After that completed, the notification-outbox test was rerun alone and **still errored** (`1 test, 7 assertions`, same `getMessage()` failure). Its historical isolated success is not a current result. The raw full/focused logs and diagnostic probes are under ignored `storage/framework/testing/`; no production code or pre-existing local modifications were edited. The audit report passes `git diff --check`.

## Scenario coverage map

Existing PHP tests exercised by the suite cover these groups; “covered” does not mean every data combination, policy or concurrent interleaving has been proven.

| Student situation | Intended outcome / existing coverage |
| --- | --- |
| Applicant without approved application | Enrollment/payment eligibility denied |
| Applicant submits another program | Approved-program restriction enforced |
| Standard load includes another year/semester | Rejected server-side; mixed load requires Irregular mode |
| Transferee takes standard curriculum | Admission type remains separate from load classification |
| Duplicate subjects or foreign-program subjects | Invalid selection rejected |
| Double-click enrollment submit | Active same-term duplicate guard; simultaneous creation is not separately proven here |
| Previous term enrollment exists | Does not block a new configured term |
| Term changes while form is open | Stale semester rejected; historical enrollment retains its own term |
| Cash payment before Registrar review | Payment verified; enrollment remains pending review |
| GCash proof submitted | Pending verification, not automatically paid |
| Invalid proof / repeated upload | Validation and pending/verified attempt guards |
| Cashier rejects proof | Correction/resubmission flow; original attempt retained |
| Cash and GCash attempts compete | Same-user/term payment guards and transactional duplicate checks |
| Approval attempted without verified payment | Denied; no student-profile creation |
| Full/inactive/wrong-term/missing-capacity section | Ineligible; no assumed unlimited seats |
| Sections overlap or contain unknown day text | Approval/replacement blocked |
| Section not yet assigned | Explicit section selection required; multi-student dead end above remains |
| One subject full | Return only affected subject; payment and program retained |
| Several subjects full | Complete replacement choices required; atomic rollback on invalid choice |
| Same subject, another section | Does not by itself make the student Irregular |
| Another program hosts the same subject | Explicit offering load treatment applies; official degree stays unchanged |
| Different academic subject | Directed approved equivalency and matching units required |
| Reverse mapping only / no mapping | No inferred equivalence |
| Replacements duplicate an unaffected academic subject | Rejected |
| Student reselects the recorded unavailable section | Rejected even in crafted POST |
| Other unchanged subject still requires Irregular | Complete-load treatment preserves it |
| Repeated revisions | Baseline and history retained; old requests cannot overwrite current state |
| Two students race for the last replacement seat | Existing real two-process MySQL test verifies both wait on the offering lock; at most one succeeds |
| Approved historical enrollment revision | Ordinary revision endpoints deny changes |
| No eligible replacement | Accurate guidance/refund follow-up; no invented reduced-load policy |
| Student requests refund | Explicit confirmation; refund stays pending until Cashier reconciliation |
| Partial, duplicate, unreferenced refund | Existing exact-full-refund protections |
| Cashier confirms actual full refund | Original collection retained; separate refund confirmation |
| Incorrect paid rejection | Guarded recovery with audit and no-external-refund confirmation; duplicate-active gap above remains |
| Another user's payment/revision/COR requested | Ownership and role checks, including secondary-role financial scoping |
| Notification or stale direct link | Current destination/state/ownership handling covered by route tests |

## Practical student acceptance scenarios

1. **Two full subjects after payment:** a Tourism student pays, then finds both a major and GE section full. Registrar returns those two only. Student selects one same-subject section and one explicitly approved equivalent. Check total credits, duplicates, clashes, remaining seats, recorded load treatment, unchanged degree program, unchanged receipt and no second collection. Registrar approves only after the complete load passes. This is the highest-value realistic role walkthrough.
2. **Last seat disappears while choosing:** two students open the same available option. One submits first. The other should receive a clear seat-unavailable message and retain the original enrollment/payment, then refresh alternatives. Existing replacement concurrency test covers the core transaction, not the full authenticated UI.
3. **Student wants correction instead of refund:** paid enrollment was wrongly rejected for a full class. Registrar confirms no refund with Cashier, records the correction and returns affected subjects. Also try with a newer pending enrollment: currently reproduces the duplicate-active defect and must be fixed before acceptance.
4. **No alternative fits:** every replacement is full or clashes with a retained subject. Student must not be forced into an invalid load. Show academic guidance and the existing confirmed refund-request choice; reduced load/deferral requires school policy.
5. **Returning student selects a different degree:** ordinary form should not function as an unapproved shift. Currently reproduces the program mismatch above.
6. **Term changes during a revision:** preserve the enrollment's own term; do not silently replace it with current settings. Verify which historical offerings are still academically open with the Registrar.
7. **Phone disconnects after GCash proof upload:** payment remains a single pending attempt, retry cannot collect twice, and notification/history links show the actual saved state. Real network interruption and physical GCash transfer remain manual checks.
8. **Cashier has sent the refund but not recorded it:** Registrar must not confirm “no refund issued” and recover the record. Software cannot observe an unrecorded external transfer. Manual coordination remains mandatory.

## Remaining limits and next priorities

Fix the three reproduced defects first. Then add two-process proofs for initial section assignment/approval, recovery versus refund confirmation, and recovery versus fresh enrollment creation. Exercise real authenticated Student/new-applicant/Registrar/Cashier/Admin sessions, keyboard behavior, validation-error recovery and phone layouts. The existing two-process replacement test does not prove those additional races.

Prerequisite graph, passing grades, maximum units, overload authority, reduced-load/deferral eligibility, scholarship restrictions and refund schedules are not configured here. These require approved school rules; this audit does not infer thresholds, entitlements, expiry times or automatic academic enforcement. GCash refunds remain external actions requiring recorded Cashier confirmation.
