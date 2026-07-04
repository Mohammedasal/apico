# APICO Factory Management System

## Phase 1 Handoff and Phase 2 Context

Last updated: 2026-07-04  
Repository: `git@github.com:Mohammedasal/apico.git`  
Phase 1 commit: `fc315d9`  
Production server: Contabo VPS at `5.189.156.240`  
Production application path: `/var/www/apico`

## 1. Project Purpose

APICO is a bilingual English/Arabic factory ledger and operations system for a plastic recycling business. It replaces several Excel workbooks used for:

- Customer recycling transactions.
- Stock purchases and stock sales.
- Customer and supplier payments.
- Customer and supplier statements.
- Daily production and monthly factory expenses.
- Incoming and outgoing cheque monitoring.
- Stock profitability and factory profit and loss reporting.
- Operational dashboards and management statistics.

The application is designed for JOD financial values and kilogram/ton production quantities. Financial and weight values are generally stored to three decimal places.

## 2. Technology

- PHP `^8.3` (local environment currently uses PHP 8.4).
- Laravel `^13.8`.
- Blade server-rendered UI.
- Vanilla JavaScript for interactive form behavior.
- Vite and Tailwind packages are installed, but most application styling is custom CSS in the main Blade layout.
- SQLite is supported locally; the production server may use the configured Laravel database connection.
- XLSX import uses custom workbook parsing services.
- XLSX export uses a custom `ZipArchive`-based writer.
- PHPUnit feature tests.
- Nginx and PHP-FPM on the Contabo server.

## 3. Languages and Layout

- English and Arabic are supported.
- Locale switching is available from the top bar.
- Arabic uses RTL page direction.
- Arabic translations are stored in `resources/lang/ar.json`.
- All Phase 2 user-facing text must be added in both languages.

## 4. Roles and Permissions

### Admin

- Full access.
- Can create and edit operational transactions.
- Can upload sales and production workbooks.
- Can view production, P&L, stock profit, monthly reports, and cheques.
- Can manage users and settings.

### Data Entry

- Can create and edit operational transactions.
- Can work with incoming and outgoing cheques.
- Cannot access P&L, stock-profit reports, system settings, or user management.

### Viewer

- Read-only access to operational lists, customers, suppliers, statements, and non-P&L review information.
- Cannot create or edit operational data.
- Cannot see or directly access:
  - Production/P&L.
  - Monthly P&L reports.
  - Stock-profit reports.
  - Dashboard profit figures.
  - Per-stock-sale profit.
  - Incoming cheques.
  - Outgoing cheques.

Permission enforcement exists in both routes and Blade views. Relevant methods are in `App\Models\User`:

- `canWriteOperationalData()`
- `canViewFinancialReports()`
- `canViewProfitAndLoss()`
- `canManageSystem()`

## 5. Main Modules

### Dashboard

The dashboard provides:

- Shortcuts to add operational transactions.
- Recycle-in and recycle-out totals.
- Production, waste, and waste percentage.
- Payments and stock information.
- Remaining receivables/debts.
- Daily production averages.
- Monthly performance graph.
- Top five customers by remaining KG balance.
- Top five customers by remaining JOD balance.
- Factory and stock profit widgets for admin users only.

### Customers

Customer records include:

- Name.
- Phone.
- Location.
- Opening JOD balance.
- Opening KG balance.
- Active/inactive status.
- Notes.

The customer list supports search and full-list column sorting. It displays remaining JOD and KG balances.

Customer statements support:

- Date range.
- Search.
- Opening balances.
- Running JOD balance.
- Running KG balance.
- Recycle-in table.
- Recycle-out table.
- Payment table.
- Stock-sales table.
- Combined ledger.
- Ledger-only printing.
- Customizable Excel columns.
- Customer name, statement period, and generation date in exports.

### Suppliers

Supplier records include:

- Name.
- Phone.
- Location.
- Opening JOD balance.
- Active/inactive status.
- Notes.

Supplier statements include purchases, payments, running balance, search, date filters, and customizable Excel export.

### Materials

Materials are optional on all operational transactions. A material contains:

- Name.
- Optional type.
- Default processing cost field.
- Active/inactive state.

Material type is optional.

### Recycle In

Fields:

- Date.
- Customer.
- Optional material.
- Weight KG.
- Notes.

