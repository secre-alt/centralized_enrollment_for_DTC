# Rollback and recovery plan

November 27, 2026. This is a procedure, not an executed rollback or permission to deploy.

## Original reference

Original branch `nov-26`, commit `d1c8386b86f964527b4108bd0587505b3a3c9a48`; upgrade work is on `upgrade/laravel-13-controlled`. Original dependency manifests are recoverable from that commit. No commits or pushes were made by modernization work.

Changing branches alone does not restore `vendor`, `node_modules`, generated assets or caches. Do not run an old application against a new dependency directory, or discard the upgrade work to simulate rollback.

## Local recovery

1. Preserve the current upgrade diff and reports. Use a separate clean checkout/release directory at the desired verified checkpoint; do not reset or clean this working tree.
2. Install that release's exact Composer and npm lockfiles with a trusted compatible runtime. Do not resolve new versions during rollback, fake PHP versions, or ignore platform requirements.
3. Rebuild required assets, regenerate caches for that release and verify route loading, authentication, receipts and critical workflows on a disposable database.
4. Keep the operational database unchanged. Framework-only dependency rollback does not require rolling back historical application migrations.

The original Laravel 9 release is an unsupported recovery reference, not a newly approved production destination. Restoring its original lock on current PHP 8.4 reproduces the known Nette schema platform failure. A trusted compatible runtime or a separately tested compatible dependency release is required.

## Deployment rehearsal required before production approval

- Retain immutable old/new release artifacts, lockfiles, runtime/extension manifests and generated assets.
- Use an isolated staging database containing sanitized or synthetic representative records. Verify a separately authenticated backup and restore it using the existing [backup procedure](../security/backup-restore.md).
- Rehearse switching release artifacts and restarting web processes, queue workers and scheduled commands. Keep application keys and private storage consistent without copying secrets into reports.
- Confirm migration backward compatibility before switching code. Never reverse migrations containing legitimate records as a routine code rollback.
- If schema/data restoration is genuinely necessary, obtain a separate authorized recovery decision, preserve intervening legitimate transactions and use the approved DBA process.
- Validate roles, enrollment/payment preservation, receipt/reference uniqueness, appointment locks, exports and private downloads before reopening writes.

No backup restoration, deployment rollback rehearsal or production runtime verification has yet been performed by this work. Production deployment remains prohibited.
