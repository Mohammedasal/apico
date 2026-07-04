# APICO Phase 2: Accounting Layer over the Operational System

## 1. Purpose

Build a full accounting layer for the APICO Factory Management System without replacing or breaking the existing operational workflow.

The current APICO system must remain the source of truth for factory operations:

- Customers.
- Suppliers.
- Recycle in.
- Recycle out.
- Customer payments.
- Supplier payments.
- Stock purchases.
- Stock sales.
- Inventory calculations.
- Cheques.
- Production days.
- Monthly factory reports.

Phase 2 must add a proper accounting engine on top of the existing system. Users must continue entering each business transaction only once. The accounting layer must automatically generate debit and credit journal entries from the existing operational records.

The user must not enter the same payment, purchase, sale, customer charge, supplier payment, or cheque twice.

---

## 2. Core Principle

### One business action = one operational record + automatic accounting entry

Example:

```text
User adds customer payment once in the existing Customer Payment screen.
The customer ledger updates as it already does.
The accounting layer automatically posts:
Debit: Cash / Bank / Cheques Receivable
Credit: Accounts Receivable - Customer
```

Manual journal entries are allowed only for accounting-only transactions that do not already exist in the operational layer.

Examples of accounting-only transactions:

- Owner capital.
- Owner withdrawals.
- Bank adjustments.
- Depreciation.
- Salary accruals.
- Rent accruals.
- Tax adjustments.
- Social security accruals.
- Opening balances.
- Accountant-approved corrections.

Manual journal entries must not be used to duplicate existing APICO operational transactions.

---

## 3. Phase 1 Context to Preserve

Before making changes, read `APICO_PHASE_1_HANDOFF.md` completely.

Preserve all existing Phase 1 behavior, including:

- Laravel and Blade structure.
- English and Arabic bilingual behavior.
- RTL Arabic layout.
- Existing roles and permissions.
- Customer statement calculations.
- Supplier statement calculations.
- Recycle-in and recycle-out logic.
- Stock purchase and stock sale logic.
- Weighted-average inventory cost.
- Stock availability checks.
- Existing cheque monitoring.
- Current production and P&L reports.
- XLSX imports and exports.
- Existing tests.
- Production deployment flow.

Do not remove or rewrite Phase 1 modules unless the change is required for safe accounting integration.

---

## 4. Main Objective

Add a formal double-entry accounting layer that supports:

- Chart of Accounts.
- Journal entries.
- Automatic posting from operational records.
- Cash and bank tracking.
- Customer receivable accounting.
- Supplier payable accounting.
- Inventory accounting.
- Salaries.
- Expenses.
- Maintenance.
- Rent.
- Utilities.
- Purchases.
- Cheques receivable and payable.
- Trial balance.
- General ledger.
- Profit and loss statement.
- Balance sheet.
- Receivable aging.
- Payable aging.
- Audit trail.
- Accounting period locks.

---

## 5. Architecture

Use three clear layers.

### 5.1 Existing Operational Layer

This layer already exists and must continue to work.

Examples:

- `customers`
- `suppliers`
- `recycle_ins`
- `recycle_outs`
- `payments`
- `supplier_payments`
- `stock_purchases`
- `stock_sales`
- `production_days`
- `monthly_expenses`
- `cheques_out`

### 5.2 New Accounting Layer

This layer stores formal accounting data.

Examples:

- `chart_of_accounts`
- `journal_entries`
- `journal_entry_lines`
- `account_mappings`
- `bank_accounts`
- `cash_accounts`
- `expense_categories`
- `expense_vouchers`
- `employees`
- `payroll_runs`
- `payroll_lines`
- `accounting_periods`

### 5.3 Posting Bridge

Create a posting service that connects the operational layer to the accounting layer.

Suggested class:

```php
App\Services\AccountingPostingService
```

The service must be responsible for creating, updating, reversing, and reposting journal entries for operational transactions.

---

## 6. Chart of Accounts

Create a flexible hierarchical Chart of Accounts.

### 6.1 Account Types

The system must support these main account types:

