# APICO System Audit: Current vs Suggested

Audit date: 2026-10-17

## Implementation Status: 2026-10-20

Phase 3 work is isolated on branch `phase-3-safety-regression`. It has not been deployed to production. The original baseline tag remains `phase-2-local-baseline-2026-10-17`.

Completed and regression-tested:

- Active-user enforcement, centralized user capabilities, corrected accountant access, restricted cheque settlement, `Asia/Amman`, and production-safe environment defaults.
- Bilingual route/role/page smoke coverage, including parameterized records and translation-key completeness.
- Completed-period actual P&L, partial-period expense labeling, future-month exclusion, expense-source selection/reconciliation, and accounting source-coverage checks.
- Stock profit row/summary reconciliation, explicit recycle output corrections, unclassified material isolation, negative-stock blocking, and an as-of-date inventory valuation report.
- Supplier payment parity: filters, full-data sorting, repeat entry, export, controlled deletion, accounting reversal, and audit coverage.
- Manual cheque settlement with derived due states and an idempotent daily alert command. No automatic bank settlement is performed.
- General Ledger, Trial Balance, accounting P&L, Balance Sheet, AR/AP aging, Cash & Bank, customizable exports, and idempotent historical journal backfill.
- Sales and production workbook staging, read-only preview, file hash verification, verified database backup, transactional commit, invalid-row continuation, and import audit provenance.
- Imported recycle revenue variances are shown before confirmation, preserved with calculated and workbook totals, and timestamped as approved by the admin import action.
- Verified MySQL/SQLite backup service, daily server backup script with checksums and retention, permanent predeploy backups, and an on-demand verified Artisan backup command.
- Before/after audit records for operational transactions, supplier payments, customers, suppliers, users, production, monthly expenses, settings, cheque settlement, accounting actions, and committed imports. An admin-only searchable Audit Log page is available.
- Automated release quality workflow with Composer and pnpm vulnerability audits. Current local regression: 163 tests and 924 assertions passing. Phase 3 local release qualification is recorded in `docs/PHASE_3_RELEASE_QUALIFICATION.md`.

Still open before release qualification:

- Decide and document the conversion-cost allocation policy when production and stock-sale months differ.
- Define process loss only after production inputs can be matched to processing batches; do not infer it from unrelated recycle-in/out totals.
- Supply the production predeployment backup reference and obtain release-owner/operator signatures. The local checklist, rollback procedure, browser/RTL/print evidence, MySQL backup/restore drill, and migration rehearsal are complete. No deployment is authorized yet.

## Purpose

This document defines the current behavior, the proposed behavior, and the regression evidence required before deployment. Recommendations are not yet implemented unless explicitly marked as existing.

## Baseline

- Branch: `phase-2-accounting`
- Commit: `82e4afb`
- Automated suite: 92 tests, 437 assertions, all passing
- Route inventory: 120 routes, including 71 GET pages and 50 write routes
- Local copied-live database is used for reconciliation; production is not modified
- Application timezone currently resolves to UTC
- No scheduled Laravel tasks are registered
- Dependency vulnerability scans are not yet complete because Composer and npm executables are not available in the current shell path

## Security and Access

