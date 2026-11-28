# Independent appointments and document requests: repository audit

Audit date: 28 November 2026  
Branch: `upgrade/laravel-13-controlled`  
Baseline commit: `139b523ac23f5ee44d90a3758a7230f8f32ae162`

## Scope and baseline

The repository is Laravel 13 / PHP 8.4 with Blade, AdminLTE, MySQL-compatible migrations, PHPUnit, and existing browser fixtures. This differs from the Laravel 9 version in older repository guidance and matches `composer.json` and the checked-in test configuration. PHPUnit is configured to use the dedicated MySQL database `dtc_testing`; focused baseline suites were run there, and no migrations were applied.

The worktree was already dirty before this audit. Existing edits include the student appointment controller, view provider, cancellation service, advising documentation and views, appointment safety and document UI tests, plus untracked `public/css/advising-appointments.css`. They are preserved and are not attributed to this audit.

## Dependency map and classification

| Relationship / path | Classification | Finding |
| --- | --- | --- |
| `DocumentRequest.appointment_id` → `Appointment` | Historical compatibility; obsolete for new requests | Nullable in the original migration. New submissions in `DocumentSubmissionService` explicitly create requests with `appointment_id = null`. Legacy controllers and confirmation actions still read/write this link. |
| `payment_appointment_id` and `AppointmentPayment` bundle links | Historical financial compatibility | Existing document bundles use appointment payment rows. Cashier reconciliation and transaction history still query these rows. Preserve; do not migrate or rewrite original verified payments. |
| `pending_claim_appointment_id`, `AppointmentDocumentItem`, claiming lifecycle events | Historical compatibility; obsolete for new claiming | New walk-in release permits ready requests with no claim appointment and records a document workflow event. For legacy claim reservations it also locks and advances linked historical appointments. |
| Portal claiming appointment POST | Obsolete for new transactions | `ClaimingAppointmentController::store` rejects new claiming reservations. Retain route/history views to explain retired bookings and keep old release routes usable. |
| Portal document submission | Independent for new requests | Student/alumni submission uses `DocumentSubmissionService`; no slot or appointment is created. Existing route uses a document submission idempotency middleware, not an appointment requirement. |
| Registrar document release | Independent with legacy reconciliation | `Registrar\DocumentRequestController::releaseWalkIn` calls `WalkInDocumentReleaseService`; ready, assessed, paid requests can be physically released without an appointment. It safely maintains any matching historical claiming lifecycle. |
| Legacy appointment confirmation/edit/cancel and linked requests | Unsafe to remove without reconciliation | Registrar confirmation may create/link a request for older appointment submissions; student edit/cancel and Cashier bundle operations preserve historical semantics. Do not repurpose these records as consultation bookings. |
| Advising appointment visit type | New independent appointment path, currently limited | Student/alumni advising uses a shared slot with capacity and lifecycle history. It is unpaid and does not create a document request. Categories are hardcoded in the controller and are not a Registrar-managed service catalog. |
| Registrar appointment index and payment actions | Mixed legacy and consultation workflow | List/counts and payment endpoints include legacy appointment concepts; pending payment list excludes advising. Separate consultation reporting and configurable services are not yet implemented. |

## Current flow summary

**New document request:** eligible portal user → document submission service → nullable appointment link → Registrar assessment/status workflow → standalone or historical bundle payment verification → ready → walk-in release service. New claiming appointment creation is retired. The release service enforces ownership, ready status, completed assessment, payment satisfaction, exact handover confirmation, operation idempotency, and staff attribution.

**Advising appointment:** student/alumnus → select shared Registrar slot and one of four controller-defined consultation topics → pending appointment → Registrar confirms. Booking checks active capacity, date, closure, and duplicate same-user booking in the slot; lifecycle events and notifications are used. It shares appointment tables and slots with the legacy document appointment flow.

**Legacy document appointment/payment:** existing appointment, payment, bundle, proof, lifecycle, and release records remain connected across Registrar, Cashier, portal payment, and history/reporting code. They cannot be safely removed by a nullable-FK cleanup or bulk conversion.

## Schema observations

- `appointments` requires a slot and retains legacy document fields; later migrations add lifecycle/version, historical item links, and restricted foreign keys.
- `document_requests.appointment_id` is nullable and originally uses `ON DELETE SET NULL`; additive migrations add a separate preserved payment origin and pending historical claim link.
- Appointment payment rows are tied to appointments and bundle payment identity is used by Cashier reconciliation.
- These constraints support a safe additive path, but actual production row counts and unfinished legacy transaction inventory require a read-only production inventory before any data migration. No operational database was queried.