```text
Asset
Liability
Equity
Revenue
Cost of Goods Sold
Expense
```

Receivables must be under Assets.

Payables must be under Liabilities.

Example structure:

```text
1. Assets
   1.1 Cash
   1.2 Bank Accounts
   1.3 Accounts Receivable
   1.4 Cheques Receivable
   1.5 Inventory
   1.6 Fixed Assets

2. Liabilities
   2.1 Accounts Payable
   2.2 Cheques Payable
   2.3 Salaries Payable
   2.4 Social Security Payable
   2.5 Taxes Payable
   2.6 Loans

3. Equity
   3.1 Owner Capital
   3.2 Owner Withdrawals
   3.3 Retained Earnings
   3.4 Current Year Profit or Loss

4. Revenue
   4.1 Recycling Service Income
   4.2 Stock Sales Income
   4.3 Other Income

5. Cost of Goods Sold
   5.1 Stock Material Cost
   5.2 Inventory Adjustments

6. Expenses
   6.1 Salaries Expense
   6.2 Rent Expense
   6.3 Electricity Expense
   6.4 Maintenance Expense
   6.5 Fuel Expense
   6.6 Transportation Expense
   6.7 Office Expense
   6.8 Miscellaneous Expense
```

### 6.2 Chart of Accounts Fields

Create `chart_of_accounts` table.

Required fields:

```text
id
parent_id nullable
code unique
name_en
name_ar
type enum: asset, liability, equity, revenue, cogs, expense
normal_balance enum: debit, credit
is_posting boolean
is_system boolean
is_active boolean
sort_order nullable
created_by nullable
updated_by nullable
created_at
updated_at
```

Rules:

- Parent accounts are for grouping only.
- Posting accounts can receive journal lines.
- Journal lines must not be posted to non-posting parent accounts.
- System accounts cannot be deleted if they are used in mappings or journal entries.
- Account names must support English and Arabic.
- Account list must support search, filtering by type, and active/inactive status.

---

## 7. Account Mappings

Create a mapping layer so operational records know which accounting accounts to use.

Suggested table: `account_mappings`

Fields:

```text
id
mapping_key
account_id
entity_type nullable
entity_id nullable
is_active
created_by nullable
updated_by nullable
created_at
updated_at
```

Examples of `mapping_key`:

```text
cash_default
bank_default
accounts_receivable_control
accounts_payable_control
cheques_receivable
cheques_payable
inventory_default
stock_sales_income
recycling_service_income
stock_material_cogs
salary_expense
salary_payable
rent_expense
electricity_expense
maintenance_expense
social_security_expense
social_security_payable
miscellaneous_expense
owner_capital
owner_withdrawals
retained_earnings
```

Entity-specific mapping examples:

```text
entity_type: customer
entity_id: 15
account_id: Accounts Receivable - Customer ABC

entity_type: supplier
entity_id: 8
account_id: Accounts Payable - Supplier XYZ

entity_type: bank_account
entity_id: 2
account_id: Arab Bank Current Account

entity_type: material
entity_id: 3
account_id: Inventory - PET Plastic
```

### 7.1 Control Account Option

The system may use either:

1. One control account for all customers and suppliers, with customer/supplier IDs stored on journal lines.
2. Separate sub-accounts for each customer and supplier.

Recommended for APICO:

Use control accounts first, then store `customer_id` and `supplier_id` on journal lines.

Example:

```text
Debit: Accounts Receivable Control
customer_id: 15
```

This avoids creating hundreds of COA accounts while still allowing customer-level accounting reports.

---

## 8. Journal Entries

Create `journal_entries` table.

Required fields:

```text
id
entry_no unique
entry_date
memo_en nullable
memo_ar nullable
source_module nullable
source_type nullable
source_id nullable
status enum: draft, posted, reversed, void
is_auto boolean
posted_at nullable
posted_by nullable
reversed_at nullable
reversed_by nullable
reversal_of_journal_entry_id nullable
created_by nullable
updated_by nullable
created_at
updated_at
```

Create `journal_entry_lines` table.

Required fields:

```text
id
journal_entry_id
account_id
description_en nullable
description_ar nullable
debit decimal(15,3) default 0
credit decimal(15,3) default 0
customer_id nullable
supplier_id nullable
material_id nullable
employee_id nullable
bank_account_id nullable
cash_account_id nullable
cheque_id nullable
cost_center_id nullable
created_at
updated_at
```

Rules:

- Every posted journal entry must balance.
- Sum of debit lines must equal sum of credit lines.
- Debit and credit values must be stored to three decimal places for JOD consistency.
- A line cannot have both debit and credit greater than zero.
- A line cannot have both debit and credit equal to zero.
- Journal entries created from operational records should be posted automatically.
- Manual journal entries may support draft and posted status.
- Posted entries in locked periods cannot be edited directly.
- Changes in locked periods must be corrected using reversal and new entry.

---

## 9. Idempotency and Source Tracking

Automatic accounting entries must not duplicate.

Add a unique constraint or safe lookup logic for:

```text
source_module
source_type
source_id
posting_type
```

`posting_type` may be required because some records create more than one accounting entry.

Example stock sale posting types:

```text
stock_sale_revenue
stock_sale_cogs
```

If an operational transaction is saved again, the posting service must update, reverse, or repost the existing journal entry instead of creating duplicates.

Required behavior:

```text
Create operational record -> create journal entry
Edit operational record -> reverse and repost or update safely
Delete/void operational record -> reverse journal entry
Restore operational record -> repost journal entry
```

Prefer reversal and reposting for posted accounting entries because it keeps the audit trail clean.

---

## 10. Automatic Posting Rules

### 10.1 Recycle In

Current Phase 1 rule: recycle-in has no rate and no financial amount.

Accounting posting:

```text
No journal entry.
```

Only KG customer balance changes in the operational ledger.

### 10.2 Recycle Out

When recycle-out has a financial amount:

```text
Debit: Accounts Receivable Control
Credit: Recycling Service Income
```

Journal line dimensions:

```text
customer_id = recycle_out.customer_id
material_id = recycle_out.material_id when available
source_type = recycle_out
source_id = recycle_out.id
```

Amount:

```text
recycled_out_kg x rate_per_recycled_kg
```

Waste and non-recycled KG should not generate revenue unless Phase 1 already calculates a financial amount for them.

### 10.3 Customer Payment

When a customer payment is added:

For cash payment:

```text
Debit: Cash
Credit: Accounts Receivable Control
```

For bank transfer:

```text
Debit: Selected Bank Account
Credit: Accounts Receivable Control
```

For cheque payment:

```text
Debit: Cheques Receivable
Credit: Accounts Receivable Control
```

For exchange of goods:

```text
Debit: Mapped Exchange / Inventory / Clearing Account
Credit: Accounts Receivable Control
```

Journal line dimensions:

```text
customer_id = payment.customer_id
```

Negative customer payments or adjustments must reverse the debit/credit direction and require notes, preserving the existing Phase 1 rule.

### 10.4 Supplier Payment

When a supplier payment is added:

For cash payment:

```text
Debit: Accounts Payable Control
Credit: Cash
```

For bank transfer:

```text
Debit: Accounts Payable Control
Credit: Selected Bank Account
```

For cheque payment:

```text
Debit: Accounts Payable Control
Credit: Cheques Payable
```

Journal line dimensions:

```text
supplier_id = supplier_payment.supplier_id
```

### 10.5 Stock Purchase

When stock is purchased from a supplier:

```text
Debit: Inventory
Credit: Accounts Payable Control
```

Journal line dimensions:

```text
supplier_id = stock_purchase.supplier_id
material_id = stock_purchase.material_id when available
```

Amount:

```text
weight_kg x cost_per_kg
```

If material-specific inventory accounts are configured, use the material-specific inventory account. Otherwise, use default inventory account.

### 10.6 Stock Sale Revenue

When stock is sold to a customer:

```text
Debit: Accounts Receivable Control
Credit: Stock Sales Income
```

Journal line dimensions:

```text
customer_id = stock_sale.customer_id
material_id = stock_sale.material_id when available
```