| Area | Current scenario | New suggested scenario | Required regression evidence | Priority |
|---|---|---|---|---|
| Active users | `is_active` is checked at login and by role middleware, but plain `auth` routes do not recheck it. A deactivated user with an existing session can retain access to general pages. | Add an active-user middleware to every authenticated route. Deactivation terminates effective access on the next request. | Deactivate a logged-in user and confirm every authenticated page returns 403 or redirects to login. | Critical |
| Permissions | Permissions are repeated as route role strings and do not always match `User` capability methods. | Define a central capability matrix or policies for operational view/write/delete, P&L view, accounting, cheque settlement, imports, settings, and users. | Parameterized tests for every role against every route group. | Critical |
| Accountant access | `canViewProfitAndLoss()` includes accountants, but monthly report, stock profit, and production are admin-only. Customer/supplier exports also exclude accountants. | Accountants receive read access to financial reports, production summaries, statements, and exports. Only approved write actions remain restricted. | Role matrix tests plus navigation visibility tests. | High |
| Data-entry cheque settlement | Data-entry users may update incoming and outgoing cheque settlement status. | Data entry may record the cheque, but only admin/accountant may mark collected, cleared, bounced, or cancelled. | Attempt each settlement transition as all four roles. | High |
| Session behavior | Idle lifetime is 120 minutes; Remember Me can restore authentication after session expiry. | Keep this only if accepted as policy; add a visible account/session policy and optional forced logout for sensitive roles. | Time-travel session tests, remember-cookie tests, logout invalidation test. | Medium |
| Deployment safety | `.env.example` enables debug, and local runtime currently has debug enabled. | Default templates to `APP_DEBUG=false`; deployment check must fail or warn when production debug is enabled. | Production configuration test/check command. | High |
| Timezone | Runtime timezone is UTC. | Set business timezone to `Asia/Amman`, while storing timestamps consistently and rendering local business dates. | Boundary tests around midnight and cheque due dates. | High |
| Uploads | XLSX structure and size are validated. Sales import flush is transactional. MySQL is not backed up by the application before flush; the built-in backup only handles SQLite. | Stage, preview, reconcile, take a verified MySQL backup, then commit. Preserve an import batch and rollback reference. | Corrupt workbook, oversized file, invalid rows, failed import rollback, and verified backup tests. | Critical |
| Export safety | XML is escaped, which protects workbook structure. All exported values are written as text. | Preserve escaping and add spreadsheet formula-injection tests for values starting with `=`, `+`, `-`, or `@`. Export true numeric/date cell types where useful. | Malicious input export test and workbook-open validation. | Medium |
| Dependencies | Application tests pass, but dependency advisories were not scanned in this environment. | Add Composer and npm audit commands to CI and deployment checks. | CI must publish and enforce vulnerability results. | High |

## Core Business Workflows

| Area | Current scenario | New suggested scenario | Required regression evidence | Priority |
|---|---|---|---|---|
| Customer ledger | Recycle-in controls kg balance. Recycle-out controls kg and JOD. Stock sales control JOD only. Payments reduce JOD. Running balances are available. | Keep the business rules, but move them into a documented counterparty ledger service with explicit debit, credit, kg-in, and kg-out entries. | Opening, period, closing, same-date ordering, adjustment, and export reconciliation tests. | High |
| Supplier ledger | Purchases increase payable JOD and supplier payments reduce it. Statement/export exist, but workflow and list features are behind customer operations. | Make supplier operations parallel to customer operations: remaining payable on list, total purchased kg, filters, server-side sorting, create-again workflow, previous-entry bar, admin deletion with journal reversal, print, and customizable export. | Purchase/payment/cheque/edit/delete/running-balance tests and page tests. | Critical |
| Supplier payments | Separate controller and page, no filters or sorting, returns to the list after save, and no admin delete route. | Use the same transaction UX and query rules as customer payments, including supplier/date/amount/type/status filters and stay-on-form entry. | Full list/filter/sort/create/edit/delete tests. | High |
| Transaction deletion | Admin deletion exists for five operational modules. Supplier payments are excluded. | Apply the same controlled delete/reversal process to supplier payments and other financial source records. Never hard-delete posted accounting history without a reversal. | Authorization, reversal, audit, and balance restoration tests. | Critical |
| Cheques | Pending/collected/bounced/cancelled for incoming and pending/cleared/cancelled for outgoing. Status changes are manual. | Keep settlement manual. Add derived lifecycle labels: upcoming, due today, overdue, settled. A daily job refreshes alerts/reminders, not bank settlement. | Due-date boundary, overdue, collected/cleared, bounced/cancelled, and idempotent job tests. | Critical |
| Cheque automation | No scheduler exists. | Add `apico:refresh-cheque-alerts` daily in `Asia/Amman`. Optionally notify admins/accountants. Do not automatically collect or clear cheques. | Run command repeatedly with frozen dates and confirm no duplicate accounting entries. | High |
| Import and accounting | Sales import deletes and recreates operational transactions but does not post the imported replacements to accounting. Existing journals are not rebuilt as part of the import. | Import into a staging batch, validate, replace approved operational data, then run an idempotent journal backfill tied to source IDs/import batch. | Import twice with identical data and prove no duplicate transactions or journals. | Critical |
| Historical accounting | Journal data starts on 2026-06-16 while operations start in January. | Complete an idempotent historical backfill for all 2026 operations and opening balances, followed by source-to-journal reconciliation. | Every eligible source has the expected active journal entries exactly once. | Critical |

## Values and Calculations