Recycle-in has no rate and no financial amount. Its rate and total amount are saved as zero.

### Recycle Out

Fields:

- Date.
- Customer.
- Optional material.
- Recycled-out KG.
- Waste KG.
- Non-recycled KG.
- Rate per recycled KG.
- Notes.

Rules:

- Total output weight = recycled-out + waste + non-recycled.
- Financial amount = recycled-out KG x rate/KG.
- Waste and non-recycled quantities have zero financial rate.
- At least one of the three weight fields must be greater than zero.
- A zero-priced recycled quantity requires a note.

### Customer Payments

Fields:

- Date.
- Customer.
- Amount.
- Type: cash, cheque, bank transfer, or exchange of goods.
- Method.
- Reference.
- Bank.
- Cheque due date.
- Cheque status.
- Notes.

Negative payments/adjustments require a note.

### Stock Purchases

Fields:

- Date.
- Supplier.
- Optional material.
- Weight KG.
- Cost/KG.
- Notes.

Formula:

`Total purchase cost = weight KG x cost/KG`

A zero purchase cost requires a note.

### Stock Sales

Fields:

- Date.
- Customer.
- Optional material.
- Weight KG.
- Selling rate/KG.
- Total price.
- Admin stock override.
- Notes.

The pricing form works in both directions:

- Enter weight + rate/KG to calculate total price.
- Enter weight + total price to calculate rate/KG.

The last edited price field is treated as authoritative. An entered total is saved exactly. The derived selling rate is stored with six decimal places.

Purchase cost and granulation cost are not entered manually. The system calculates the material cost using weighted-average inventory cost as of the sale date.

Unsold stock remains inventory and is not charged as cost of goods sold.

After any new operational transaction is saved, the user stays on the create page. The last added transaction is shown above the form for reference, allowing rapid consecutive entry.

## 6. Inventory and Profit Calculations

### Remaining Stock

`Remaining stock KG = total purchased KG - total sold KG`

The calculation can be performed for all stock or for one material.

### Weighted-Average Stock Cost

Purchases and prior sales are processed chronologically.

For each purchase:

`inventory value += purchase total cost`

For each sale:

`sale material COGS = sold KG x current weighted-average inventory cost`

`inventory value -= sale material COGS`

`inventory KG -= sold KG`

The weighted cost used by a new stock sale is the average cost of inventory remaining on hand at the sale date. When editing a sale, that sale is excluded from the cost reconstruction.

Example:

- Purchase 60,000 KG for 18,000 JOD.
- Weighted material cost = 0.300 JOD/KG.
- Sell 1,000 KG.
- Material COGS = 300 JOD.
- Remaining inventory = 59,000 KG.

### Stock Profit

The stock-profit summary uses:

`Stock profit = stock sales revenue - material COGS - conversion cost`

Conversion cost is calculated monthly:

`Actual production cost/ton = monthly operating expenses / monthly production tons`

`Conversion cost for sold stock = sold tons x actual production cost/ton`

### Monthly Operating Expenses

Monthly expenses contain:

- Electricity bill.
- Total salaries.
- Rent.
- Miscellaneous.
- Social security.
- Other expenses.

`Total expenses = sum of all six expense categories`

### Production

Daily production contains:

- Date.
- Shift one KG.
- Shift two KG.
- Notes.

`Daily total KG = shift one KG + shift two KG`

The production page calculates monthly total production, average daily production, cost/ton, daily cost, workbook-based income, actual transaction income, and P&L.

### Actual Factory P&L

The current actual P&L formula is:

`Actual P&L = recycle-out income + stock-sales revenue - stock material COGS - operating expenses`

Reports intended as cumulative management figures should use completed months rather than presenting the current incomplete month as YTD.

## 7. Customer Ledger Formulas

### JOD Balance

`Customer JOD balance = opening balance + recycle-out charges + stock-sales value - customer payments`

### KG Balance

`Customer KG balance = opening KG balance + recycle-in KG - total recycle-out KG`

Recycle-out KG includes recycled, waste, and non-recycled quantities.

Statements calculate opening balances before the selected start date, then apply transactions in chronological order to produce running balances.

Payment rows carry the JOD balance that remained immediately after that payment.

## 8. Supplier Ledger Formula

