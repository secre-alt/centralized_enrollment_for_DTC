# Document workflow final remediation ‚Äî 28 October 2026

## 1. Root cause analysis

**Zero-fee requests:** creation always selected a financial payment method and created a bundle payment, while release checked only `is_paid` plus `payment_status=verified`. Cashier correctly required positive received amounts, so a legitimate zero fee could never satisfy release. Payment validation also required GCash data before considering the authoritative fee.

The shared pricing service now quotes validated types/quantities from server settings in centavos. Zero totals need no receipt, reference, proof, cash collection or payment transaction. Model predicates distinguish ‚ÄúNo Payment Required‚Äù from a paid request; Registrar processing uses the shared fulfillment predicate. Free items keep `is_paid=false`, no verification timestamp and no amount received. An audit entry explains the zero-fee basis. Positive-fee Cashier checks were not weakened.

**Applicant access:** requester appointments and payment pages inherited the broad portal role group. Nested Student/Alumni role middleware now protects document pages, booking mutations, payment initiation, proofs and QR endpoints. Alumni-only routes remain restricted. Search and navigation omit document actions for applicants, and applicant transaction history does not expose document/appointment records. Staff notifications for historical applicant-owned requests use the dashboard rather than a newly restricted requester URL.

**Existing dashboard test:** `AcademicIdentityTest::student_dashboard_shows_enrolled_ay_pill_when_profile_has_ay` still fails in isolation. Its fixture stores `student_profiles.enrolled_ay=2025-2026` and expects that value plus the former ‚ÄúEnrolled AY‚Äù label. The current profile partial displays the configured/current enrollment year under ‚ÄúAcademic year‚Äù and does not fall back to the profile's stored enrolled year. This display/expectation mismatch predates this remediation. It is not caused by document pricing, applicant restrictions, shared test state or the wall clock. Neither the dashboard nor that test was changed to force a pass.

## 2. Files modified

The worktree already contained substantial document, dashboard and UI edits. This list identifies files touched by this remediation; unrelated edits were retained, and the earlier four fixes remain covered.

| File | Purpose |
| --- | --- |
| `app/Services/DocumentRequestPricing.php` (new) | Shared authoritative pricing, bounds/type/quantity validation and auditable no-payment explanation. |
| `app/Services/DocumentAppointmentBookingService.php` | Authorize requester roles; use authoritative quotes; omit zero-total payment records; distinguish free items within mixed bundles. |
| `app/Models/DocumentRequest.php` | `paymentNotRequired()` and `paymentSatisfied()` domain rules. |
| `app/Models/Appointment.php` | Recognize bookings whose linked documents all require no payment, without treating them as paid. |
| `app/Http/Controllers/Student/AppointmentController.php` | Quote before requiring payment data; free-request feedback; positive-only bundle resubmission; protect legacy payment paths and free/payable type changes. |
| `app/Http/Controllers/Alumni/DocumentRequestController.php` | Same pricing/validation, free standalone audit, short-window retry protection and free pickup eligibility. |
| `app/Http/Controllers/Cashier/DocumentPaymentController.php` | Keep free requests outside payable work; verify only payable bundle items; block zero-fee financial mutations; preserve existing paid records. |
| `app/Http/Controllers/Registrar/DocumentRequestController.php` | Permit authorized free fulfillment, audit legacy free cases and provide accurate ready/notification messages. |
| `app/Http/Controllers/Registrar/AppointmentController.php` | Free ready-request eligibility, legacy pricing/audit, shared bundle notifications and safe applicant destinations; retain Cashier-only bundle verification. |
| `app/Http/Controllers/PortalPaymentController.php` | Read-only No Payment Required state, with no payment form. |
| `app/Http/Controllers/PortalDashboardController.php` | Count free ready documents correctly; remove inactive pre-enrollment document action. |
| `app/Http/Controllers/SearchController.php` | Exclude unauthorized document-booking pages from applicant search results. |
| `app/Services/TransactionHistory.php` | Exclude free requests from financial transactions and restrict applicant history to enrollment. |
| `app/Services/AppointmentCancellationService.php` | Avoid unnecessary Cashier notices for entirely free requests; keep historical applicant notification destinations safe. |
| `routes/web.php` | Student/Alumni middleware on requester appointment and document payment routes. |
| `resources/views/partials/navbar.blade.php` | Applicant eligibility marker for search suggestions. |
| `public/js/dtc-app.js` | Hide the restricted appointment suggestion for applicants. |
| `public/js/student-booking.js` | Show free selection state, hide payment choices/proof and skip unnecessary payment validation. |
| `resources/views/student/appointments/create.blade.php` | No-payment notice and payment-choice hooks. |
| `resources/views/alumni/requests/create.blade.php` | Same free-state behavior in the alumni wizard. |
| `public/css/document-request-flow.css` | Theme-consistent no-payment notice and hidden-state behavior. |
| `resources/views/student/appointments/index.blade.php` | Free booking status and pickup eligibility/instructions. |
| `resources/views/alumni/requests/index.blade.php` | Free document status and pickup eligibility/instructions. |
| `resources/views/portal/alumni/dashboard.blade.php` | Accurate free-document badges and next actions. |
| `resources/views/portal/payments/details.blade.php` | No Payment Required explanation without payment actions. |
| `resources/views/registrar/documents/index.blade.php` | Free status and release-control eligibility. |
| `resources/views/registrar/appointments/index.blade.php` | No Payment Required indicator. |
| `resources/views/cashier/document-payments/show.blade.php` | Read-only free status without verification controls. |
| `tests/Feature/DocumentRequestRemediationTest.php` (new) | Deterministic free/mixed pricing, authorization, role transitions, tampering, retry and fulfillment regressions. |
| `tests/Feature/AppointmentFlowTest.php` | Give a financial-notification fixture an explicit positive fee; preserve existing bundle regressions. |
| `tests/Feature/PortalPaymentPagesTest.php` | Replace the old applicant-access expectation with Student/Alumni expectations; negative applicant coverage is in the new test class. |
| `tests/js/document-quantity-submission.test.cjs` | Free notices, payment-choice visibility and summary updates alongside quantity/copy-amount regressions. |
| `docs/document-request-final-remediation-2026-10-28.md` (new) | This report and manual-QA plan. |

## 3. Workflow changes

- An entirely free booking creates its normal document records and appointment, but **no `AppointmentPayment` record**. A free standalone alumni request creates no financial record either.
- Free requests retain the existing schema's `unpaid`/`walk_in` storage defaults as non-financial metadata; UI and fulfillment decisions use the explicit domain predicates. They are not marked paid or verified. `not_required` is a presentation value, not a new database status.
- Mixed bundles charge only their positive total. Their zero-fee members stay free through rejection, resubmission, verification and release. Releasing a free member does not block paying the remaining payable members.
- Stored request fees remain authoritative after creation. Later price-setting changes do not retroactively waive or reprice existing requests. Already verified payments stay verified and remain in history.
- Pending edits that would cross between a free and payable workflow require a new request; existing money/proof records are not silently rewritten.
- Existing slot locking and duplicate-active-booking checks remain. Identical submitted free standalone requests from the same user/purpose/quantity within one minute reuse the existing record under a user-row lock. This is short-window retry protection, not indefinite idempotency.
- Registrar still controls submitted ‚Üí processing ‚Üí ready ‚Üí released. Alumni pickup readiness/confirmation rules remain; release requires either verified payment or a legitimately stored zero fee.

## 4. Role authorization matrix

| Role | Create through requester portal | View | Process | Verify payment | Release |
| --- | --- | --- | --- | --- | --- |
| Student | Own eligible requests | Own bookings/payment/proofs | No | No | No |
| Alumni | Own eligible requests | Own requests/bookings/payment/proofs | No | No | No |
| New Applicant | No | No Student/Alumni document functionality; own enrollment access retained | No | No | No |
| Registrar | Existing staff/legacy operations only | Authorized staff records | Yes | No bundled-document payment verification | Yes, only when fulfillment requirements are met |
| Cashier | No requester creation by default | Authorized financial records | No | Positive payable requests/bundles only | No |
| Admin | Existing authorized staff operations | Authorized oversight | Yes | Positive payable requests/bundles only | Yes, same fulfillment requirements |

Request ownership checks remain in addition to role middleware. Role transitions are checked on later requests. Historical records are not deleted when a role loses requester access; authorized staff can still process them. A document request is not a new academic eligibility policy: additional institution-specific restrictions on particular document types were not invented.

## 5. Test results

Final command output is recorded in `storage/logs/document-remediation-release-full.txt`, `storage/logs/document-remediation-final-focused.txt`, `storage/logs/document-final-js.txt`, and `storage/logs/document-academic-year-isolated.txt`.

- Full PHP suite: **441 passed, 1 failed, 0 skipped; 442 tests, 3,755 assertions**. The only failure is the independent academic-year mismatch described above.
- Focused document/payment PHP suite: **116 passed, 0 failed, 0 skipped; 889 assertions**.
- Complete JavaScript suites: **68 passed, 0 failed, 0 skipped**.

The existing academic-year failure is reproducible alone: **0 passed, 1 failed, 0 skipped; 2 assertions**. It is retained and reported, not suppressed.