Amount:

```text
stock_sale.total_price
```

### 10.7 Stock Sale Material Cost

When stock is sold, post material COGS based on existing APICO weighted-average inventory cost.

```text
Debit: Stock Material COGS
Credit: Inventory
```

Amount:

```text
sold_kg x weighted_average_inventory_cost_as_of_sale_date
```

Important:

Do not ask the user to manually enter material COGS.

The accounting posting must reuse the existing weighted-average inventory logic already used by stock profit calculations.

### 10.8 Conversion Cost and Factory Expenses

APICO Phase 1 calculates stock profit using conversion cost based on monthly operating expenses and production tons.

For Phase 2 accounting, do not double-post conversion cost on each stock sale if the actual expenses are already posted as salaries, rent, electricity, maintenance, and other expenses.

Recommended rule:

```text
General Ledger records actual expenses when they happen.
Stock Profit report may continue using conversion cost as a management costing calculation.
```

Do not debit COGS for conversion cost and also debit salaries/rent/electricity expenses for the same cost unless a formal production costing/WIP module is implemented later.

### 10.9 Expense Voucher

Add a new expense voucher module for accounting expenses that are not already represented by existing operational records.

Examples:

- Rent.
- Maintenance.
- Electricity.
- Fuel.
- Office supplies.
- Miscellaneous expenses.
- Professional fees.

If paid immediately by cash:

```text
Debit: Expense Account
Credit: Cash
```

If paid by bank:

```text
Debit: Expense Account
Credit: Selected Bank Account
```

If unpaid/accrued:

```text
Debit: Expense Account
Credit: Accrued Expenses / Payable Account
```

If paid by cheque:

```text
Debit: Expense Account
Credit: Cheques Payable
```

The user enters the expense once in the expense voucher screen. The accounting journal is generated automatically.

### 10.10 Payroll

Add employee and payroll support.

Payroll accrual:

```text
Debit: Salaries Expense
Credit: Salaries Payable
```

Payroll payment by cash:

```text
Debit: Salaries Payable
Credit: Cash
```

Payroll payment by bank:

```text
Debit: Salaries Payable
Credit: Selected Bank Account
```

Social security employer share, if entered:

```text
Debit: Social Security Expense
Credit: Social Security Payable
```

Employee deductions, if entered:

```text
Debit: Salaries Payable
Credit: Social Security Payable / Other Deduction Payable
```

Payroll must not require the user to create manual journal entries.

### 10.11 Cheques Receivable

When customer pays by cheque:

```text
Debit: Cheques Receivable
Credit: Accounts Receivable Control
```

When cheque is collected/deposited into bank:

```text
Debit: Bank Account
Credit: Cheques Receivable
```

When cheque bounces:

```text
Debit: Accounts Receivable Control
Credit: Cheques Receivable
```

The customer_id must remain attached to the bounced entry.

### 10.12 Cheques Payable

When supplier or expense is paid by cheque:

```text
Debit: Accounts Payable / Expense / Accrued Liability
Credit: Cheques Payable
```

When cheque clears from bank:

```text
Debit: Cheques Payable
Credit: Bank Account
```

When cheque is cancelled:

Reverse the original cheque payable journal entry or post a cancellation entry depending on status and period lock.

---

## 11. Cash and Bank Accounts

Create bank/cash management.

### 11.1 Bank Accounts Table

Suggested table: `bank_accounts`

Fields:

```text
id
name_en
name_ar
bank_name nullable
account_number nullable
currency default JOD
chart_account_id
is_active
opening_balance nullable
opening_balance_date nullable
created_by nullable
updated_by nullable
created_at
updated_at
```

### 11.2 Cash Accounts Table

Suggested table: `cash_accounts`

Fields:

```text
id
name_en
name_ar
chart_account_id
is_active
created_by nullable
updated_by nullable
created_at
updated_at
```

Rules:

- Payment forms should allow selecting cash/bank account where applicable.
- If there is only one default cash or bank account, auto-select it.
- Do not break existing payment forms. Add fields carefully with defaults.
- Bank and cash balances in accounting reports must come from journal entries, not manual totals.

