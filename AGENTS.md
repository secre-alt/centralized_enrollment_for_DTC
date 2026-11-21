# DTC EMS repository guidance

## Scope and working practices

This is the existing Danao Technological College Enrollment Management System. Preserve its working Student, Registrar, Cashier, Admin, applicant and alumni flows.

- Inspect `git status`, relevant code, migrations, routes, views and tests before editing. Preserve unrelated local modifications, including untracked files.
- Make focused changes using existing architecture and naming. Do not replace modules, add dependencies or introduce new workflow pages without a concrete need.
- Treat requests to design, mock up, preview, or discuss a UI as design-only. Present a proposal or mockup first without changing application code. Implement only when the user explicitly requests implementation; a request such as "can you design a bottom nav?" does not authorize implementation.
- Do not stage, commit, push, reset, discard changes, delete operational data or run destructive seeders unless explicitly authorized.
- Proceed with authorized inspection, reversible implementation and verification without repeatedly requesting permission. Ask for missing business decisions only when they affect correctness; continue independent work.
- Do not spawn sub-agents unless the user explicitly requests delegation or parallel agent work.

## Stack and implementation conventions

- Laravel 9, PHP compatible with `composer.json` (currently `^8.0.2`), MySQL, Blade, AdminLTE and Spatie roles/permissions.
- Reuse controllers under `app/Http/Controllers`, domain services under `app/Services`, Eloquent models, existing request validation and `routes/web.php` route groups.
- Reuse shared Blade components, responsive styles, notifications and existing portal navigation. Assets include directly served files in `public/css` and `public/js`; inspect whether a change actually uses Vite before rebuilding.
- Use additive migrations for schema changes. Preserve historical records and avoid guessing ambiguous backfills.
- Read the configured academic year and semester from existing settings. Preserve the distinction between an enrollment's own term and the currently configured term. Use Asia/Manila for user-facing academic/payment dates, following existing storage conventions.
- Render untrusted text with escaped Blade expressions or DOM text APIs. Enforce permissions, ownership, eligibility and state transitions on the server; client-side controls are supplementary.

## References

Read the relevant documents before changing their workflows:

- `docs/paid-enrollment-subject-replacement-plan.md`
- `docs/enrollment-workflow-acceptance.md`
- `docs/payment-history-enhancements.md`
- `docs/payment-reports.md`
- `docs/personal-payment-history-exports.md`

Treat these as implementation context, not evidence that every acceptance requirement is complete. Verify the actual code. Resolve conflicts with the user's current specification explicitly and update documentation when behavior changes.

## Academic workflow invariants

- A returned paid enrollment remains attached to its original verified payments. Subject replacement is not automatic rejection, cancellation or refund.
- Reuse the existing enrollment and revision records. Preserve unaffected selections and the student's official `program_id`; a class's hosting program is a separate concept.
- `subject_ids` retains curriculum identity. Section assignments map those subjects to actual offerings. Equivalencies are directed, explicitly approved records; similar names or matching units alone do not establish equivalency.
- Approved historical enrollments are immutable through the ordinary revision endpoints.
- Validate the complete final load: subject identity, directed equivalency, credits, duplicates, schedules, term, section activity and capacity. Reject the recorded unavailable section even in a crafted POST.
- Calculate Regular/Irregular status from all current assignments and equivalencies plus the preserved baseline. A section change alone does not imply Irregular, and revising one subject must not clear treatment required by another.
- Capacity belongs to an offering. Follow the documented pending/approved occupancy rule; exclude inactive academic states. Missing capacity or unknown active section assignments must not imply available seats.
- Serialize critical seat changes with transactions and offering locks. Count current committed occupancy after acquiring the lock; an earlier MySQL repeatable-read snapshot can overbook a section. Preserve bounded deadlock retries and keep notifications outside retried transactions where appropriate.
- Retain revision actors, reasons, original/new choices, timestamps and state history. Guard repeated and conflicting submissions; require explicit student revision/refund confirmation.