## Proposed incremental plan

1. Keep legacy associations readable and preserve every historical payment, proof, receipt, lifecycle event, and release record.
2. Make consultation appointment service configuration additive, with explicit role eligibility and approval settings; seed no institutional eligibility assumptions beyond approved Registrar decisions.
3. Move advising booking to configured service records while retaining the existing appointment identity, slot locking, lifecycle idempotency, and shared schedule rules.
4. Keep document submission, payment verification, assessment, and walk-in release appointment-free. Add regression coverage for no appointment creation and release without a claim appointment.
5. Keep historical payment bundle and claiming compatibility behind legacy paths. Do not convert unfinished legacy records or change financial status automatically.
6. Separate Registrar consultation queues and summaries from legacy document appointments, then update navigation/help text and capstone traceability documentation.
7. Run focused tests sequentially against `dtc_testing`, then relevant full regression/browser checks. Record manual role and production-inventory limitations separately.

## Policy and environment questions left open

- Registrar-approved service names, descriptions, requirements, eligibility roles, and which services require approval.
- Applicant eligibility for appointment services and document types.
- Whether duplicate appointments across different slots on a day are allowed. Existing advising code only prevents duplicates for one user in the same slot.
- Published schedule/holiday source and reminder delivery policy.
- Production inventory of active legacy appointment-document/payment bundles and any migration/reconciliation requirement.
- Free-document handling: repository review notes an existing zero-fee/Cashier validation mismatch; no new payment or waiver policy should be inferred.

## Implementation and verification status

Implementation follows the approved decisions: consultation appointments are free; the active default service is General Registrar Consultation / Advising, eligible to students and alumni, with Registrar confirmation; document submission and walk-in release remain appointment-free. Applicants do not see the default service. Eight example services are seeded inactive.

- Added an additive catalog/lifecycle migration. It backfills only known advising appointments; historical document appointment, document, payment, receipt, and proof rows are not rewritten. Rollback refuses after service/lifecycle records exist, so operational rollback may require an application-only rollback and an explicit data plan.
- Added Registrar service configuration and consultation lifecycle actions, private staff remarks, date/status summaries, and consultation portal booking/rescheduling/cancellation. Historical document appointment routes are read-only for appointment operations.
- Historical Cashier bundle reconciliation remains in its existing workspace; new document submission, assessment, payment verification, and walk-in release remain independent.
- No operational database was queried or migrated. Migration execution was exercised by PHPUnit using configured `dtc_testing` only.
- `AppointmentServiceWorkflowTest`: **6 tests, 70 assertions passed**.
- Existing `AppointmentSlotConcurrencyTest`: **2 tests, 27 assertions passed**, proving competing legacy submission workers wait on the MySQL slot lock. A dedicated consultation concurrency worker was not added.
- PHP syntax checks passed for changed controllers, model, and migration. Pint check reports style differences; formatting was not applied to avoid broad formatting.
- Combined `AppointmentEditingSafetyTest|AppointmentFlowTest|DocumentRequestUiTest|AppointmentLifecycleIntegrityTest|AppointmentFreeSubmissionTest|WalkInDocumentReleaseTest|AppointmentServiceWorkflowTest`: **119 tests, 1 error, 29 failures**. This includes assertions for prior document-appointment mutations and stale UI copy; `DocumentRequestUiTest::test_both_roles_have_independent_navigation_and_owner_scoped_history` errors on a null menu lookup. Legacy/UI regression acceptance is not established.
- Full `php vendor/phpunit/phpunit/phpunit --no-coverage` was attempted (797 tests discovered) but interrupted at about 378/797 after a prolonged block of integration errors; PHPUnit did not produce a terminal summary. Treat the full suite as incomplete.
- No full-suite or authenticated manual browser/role acceptance was completed. Production legacy appointment/bundle inventory remains unknown.

## Phase status

- Audit and migration/history strategy: complete at source level; production row counts and unfinished legacy cases remain unknown.
- Catalog, booking, consultation lifecycle, separation, navigation, summary report, and documentation: implemented; focused tests listed above passed.
- Regression reconciliation and release acceptance: incomplete. Broader tests currently fail as recorded.
- The isolated feature branch named in the pasted specification was not created because the repository already has active changes on `upgrade/laravel-13-controlled`. No files were staged, committed, or deployed.