| Area | Current scenario | New suggested scenario | Required regression evidence | Priority |
|---|---|---|---|---|
| Production day | A date counts when Shift 1 + Shift 2 is greater than zero. Current copied-live data has 253 active days and 1,031,055 kg. | Keep this definition and display the unit clearly as kg/day and tons/day. | Zero shift, one shift, two shifts, correction, and leap-day tests. | High |
| Daily production average | Total production kg divided by active production days. Current result is 4,075.316 kg/day. | Keep the formula; allow calendar-day average only as a separately named optional metric. | Independent monthly and period recalculation tests. | High |
| Production import | Positive days are updated, but a later blank/zero workbook cell does not remove an older stored production day. | Treat the uploaded year as a replaceable version after preview, or explicitly ask whether to replace or merge. Record import batch provenance. | Reimport a corrected workbook with a removed day and confirm the selected mode. | Critical |
| Waste | Waste percentage is waste kg divided by total recycle-out kg. Total out equals recycled + waste + non-recycled. | Keep this as `Waste % of Output`. Add a separately defined process-loss metric only if recycle-in can be matched to processed batches. | Component equality and denominator tests. | High |
| Waste classification | Non-positive imported recycle-out rates are classified as waste. A 235 kg negative adjustment is currently counted as waste. | Add explicit transaction/adjustment types. Purchases, returns, discounts, and corrections must not be inferred as waste from rate alone. | Negative rate, zero-rate true waste, return, and correction tests. | Critical |
| Recycle revenue | Reports sum stored `total_amount`. Manual entries calculate recycled kg x rate; imported totals may override multiplication. | Define one authoritative rule. Permit total overrides only with an adjustment reason and show the variance. | Rate/total mismatch test and approval/audit test. | High |
| Stock revenue | Stored sales value is used; form supports weight + rate or weight + total. | Keep both modes and store input mode, calculated counterpart, rounding, and any override reason. | Bidirectional calculation and precision tests. | High |
| Stock COGS | Moving weighted-average cost by transaction date. Current remaining stock is excluded from COGS: 23,701.900 kg with estimated value 8,497.563 JOD. | Keep moving weighted average, add an inventory valuation report and negative-stock controls. Define treatment by material and for missing material. | Purchase-sale sequence, same date, return, negative stock, missing material, and closing valuation tests. | Critical |
| Stock row profit | Headline dynamically recalculates COGS and conversion, but detail/export uses stored `net_profit`. Current row total is 99,664.140 JOD versus headline 20,101.842 JOD. | Calculate report rows from the same cost-allocation engine as the headline. Show material COGS, conversion allocation, gross profit, and margin per row. | Detail rows must sum exactly to the report summary within rounding tolerance. | Critical |
| Conversion allocation | Monthly expenses / production tons x stock tons sold. | Keep only as a clearly named management allocation. Decide whether allocation follows production month, sale month, or inventory layer. | Cross-month production/sale tests and allocation reconciliation. | Critical |
| Factory P&L | Recycle revenue + stock revenue - stock material COGS - monthly expenses. Conversion is not subtracted again. | Keep the no-double-count formula, but use one approved expense source and completed periods by default. | Revenue, COGS, expense, and final P&L reconciliation by month and year. | Critical |
| Partial month P&L | A partial date range subtracts the full monthly expense row. October through the 17th therefore includes all October expenses. | Default financial P&L to completed months. For month-to-date, use explicitly approved accrual/proration logic and label it MTD estimate. | Mid-month, completed-month, custom partial range, and no-activity tests. | Critical |
| Future months | Monthly graph/report can show future rent-only losses. | Do not show future months unless forecast mode is explicitly selected. | Future expense rows must not appear in actual results. | High |
| Expense source | Operational P&L uses legacy monthly expenses. Accounting expense vouchers are separate. | Select one authoritative source. During migration, reconcile legacy monthly totals to vouchers and prevent double counting. | Parallel-source and cutover-date tests. | Critical |
| Trial Balance | Entries balance internally, but historical source coverage is incomplete. | Add source coverage reconciliation, backfill all history, and block “complete” reporting when unreconciled. | Trial balance difference zero and source coverage 100%. | Critical |

## Pages, Filters, and Results