`Supplier balance = opening balance + stock purchases - supplier payments`

Supplier cheque payments appear in outgoing cheque monitoring.

## 9. Cheques

### Incoming Cheques

Incoming cheques are generated from customer payments whose payment type is `cheque`. They include due date and status tracking.

### Outgoing Cheques

Outgoing cheque monitoring includes:

- Manually entered outgoing cheques.
- Supplier payments made by cheque.
- Due dates.
- Amounts.
- Bank/reference data.
- Status tracking.

Viewers cannot access cheque pages.

## 10. Excel Imports

### Sales Workbook Upload

Admin-only upload from the dashboard.

Workbook interpretation:

- First two sheets: dashboards, ignored.
- Third sheet: ignored.
- Fourth/purchase sheet: stock purchases.
- Remaining sheets: customer sheets containing recycle-in, recycle-out, payments, and stock sales.
- Supplier names are read from the purchase table's `supplier name` column.
- Customer payment notes may be parsed for Arabic cheque-date text.

Important: sales workbook import is destructive by design.

Before import, it deletes existing:

- Stock sales.
- Stock purchases.
- Customer payments.
- Recycle-out rows.
- Recycle-in rows.
- Imported customers other than `Sample Customer`.
- Suppliers without payment history.

The import runs inside a database transaction. For SQLite, a timestamped database backup is created in `storage/app/backups` before deletion. Uploaded files are retained under `storage/app/imports`.

The importer reports transactions with missing dates or dates outside the expected year, including customer and transaction details.

### Production Workbook Upload

Admin-only upload from the Production page.

It imports:

- Daily production.
- Monthly expenses/costs.
- Outgoing cheque information from the workbook.

## 11. Exports and Printing

- Customer statements export to XLSX.
- Supplier statements export to XLSX.
- Monthly reports export to XLSX.
- Stock-profit reports export to XLSX.
- Alert reports export to XLSX.
- Export columns are user-selectable.
- Statement exports include the account name, date period, and generation date.
- Customer statements include print CSS and a ledger-only print action.

The custom XLSX writer is `App\Services\SimpleXlsxExporter` and requires PHP `ZipArchive`.

## 12. Search and Filters

- Customer and supplier lists support name/details search.
- Recycle-in and recycle-out lists support customer, date range, and weight range filters.
- Statements support date ranges and text search.
- Reports support relevant date, customer, material, and text filters.
- Customer sorting is performed on the full loaded customer list, not only the visible page.

## 13. Authentication and Audit Information

- Users log in with email and password.
- Inactive users cannot log in.
- Operational records store `created_by`, `updated_by`, `created_at`, and `updated_at`.
- UI lists display creator/editor and timestamps.
- An `audit_logs` table and model exist, but automatic before/after audit event recording is not currently wired globally. This is a Phase 2 opportunity.

## 14. Main Code Locations

- Routes: `routes/web.php`
- Operational CRUD: `app/Http/Controllers/OperationController.php`
- Financial calculations: `app/Services/ApicoCalculator.php`
- Sales workbook import: `app/Services/ApicoExcelImporter.php`
- Production workbook import: `app/Services/ProductionExcelImporter.php`
- XLSX export: `app/Services/SimpleXlsxExporter.php`
- Dashboard: `app/Http/Controllers/DashboardController.php`
- Production/P&L: `app/Http/Controllers/ProductionController.php`
- Reports: `app/Http/Controllers/ReportController.php`
- Main UI and CSS: `resources/views/layouts/app.blade.php`
- Translation file: `resources/lang/ar.json`
- Database schema: `database/migrations`
- Tests: `tests/Feature`

## 15. Database Tables

Business tables:

- `users`
- `customers`
- `suppliers`
- `materials`
- `recycle_ins`
- `recycle_outs`
- `payments`
- `stock_purchases`
- `stock_sales`
- `supplier_payments`
- `production_days`
- `monthly_expenses`
- `cheques_out`
- `settings`
- `audit_logs`

Laravel infrastructure tables:

- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `sessions` if database sessions are enabled.

## 16. Important Validation and Safety Rules