PHP syntax checks, route middleware inspection, JavaScript syntax checks and Blade compilation were run. `git diff --check` reports no whitespace errors; Git still warns that several LF files will be converted to CRLF on a later write.

## 6. Security review

- Browser fees, totals, discounts, waiver flags and paid flags do not control pricing or exemption. Server-configured prices and validated types/quantities do.
- Invalid/negative/sub-cent prices are rejected; duplicates and invalid quantities are rejected; quote totals fit the existing Cashier amount limit.
- Slot/request/payment locks preserve existing booking and verification consistency. No positive amount check was relaxed to accept invented zero payments.
- No exemption endpoint or client-writable exemption field was introduced. Paid records do not silently become exempt after a settings change.
- Applicant direct URLs, forged AJAX submissions, payment/proof routes, edits, cancellation and history are denied server-side. Student and alumni ownership checks still reject other users' records.
- Free queries use scalar predicates and existing eager-loaded document collections in listing views. Pricing settings are fetched together. No new per-row pricing lookup was added.
- Earlier quantity-state, sparse-index, optional-receipt and single-item bundle-bypass fixes remain intact and covered by regression tests.
- No authentication/session changes, CAPTCHA changes, new dependencies or new migrations were introduced in this remediation. Nothing was staged, committed, pushed or deployed.

## 7. Remaining risks and manual verification

Manual browser verification is **pending**. Prepared checks:

| Actor | Browser scenario |
| --- | --- |
| Student | Book free single/multiple documents; confirm No Payment Required, no proof demand, correct summary and no financial entry. Repeat paid cash/GCash and mixed bundles; verify quantity restoration, QR saving and copying exact amounts. |
| Alumni | Repeat free standalone and pickup workflows; verify readiness before pickup, no receipt demand for free requests, retry protection, existing paid history and GCash resubmission. |
| New Applicant | Attempt direct booking/payment/proof/history URLs and forged requests; confirm denial while application/enrollment/payment access remains. Check navigation/search does not offer document booking. |
| Registrar | Process free requests through each valid status; reject skipped/backward transitions; confirm free ready pickup; verify positive unpaid requests still cannot be released. |
| Cashier | Verify paid cash/GCash and mixed bundles with exact amounts; reject mismatches; confirm free requests have no collection/verification form and free bundle members stay distinct. |
| Admin | Repeat both staff workflows and verify audit reasons, ownership boundaries and historical records after a role change. |

This remains a single-server local environment. Production HTTPS, mail delivery, multi-worker/cache deployment behavior and concurrent load have not been claimed tested. The short-window standalone retry rule may need an explicit user-visible idempotency token if the institution later requires indefinite retry deduplication.

## 8. Release readiness

**READY FOR MANUAL QA.** The document-domain regressions are passing in focused verification. This is not approval for merge or production: the independent academic-year display/test mismatch and manual/browser/deployment gates remain open.

## Phase 2B update ‚Äî 2026-11-23

New Student and Alumni document requests now require a submission appointment. The shared booking transaction creates a `submission` appointment and an `appointment_document_items` row per document, preserving the original `payment_appointment_id` and leaving claim reservations empty. A submission visit is not a promise of collection. Submission completion is not required before processing.

The earlier standalone creation and retry behavior described above applies to historical behavior only. Existing standalone Alumni records retain their viewing, payment, processing, and release paths; no historical appointments or item rows are fabricated. New Alumni requests with no available submission time cannot be submitted until a slot is available. Both roles use the existing slot lock and capacity checks, and duplicate submissions for the same active slot are rejected.

Manual QA should verify required single-document slot selection in both portals, submission wording, existing standalone history, and Registrar confirmation of submitted Alumni documents. Claim booking, new release restrictions, rescheduling recovery, and closure recovery are outside Phase 2B.

## Phase 2C update ‚Äî 2026-11-23

Students and Alumni can book a separate claiming visit from `/portal/claiming-appointments`, selecting ready, payment-satisfied documents from any of their original bundles or historical standalone requests. The service rechecks `paymentSatisfied()` on locked documents, creates a pending claiming appointment, conditionally reserves each document through `pending_claim_appointment_id`, and adds exact visit items and an audit entry in one transaction. A failed selection rolls back the entire booking. Original appointment links, payment origins, payments, receipts, and proof history are preserved.

Locks follow slot, active appointments ordered by ID, selected documents ordered by ID, then new visit items ordered by document ID. Claim booking has three bounded transaction attempts for database deadlocks. Both submission and claim booking read current slot occupancy with locking reads, including when an earlier MySQL repeatable-read snapshot exists.

This is booking only. Claim appointments are shown separately from submission/payment cards. Legacy appointment editing, cancellation, confirmation, and appointment-payment submission cannot be used to mutate a claim or create another collection. Reservation-aware cancellation/rescheduling and Registrar claiming controls require later approval; reservations do not automatically expire. Physical release, completion, closure recovery, and notification processing are not added here. Existing legacy document release paths remain unchanged until Phase 2D.

## Phase 2D update - 2026-11-23

Implementation scope: add focused handover and rollback tests, implement one Registrar release service using the existing lifecycle fields, expose exact visit items with the shared confirmation dialog, guard legacy release, then run actual MySQL concurrency and regression suites. No migration, payment rewrite, or historical backfill is needed.

Registrar personnel now use `/registrar/claiming-appointments` to view claimants, visit times, exact documents, readiness, payment eligibility, release staff/time, and partial progress. Only checked documents with confirmed physical handover are released. The owner portal displays item release attribution and progress. Admin-only accounts cannot invoke the new handover endpoint; existing historical legacy permissions are preserved.

The transaction locks the slot, claiming appointment, all visit documents by ID, and visit items by document ID. It rechecks active claiming purpose, exact membership, claimant ownership, ready status, the reservation pointer, existing `paymentSatisfied()`, and absence of previous item release. Document status, item release attribution, reservation cleanup, and audit records commit together with three bounded transaction attempts. Unselected documents are unchanged. An appointment completes only when every item has release attribution and its own released document; cancelled or otherwise unresolved items do not count as completed.

Legacy direct release rejects reserved documents and documents participating in new submission/claiming visits. Historical standalone Alumni and legacy appointment records without those lifecycle items keep their original processing/release path. Submission completion is not required. Original submission/payment origins, amounts, receipts, verification decisions, and GCash proof history are unchanged.

Manual acceptance: as Registrar, find a Student and an Alumni visit, select one document, confirm physical handover in the checkbox and shared dialog, and verify partial progress plus untouched remaining reservations. Retry the released document through a stale page; verify rejection. Release the remaining eligible items and verify completion and owner-visible release staff/time. Verify Student, Alumni, Cashier, and Admin-only users cannot access the Registrar endpoint. Verify legacy standalone release still works and direct release of a reserved/new lifecycle document is blocked. Compare original receipts/payment history before and after release.

Deployment requires the existing Phase 1/2A migrations to be present; Phase 2D adds no migration or backfill. Do not roll back those tables after recording handovers. Reverting application code must retain release metadata and reservations; older code lacks the new handover protections, so disable release writes during a coordinated application rollback. No release data should be reversed or deleted. Cancellation, no-show handling, expiry, closure recovery, and notifications remain outside this phase and require separate approval.

Automated Phase 2D verification: focused regression passed **121 tests, 1,437 assertions**, including six actual MySQL multi-process claiming/release tests and the existing slot concurrency tests. The final full PHP regression passed **669 tests, 7,994 assertions** against `dtc_testing`. An earlier full run stretched across a host interruption and failed one unrelated enrollment-filter test on session inactivity; the uninterrupted rerun passed. Two older document tests now perform actual claiming handover instead of direct release for new submissions. A pre-existing notification retry test now refreshes its stale fixture before changing `available_at`, preventing Eloquent from skipping the retry timestamp update; notification application code is unchanged. Targeted PHP syntax, formatting, route inspection, and diff checks passed. Manual browser acceptance and the JavaScript suite were not run for this phase. Phase 2E has not started.

## Phase 2E-2 integrity foundation

This supersedes the earlier statement that Phase 2E has not started: only the integrity foundation is implemented. Claim cancellation, successor creation, submission recovery, missed-appointment handling, disruption processing, notification recovery, and reservation expiry remain unavailable. Existing supported legacy cancellation behavior is preserved; the claiming cancellation guard remains in place. No Citizen's Charter rules, payment eligibility, fees, receipt/report identity, or GCash verification behavior is changed.

### Schema and preservation

`2026_11_24_000001_add_appointment_lifecycle_integrity.php` adds:

- `appointments.lifecycle_version`: unsigned BIGINT, default zero. Existing records receive only this technical baseline, not a fabricated event or institutional outcome.
- `appointments.previous_appointment_id`: nullable unique self-reference with restricted deletion. No runtime operation creates successors in this phase.
- `appointment_document_items.resolution`: nullable VARCHAR(20); `resolved_at`: nullable timestamp; `resolved_by`: nullable user FK with restricted deletion. Historical items stay unclassified.
- `appointment_lifecycle_events`: appointment and actor FKs with restricted deletion; unique UUID operation key; SHA-256 request fingerprint; action; nullable previous version; resulting version; JSON exact document-item IDs, before/after snapshots, and recorded outcome; nullable reason; creation timestamp; appointment/history index.
- MySQL UPDATE/DELETE triggers and model guards make ledger rows append-only. Runtime database credentials need ordinary ledger INSERT/SELECT access; the migration account additionally needs TRIGGER privilege.