---

## 12. Expenses Module

Add an expense voucher module.

### 12.1 Expense Categories

Suggested table: `expense_categories`

Fields:

```text
id
name_en
name_ar
default_account_id
is_active
created_by nullable
updated_by nullable
created_at
updated_at
```

Example categories:

```text
Rent
Electricity
Maintenance
Fuel
Transportation
Office Expenses
Miscellaneous
Professional Fees
Cleaning
Factory Supplies
```

### 12.2 Expense Vouchers

Suggested table: `expense_vouchers`

Fields:

```text
id
voucher_no unique
expense_date
expense_category_id
amount decimal(15,3)
payment_status enum: paid, unpaid, partially_paid
payment_type enum: cash, bank_transfer, cheque, credit
cash_account_id nullable
bank_account_id nullable
payable_account_id nullable
cheque_due_date nullable
cheque_bank nullable
reference nullable
notes nullable
created_by nullable
updated_by nullable
created_at
updated_at
```

Rules:

- The expense category determines the debit account.
- The payment type determines the credit account.
- Expenses must automatically generate journal entries.
- Attachments should be supported if a document attachment system exists or is added later.
- Expense vouchers must appear in expense reports and accounting reports.

---

## 13. Payroll Module

Add a simple payroll module for factory salaries.

### 13.1 Employees

Suggested table: `employees`

Fields:

```text
id
name_en
name_ar nullable
phone nullable
position nullable
base_salary decimal(15,3) nullable
is_active
notes nullable
created_by nullable
updated_by nullable
created_at
updated_at
```

### 13.2 Payroll Runs

Suggested table: `payroll_runs`

Fields:

```text
id
period_month
period_year
payroll_date
status enum: draft, posted, paid, cancelled
total_gross decimal(15,3)
total_deductions decimal(15,3)
total_net decimal(15,3)
notes nullable
created_by nullable
updated_by nullable
posted_at nullable
posted_by nullable
created_at
updated_at
```

### 13.3 Payroll Lines

Suggested table: `payroll_lines`

Fields:

```text
id
payroll_run_id
employee_id
gross_salary decimal(15,3)
allowances decimal(15,3) default 0
deductions decimal(15,3) default 0
net_salary decimal(15,3)
notes nullable
created_at
updated_at
```

Rules:

- Draft payroll can be edited.
- Posted payroll creates salary accrual journal entry.
- Paid payroll creates payment journal entry.
- Cancelled payroll reverses accounting entries.
- Paid payroll in locked periods cannot be edited.

---

## 14. Existing Monthly Expenses Compatibility

Phase 1 has `monthly_expenses` used for production and P&L calculations.

Do not delete this table in Phase 2.

Recommended behavior:

1. Keep the existing monthly expenses screen and reports working.
2. Add expense vouchers as the new detailed accounting source.
3. Add a setting to decide whether monthly factory expense totals come from:
   - Existing `monthly_expenses`, or
   - Posted accounting expense vouchers grouped by month.
4. Never count the same expense twice.

Suggested setting:

```text
monthly_expense_source = legacy_monthly_expenses | accounting_expense_vouchers
```

Default should be `legacy_monthly_expenses` to preserve Phase 1 behavior.

Admin can switch to `accounting_expense_vouchers` after verifying the accountant workflow.

---

## 15. Accounting Periods and Locks

Create `accounting_periods` table.

Fields:

```text
id
period_year
period_month
start_date
end_date
status enum: open, locked
locked_at nullable
locked_by nullable
created_at
updated_at
```

Rules:

- Admin or Accountant can lock periods.
- Locked periods cannot receive new operational postings unless user has special permission.
- Edits to transactions in locked periods must create reversal entries in an open period.
- Reports must clearly show whether a period is open or locked.

---

## 16. Permissions

Preserve existing roles:

- Admin.
- Data Entry.
- Viewer.

Add optional new role:

```text
Accountant
```

### 16.1 Admin

Can:

- Manage Chart of Accounts.
- Manage account mappings.
- View all accounting reports.
- Create manual journals.
- Post/reverse journals.
- Manage bank/cash accounts.
- Manage expenses and payroll.
- Lock/unlock accounting periods.
- Configure accounting settings.

### 16.2 Accountant

Can:

- View accounting reports.
- Manage expenses.
- Manage payroll.
- Create manual journals.
- Post journals if allowed.
- Review automatic postings.
- Manage bank/cash accounts if allowed.

Cannot:

- Manage users unless explicitly allowed.
- Change system-critical settings unless explicitly allowed.

### 16.3 Data Entry

Can:

- Continue using existing operational screens.
- Add operational transactions based on current Phase 1 permissions.

May optionally:

- Select payment cash/bank/cheque account on forms.

Cannot:

- Manage Chart of Accounts.
- View full accounting reports unless allowed.
- Create manual journals.
- Change account mappings.
- Lock periods.

### 16.4 Viewer

Can:

- Keep existing read-only access.

Cannot:

- View restricted accounting reports unless explicitly allowed.
- Create/edit journals.
- Manage accounts.
- View profit-sensitive reports unless already allowed by Phase 1 permissions.

All permissions must be enforced in routes/controllers and Blade views.

---

## 17. Reports

Add accounting reports.

### 17.1 General Ledger

Filters:

```text
Date from
Date to
Account
Account type
Customer
Supplier
Material
Employee
Source module
Posted only / include drafts
```

Columns:

```text
Date
Entry No.
Account Code
Account Name
Description
Debit
Credit
Running Balance
Source Module
Source Reference
Customer/Supplier/Employee dimension when available
Created By
```

### 17.2 Trial Balance

Filters:

```text
Date from
Date to
Account type
Show zero balances yes/no
```

Columns:

```text
Account Code
Account Name
Opening Debit
Opening Credit
Period Debit
Period Credit
Closing Debit
Closing Credit
```

Rules:

- Total closing debit must equal total closing credit.
- If not balanced, show clear error.

### 17.3 Profit and Loss Statement

Filters:

```text
Date from
Date to
Monthly breakdown yes/no
```

Sections:

```text
Revenue
Cost of Goods Sold
Gross Profit
Expenses
Net Profit / Loss
```

Important:

Accounting P&L must be based on posted journal entries.

Management stock-profit reports may continue using Phase 1 stock-profit formulas.

### 17.4 Balance Sheet

As-of-date report.

Sections:

```text
Assets
Liabilities
Equity
```

Rules:

```text
Assets = Liabilities + Equity
```

If not equal, show difference clearly.

### 17.5 Customer Receivable Aging

Use customer dimensions on journal lines or existing customer ledger.

Buckets:

```text
Current
1-30 days
31-60 days
61-90 days
Over 90 days
```

### 17.6 Supplier Payable Aging

Use supplier dimensions on journal lines or existing supplier ledger.

Buckets:

```text
Current
1-30 days
31-60 days
61-90 days
Over 90 days
```

### 17.7 Cash and Bank Report

Show:

```text
Opening balance
Cash/bank inflows
Cash/bank outflows
Closing balance
Related journal entries
```

### 17.8 Expense Report

Filters:

```text
Date range
Expense category
Payment type
Cash/bank account
Status
```

Columns:

```text
Date
Voucher No.
Category
Amount
Payment Type
Paid/Unpaid
Account
Reference
Notes
```

### 17.9 Payroll Report

Filters:

```text
Month
Year
Employee
Status
```

Columns:

```text
Employee
Gross Salary
Allowances
Deductions
Net Salary
Payment Status
```

### 17.10 Exports

All accounting reports should support XLSX export using the existing exporter style where possible.

Exports must support English and Arabic column labels.

---

## 18. UI Requirements

Add Accounting menu group.

Suggested navigation:

```text
Accounting
  Dashboard
  Chart of Accounts
  Journal Entries
  Cash & Bank Accounts
  Expenses
  Payroll
  Reports
    General Ledger
    Trial Balance
    Profit & Loss
    Balance Sheet
    Receivable Aging
    Payable Aging
    Cash & Bank Report
```

