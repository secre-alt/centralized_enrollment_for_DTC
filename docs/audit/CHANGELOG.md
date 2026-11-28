# Modernization changelog

## November 27, 2026 — in progress

- Created `upgrade/laravel-13-controlled` from `nov-26` at `d1c8386b86f964527b4108bd0587505b3a3c9a48` without committing or pushing.
- Established default-suite Laravel 9 baseline: 766 tests / 9,334 assertions passed.
- Retrieved Composer/npm advisory evidence using network-enabled execution.
- Attempted a hash-verified isolated PHP 8.2 baseline runtime; Windows Application Control blocked execution. System PHP unchanged.
- Prepared hash-verified isolated PHP 8.4.28 successfully; required extensions, Composer metadata retrieval, platform checks and Artisan boot pass. Optional ZIP DLL is blocked and not configured; system PHP remains unchanged.
- Installed Laravel 10 checkpoint and compatible/security-related Composer updates; actual platform check now passes.
- Updated Vite/plugin and removed the absent CSS build entry; build succeeds without changing established page assets or UI.
- Corrected the modal fixture's welcome-dialog radius expectation to 16px at the user's explicit selection, retaining 12px checks for other dialogs. All 42 fixtures pass; no stylesheet changes.
- Corrected two mail retry test setups for Laravel 10 by restoring the original mail fake after deliberate mocked enqueue failures; focused PHP 8.4.28 run passes 2 tests / 29 assertions. Complete checkpoint repeat passed 766 tests / 9,334 assertions.
- Added a guarded synthetic-record/schema snapshot tool for before/after dependency transitions on `dtc_testing`; it emits schema metadata and record hashes, not record contents.
- Installed Laravel 11.57.2 with Sanctum 4, Permission 6, PHPUnit 11 and compatible Collision/Pint. Preserved application structure and custom middleware; removed unused Doctrine DBAL.
- Required the actual tested PHP `~8.4.28` branch, converted test metadata to attributes and migrated PHPUnit XML while retaining forced MySQL isolation. Discovery retains all 766 test identities/datasets and all 240 route entries match baseline.
- Verified the Laravel 10-to-11 transition preserves schema metadata and synthetic record hashes across all 49 tables. Laravel 11 complete repeat passed 775 tests / 9,374 assertions.
- Added audit plan, dependency inventory, evidence, validation reports and rollback/readiness gates.
- Preserved account-linked enrollment/payment history and financial actors during administrative removal; unused staff removal remains available. Protected legacy JSON curriculum identity and credited-subject history during subject/program removal, and rechecked selected subjects under locks during enrollment creation.
- Restricted the development administrator seed to local/testing environments and validated bounded search input. Nine focused security/data-preservation tests pass with 40 assertions; complete regression acceptance remains pending.
- Froze one multi-page workflow fixture after a recorded operating-system clock jump exceeded its idle-session limit; its focused rerun passes 25 assertions. Session security logic is unchanged.
- Rejected malformed password-link/hash inputs before rendering or hashing, and corrected Help Center enrollment guidance to place Cashier verification before final Registrar approval. Focused input/history/help verification passes 18 tests / 142 assertions.
- Made legacy CSV escape arguments explicit for PHP 8.4 compatibility. Existing timetable/import checks pass 40 tests / 432 assertions.
- Corrected three stale JavaScript fixtures without modifying application JS/CSS; the complete Node run passes 94 tests with the patched PHP subprocess runtime.
- Downloaded and hash-verified an isolated MariaDB 11.4.14 archive; Windows Application Control blocked executable startup. No database service or operational server was changed.

No migrations added, production records changed, deployment performed, commits created or pushes made. Subsequent checkpoint results must be appended after execution.