| Area | Current scenario | New suggested scenario | Required regression evidence | Priority |
|---|---|---|---|---|
| Page coverage | 71 GET pages exist. There is no single smoke suite that visits every page for each permitted role. | Add route-driven page smoke tests using seeded records for parameterized routes and both languages. | Every GET page returns the expected 200/302/403 for admin, accountant, data entry, viewer, inactive user, and guest. | Critical |
| Forms | Validation tests cover core operations, but not every blank, boundary, precision, stale-edit, and duplicate-submit case. | Create a data-driven input matrix per form. Add idempotency or duplicate warnings for sensitive submissions. | Valid, invalid, minimum, maximum, decimal precision, duplicate, and concurrency tests. | High |
| Filters | Core operational lists have date/customer/supplier/material/weight/amount filters and server-side sorting. Other pages use inconsistent or no filtering. | Build reusable filter objects and a common filter bar. Preserve filters across pagination, export, edit return, and language switch. | Query-string persistence and total-dataset sort/filter tests. | High |
| Customer list | Loads all customers and sorts in the browser; balances are calculated one customer at a time. | Server-side sorting and pagination using aggregate balance queries. | Sort applies to the complete dataset, not only the visible page. Query-count/performance test. | High |
| Supplier list | Paginated by name; shows phone/location/opening balance, not current payable. No column sorting. | Show current payable, purchased kg, status, and last activity; add whole-dataset server-side sorting. | Balance reconciliation and full-dataset sorting tests. | High |
| Supplier payment list | No filters, sorting, export, delete, or quick-repeat entry. | Match operational transaction lists and customer payment workflow. | Feature parity test matrix. | High |
| Exports | Several reports support customizable columns, but not all pages and not all totals reconcile to filtered data. | Standardize export metadata, selected columns, numeric values, totals, filters, language, and filename period. | Export rows/totals must equal the filtered screen dataset. | High |
| Arabic/English | Core translation and RTL exist, but new messages and future jobs need explicit coverage. | Add translation-key completeness and RTL smoke checks for every page. | No missing keys/placeholders; page titles and generated exports localized. | Medium |
| Error handling | Imports now skip invalid transaction rows, but general workflows rely on ordinary validation/errors. | Add friendly domain errors, correlation IDs in logs, and protected production error pages with debug disabled. | Exception-path tests and no sensitive output in production mode. | High |

## Proposed Regression Suites

1. `SecurityRoleMatrixTest`: every route and role, inactive sessions, CSRF, login/logout, sensitive action restrictions.
2. `AllPagesSmokeTest`: all 71 GET pages, seeded route parameters, English and Arabic.
3. `OperationalWorkflowTest`: create/edit/delete/import for recycle-in, recycle-out, customer payments, purchases, sales, supplier payments.
4. `CounterpartyLedgerReconciliationTest`: customer AR/kg and supplier AP from opening through closing balance.
5. `CalculationReconciliationTest`: production, waste, revenue, COGS, inventory, stock profit, factory P&L, partial/completed periods.
6. `ChequeLifecycleTest`: upcoming/due/overdue and manual settlement with accounting entries.
7. `ImportIdempotencyTest`: preview, invalid rows, rollback, replacement, backup, and journal backfill.
8. `FilterSortExportContractTest`: full-dataset sorting, pagination persistence, filtered exports, customizable columns.
9. `AccountingSourceCoverageTest`: every source transaction maps to the expected active journal entries exactly once.
10. Browser checks: desktop/mobile, Arabic RTL, searchable dropdowns, pagination controls, print/PDF layouts, and no overlapping content.

## Implementation Milestones

### Milestone 1: Safety Net

- Add central permission matrix and active-user middleware.
- Add all-page smoke and role-matrix tests.
- Set application timezone and production-safe debug defaults.
- Add dependency audits to CI.

### Milestone 2: Calculation Integrity

- Fix completed-period and future-month behavior.
- Reconcile stock detail profit to summary.
- Add explicit adjustment types and calculation warnings.
- Make production import replacement behavior explicit and testable.

### Milestone 3: Supplier Parity

- Upgrade supplier list, supplier payment list/form, filters, sorting, export, delete/reversal, and quick repeat entry.
- Add supplier AP dashboard and aging-ready ledger structure.

### Milestone 4: Cheque Automation

- Add derived upcoming/due/overdue states.
- Add daily alert job and scheduler configuration.
- Keep collected/cleared/bounced/cancelled changes manual and audited.

### Milestone 5: Accounting Completion

- Reconcile expense sources.
- Backfill historical journals idempotently.
- Add General Ledger, accounting P&L, Balance Sheet, AR/AP aging, and cash/bank reports.

### Milestone 6: UX and Release Qualification

- Standardize filters, sorting, exports, language behavior, and transaction entry UX.
- Run full automated, browser, import, backup/restore, and deployment regression.
- Produce a signed release checklist and rollback point before live deployment.
