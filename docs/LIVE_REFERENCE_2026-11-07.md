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