Closure idempotency/reconciliation fields are intentionally omitted: exact field definitions and processing semantics were not finalized for this foundation. No notification outbox is added. No historical backfill, historical payment rewrite, or operational database migration has been run. Migration rollback refuses to remove populated lifecycle metadata or events.

### Command and locking protocol

Existing HTTP mutation forms carry a fresh operation UUID and, when targeting an existing appointment, its displayed lifecycle version. Middleware rejects omitted metadata; old browser tabs must reload after deployment. Fingerprints retain scalar distinctions and hash uploaded file contents rather than temporary/storage paths. Malformed upload arrays still reach normal validation instead of crashing fingerprint generation. The operation key, actor, action, URL, and canonical payload govern replay; a conflicting completed key is rejected.

`AppointmentLifecycle` owns the transaction, three bounded deadlock attempts, slot/appointment/document/item locks, current-state checks, atomic compare-and-increment, and the lifecycle event. It discovers old/destination slots and related appointment IDs before locking, takes slots and appointments in ascending ID order, rejects changed candidate relationships, then locks documents by ID and items by document ID. Existing callbacks re-read rows already held by the command. Capacity checks use current locking reads. No disruptions are mutated by this phase.

Coverage includes submission/claim creation, existing pending edits and appointment-slot link changes, confirmation, supported non-claim cancellation, physical claiming handover/completion, legacy Alumni pickup, and legacy release linked to an appointment. Each successful command advances its appointment exactly once. A failed or stale transaction advances nothing and leaves no new audit/event. Historical standalone document release has no appointment to version; it retains its existing document audit trail without synthetic appointments or items. Trusted service callers can generate a command key at entry and must retain it for retries; HTTP callers must supply the form key.

The old Alumni pickup endpoint is restricted to legacy records: it cannot replace the submission link of a document that already has submission/claiming lifecycle items. Those ready documents use the existing Phase 2C claiming interface instead. Historical standalone and legacy-only pickup records remain supported.

Identical completed retries return the recorded business outcome and acknowledge success without repeating the mutation or its notifications. Booking replays are intercepted before storing another proof; a concurrent replay discards only its own newly uploaded, unreferenced file. Original evidence is retained. Ledger outcomes are decoded through an explicit model allowlist, not PHP object deserialization.

### Phase 2E-2 file manifest

New files:

```text
app/Exceptions/LifecycleOperationDeclined.php
app/Http/Middleware/AppointmentLifecycleCommand.php
app/Models/AppointmentLifecycleEvent.php
app/Services/AppointmentLifecycle.php
database/migrations/2026_11_24_000001_add_appointment_lifecycle_integrity.php
resources/views/partials/appointment-command.blade.php
tests/Feature/AppointmentLifecycleIntegrityTest.php
tests/Feature/AppointmentLifecycleMigrationTest.php
```

Updated files (including files already uncommitted from earlier phases):

```text
app/Http/Controllers/Alumni/DocumentRequestController.php
app/Http/Controllers/Registrar/AppointmentController.php
app/Http/Controllers/Registrar/DocumentRequestController.php
app/Http/Controllers/Student/AppointmentController.php
app/Http/Kernel.php
app/Models/Appointment.php
app/Models/AppointmentDocumentItem.php
app/Services/AppointmentCancellationService.php
app/Services/ClaimingAppointmentBookingService.php
app/Services/ClaimingAppointmentReleaseService.php
app/Services/DocumentAppointmentBookingService.php
resources/views/alumni/requests/create.blade.php
resources/views/alumni/requests/index.blade.php
resources/views/portal/claiming-appointments.blade.php
resources/views/registrar/appointments/claiming.blade.php
resources/views/registrar/appointments/index.blade.php
resources/views/registrar/documents/index.blade.php
resources/views/student/appointments/create.blade.php
resources/views/student/appointments/index.blade.php
routes/web.php
tests/Feature/AppointmentFlowTest.php
tests/Feature/ClaimingAppointmentConcurrencyTest.php
tests/Support/claiming-appointment-worker.php
tests/Support/document-appointment-booking-worker.php
tests/TestCase.php
docs/document-request-final-remediation-2026-10-28.md
```

The test HTTP fixture helper supplies fresh-form command metadata only for the existing appointment endpoints. Missing-metadata tests deliberately use raw requests; stale/conflicting tests supply explicit metadata. The JSON quantity-validation fixture now supplies an operation key so it continues to test quantity validation. Intentional changes to concurrency expectations reject one stale partial handover, then complete the remaining handover with a refreshed version.

### Acceptance and deployment

Final automated verification: full PHP regression passed **688 tests, 8,166 assertions**. The broad focused regression passed **221 tests, 2,124 assertions** before the final boundary additions; the final integrity suite passed **14 tests, 109 assertions**. A compatibility subset after protecting submission links passed **44 tests, 540 assertions**. These suites overlap and must not be added together. The final full run includes all final code and **12 actual MySQL multi-process document/slot concurrency tests**, including competing booking, confirmation, editing, release, and legacy pickup. Relevant JavaScript tests passed **8 tests, zero failures**. Database suites ran sequentially only against `dtc_testing`.

Coverage includes fresh schema, migration over historical released/paid records, FK restrictions, SQL/model append-only enforcement, stale/missing commands, duplicate/conflicting keys, exact item auditing, event-insert and version-write rollback, legacy compatibility, and request/file fingerprint boundaries. Earlier full runs were deliberately stopped to complete boundary corrections; only the completed final run is reported as full regression acceptance. Manual browser acceptance and production deployment remain unperformed.

Manual acceptance remains required: use Student and Alumni booking forms; retry an identical POST; submit an older edit/confirmation/release tab after another mutation; verify one version/event per successful command and no stale writes; perform partial then final handover; verify legacy pickup and standalone release; compare payment/receipt/proof history; and verify unauthorized roles and foreign owners remain denied. Claim cancellation and recovery must remain blocked.

Deploy application files together with the additive migration in a maintenance window after backup and review of MySQL DDL/trigger permissions. MySQL DDL is not fully transactional: inspect and repair a failed migration before enabling writes. Refresh normal route/view caches and require old forms to reload. Do not reset or migrate the operational database as a test. No production migration or deployment is performed here.

If application rollback is necessary, disable appointment lifecycle writes while keeping the new schema and all ledger/resolution data. Older code does not honor the new version/ledger protocol. Do not drop populated foundation tables, delete events, reverse physical releases, or recreate payments to roll back application code. Phase 2E-3 requires separate approval.

## Appointment-free transition ‚Äî Phase 2: submission only

Option A is approved for new requests: each payable document uses its own existing standalone document payment fields, verification, proof history, and receipt eligibility. No new payment entity or combined payment is introduced. Zero-fee documents remain unpaid and payment-satisfied through the unchanged `paymentSatisfied()` implementation. Historical appointment bundles and their immutable payment origins remain unchanged.

Both existing submission URLs now create document requests without slots, appointments, appointment payments, submission items, or reservation pointers. Student legacy document codes are normalized in its controller; Alumni retains its own input format. A shared `DocumentSubmissionService` reuses `DocumentRequestPricing`, creates the documents and individual proof records atomically, and records an append-only document workflow event. Separate nested payment fields support mixed methods. A top-level proof/reference is accepted only for one payable document; proofs/references cannot be reused among payable documents in the same submission. Proofs remain private. No global or fuzzy financial-reference deduplication is added.

Submission commands require a UUID operation key. The ledger's unique constraint prevents concurrent identical commands creating two committed sets. Completed retries match actor, action, and canonical request/file fingerprint; conflicting input is rejected. Newly uploaded files are cleaned up on failure or a losing concurrent command, never by deleting historical evidence. Transactions use three bounded attempts. Cached operation keys already recorded in the appointment ledger are rejected before creating new standalone requests. Nested file fingerprinting removes only uploaded fields, retaining their document types, copies, methods, and references in the fingerprint.

Student history now includes owner-scoped standalone records alongside historical appointments. Alumni history remains document-based. Existing Cashier verification, reports, receipts, payment history, and PDF/CSV queries are reused without financial logic changes. Forms offer separate payment/proof controls per selected document, and related navigation/dashboard/help text describes appointment-free submission. Old appointment-booked alerts are not emitted by the new submission path; ready-notification work is outside this phase.

Claiming appointments and physical release remain the existing workflow. `DocumentRequest::usesClaimingWorkflow()` recognizes new submission events as well as existing submission/claiming items. Registrar direct-release and legacy Alumni pickup guards therefore cannot mistake a new standalone request for a historical standalone record. Historical standalone Alumni records retain their legacy paths. No reservations are cleared and no release attribution is backfilled.

### Phase 2 file manifest

New:

- `app/Http/Middleware/DocumentSubmissionCommand.php`
- `app/Services/DocumentSubmissionService.php`
- `public/js/document-submission.js`
- `resources/views/portal/partials/document-submission.blade.php`
- `resources/views/portal/partials/standalone-documents.blade.php`
- `tests/Feature/AppointmentFreeSubmissionTest.php`
- `tests/SeedsHistoricalDocumentAppointments.php`
- `tests/js/document-submission.test.cjs`