## Payments, refunds and access

- Never delete, rewrite or downgrade an original verified payment, receipt, GCash verification or Cashier audit record to implement an academic revision.
- A valid fixed-fee revision does not create another payment or revenue collection. Keep financial refund status separate from academic enrollment status.
- Reuse existing Cashier reconciliation. Pending refunds are not completed refunds. Do not issue, simulate or automatically confirm external GCash refunds.
- The current reconciliation path supports an exact full refund with a required reference and recorded confirmer/time. Do not invent partial-refund, denial or adjustment rules. Extend financial behavior only against approved policy and preserve duplicate/over-refund protections.
- Apply ownership to every portal enrollment, revision, payment, export and receipt endpoint. Secondary staff roles must never widen a portal request to other users' financial records.
- Reuse `DocumentPaymentIdentity` for mirrored document/bundle identity and `TransactionHistory` for scoped history. Do not introduce fuzzy/global reference deduplication or duplicate child receipts.
- Monetary totals should use integer centavos from stored decimal values. Keep refunded original collections distinct from separately confirmed refunds. Preserve private/no-store behavior for financial responses.

## Institutional policy boundaries

Use the authorities identified by the user: approved curricula/catalogs and Registrar rules for prerequisites; school academic-load rules and applicable CHED program standards for unit limits/overloads; enrollment, leave, withdrawal and scholarship policies for reduced load/deferral; published school refund schedules and applicable rules for refunds.

These source categories do not supply actual prerequisite mappings, passing grades, unit caps, waiver authority, refund percentages or effective dates. Do not infer those values or claim automated enforcement where no approved rules are configured. Implement clearly supported behavior, expose accurate limitations, and document the decision needed. Do not invent seat-expiry durations, refund entitlements or equivalency permissions.

## Verification and database safety

- PHPUnit is configured in `phpunit.xml` to use MySQL database `dtc_testing`. Verify that configuration before database tests or fixture scripts. Never point tests, `migrate:fresh`, rollback or destructive fixtures at the operational database.
- Run database suites sequentially when they share `dtc_testing`. Independent concurrency-test workers are intentional; do not run another database suite alongside them.
- Run focused tests during implementation. For changes spanning enrollment, payments, shared services or authorization, run the full suite before claiming regression acceptance. Record baseline failures separately from new failures.
- Test critical behavior: ownership and role boundaries, invalid transitions, multiple affected subjects, repeated revisions, rollback, payment preservation, refund state, schedule/capacity checks and duplicate submissions.
- A sequential last-seat test does not establish concurrency safety. Preserve the actual two-process MySQL test and its proof that both workers wait on the offering lock.
- Use targeted formatting and syntax checks; avoid formatting unrelated files. Do not run destructive migrations against the main application merely to verify a change.

Common commands, from the repository root:

```text
php vendor/phpunit/phpunit/phpunit --filter=EnrollmentSubjectReplacementWorkflowTest --no-coverage
php vendor/phpunit/phpunit/phpunit --filter=EnrollmentReplacementConcurrencyTest --no-coverage
php vendor/phpunit/phpunit/phpunit --no-coverage
php vendor/bin/pint path/to/changed.php
php -l path/to/changed.php
node --check public/js/enrollment-revision.js
node tests/browser/enrollment-revision.cjs
git diff --check
```

The Edge browser fixture uses disposable local HTML and supports `EDGE_BINARY`. It checks revision JavaScript, not every authenticated portal. Report browser/environment limitations honestly; do not substitute fixture success for manual role acceptance. The per-role checklist is in `docs/enrollment-workflow-acceptance.md`.

## Delivery

Report the actual behavior changed, relevant files, migrations/commands required, tests run and their outcomes, and remaining policy or environment limits. Distinguish implemented functionality, automated verification and unperformed manual checks. Do not claim full-spec completion from a passing targeted suite, and do not present historical test counts as current results.
