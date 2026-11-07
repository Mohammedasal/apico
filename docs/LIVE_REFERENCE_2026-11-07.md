# APICO Live Database Reference — 2026-11-07

This manifest records the verified live database snapshot restored locally for deployment rehearsal. It contains no credentials or SQL data.

## Source

- Production application directory: `/var/www/apico`
- Production branch: `phase-2-accounting`
- Production commit: `82e4afb96511756d04be69b98d2830140308e0ee`
- Laravel: 13.12.0
- PHP: 8.4.22
- Environment: `production`
- Production debug at capture time: **enabled — must be disabled before deployment**
- Production timezone at capture time: **UTC — Phase 3 expects Asia/Amman**

## Backup evidence

- Server archive: `/home/apico/backups/predeploy/apico-predeploy-20261107-141115.sql.gz`
- Server checksum file: `/home/apico/backups/predeploy/apico-predeploy-20261107-141115.sql.gz.sha256`
- SHA-256: `e17b2400c4fd3cb5470c6475ddf2b6e686c999385cada8d1fdcffc22c5a1e67a`
- Compressed size: 224,059 bytes
- Decompressed SQL size: 3,914,514 bytes
- Download checksum: verified
- Gzip integrity: verified

The archive and extracted SQL remain outside Git under the qualification task's `work` directory.

## Local restore

- Local database: `apico_live_reference_20261107`
- Restore mode: isolated; `apico_local` and `apico_qualification_20261020` were not replaced
- Tables: 36
- Applied migrations: 16
- Phase 3 migration `2026_10_17_000001_classify_recycle_out_adjustments`: pending

## Reference counts and totals

| Area | Reference value |
| --- | ---: |
| Users | 4 |
| Customers | 1,215 |
| Suppliers | 955 |
| Materials | 6 |
| Recycle-in rows | 733 |
| Recycle-in kg | 988,990.000 |
| Recycle-out rows | 830 |
| Recycle-out kg | 941,756.000 |
| Recycle-out amount JOD | 120,715.496 |
| Customer-payment rows | 629 |
| Customer payments JOD | 231,160.646 |
| Stock-purchase rows | 789 |
| Stock-purchase kg | 228,050.050 |
| Stock-purchase cost JOD | 77,221.988 |
| Stock-sale rows | 362 |
| Stock-sale kg | 206,429.500 |
| Stock-sale value JOD | 123,202.730 |
| Supplier-payment rows | 0 |
| Supplier payments JOD | 0.000 |
| Production days | 277 |
| Production shift kg | 1,129,655.000 |
| Monthly-expense rows | 12 |
| Expense vouchers | 0 |
| Journal entries | 893 |
| Journal entry lines | 1,786 |
| Journal debits JOD | 454,874.867 |
| Journal credits JOD | 454,874.867 |
| Audit logs | 995 |
| Sessions at capture | 28 |

Journal debits and credits match exactly in this snapshot.

## Usage rules

- Use this database only as the immutable predeployment reference and as the source for an isolated migration rehearsal copy.
- Do not point routine local development at this database.
- Do not migrate this reference database in place. Clone it first, then rehearse on the clone.
- Do not commit the SQL archive, extracted SQL, `.env`, passwords, or SSH material.
- Before the eventual production deployment, create a new permanent predeployment backup because production may change after this reference was captured.

## Phase 3 migration rehearsal

- Rehearsal database: `apico_live_rehearsal_20261107`
- Source: isolated clone of `apico_live_reference_20261107`
- Migration duration: 4.69 seconds locally
- Migration batch: 4
- Reference database modified: no
- Missing or extra recycle-out IDs: 0
- Recycle-out rows with changed original fields: 0
- Original recycle-out totals before/after: identical
- Journal debit/credit before/after: 454,874.867 / 454,874.867 JOD
- Classified output rows: 829
- Classified correction rows: 1
- Classified return rows: 0
- Amount-variance rows: 1
- Amount variance: -70.500 JOD
- Calculation mismatches after migration: 0
- Variance-calculation mismatches after migration: 0
- Dashboard remaining recycle stock: 47,234.000 kg
- Raw purchased stock remaining: 21,620.550 kg

## Accounting backfill rehearsal

- Backfill database: `apico_live_backfill_rehearsal_20261107`
- Source: disposable clone of the successfully migrated rehearsal database
- Eligible operational sources: 2,610
- Sources missing journals before the corrected rehearsal: 1,689
- Baseline journals: 893 entries / 1,786 lines
- Baseline debit and credit: 454,874.867 JOD each

The first commit rehearsal rejected historical zero/negative stock-purchase corrections with `Accounting amount must be greater than zero`. The command was inside one database transaction, and verification confirmed a complete rollback to 893 entries / 1,786 lines. Candidate code commit `47d618d` then added explicit correction-direction posting and zero-value handling for payments, purchases, and sales, and aligned the backfill and dashboard coverage rules.

The corrected commit rehearsal completed successfully:

| Check | Result |
| --- | ---: |
| Journal entries after backfill | 2,867 |
| Journal lines after backfill | 5,734 |
| Debit after backfill | 930,504.656 JOD |
| Credit after backfill | 930,504.656 JOD |
| Expected operational journals covered | 2,660 / 2,660 |
| Sources still missing journals | 0 |
| Dashboard coverage | 100% |

A second `--commit` run was idempotent: the entry/line counts and totals did not change, and the command again reported zero missing sources. MySQL checksums for `customers`, `suppliers`, `recycle_outs`, `payments`, `stock_purchases`, `stock_sales`, and `supplier_payments` match the migrated source clone exactly, confirming that backfill added journals without changing operational source rows.

This rehearsal proves the candidate can backfill the captured data; it does not authorize a production backfill. Production `apico:accounting-backfill --commit` remains a separately approved accounting action and is excluded from the application/schema deployment.
