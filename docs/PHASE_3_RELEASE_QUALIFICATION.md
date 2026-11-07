# APICO Phase 3 Release Qualification

- Qualification date: 2026-10-20; live-data continuation: 2026-11-07
- Source branch: `live-reference-2026-11-07`
- Starting commit: `15c503136f5378046fe13a5292966548f665cf24`
- Qualified code commit: `47d618d`

Environment: local Windows/XAMPP; production was read only except for creating the requested backup files; no deployment

## Release decision

**Code checkpoint: qualified. Deployment: HOLD.**

The Phase 3 code, migration, and historical journal backfill behavior are qualified against a restored 2026-11-07 live snapshot. Production deployment remains explicitly unauthorized until the production environment is corrected, a fresh backup is captured for the maintenance window, and the release owner/operator sign the deployment section below.

## Qualification evidence

| Gate | Result | Evidence |
| --- | --- | --- |
| Full Laravel regression | PASS | 165 tests, 932 assertions, 0 failures after the live-data correction fix. |
| Production asset build | PASS | `pnpm run build`; Vite 8.3.4 completed successfully. The optional Fontaine optimization notice is non-blocking. |
| Frontend dependency audit | PASS | `pnpm audit --audit-level=high`: no known vulnerabilities. |
| PHP dependency audit | PASS after lock refresh | The original lockfile exposed 26 advisories across six packages. Dependencies were refreshed within `composer.json` constraints; `composer audit --locked` then reported no advisories. |
| Desktop browser | PASS | 1440x900 checks on Dashboard, Customers, Supplier Payments, Monthly Report, Stock Profit, Trial Balance, General Ledger, and Audit Log. No document-level horizontal overflow. |
| Mobile browser | PASS | 390x844 checks on the same eight pages. No document-level horizontal overflow; wide tables remain inside horizontal-scroll containers. |
| Arabic RTL | PASS | Dashboard, Customers, Monthly Report, and Trial Balance report `lang=ar`, `dir=rtl`, translated titles/headings, and no document-level overflow at 390x844. |
| Print/PDF layout | PASS with browser limitation noted | Customer Statement rendered with five tables, a dedicated running-ledger print area, both print actions, and an active `@media print` rule. Print CSS uses A4 landscape, hides navigation/forms/no-print controls, avoids row splitting, and supports ledger-only output. The in-app qualification browser does not expose its native print-preview surface, so no PDF file was saved. |
| Dashboard recycling balance | PASS | Dashboard shows total remaining recycling stock as customer opening kg plus recycle-in minus recycle-out, independently from purchased stock inventory. |
| Statement print filenames | PASS | Customer and supplier statement and ledger-only print titles include the counterparty name and selected month. |
| MySQL backup/restore | PASS | The requested production snapshot was downloaded, SHA-256/gzip verified, and restored to isolated database `apico_live_reference_20261107`: 36 tables, 4 users, and 1,215 customers. |
| Migration rehearsal | PASS | Migration `2026_10_17_000001_classify_recycle_out_adjustments` ran in 4.69 seconds on a disposable live-data clone. All 830 recycle-out IDs and original values were preserved; calculated/variance mismatches were zero. |
| Accounting backfill rehearsal | PASS | A failed first attempt rolled back completely. After correction handling was added, the disposable clone reached 2,867 entries / 5,734 lines, balanced at 930,504.656 JOD, 2,660/2,660 expected journals, zero missing, unchanged source-table checksums, and an unchanged second commit run. |
| Production safety | PASS | Production database/application/schema were not changed. Only backup archive/checksum files were created; no migration, backfill, code switch, tag push, or deployment occurred. |

## Backup/restore rehearsal record

- Temporary dump: `work/apico-local-qualification-20261020.sql` (outside the repository)
- Dump size: 3,058,451 bytes
- SHA-256: `de6d7f8613f3ee7f96a7f4ad335b92fe0081dfd1cb320ee899a92dab0791c663`
- Restore target: `apico_qualification_20261020`
- Source/restore sample counts: 36 tables, 4 users, 1,215 customers
- Migration result: all migrations through `2026_10_17_000001_classify_recycle_out_adjustments` marked ran on the restore target

This local rehearsal artifact is not the required production predeployment backup. Database dumps and credentials must not be committed.

## Live reference record

- Production code at capture: `82e4afb96511756d04be69b98d2830140308e0ee` on `phase-2-accounting`
- Archive: `/home/apico/backups/predeploy/apico-predeploy-20261107-141115.sql.gz`
- SHA-256: `e17b2400c4fd3cb5470c6475ddf2b6e686c999385cada8d1fdcffc22c5a1e67a`
- Local immutable restore: `apico_live_reference_20261107`
- Migrated clone: `apico_live_rehearsal_20261107`
- Backfill clone: `apico_live_backfill_rehearsal_20261107`
- Detailed evidence: `docs/LIVE_REFERENCE_2026-11-07.md`

This is a verified reference, not a substitute for a new backup immediately before deployment if production has changed.

## Deployment checklist (not executed)

- [ ] Release owner approves the production maintenance window.
- [ ] Production `.env` is corrected to `APP_ENV=production`, `APP_DEBUG=false`, and `APP_TIMEZONE=Asia/Amman` (capture showed debug enabled and UTC).
- [ ] A fresh permanent `predeploy` backup is created for the approved maintenance window; the current live commit does not yet contain `ops/server/backup-apico.sh`.
- [ ] The production backup archive, checksum, and restore-readiness check are recorded below.
- [ ] The release checkpoint commit/tag is confirmed as the intended deployment source.
- [ ] Database migration status is captured before deployment.
- [ ] Deployment and `php artisan migrate --force` receive explicit authorization.
- [ ] Post-deployment smoke, accounting reconciliation, scheduler, and queue checks pass.
- [ ] Rollback is rehearsed against the recorded checkpoint and backup reference.

Reference backup: `/home/apico/backups/predeploy/apico-predeploy-20261107-141115.sql.gz` — fresh window backup pending

Maintenance window: **PENDING**

Release owner approval: **PENDING**

Deployment operator sign-off: **PENDING**

## Rollback plan

1. Stop the deployment if any preflight, migration, smoke, or reconciliation gate fails.
2. Restore application code to the Phase 3 release checkpoint recorded in Git.
3. If a migration or write occurred, restore the permanent production `predeploy` backup whose archive and checksum are recorded above.
4. Clear Laravel caches, restart only the application services covered by the deployment procedure, and rerun smoke and accounting reconciliation checks.
5. Preserve logs, the failed release commit, and the restored backup reference for review.

## Qualification sign-off

Prepared by: Codex local qualification run  
Technical result: **PASS for code checkpoint**  
Production authorization: **NOT GRANTED**