- Materials are optional everywhere.
- Transaction dates are required.
- Customer/supplier relationships are validated.
- Weights must be positive where applicable.
- Stock sales cannot exceed available stock unless stock override is enabled.
- Zero-value exceptional transactions require notes.
- Negative payment adjustments require notes.
- Sales import is restricted to admin and runs in a transaction.
- P&L and cheque routes are protected server-side, not only hidden in navigation.

## 17. Testing Status

At Phase 1 commit `fc315d9`:

- Full test suite: 28 tests passed.
- Assertions: 94 passed.

Coverage includes:

- Customer and supplier statements.
- Recycle calculations.
- Optional materials.
- Decimal rate accuracy.
- Stock availability checks.
- Weighted-average inventory cost.
- Two-way stock-sale pricing.
- Stock and factory P&L formulas.
- Cheque visibility.
- Viewer authorization.
- Consecutive transaction-entry workflow.

Before testing locally, clear optimized production caches:

```bash
php artisan optimize:clear
php artisan test
```

## 18. Local Development

```bash
cd outputs/apico-laravel
composer install
npm install
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Local URL:

`http://127.0.0.1:8000`

## 19. Production Deployment

```bash
ssh root@5.189.156.240
cd /var/www/apico
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

Never overwrite the production `.env` or database during deployment. Back up the production database before large imports or schema-heavy Phase 2 releases.

## 20. Known Phase 1 Constraints

- The UI is server-rendered and uses inline/custom CSS rather than a full component design system.
- Audit-log persistence is not automatically connected to model events.
- Some legacy columns remain for compatibility, including stock-sale granulation cost and material processing cost, although manual stock-sale granulation cost entry was removed.
- The default granulation-cost setting remains in the database but is no longer part of the stock-sale entry workflow.
- Sales workbook import deliberately replaces operational data and needs strong confirmation/backup UX in a future phase.
- No public API or mobile application exists.
- No automated deployment pipeline exists; deployment is currently Git pull plus Laravel maintenance commands.
- Production backups, scheduled jobs, monitoring, and off-server backup retention should be formalized.

## 21. Recommended Phase 2 Priorities

1. Add complete audit history with before/after values and restore visibility.
2. Add a non-destructive workbook preview, validation report, confirmation step, and rollback workflow.
3. Formalize inventory accounting with stock lots or a persistent inventory ledger if material-level traceability is required.
4. Add bank accounts and cheque cash-flow forecasting.
5. Add receivable/payable aging reports.
6. Add expense categories, budgets, and approval workflows.
7. Add document attachments for payments, purchases, sales, and cheques.
8. Add scheduled database backups and an admin restore/download interface.
9. Add notifications for due cheques, high balances, and low stock.
10. Add a deployment pipeline with automated tests, migrations, backups, and rollback.
11. Improve responsive/mobile transaction entry.
12. Expand automated tests around imports, permissions, Arabic rendering, and production deployment configuration.

## 22. Ready-to-Use Phase 2 Prompt

Use this handoff file as the authoritative description of Phase 1.

```text
You are continuing development of the APICO Factory Management System.

Read APICO_PHASE_1_HANDOFF.md completely before making changes. Inspect the existing Laravel code and preserve its current calculations, bilingual English/Arabic behavior, RTL support, roles, statement balances, weighted-average inventory costing, imports, exports, and production deployment setup.

Phase 2 objective:
[DESCRIBE THE PHASE 2 OBJECTIVE HERE]

Required features:
[LIST FEATURES HERE]

Business rules:
[LIST NEW OR CHANGED RULES HERE]

Permissions:
[STATE WHICH ROLES CAN VIEW OR CHANGE EACH FEATURE]

Reports/exports:
[STATE REQUIRED FILTERS, COLUMNS, EXCEL/PDF OUTPUTS, AND CALCULATIONS]

Acceptance criteria:
[LIST CONCRETE EXAMPLES AND EXPECTED NUMBERS]

Implementation requirements:
- Follow existing Laravel patterns and keep changes scoped.
- Add English and Arabic translations for every user-facing string.
- Verify Arabic RTL layout.
- Enforce permissions in routes/controllers as well as the UI.
- Use database transactions for financial or destructive operations.
- Preserve exact JOD/KG decimal behavior.
- Add migrations without modifying old production migrations.
- Add focused feature tests and run the full test suite.
- Document any calculation or compatibility decision.
- Provide safe Contabo deployment commands, including backup and rollback steps.
```

