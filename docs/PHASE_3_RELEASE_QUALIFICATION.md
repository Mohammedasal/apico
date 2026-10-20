# APICO Phase 3 Release Qualification

Qualification date: 2026-10-20  
Source branch: `phase-3-safety-regression`  
Starting commit: `15c503136f5378046fe13a5292966548f665cf24`  
Environment: local Windows/XAMPP clone; no production access and no deployment

## Release decision

**Code checkpoint: qualified. Deployment: HOLD.**

The Phase 3 code and migration are qualified for a release checkpoint. Production deployment remains explicitly unauthorized until an operator supplies the production backup reference, confirms the maintenance window, and signs the deployment section below.

## Qualification evidence

| Gate | Result | Evidence |
| --- | --- | --- |
| Full Laravel regression | PASS | 161 tests, 915 assertions, 0 failures on PHP 8.4.21 and Laravel 13.38.0. |
| Production asset build | PASS | `pnpm run build`; Vite 8.3.4 completed successfully. The optional Fontaine optimization notice is non-blocking. |
| Frontend dependency audit | PASS | `pnpm audit --audit-level=high`: no known vulnerabilities. |
| PHP dependency audit | PASS after lock refresh | The original lockfile exposed 26 advisories across six packages. Dependencies were refreshed within `composer.json` constraints; `composer audit --locked` then reported no advisories. |
| Desktop browser | PASS | 1440x900 checks on Dashboard, Customers, Supplier Payments, Monthly Report, Stock Profit, Trial Balance, General Ledger, and Audit Log. No document-level horizontal overflow. |
| Mobile browser | PASS | 390x844 checks on the same eight pages. No document-level horizontal overflow; wide tables remain inside horizontal-scroll containers. |
| Arabic RTL | PASS | Dashboard, Customers, Monthly Report, and Trial Balance report `lang=ar`, `dir=rtl`, translated titles/headings, and no document-level overflow at 390x844. |
| Print/PDF layout | PASS with browser limitation noted | Customer Statement rendered with five tables, a dedicated running-ledger print area, both print actions, and an active `@media print` rule. Print CSS uses A4 landscape, hides navigation/forms/no-print controls, avoids row splitting, and supports ledger-only output. The in-app qualification browser does not expose its native print-preview surface, so no PDF file was saved. |
| MySQL backup/restore | PASS | `apico_local` was dumped with `--single-transaction --routines --triggers`, verified non-empty, SHA-256 recorded, and restored to isolated database `apico_qualification_20261020`. Source and restore matched at 36 tables, 4 users, and 1,215 customers. |
| Migration rehearsal | PASS | Pending migration `2026_10_17_000001_classify_recycle_out_adjustments` ran successfully on the restored clone only. The working `apico_local` database was not migrated. |
| Production safety | PASS | No production connection, backup, migration, tag push, or deployment was performed. |

## Backup/restore rehearsal record

- Temporary dump: `work/apico-local-qualification-20261020.sql` (outside the repository)
- Dump size: 3,058,451 bytes
- SHA-256: `de6d7f8613f3ee7f96a7f4ad335b92fe0081dfd1cb320ee899a92dab0791c663`
- Restore target: `apico_qualification_20261020`
- Source/restore sample counts: 36 tables, 4 users, 1,215 customers
- Migration result: all migrations through `2026_10_17_000001_classify_recycle_out_adjustments` marked ran on the restore target

This local rehearsal artifact is not the required production predeployment backup. Database dumps and credentials must not be committed.

## Deployment checklist (not executed)

- [ ] Release owner approves the production maintenance window.
- [ ] Production `.env` is verified with `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] A permanent `predeploy` backup is created with `ops/server/backup-apico.sh predeploy`.
- [ ] The production backup archive, checksum, and restore-readiness check are recorded below.
- [ ] The release checkpoint commit/tag is confirmed as the intended deployment source.
- [ ] Database migration status is captured before deployment.
- [ ] Deployment and `php artisan migrate --force` receive explicit authorization.
- [ ] Post-deployment smoke, accounting reconciliation, scheduler, and queue checks pass.
- [ ] Rollback is rehearsed against the recorded checkpoint and backup reference.

Production backup reference: **PENDING — no production action authorized**  
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
