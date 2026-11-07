# APICO Phase 3 Deployment Plan

Plan date: 2026-11-07  
Candidate branch: `phase-3-safety-regression`  
Candidate tag: `phase-3-deployment-candidate-2026-11-07`  
Status: **PREPARATION ONLY — deployment is not authorized**

Current hold: the 2026-11-07 live-data rehearsal found 1,690 of 2,610 operational sources missing expected accounting journals. The schema migration itself passed without changing original transaction values. Historical accounting backfill remains unapproved and must be rehearsed separately before production deployment.

## Scope

This release includes the qualified Phase 3 safety, reporting, accounting, import, audit, and export work plus:

- Main-dashboard `Remaining Recycle Stock Kg`, calculated as all customer opening recycling kg plus recycle-in minus recycle-out.
- Customer and supplier statement print titles containing the counterparty name and selected month.
- Separate ledger-only print titles containing the counterparty name and selected month.

The release contains one database migration: `2026_10_17_000001_classify_recycle_out_adjustments`.

## Required approvals and evidence

Do not start the maintenance window until every field is complete.

- Release owner: **PENDING**
- Deployment operator: **PENDING**
- Maintenance window: **PENDING**
- Current production commit: **PENDING**
- Candidate commit/tag verified: **PENDING**
- Permanent predeployment backup path: **PENDING**
- Backup SHA-256: **PENDING**
- Backup restore verification: **PENDING**
- Rollback owner: **PENDING**

## Phase 1 — read-only production preflight

1. Confirm the deployed commit, PHP version, Laravel environment, debug state, timezone, queue, cache, session, and database drivers.
2. Confirm `APP_ENV=production`, `APP_DEBUG=false`, and `APP_TIMEZONE=Asia/Amman` without printing secrets.
3. Run `php artisan migrate:status` and record the current migration state.
4. Confirm sufficient free disk space for the release, Composer install, asset build, and at least two database backups.
5. Confirm the scheduler is running and that the queue process supervision method is known.
6. Record these predeployment database values:
   - Total customers, suppliers, users, and sessions.
   - Total recycle-in and recycle-out rows and kg.
   - Total payments, stock purchases, stock sales, and supplier payments.
   - Total journals and journal lines.
   - Trial-balance difference.
   - Customer receivables, supplier payables, stock kg, and latest completed-month P&L.
7. Run a dry-run accounting backfill only: `php artisan apico:accounting-backfill`. Do not add `--commit` during deployment preparation.

**Hold point A:** Release owner reviews the recorded values and authorizes backup creation. No code or schema change has occurred.

## Phase 2 — permanent backup and rollback preparation

From `/var/www/apico`, create the permanent backup using the deployed script:

```bash
sudo -u apico /var/www/apico/ops/server/backup-apico.sh predeploy
```

Then:

1. Record the exact `.sql.gz` path and `.sha256` path.
2. Verify the checksum and `gzip -t` result.
3. Copy the backup to the approved secondary location.
4. Restore the archive into an isolated temporary database.
5. Compare table count and the Phase 1 record counts between production and the restore.
6. Record the current production Git commit as the code rollback point.
7. Confirm the previous application release is still obtainable without changing production.

**Hold point B:** Deployment operator and release owner confirm the restore test, backup checksum, rollback commit, and maintenance window.

## Phase 3 — maintenance window and release installation

1. Pause user access with Laravel maintenance mode.
2. Stop workers cleanly after allowing current jobs to finish.
3. Reconfirm that no import, accounting backfill, payroll, or manual settlement job is running.
4. Install the exact candidate tag. Do not deploy an uncommitted directory or a moving branch head.
5. Install locked PHP dependencies with production flags.
6. Install locked frontend dependencies and build production assets, or install an already verified build artifact.
7. Clear stale caches before migration.
8. Run `php artisan migrate --force` once.

Migration-specific expectations:

- Four columns are added to `recycle_outs`: `transaction_type`, `calculated_amount`, `amount_variance`, and `override_reason`.
- Every existing recycle-out row is classified and receives calculated/variance values.
- Original dates, weights, rates, and stored totals are not intentionally overwritten.
- MariaDB DDL can auto-commit. A migration failure after columns are added must be treated as a rollback event; do not blindly rerun it.

9. Rebuild Laravel caches with the production environment.
10. Restart workers and confirm the scheduler configuration.

**Hold point C:** Keep maintenance mode enabled until all database and application checks in Phase 4 pass.

## Phase 4 — post-migration reconciliation

Before reopening the application:

1. Confirm all migrations are marked `Ran` exactly once.
2. Confirm the recycle-out row count is unchanged.
3. Confirm pre/post totals match for original recycle-out weight, recycled kg, waste kg, non-recycled kg, rate, and stored amount.
4. Record counts grouped by the new `transaction_type` and the count/sum of non-zero amount variances.
5. Re-run all Phase 1 record counts and financial totals.
6. Confirm trial-balance difference remains zero and source-coverage warnings have not increased.
7. Confirm no unexpected journals, imports, settlements, deletions, or audit events were created by deployment.

Application smoke checks:

- Admin, accountant, data-entry, viewer, inactive-user, and logout behavior.
- Dashboard totals, including `Remaining Recycle Stock Kg`.
- Customer and supplier lists and statements.
- Full statement and ledger-only print titles with counterparty name and month.
- Recycle-in/out, payments, purchases, sales, and supplier-payment read pages.
- Monthly, stock-profit, inventory-valuation, accounting, aging, and cash/bank reports.
- Arabic RTL rendering.
- Queue, scheduler, cheque alert cache, logs, and protected production error behavior.

**Hold point D:** Release owner compares the pre/post evidence and explicitly approves reopening the application.

## Phase 5 — reopen and observe

1. Disable maintenance mode.
2. Perform one read-only browser pass as each representative role.
3. Monitor application, queue, scheduler, database, and web-server logs for at least the agreed observation window.
4. Do not run imports or `apico:accounting-backfill --commit` during the observation window.
5. Record the final deployed commit, migration batch, backup reference, approvers, and completion time.

## Rollback triggers

Rollback immediately if any of these occurs:

- Migration error or partial recycle-out classification.
- Changed row counts or changed original transaction totals.
- Trial balance becomes non-zero or source coverage regresses.
- Authentication/authorization failure.
- Missing assets, repeated HTTP 500 responses, queue failure, or scheduler failure.
- Material dashboard, ledger, P&L, inventory, receivable, or payable discrepancy.
- The release tag or production environment does not match the approved evidence.

## Rollback procedure

1. Enable maintenance mode and stop workers.
2. Preserve current logs and record the failed commit and migration state.
3. Restore the previous application release.
4. If the migration failed before any production writes and the old application operates safely with the added columns, leave the columns in place until the incident is reviewed rather than improvising destructive SQL.
5. If original data changed, the migration partially backfilled, or postdeployment writes occurred, restore the verified permanent predeployment backup.
6. Clear/rebuild caches for the restored application and restart workers.
7. Re-run the Phase 1 counts and financial reconciliation before reopening.
8. Document the failure; do not retry deployment without a new reviewed plan.

## Explicitly excluded from this deployment

- Production Excel import or data wipe.
- `apico:accounting-backfill --commit`.
- Automatic cheque settlement.
- Manual transaction deletion or reversal.
- Changing the approved expense source or cutover date.
- Any production action before all four hold points receive the required approval.