Updated, preserving existing unrelated local modifications:

- `app/Http/Controllers/Alumni/DocumentRequestController.php`
- `app/Http/Controllers/HelpCenterController.php`
- `app/Http/Controllers/PortalDashboardController.php`
- `app/Http/Controllers/Registrar/DocumentRequestController.php`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Controllers/Student/AppointmentController.php`
- `app/Http/Kernel.php`
- `app/Models/DocumentRequest.php`
- `app/Providers/ViewServiceProvider.php`
- `app/Services/AppointmentLifecycle.php`
- `resources/views/alumni/requests/create.blade.php`
- `resources/views/alumni/requests/index.blade.php`
- `resources/views/portal/alumni/dashboard.blade.php`
- `resources/views/portal/student/dashboard.blade.php`
- `resources/views/registrar/documents/index.blade.php`
- `resources/views/student/appointments/create.blade.php`
- `resources/views/student/appointments/index.blade.php`
- `routes/web.php`
- `tests/Feature/AppointmentFlowTest.php`
- `tests/Feature/AppointmentLifecycleIntegrityTest.php`
- `tests/Feature/AppointmentSchedulingTest.php`
- `tests/Feature/ClaimingAppointmentConcurrencyTest.php`
- `tests/Feature/DocumentRequestPaymentTrackingTest.php`
- `tests/Feature/DocumentRequestRemediationTest.php`
- `tests/Feature/SubmissionAppointmentTest.php`
- `tests/Support/claiming-appointment-worker.php`
- `tests/js/document-quantity-submission.test.cjs`
- `docs/document-request-final-remediation-2026-10-28.md`

Legacy bundle tests now explicitly create historical fixtures through the preserved booking service; they do not expect the changed public submission endpoints to create new bundles. Tests of those public endpoints assert standalone behavior. The old inline Alumni quantity-script test is replaced by a test executing the shared submission JavaScript; the historical Student script check is retained.

### Deployment, rollback, and manual acceptance

This phase introduces no migration. Deploy only after the reviewed Phase 1 document-workflow schema is present, together with the earlier historical payment/lifecycle schema. No operational migration, backfill, dependency installation, or financial-data rewrite is performed here. Directly served Blade/JavaScript assets need no Vite build; refresh normal application route/view caches and reload old submission forms during the reviewed deployment.

Preserve the additive schema and all document/appointment ledgers on rollback. Disable new submission mutations before reverting application code: older Student history only lists appointments and would hide new standalone requests. Use a compatible application correction retaining standalone history and claiming guards. Never recreate payments, clear reservations, drop populated event tables, or reverse physical handovers as rollback steps.

Manual role acceptance remains required: submit multiple documents from each portal without a slot; select distinct Cash/GCash methods and files; check quantities and independent amounts; verify one document at a time as Cashier; inspect each receipt and both export formats; retry the same command; confirm foreign owners are denied; book a claiming appointment for ready/payment-satisfied new requests; and verify historical bundle and standalone Alumni records remain accessible. New ready-notification delivery and walk-in release are not implemented in this phase.

### Phase 2 verification results

All database-mutating runs used MySQL `dtc_testing`, forced by PHPUnit configuration and checked by the schema/concurrency fixtures. The completed final full PHP run passed **711 tests / 8,328 assertions** (10:53.735). The broad focused run passed **183 tests / 1,684 assertions**; after the final workflow-origin guard and navigation changes, the boundary/compatibility run passed **39 tests / 573 assertions**. These runs overlap. Intermediate red runs and deliberately stopped runs are not regression acceptance results.

The full run includes 13 actual MySQL multi-process document/slot cases: 11 claiming/lifecycle/submission cases and two slot cases. The new identical standalone-submission race also passed independently (**1 test / 13 assertions**), proving both workers wait on a held MySQL row lock and then commit exactly one document set/event, with no appointment or appointment payment. The final full run repeats that proof with the final source.

Relevant JavaScript passed **8 tests / 0 failures** using `node --test tests/js/document-submission.test.cjs tests/js/document-quantity-submission.test.cjs tests/js/modal-focus.test.cjs tests/js/session-feedback.test.cjs`. PHP syntax, targeted Pint, route inspection, JavaScript syntax, and diff checks passed. The full JavaScript suite and authenticated manual browser acceptance were not run; no unrelated JavaScript failures were changed.

Financial fixture reconciliation confirms two independently verified standalone documents total **PHP 130.00 / 13,000 centavos**, appear as exactly two owner-scoped history/report entries, and issue separate eligible receipts for both portal roles. Duplicate verification is rejected, unpaid siblings remain unpaid, and portal PDF/CSV exports remain accessible. Historical cancelled-bundle reconciliation still reports one **PHP 50.00 / 5,000 centavos** collection after rebooking, preserving the original receipt/proofs. New nested GCash uploads remain separate; rejection/resubmission retains earlier evidence. No financial report, receipt, payment-history query, refund logic, or `paymentSatisfied()` implementation was changed. Operational totals were not queried or reconciled.

## Phase 3 ‚Äî Durable document-ready notifications

### Implemented boundary

Only the Registrar/Admin `processing` to `ready` command uses the new `DocumentReadyService`. It locks the document, validates the current status, and persists the status/remarks, existing status audit, append-only `document.ready` workflow event, owner notification, and existing Cashier/Admin alerts in one transaction with three deadlock attempts. A failed notification or ledger write rolls back readiness. Processing and legacy release retain their existing paths; historical ready rows are not retrospectively classified or notified.

The ledger records the authenticated actor, UUID operation key, canonical request fingerprint (including route/document identity), exact document ID, before/after status and relationship state, timestamp, and original notification ID/outcome. Identical completed commands replay that outcome before checking the now-ready status. Conflicting actor/action/input reuse is rejected; the unique ledger operation key is the final concurrency safeguard. A second distinct command cannot transition an already-ready document. Notification event keys are `document-ready:{document ID}`, scoped by the existing unique recipient/event constraint. Read state is preserved; a notice explicitly deleted by its owner is not recreated by command retries.

`NotificationService::prepareUserUpdate` is the strict persistence step used inside the ready transaction. Existing best-effort callers continue through `sendUserUpdateSafely`, which reuses the same preparation and dispatch functions. No second notification subsystem or outbox was added. Owner destinations remain the Student document history within appointments or Alumni document history; existing ownership checks remain authoritative. Staff alerts remain in-app only and use event keys as well.

Ready messages identify the prepared document and distinguish payment-exempt, payment-verified, pending-verification, rejected, and outstanding payment states using the unchanged model payment methods. They do not include staff remarks, proof paths, references, receipts, or payment amounts. New workflows and documents with existing reservations retain claiming appointment instructions. Historical standalone records retain their existing collection arrangements. Only configured Registrar email, phone, office, and hours are included; no contact details or schedules are invented. Readiness is not physical handover.

### Email retry and operational limits

Email dispatch occurs after the outermost transaction commits, using the existing `WorkflowUpdate` mailable and escaped workflow-update template. The existing notification row is the durable enqueue intent: `pending` to `enqueueing` to `queued`, with `enqueue_failed` on a definite enqueue failure. Queue execution retains the existing `job_retrying`, `job_failed`, `sent`, and `suppressed` tracking and three job attempts. Identical completed command retries cannot enqueue another queued/sent message.

Missing/invalid recipient addresses retain the in-app notice and record `invalid_recipient`. After correcting the address, an operator can use `php artisan workflow-emails:retry <notification ID>`. The same command recovers `pending` intents left after a process stops between commit and dispatch, and definite `enqueue_failed` attempts. Already queued/sent messages are rejected by the retry CLI; failed jobs use the existing Laravel failed-job inspection and `queue:retry` workflow. This command does not confirm inbox delivery.

A process crash during `enqueueing` can leave an uncertain queue acknowledgement. Do not blindly reset that state or enqueue another message: inspect the queue/job and provider evidence first. Likewise, SMTP acceptance followed by a lost acknowledgement can duplicate delivery on a queue retry. Exactly-once SMTP delivery is not promised. Explicit user deletion of a notification is respected by existing email delivery behavior.

### Phase 3 file manifest

- `app/Services/DocumentReadyService.php` (new)
- `app/Services/NotificationService.php`
- `app/Http/Controllers/Registrar/DocumentRequestController.php`
- `app/Console/Commands/RetryWorkflowEmail.php`
- `tests/Feature/DocumentReadyNotificationTest.php` (new)
- `tests/Feature/DocumentRequestPaymentTrackingTest.php`
- `tests/Feature/ClaimingAppointmentConcurrencyTest.php`
- `tests/Support/claiming-appointment-worker.php`
- `tests/TestCase.php`
- `docs/document-request-final-remediation-2026-10-28.md`

### Deployment and manual acceptance

No Phase 3 migration, historical backfill, operational data command, dependency installation, or frontend build is required. Deploy only with the previously approved document workflow ledger and notification event/email tracking schema present. Deploy the service, controller, notification service, and retry command together; refresh application caches according to the existing deployment process and reload old Registrar forms to obtain their UUID command metadata. Keep existing queue workers and failed-job monitoring operational.

Preserve all workflow events and notifications on rollback. Pause new ready mutations before reverting to older application code, which lacks event-keyed ready deduplication. Do not drop populated ledgers or undo document readiness to retry email. Payment amounts, payment origins, receipts, proofs, reports, reservations, and physical release logic are untouched.

Manual acceptance remains necessary: as Registrar, mark a processing Student and Alumni request ready; inspect each owner's notification and correct history destination; confirm unread/read and delete behavior; confirm payment-aware claiming instructions and configured contact details; verify another owner cannot open the notice. On a configured mail/queue environment, observe queued delivery, simulate a definite enqueue failure, retry by notification ID, and inspect failed-job retry tracking. Confirm historical reservations remain unchanged and no document is physically released. Phase 4 appointment-free handover is not implemented.


### Phase 3 verification results

All database-mutating tests used the isolated MySQL database dtc_testing. The final full PHP run passed **722 tests / 8,483 assertions** (16:21.893), with zero failures. The focused ready/email/payment/concurrency run passed **59 tests / 636 assertions**; these overlap the full run. The initial seven-test ready run passed **7 tests / 106 assertions** before the additional integrity cases were added. Initial deliberately red tests are not regression results.

The final full run includes **15 actual MySQL multi-process document/slot cases**: 13 claiming/lifecycle/submission/ready cases and two slot cases. The two new ready cases prove workers wait on a held document lock: identical operation keys yield one ready event and one owner notice with two successful acknowledgements; distinct competing keys yield one transition and one rejected command. Existing reservation, physical release, payment verification, confirmation, legacy pickup, submission, and slot races remain passing.

The nine dedicated ready tests cover both portal roles, atomic owner notification and ledger persistence, outer transaction commit/rollback, notification and ledger write failures, enqueue failure and retry, conflicting/duplicate commands, deleted/read notices, missing/invalid addresses and correction, zero-fee/pending/verified payment wording, preserved historical appointment/payment links and claiming reservations, unrelated updates, historical ready rows, missing command metadata, and role/owner privacy. Existing workflow email tests cover delivery failure/retry and duplicate queued-job suppression.

Relevant JavaScript passed **17 tests / zero failures**: two document-submission/quantity tests and 15 notification, notification-toast, and notification-polling tests. No JavaScript source or frontend behavior was changed. PHP syntax checks and git diff --check passed. No manual authenticated browser acceptance or external SMTP delivery was performed. No operational financial records were queried for reconciliation or modified; existing payment, receipt, proof, report/export, legacy compatibility, and duplicate-collection regressions passed in the full isolated suite.


## Phase 4 - Appointment-free physical collection

### Dependencies and migration plan

The pre-implementation inspection confirmed that the approved schema already provides document-level released_at/released_by, pending_claim_appointment_id, appointment_document_items handover metadata, appointment lifecycle versions, both append-only event ledgers, and event-keyed notification/email tracking. **No Phase 4 migration, operational database command, historical backfill, dependency installation, or financial schema change is required.** The existing Laravel-installed Ramsey UUID implementation derives stable per-appointment operation keys; no package was added.

The existing Registrar document queue supplies name/email search and a claimant-scoped collection card. No new workflow page or unrelated redesign was introduced. Registrar-only POST /registrar/documents/walk-in-release accepts claimant_id, exact document_ids, a UUID operation_key, and an explicit handover_confirmed attestation. Server-side checks bind every selected document to the actual claimant; browser owner, eligibility, staff, timestamps, and status fields cannot authorize release.

### Release and historical reservation rules

| Record situation | Phase 4 behavior |
|---|---|
| Ready, payment-satisfied Student/Alumni document without a claiming reservation or conflicting item | Release the selected document without creating any appointment. |
| Active pending/confirmed historical claiming appointment with matching owner, pointer, and unresolved/unreleased item | Release only the selected document, attribute the document and matching historical item, and clear only its matching pointer within the transaction. |
| Partially released historical claiming appointment | Preserve all prior handovers. Unselected documents and reservations remain unchanged. Complete only when every item is physically released and its document is released for the correct owner with no pending pointer. |
| Cancelled/completed claiming appointment with an unresolved pointer, missing/wrong item, prior release metadata on a ready document, or unapproved resolution metadata | Reject for Registrar review. Do not reopen, cancel, clear, transfer, expire, or repair records automatically. |
| Historical standalone Alumni or legacy/submission appointment link | Release through the dedicated collection command, preserving original appointment links and payment identity. Do not fabricate appointments/items or change original submission/legacy appointment attendance status. |
| Already released historical document with nullable document attribution | Preserve it as recorded; no retrospective attribution is guessed. Completed pre-transition handover commands retain their old middleware replay path. |

The old document status dropdown cannot perform release. Historical Registrar claiming handover controls remain available and delegate to the same release service, enforcing the exact expected reservation and current lifecycle version. Only Registrar personnel can physically release, including through these historical controls. Admin's existing request review/read permissions remain; a staff role alone cannot perform handover without Registrar authorization.

### Transactions, locking, versioning, and idempotency

WalkInDocumentReleaseService discovers candidate appointment/slot/item/document IDs before locking. The transaction uses the established order: slots ascending ID, appointments ascending ID, document requests ascending ID, then items ascending document ID and item ID. It never acquires newly discovered earlier-order locks. Changed pointers, owner/slot relationships, unknown selected memberships, and newly discovered unlocked sibling documents cause rejection and refresh instead. It uses current locking reads and three bounded transaction attempts.

Every selected document is validated before any handover write: correct owner and portal role, current ready status, no prior release attribution, unchanged candidate pointer, unchanged authoritative paymentSatisfied(), and intact matching reservation membership where applicable. A failure rolls back the entire selection. Duplicate IDs are rejected after normalization as well as at HTTP validation. No payment SQL rule replaces the model method.

A successful transaction writes each selected document's status, released_at and releasing Registrar; the matching historical item when present; the exact reservation cleanup; existing-style audit logs; one append-only documents.walk_in_released event; and owner-specific in-app notifications. Historical claims receive a conditional lifecycle-version increment and append-only appointment event with the exact affected item IDs. Derived UUIDv5 child keys allow one document command to reconcile multiple appointments without colliding with the appointment ledger's unique operation key. Historical completion audit events are retained.

The document event records actor, stable operation key, request fingerprint bound to route/claimant/selected IDs/expected claim, exact document IDs, before/after document/item/appointment states, original outcome and notification IDs, and timestamp. The unique operation key plus locked forward-only status transition prevents duplicate handover. Identical completed commands replay before stale-state checks; different input or actor reuse is rejected. No version/event remains after rollback. Existing old-ledger completed retries return their recorded outcome without creating document attribution or notifications.

### Notifications, histories, and booking retirement

One owner notification per released document uses document-released:{ID}, distinct from all ready notifications. Existing NotificationService strict preparation is included in the release transaction. Existing tracked WorkflowUpdate email dispatch waits for commit; enqueue/delivery failures retain the committed physical release and reuse the Phase 3 retry mechanisms. Replayed commands cannot duplicate queued/sent emails, reset read state, or recreate explicitly deleted notices. Email is not proof of physical collection, and SMTP delivery is not exactly once.

Student and Alumni histories display current document status and new release timestamps/staff. Historical claiming appointments and their item progress remain owner-scoped and readable. Original payment bundles, standalone payment verification decisions, receipts, GCash proof history, and payment/report/export identity are unchanged.

After focused release verification, new portal claiming bookings and legacy Alumni pickup bookings were retired server-side with a clear validation message. Their historical URLs remain accessible; new forms and slot selectors were removed from collection interfaces. The original booking service remains for explicit historical regression fixtures and is no longer called by a public booking action. Generic appointment administration, schedule/closure management, historical edits, and unrelated appointment functionality were preserved.

New ready notifications and portal collection guidance now explain appointment-free collection during published office hours with a valid ID and satisfied payment requirements. Existing configured Registrar contacts/hours are reused. Previously created or already queued ready messages are historical snapshots and are not rewritten; their owner-scoped destinations show the current collection instructions.

### Phase 4 changed files (relative to Phases 1-3)

Application:
- app/Services/WalkInDocumentReleaseService.php (new)
- app/Services/ClaimingAppointmentReleaseService.php
- app/Services/DocumentReadyService.php
- app/Http/Controllers/Registrar/DocumentRequestController.php
- app/Http/Controllers/ClaimingAppointmentController.php
- app/Http/Controllers/Alumni/DocumentRequestController.php
- app/Http/Controllers/Student/AppointmentController.php
- app/Http/Controllers/HelpCenterController.php
- routes/web.php

Views:
- resources/views/registrar/documents/_walk-in-collection.blade.php (new)
- resources/views/registrar/documents/index.blade.php
- resources/views/portal/claiming-appointments.blade.php
- resources/views/portal/partials/standalone-documents.blade.php
- resources/views/alumni/requests/index.blade.php
- resources/views/student/appointments/index.blade.php

Tests and documentation:
- tests/Feature/WalkInDocumentReleaseTest.php (new)
- tests/SeedsHistoricalClaimingAppointments.php (new)
- tests/Feature/ClaimingAppointmentTest.php
- tests/Feature/ClaimingReleaseTest.php
- tests/Feature/ClaimingAppointmentConcurrencyTest.php
- tests/Support/claiming-appointment-worker.php
- tests/browser/walk-in-release.cjs (new)
- tests/Feature/AppointmentLifecycleIntegrityTest.php
- tests/Feature/AppointmentFlowTest.php
- tests/Feature/DocumentIntegrityTest.php
- tests/Feature/DocumentReadyNotificationTest.php
- tests/Feature/DocumentRequestPaymentTrackingTest.php
- tests/Feature/DocumentRequestRemediationTest.php
- tests/Feature/PrecommitFlowRegressionTest.php
- tests/Feature/SubmissionAppointmentTest.php
- docs/document-request-final-remediation-2026-10-28.md

Historical booking regression fixtures now explicitly exercise the preserved internal service rather than expecting retired public endpoints to create appointments. Public retirement, ownership, and no-new-appointment behavior are tested independently. Tests formerly asserting new pickup booking now assert retirement/preservation or actual appointment-free release; financial reconciliation coverage remains.

### Deployment, rollback, and manual acceptance

Deploy only with all reviewed Phase 1-3 schema dependencies present. Deploy controllers, release services, routes, messages, and views together; refresh normal route/view caches and restart queue workers through the established deployment procedure. No Vite rebuild or operational migration is introduced. Reload old Registrar forms. Stale portal booking forms receive a retirement error; existing appointment and payment records remain.

Rollback is application-only: pause release mutations first, retain attribution, all ledgers/items/pointers and financial history, and use a compatible application correction. Do not reverse physical handovers, drop populated schema, recreate payments, clear reservations, or blindly restore old unconfirmed/direct release controls. Any exception repair for inconsistent cancelled/completed reservations requires separate approval; this phase deliberately blocks those cases.

Manual acceptance checklist:
1. Search Student and Alumni identities in the Registrar document queue; open Collect documents and verify exact owner, document names, copies and payment eligibility.
2. Select a subset of ready documents, verify physical identity and handover, explicitly confirm, and inspect per-document status/staff/time. Unselected documents remain unchanged.
3. Try unpaid/unready/foreign/already released selections and a missing confirmation; verify no partial effects.
4. Reconcile an intact historical claim in two partial handovers; inspect matching item attribution, retained siblings, version history and completion only after the last item.
5. Confirm cancelled/completed or inconsistent reservations are blocked and preserved; process a valid historical standalone Alumni request without inventing an appointment.
6. Retry the identical command and a conflicting key; check owner notifications, prior ready notices, payment history, receipts and exports.
7. Confirm Students/Alumni/Cashier/Admin without Registrar cannot release, foreign owners cannot open histories/notices, new claiming booking forms are absent and stale booking posts create nothing.
8. Exercise the configured queue/mail environment and existing retry commands. Inspect failed jobs and uncertain enqueueing states before operator retry; do not assume exactly-once SMTP.

No authenticated manual browser or external SMTP acceptance is claimed by the automated PHP/JavaScript results. No operational financial reconciliation or data modification was performed. Phase 4 stops here; reservation exception recovery, automatic expiry/cancellation/refunds, office closure recovery, Charter fees/deadlines, and unrelated redesign remain outside scope.


### Phase 4 browser fixture

The owner-history feature test exports its rendered collection form to ignored storage/framework/testing/walk-in-release-form.html using only fake dtc_testing records. Run that PHP test before node tests/browser/walk-in-release.cjs. The Edge fixture isolates the actual rendered form, does not submit it or access the database/network, and checks disabled unpaid selections, exact FormData document IDs, UUID/claimant identity fields, and required physical confirmation. The fixture passed outside the sandbox after sandboxed Edge launch timeouts. This is browser verification of native form controls, not authenticated manual role acceptance.


### Phase 4 final verification results

The final full PHP regression run passed **735 tests / 8,696 assertions**, zero failures (11:04.199, 140 MB). All database-mutating tests used MySQL dtc_testing, forced by PHPUnit configuration; new feature/concurrency fixtures also assert the selected database. Database suites ran sequentially, with only their intentional multi-process workers sharing the testing database.

Final focused acceptance passed **13 tests / 214 assertions** for WalkInDocumentReleaseTest and DocumentIntegrityTest. The rendered-form fixture/export test then passed independently (**1 test / 18 assertions**). The updated SubmissionAppointmentTest passed **3 tests / 45 assertions** before the final full rerun. These overlap the full suite. An earlier full run had one obsolete expectation that the generic status dropdown could release a legacy Alumni document; its fixture was updated to assert dropdown rejection and confirmed walk-in release with the original verified receipt. That earlier failed run is not acceptance evidence; the final full rerun is.

The full run includes **17 actual MySQL multi-process document/slot cases**: 15 claiming/lifecycle/ready/submission/release cases and two appointment-slot cases. New walk-in coverage proves competing Registrars cannot release the same standalone Alumni document twice, walk-in and historical handover cannot both release a reserved document, and identical concurrent walk-in commands replay one event/owner notice. Existing stale-version, partial-handover, committed payment re-read, slot administration/capacity, confirmation and submission concurrency protections remain passing. Workers must actually wait on held MySQL row locks before their parent transaction commits.

Financial reconciliation fixtures verify **13,000 centavos across exactly three collections** before and after partial collection involving two historical bundles plus an independent standalone payment. Stored payment and GCash proof attributes and financial report rows remain identical. Bundle receipts remain authoritative, child duplicate receipts remain unavailable, the standalone receipt remains accessible, and owner PDF/CSV history exports work. Uncollected documents remain ready. No operational totals were queried or financially reconciled.

Relevant JavaScript passed **17 tests / zero failures** for submission/quantity and notification/polling/toast regressions. The actual Edge fixture passed outside the sandbox after sandbox launch timeouts, checking the rendered form's unpaid controls, exact selected IDs, command identity and required physical confirmation without submitting forms or accessing the database. Authenticated manual role acceptance and external SMTP delivery remain unperformed. Syntax checks and git diff --check passed. No schema, dependency, operational database, financial-policy, automatic recovery, or backfill action was introduced. Phase 4 ends here.


### Request-form visual restoration ? 2026-11-24

Restored the existing document-request-flow and booking-create visual styles for both Student and Alumni create forms: document selection cards, bordered sections, history links, dark request-information sidebar and gold submit button. The shared appointment-free form keeps its existing field names, per-document payment/proof inputs, validation hooks and command identity. No appointment fields, backend changes or migrations were introduced.

Verification: AppointmentFreeSubmissionTest passed 12 tests / 150 assertions on dtc_testing, including both rendered portal forms; document-submission and document-quantity JavaScript tests passed 2 / 2. Authenticated visual browser review was not performed.

### Student and Alumni document-request UI ‚Äî 2026-11-24

Both portal roles now use the existing `/portal/documents` create/history module. Document Requests has its own sidebar group, while Appointments remains separately accessible. Student dashboard/search shortcuts and document-payment back navigation point to document requests. Historical appointment URLs, relationships, lifecycle commands and payment submission endpoints remain available. The role middleware on the document route group now permits Student and Alumni; every existing owner check remains in place.

The shared request form uses configured document fees, selectable keyboard-accessible cards, expandable copy/payment controls, nearby field errors and separate Cash/GCash guidance. Its live summary lists document names, copies, unit prices, subtotals, methods and an estimated total using integer centavos. Invalid quantities suppress the total rather than displaying a misleading estimate. Selected fields alone submit; each GCash document retains its own reference/proof. The submit button displays a pending state, and an empty selection produces an accessible error. Existing application toasts continue to report server outcomes.

Collection guidance describes submission, payment, processing, ready email/in-app notification and physical handover with valid ID. Office details appear only when configured. No submission/claiming appointment or representative-collection policy is introduced. The shared history distinguishes processing, payment and collection, includes recorded Manila timestamps, and identifies partial historical collections without changing uncollected documents. Staff remarks are no longer rendered in this document history. Receipt links use the existing owner-scoped TransactionHistory query and eligibility check, which in turn reuses DocumentPaymentIdentity; bundle children do not receive duplicate receipts.

The only submission-service adjustment maps validation messages back to the submitted array indexes, so sparse selections show missing/reused GCash proof errors beside the correct document. Pricing, paymentSatisfied(), verification, financial identity, ready notifications and physical-release attribution are unchanged.

Affected files for this UI increment:
- app/Providers/ViewServiceProvider.php; routes/web.php
- app/Http/Controllers/Alumni/DocumentRequestController.php (read-only receipt eligibility), PortalPaymentController.php (back link), SearchController.php (shortcuts)
- app/Services/DocumentSubmissionService.php (error keys only)
- resources/views/portal/partials/document-submission.blade.php; resources/views/alumni/requests/index.blade.php
- resources/views/portal/student/dashboard.blade.php; resources/views/student/appointments/index.blade.php; resources/views/portal/claiming-appointments.blade.php
- public/js/document-submission.js; public/css/booking-create.css
- tests/Feature/DocumentRequestUiTest.php; DocumentRequestPaymentTrackingTest.php; PaymentHistorySidebarTest.php; SidebarNavigationTest.php
- tests/js/document-submission.test.cjs; tests/browser/document-request-ui.cjs

No migrations, dependencies, Vite rebuild, operational database actions or historical backfills are required by this increment. Earlier uncommitted schema/workflow changes remain separate deployment prerequisites; this UI verification does not authorize their operational migration.

The browser fixture uses the form rendered from dtc_testing, local assets and installed Playwright with Edge. It blocks network requests, checks real keyboard selection, live totals, FormData isolation, native copy limits and content bounds at exact 1280px/390px viewports, and captures desktop light/mobile light/mobile dark screenshots in ignored storage/framework/testing. Run DocumentRequestUiTest before `node tests/browser/document-request-ui.cjs`. The original Edge command-line window fixture produced cropped mobile screenshots, so it was replaced with exact viewport emulation. Edge needs execution outside the sandbox in this environment. This fixture is not authenticated role acceptance, full sidebar/history browser testing, SMTP delivery verification or external payment verification.

Verification for this increment:
- Baseline AppointmentFreeSubmissionTest: 12 tests / 150 assertions passed before edits.
- First full run: 740 tests / 8,771 assertions, four failures from superseded expectations (Student document-route denial, public staff remarks, old sidebar placement and Appointments submenu). Those assertions now cover the requested document navigation and private remarks. These were changes in expected behavior, not pre-existing baseline failures.
- Focused navigation/payment/history/submission regressions after updating expectations: 51 tests / 740 assertions passed. After the sparse-error regression was added, DocumentRequestUiTest plus AppointmentFreeSubmissionTest passed 17 tests / 236 assertions. These runs overlap the full suite.
- Final sequential full suite, `php vendor/phpunit/phpunit/phpunit --no-coverage`: **741 tests / 8,807 assertions passed**, zero failures (09:33.444, 136 MB), using PHPUnit's forced MySQL dtc_testing configuration. Existing actual multi-process lock tests ran within this suite; no second database suite ran alongside it.
- `node --test tests/js/document-submission.test.cjs tests/js/document-quantity-submission.test.cjs`: 2 tests passed, zero failures.
- Final `node tests/browser/document-request-ui.cjs`: desktop 1280px light, mobile 390px light and mobile 390px dark passed in Edge outside the sandbox. Screenshots were inspected. The fixture covers the shared form, not authenticated navigation/history or GCash provider operations; independent GCash fields are covered by JavaScript and PHP tests.
- PHP syntax checks on changed controllers/provider/service/routes and the new feature test, JavaScript syntax checks, and `git diff --check` passed. Existing unrelated working-tree modifications were retained; no stage, commit, push or operational migration was performed.


### Three-step request form

Both portals now present Document ? Purpose ? Payment with Next/Back navigation. Selected document payment controls move into the final step without changing field names or sharing payments/proofs. Purpose remains optional. Navigation validates the current step, and invalid hidden fields reopen their step. Backend behavior and appointment-free submission remain unchanged. Verification: 12 focused PHP tests / 150 assertions; 2 JavaScript tests, including navigation and per-document payment controls. No authenticated visual browser review or full-suite rerun.


### Compact document-picker polish

Fixed the three-step indicator inheriting four-step connector positioning. The submission-only step list now uses three aligned columns without crossing lines. Document selection uses a quiet surface and selected border, shorter guidance, and compact labelled minus/input/plus copy controls with 1?10 bounds; direct numeric entry remains available. The request sidebar uses a subdued dark surface. Both portals retain the same fields, validation, independent payment controls and Document ? Purpose ? Payment navigation. Legacy appointment styling is unaffected by the scoped overrides.

Verification: focused AppointmentFreeSubmissionTest passed 12 tests / 150 assertions; rendered-form fixture rerun passed 1 test / 17 assertions; two JavaScript tests passed including copy limits and navigation. New static rendered-fixture browser check (`node tests/browser/document-picker.cjs`, after the form PHP test) passed eight portal/viewport/theme combinations with Edge, checking compact controls, navigation, retained quantities and no horizontal overflow. Desktop and mobile dark screenshots were inspected. The fixture blocks network access and omits the remote font import; it does not represent authenticated navigation or the full AdminLTE shell. Full PHP suite was not rerun for this presentation-only change. No migrations or operational data changes.


### Mobile request action bar and scrolling footer

On Student and Alumni request pages, mobile keeps a compact estimated total and Back/Next/Submit actions in a bottom bar. The detailed summary and What happens next use native disclosure controls, initially collapsed on mobile and expanded on desktop. The footer is explicitly static on these request pages, with reserved space below the wrapper on mobile so it and the final fields can scroll clear of the action bar. Other pages keep their existing footer behavior. Payment fields, backend validation, independent payments and the three-step order are unchanged.

Verification: 12 PHP tests / 150 assertions on dtc_testing; two JavaScript tests including estimate/quantity changes; eight rendered-fixture Edge cases across both portals, desktop/mobile and light/dark. Browser checks include disclosure defaults, fixed mobile actions, static footer, footer clearance, navigation and preserved quantities. The mobile screenshot was inspected. Full PHP suite and authenticated role/browser navigation were not rerun. No schema or operational data changes.


### Request-page footer removal

The footer is now hidden on Student and Alumni document submission pages only, superseding the scrolling-footer presentation above. Mobile request actions and their reserved space remain. Updated the browser fixture to assert the footer is hidden in both themes and both viewport sizes; all eight cases passed. No backend or database changes.

### Citizen's Charter guidance ó 24 November 2026

The shared Student/Alumni submission form now lists the visible charter requirements: clearance for first diploma/certification requests; clearance for first TOR requests or transfer purposes plus two 2◊2 photos with a white background or in academic gown; and originals/photocopies for certified copies. Guidance asks requesters to confirm clearance instructions and present required originals. This is informational: online submission does not certify requirements are complete, and no new upload or eligibility rule was introduced.

Document history now explains the next action for unpaid, pending-verification, rejected, submitted, processing and ready requests. Ready notifications additionally ask for a claim stub if issued and a receipt if payment was required; existing configured office/contact details and payment-condition notices remain. Walk-in collection and historical appointment records are preserved.

Registrar decisions still required before changing policy:

- Confirm the current approved fee schedule, including TOR per-page/local/international pricing and diploma issuance rules. Existing configured and historical fees have not been changed or certified as charter-compliant.
- Resolve the general 3ñ5 working-day instruction versus document-specific 7 working-day lead times, the start of the clock and non-working days.
- Confirm whether verified payment must precede preparation, and how requirement checking is recorded. This change adds no payment-before-processing gate or automated deadline.
- Confirm document-specific clearance/photo instructions and any authorized-representative collection rules. Existing valid-ID collection guidance remains; no representative authorization workflow is invented.

Release inspection found existing Registrar-only authority, claimant ownership, ready/payment eligibility, prior-handover checks, explicit physical-handover confirmation, transactions/row locks and command replay protection. No release logic or financial records were changed.

Verification for the charter guidance:

- PHPUnit was confirmed to force MySQL `dtc_testing` before running suites; database suites ran sequentially.
- Focused guidance/ready/release run: 21 tests, 338 assertions, all passed. The subsequently expanded guidance case also passed in the full run and final UI rerun.
- Full suite: 742 tests, 8,810 assertions; 741 passed and one failed. Log: `storage/logs/charter-guidance-full-tests.txt`. The sole failure was a pre-existing UI heading expectation (`How collection works`) while the shared form already rendered `What happens next`. Updated that assertion to the existing heading; the complete DocumentRequestUiTest class then passed 6 tests / 111 assertions. The full suite was not repeated after this test-expectation-only correction.
- JavaScript submission check: 1 test passed. Edge rendered-fixture check: all 8 portal/viewport/theme combinations passed. Its compact-control assertion now measures the copy row rather than the expanded panel containing requirements, and it verifies visible TOR requirements.
- PHP syntax checks, browser-script syntax and `git diff --check` passed.
- No authenticated manual role/browser acceptance, new migration, asset build, operational database migration, staging, commit or deployment was performed. The browser fixture does not replace full portal acceptance.

### Payment-step presentation ó 25 November 2026

The shared Student/Alumni payment step now uses Cash at Cashier / GCash radio choices for each document, with a live copy count and document amount. GCash QR, configured recipient details, reference and proof fields appear only for a selected document using GCash. Switching to Cash disables GCash proof/reference submission while retaining entered values if the requester switches back. Repeated document labels and instructions were shortened, and the action reads Submit request. Per-document field names, server pricing, independent proofs, verification and financial records are unchanged.

Verification: 18 focused PHP tests / 261 assertions passed on dtc_testing; the JavaScript submission test passed. The rendered-form Edge fixture checks both portals, desktop/mobile and light/dark, conditional QR visibility, Cash exclusion of GCash fields, retained reference input and independent payment choices. Mobile Cash and GCash screenshots were inspected; fixture network requests are blocked, so the QR image itself is not visually verified. No new migrations, dependencies or asset build. The full suite and authenticated manual portal checks were not repeated for this presentation change.

### GCash payment guidance and quick copy ó 25 November 2026

The standalone document form now groups GCash into Pay the document amount, Scan or save the QR, and Enter payment details. It reuses the existing document-gcash-account component and delegated data-copy-value controls for the configured number and exact per-document amount; no second clipboard handler was added. The QR is centered with a Download QR action and same-phone instructions. The upload control shows the filename as text and supports Replace file / Remove without changing the submitted field names, file types, 5 MB server limit or Cashier verification rules. Scoped focus/scroll spacing helps mobile fields clear the fixed action bar.

Verification: 18 PHP tests / 261 assertions passed on dtc_testing; the JavaScript submission check and syntax/diff checks passed. Eight Edge rendered-fixture portal/viewport/theme cases passed, including simulated copy success/failure, exact number/amount, filename text safety, file replacement/removal, Cash exclusion of GCash fields, and independent payment choices. The mobile GCash screenshot was inspected. Fixtures block network images and simulate clipboard fallback; actual QR rendering, real device clipboard behavior and authenticated portal acceptance were not manually verified. No schema, payment-service or pricing changes; no asset build required. The full suite was not repeated for this presentation change.

### New document submission staff notifications ó 25 November 2026

Root cause: the appointment-free DocumentSubmissionService created requests, proofs and workflow/audit events but did not create staff notifications. Both Student submission routes and Alumni document submission use that service.

The service now persists one New Document Request in-app alert per document for Registrar/Admin recipients inside the submission transaction. It reuses NotificationService::sendForEvent with document-specific keys, includes requester/document/copy information and links to the Registrar queue filtered by claimant. Dual staff roles produce a single recipient; successful command replays do not duplicate alerts, and rollback removes both requests and alerts. No staff email or retrospective notification backfill is introduced; payment data and policy remain unchanged.

Verification:

- Confirmed phpunit.xml forces MySQL dtc_testing; database runs were sequential.
- Focused submission suite: 13 tests / 230 assertions passed, including Student/Alumni Cash/GCash/free submissions, multiple documents, dual-role recipients, replay and rollback.
- Full suite: 743 tests / 8,905 assertions, 741 passed and two failed. Log: storage/logs/document-submission-notification-full-tests.txt. One pre-existing UI assertion expected the removed words separate payment; it now checks the current Pay separately for each document instruction. The other test expected zero Admin alerts after standalone submission; it now expects the new document alerts for Admin/Registrar while continuing to reject obsolete appointment alerts.
- After those expectation-only corrections, both affected tests plus the entire submission test class passed: 15 tests / 250 assertions. The full suite was not repeated after these test-only updates.
- PHP syntax checks and git diff --check passed. No migration, operational data change, notification backfill, deployment or manual authenticated bell review was performed.

### Document service separation and Registrar assessment ó 25 November 2026

Approved scope: keep existing configured document fees for now. No approved per-page schedule or effective date was supplied, so assessment confirms the fee saved with the request rather than introducing new TOR rates, repricing historical requests, or changing verified collections.

New document workflow:

- Canonical Student/Alumni submission, history, payment details, proof and QR links use the document routes. The legacy Student appointment entry points delegate document work to the existing document controller, preserving legacy submitted type mappings and URLs. Standalone documents no longer appear as appointment-history rows. Historical appointment bundles, receipts, proofs and original payment relationships remain intact.
- Shared request fingerprinting lives in App\Support\WorkflowCommandFingerprint. AppointmentLifecycle delegates to it without changing the fingerprint format; document submission, ready and walk-in release no longer call the appointment service for fingerprinting.
- New submissions set assessment_required and begin with assessment_status=pending. Registrar/Admin use Assess request on the existing document queue to record incomplete/complete requirements, public requirement notes, the confirmed saved fee, actor, time and version. The same document-specific charter guidance appears in submission and assessment. The requester receives an in-app assessment update and sees the result in document history.
- Incomplete assessment requires a missing-requirements explanation. Complete assessment requires explicit confirmation of the saved fee. The confirmed amount is copied from the locked record, not accepted from the browser or recalculated from later settings. No fee-adjustment, waiver, refund or arbitrary price-entry endpoint was added. A pricing discrepancy remains for Cashier/Admin review.
- New requests cannot enter preparation, become ready or be physically released without complete requirements and matching confirmed fee. Payment verification remains a separate existing lifecycle; this change does not introduce a new payment-before-assessment gate or invalidate proofs/payments already submitted. Physical release still requires payment eligibility and explicit handover.
- Assessment updates use row locking, version checks, event-keyed notifications, immutable workflow events and audit history. Replayed commands do not duplicate updates; conflicting/stale commands are rejected. Assessment and notifications roll back together on failure.
- Existing records default assessment_required=false with no inferred clearance, assessor, fee reassessment or automatic hold. Ordinary legacy fulfillment continues. No historical payment or appointment data is removed.
- New appointment booking remains retired. There is no approved scheduling need to justify adding it. Historical appointment recovery and payment reconciliation remain available.

Database change: additive migration 2026_11_25_000002_add_document_assessment.php adds assessment metadata only. Run php artisan migrate during the application's normal update process before using the new submission/assessment code. No operational migration was run during development. Assets are directly served; no build is required.

Policy limits: requirement evaluation is a manual Registrar check, not automated clearance verification. Fees remain the configured saved fees; per-page/local/international pricing and charter lead-time conflicts remain unimplemented pending approved rules. Staff should not mark an assessment complete when the saved fee is disputed. No partial-refund or adjustment policy is inferred.

Separation/assessment verification completed:

- Full suite on MySQL dtc_testing: 748 tests / 9,017 assertions, 745 passed and three failed (storage/logs/document-separation-assessment-full-tests.txt). Two pre-existing workflow tests skipped the newly required assessment; one expected standalone documents in appointment history. Updated those tests to assess before preparation and check document history. The updated SubmissionAppointmentTest additionally asserts preparation is blocked before assessment.
- Final affected suites (DocumentAssessmentTest, DocumentRequestRemediationTest and SubmissionAppointmentTest): 21 tests / 296 assertions, all passed. The full suite was not repeated after these test-only corrections.
- Earlier compatibility run: 103 tests / 990 assertions, one stale document-notification destination expectation; updated to the canonical document route before the full run, which passed that test.
- JavaScript submission test passed; Edge document-picker fixture passed all eight portal/viewport/theme cases. These fixtures cover document forms, not authenticated manual assessment acceptance.
- Targeted PHP syntax, formatting of new PHP files, and git diff --check passed. No operational migration, deployment, staging, commit, new appointment scheduling, or manual role acceptance was performed.

Manual acceptance after applying the migration: submit as Student and Alumni; confirm pending assessment and Admin/Registrar alert; record missing requirements and inspect the requester notice; complete requirements and fee confirmation; confirm preparation becomes available; verify Cash/GCash separately; mark ready and physically release with ID/handover confirmation. Also check a pre-migration paid request and a historical bundle receipt remain unchanged. Assessment notes are requester-visible and escaped.

### Role-page presentation polish ó 25 November 2026

Approved presentation pass: Student/Alumni document history now shows Submitted ? Assessment ? Preparing ? Ready ? Released, with a five-column responsive tracker. Payment presentation consistently distinguishes Payment due, Under verification and Verified without changing stored statuses. Appointment history links to document history instead of offering a competing document submission action; historical claiming visits are identified as past visits. Registrar/Admin document queue actions read Start preparation / Mark ready, and appointment screens explain that visit confirmation does not approve documents or verify payments. Cashier/Admin payment queues display document references, Cash at Cashier versus GCash, and a short explanation of separate document/payment lifecycles and historical bundle receipts. Payment detail status labels match the queue. Existing permission checks, assessment requirements, transaction records and operations remain unchanged.

Verification: 47 focused tests / 567 assertions passed (DocumentRequestUiTest, DocumentAssessmentTest, DocumentRequestPaymentTrackingTest, AppointmentSchedulingTest), including shared Registrar/Admin and Cashier/Admin page checks. One prior payment-label expectation was updated to Under verification. After adding rendered history fixtures, the guidance/history test passed again with 35 assertions. Eight Edge tracker fixtures passed across Student/Alumni, desktop/mobile and light/dark; the mobile dark tracker screenshot was inspected. Syntax and git diff --check passed. No full-suite rerun or authenticated manual visual acceptance of each role was performed for this presentation-only pass. The browser fixture isolates the progress tracker; it does not prove complete-page visual acceptance. No migration, build or operational data changes are required by this polish.


## Admin staff access - 25 November 2026

Admin accounts are authorized to perform all Registrar and Cashier staff workflows. Registrar document handover and historical claiming routes, service checks and controls include Admin. Cashier refund confirmation and recipient-scoped notification polling also include Admin. Portal ownership restrictions remain unchanged.

Release still requires exact document selection, satisfied assessment/payment requirements and explicit physical handover confirmation. Opening the collection form does not release a document. Confirmed handover records the actual Admin or Registrar actor and release time; the owner sees Released on the next page load. Refund confirmation retains the exact full amount, required reference and actual confirming actor; it does not issue an external refund.


### Collection confirmation interface

The request-row Release documents action opens a centered modal in the list. It preselects the clicked ready document and lists other ready documents for the same claimant, including unavailable items with their assessment/payment reason. Staff must select at least one eligible document and confirm physical handover before submitting. Cancel and reopen clear the previous confirmation. The existing release service still validates the exact selection and records the actor/time; successful submission returns to the document request list.