UI rules:

- Match the current APICO layout style.
- Support Arabic translations for all new text.
- Support RTL layout.
- Keep forms simple and fast for factory usage.
- Do not add unnecessary frontend complexity.
- Use server-rendered Blade unless existing app conventions change.

---

## 19. Audit Trail

The handoff mentions an `audit_logs` table exists but automatic recording is not globally wired.

Phase 2 should wire audit logs for accounting-sensitive actions.

Track:

- Chart of Accounts create/update/deactivate.
- Account mapping changes.
- Journal create/post/reverse/void.
- Expense voucher create/update/post/cancel.
- Payroll create/post/pay/cancel.
- Accounting period lock/unlock.
- Bank/cash account create/update/deactivate.

Audit logs should include:

```text
user_id
action
model_type
model_id
before_values JSON
after_values JSON
ip_address nullable
user_agent nullable
created_at
```

---

## 20. Validation Rules

General validation:

- Transaction date is required.
- Amounts must use three decimals.
- Debit/credit entries must balance.
- Accounts must be active and posting-enabled.
- Required mappings must exist before automatic posting.
- Locked periods cannot be modified without reversal workflow.
- Negative values require notes and clear reversal behavior.

Operational posting validation:

- If customer payment type is bank transfer, bank account is required unless default bank is configured.
- If payment type is cash, cash account is required unless default cash is configured.
- If payment type is cheque, cheque due date and cheque account mapping are required.
- If stock sale COGS cannot be calculated, block posting and show a clear error.
- If inventory is insufficient and override is not enabled, preserve existing Phase 1 stock availability validation.

---

## 21. Backfill Existing Operational Records

Add an Artisan command to generate accounting entries for existing operational records after the Chart of Accounts and mappings are configured.

Suggested command:

```bash
php artisan apico:accounting-backfill --from=2026-01-01 --to=2026-05-31
```

Options:

```text
--from
--to
--module=payments|supplier_payments|stock_purchases|stock_sales|recycle_outs|all
--dry-run
--force
```

Rules:

- Dry-run must show expected journal counts and total debit/credit impact.
- Backfill must be idempotent.
- Backfill must not duplicate journal entries.
- Backfill must run inside database transactions.
- Backfill must report skipped records with reasons.
- Backfill must not import trial balance automatically.

Opening balances can be handled manually by the accountant through opening journal entries.

---

## 22. Testing Requirements

Add feature tests for:

### Chart of Accounts

- Admin can create accounts.
- Non-authorized roles cannot manage accounts.
- Cannot post to parent account.
- Cannot delete account with journal history.

### Journal Entries

- Manual balanced journal can be posted.
- Unbalanced journal cannot be posted.
- Posted journal cannot be edited in locked period.
- Reversal journal is created correctly.

### Automatic Posting

- Customer payment creates correct debit/credit.
- Supplier payment creates correct debit/credit.
- Stock purchase creates inventory/AP posting.
- Stock sale creates revenue and material COGS postings.
- Recycle-out creates AR/revenue posting.
- Recycle-in creates no accounting entry.
- Editing an operational record reverses and reposts safely.
- Deleting/voiding an operational record reverses accounting safely.
- Duplicate posting is prevented.

### Expenses

- Cash expense posts expense/cash.
- Bank expense posts expense/bank.
- Credit expense posts expense/payable.
- Cheque expense posts expense/cheques payable.

### Payroll

- Payroll accrual posts salaries expense/salaries payable.
- Payroll payment posts salaries payable/cash or bank.
- Cancelled payroll reverses entries.

### Reports

- Trial balance balances.
- General ledger running balance is correct.
- P&L totals revenue and expenses correctly.
- Balance sheet balances.
- AR/AP reports filter by customer/supplier.

### Permissions

- Viewer cannot access restricted accounting routes.
- Data Entry cannot manage COA or journals.
- Accountant can access accounting modules according to permissions.
- Admin has full access.

Run full test suite:

```bash
php artisan optimize:clear
php artisan test
```

---

## 23. Deployment Requirements

Before deployment:

```bash
php artisan optimize:clear
php artisan test
```

Production deployment must include database backup before migrations.

Suggested production flow:

```bash
ssh root@5.189.156.240
cd /var/www/apico

# Backup database before schema-heavy Phase 2 deployment.
# Use the correct production database backup command depending on the configured DB driver.

git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

Never overwrite production `.env`.

Do not run accounting backfill on production until:

- Chart of Accounts is approved.
- Account mappings are approved.
- Accountant confirms opening balance strategy.
- Dry-run output is reviewed.
- Production database is backed up.

---

## 24. Acceptance Criteria

Phase 2 is accepted when all of the following are true:

1. Existing APICO operational screens still work as before.
2. Users enter customer payments only once.
3. Users enter supplier payments only once.
4. Users enter stock purchases only once.
5. Users enter stock sales only once.
6. Accounting journals are generated automatically from operational records.
7. Duplicate journals are prevented.
8. Edited operational records update accounting safely through reversal and reposting.
9. Chart of Accounts supports hierarchy, English, Arabic, active/inactive, and posting/non-posting accounts.
10. Bank and cash accounts are linked to accounting accounts.
11. Expense vouchers generate correct accounting entries.
12. Payroll generates correct accrual and payment entries.
13. Trial balance report balances from posted journals.
14. General ledger shows transaction source and dimensions.
15. P&L and Balance Sheet are generated from posted journals.
16. Customer and supplier dimensions allow AR/AP reporting.
17. Arabic UI is translated and RTL-safe.
18. Permissions are enforced in routes/controllers and views.
19. Audit logs record accounting-sensitive changes.
20. Full test suite passes.

---

## 25. Ready-to-Use Codex Prompt

Use this prompt when starting the coding work:

```text
You are continuing development of the APICO Factory Management System.

Read APICO_PHASE_1_HANDOFF.md completely before making changes.

Objective:
Build Phase 2 Accounting Layer over the existing APICO operational system.

Critical rule:
The existing operational system remains the source of truth. Users must not enter the same business transaction twice. Customer payments, supplier payments, stock purchases, stock sales, recycle-out charges, cheques, expenses, and payroll must automatically generate accounting journal entries where applicable.

Do not replace the operational layer. Add accounting on top of it.

Required features:
- Hierarchical Chart of Accounts.
- Account mappings for operational posting.
- Journal entries and journal lines.
- Automatic posting service.
- Cash and bank accounts.
- Expense vouchers.
- Payroll.
- Cheques receivable/payable accounting.
- Accounting period locks.
- General Ledger.
- Trial Balance.
- Profit and Loss.
- Balance Sheet.
- AR aging.
- AP aging.
- Audit logs for accounting-sensitive changes.

Business rules:
- Recycle-in creates no accounting entry.
- Recycle-out creates AR and recycling income entry.
- Customer payment creates cash/bank/cheque debit and AR credit.
- Supplier payment creates AP debit and cash/bank/cheque credit.
- Stock purchase creates inventory debit and AP credit.
- Stock sale creates AR debit and sales income credit.
- Stock sale also creates material COGS debit and inventory credit using existing weighted-average inventory cost.
- Actual salaries, rent, electricity, maintenance, and expenses are posted as expenses when entered. Do not double-post conversion cost as COGS unless a future formal WIP/costing module is built.
- Manual journals are only for accounting-only transactions and corrections.
- Opening balances are handled manually by the accountant, not by automatic trial-balance import.

Implementation requirements:
- Preserve existing Laravel/Blade architecture.
- Preserve English/Arabic bilingual support.
- Add Arabic translations for all new strings.
- Verify RTL layout.
- Enforce permissions in routes/controllers and views.
- Use database transactions for posting operations.
- Use reversal and reposting for posted entries when source records change.
- Prevent duplicate journal entries with source tracking and idempotency rules.
- Add migrations without modifying old production migrations.
- Add focused feature tests.
- Run the full test suite.
- Provide safe Contabo deployment steps with backup requirements.
```
