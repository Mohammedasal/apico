<?php

namespace Database\Seeders;

use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $accounts = [];

            foreach ($this->accounts() as $definition) {
                $parentCode = $definition['parent'] ?? null;
                unset($definition['parent']);

                $account = ChartOfAccount::updateOrCreate(
                    ['code' => $definition['code']],
                    $definition + ['parent_id' => $parentCode ? $accounts[$parentCode]->id : null]
                );
                $accounts[$account->code] = $account;
            }

            foreach ($this->mappings() as $mappingKey => $accountCode) {
                AccountMapping::updateOrCreate(
                    ['mapping_key' => $mappingKey, 'entity_type' => null, 'entity_id' => null],
                    ['account_id' => $accounts[$accountCode]->id, 'is_active' => true]
                );
            }

            CashAccount::updateOrCreate(
                ['name_en' => 'Main Cash'],
                ['name_ar' => 'الصندوق الرئيسي', 'chart_account_id' => $accounts['1110']->id, 'is_active' => true, 'is_default' => true]
            );
            BankAccount::updateOrCreate(
                ['name_en' => 'Main Bank Account'],
                ['name_ar' => 'الحساب البنكي الرئيسي', 'currency' => 'JOD', 'chart_account_id' => $accounts['1210']->id, 'is_active' => true, 'is_default' => true]
            );

            Setting::firstOrCreate(
                ['key' => 'accounting_enabled'],
                ['value' => '0', 'description' => 'Enable automatic accounting posting after mappings are approved']
            );
            Setting::firstOrCreate(
                ['key' => 'monthly_expense_source'],
                ['value' => 'legacy_monthly_expenses', 'description' => 'Source for monthly factory expense totals']
            );
        });
    }

    private function account(string $code, string $nameEn, string $nameAr, string $type, string $normalBalance, bool $posting, int $sortOrder, ?string $parent = null): array
    {
        return [
            'code' => $code,
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_posting' => $posting,
            'is_system' => true,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'parent' => $parent,
        ];
    }

    private function accounts(): array
    {
        return [
            $this->account('1000', 'Assets', 'الأصول', 'asset', 'debit', false, 100),
            $this->account('1100', 'Cash', 'النقدية', 'asset', 'debit', false, 110, '1000'),
            $this->account('1110', 'Main Cash', 'الصندوق الرئيسي', 'asset', 'debit', true, 111, '1100'),
            $this->account('1200', 'Bank Accounts', 'الحسابات البنكية', 'asset', 'debit', false, 120, '1000'),
            $this->account('1210', 'Main Bank Account', 'الحساب البنكي الرئيسي', 'asset', 'debit', true, 121, '1200'),
            $this->account('1300', 'Accounts Receivable Control', 'ذمم العملاء', 'asset', 'debit', true, 130, '1000'),
            $this->account('1400', 'Cheques Receivable', 'شيكات برسم التحصيل', 'asset', 'debit', true, 140, '1000'),
            $this->account('1500', 'Inventory', 'المخزون', 'asset', 'debit', true, 150, '1000'),
            $this->account('1600', 'Exchange of Goods Clearing', 'حساب مقاصة البضائع', 'asset', 'debit', true, 160, '1000'),

            $this->account('2000', 'Liabilities', 'الالتزامات', 'liability', 'credit', false, 200),
            $this->account('2100', 'Accounts Payable Control', 'ذمم الموردين', 'liability', 'credit', true, 210, '2000'),
            $this->account('2200', 'Cheques Payable', 'شيكات آجلة الدفع', 'liability', 'credit', true, 220, '2000'),
            $this->account('2300', 'Accrued Expenses', 'مصاريف مستحقة', 'liability', 'credit', true, 230, '2000'),
            $this->account('2400', 'Salaries Payable', 'رواتب مستحقة', 'liability', 'credit', true, 240, '2000'),
            $this->account('2500', 'Social Security Payable', 'ضمان اجتماعي مستحق', 'liability', 'credit', true, 250, '2000'),

            $this->account('3000', 'Equity', 'حقوق الملكية', 'equity', 'credit', false, 300),
            $this->account('3100', 'Owner Capital', 'رأس مال المالك', 'equity', 'credit', true, 310, '3000'),
            $this->account('3200', 'Owner Withdrawals', 'مسحوبات المالك', 'equity', 'debit', true, 320, '3000'),
            $this->account('3300', 'Retained Earnings', 'أرباح محتجزة', 'equity', 'credit', true, 330, '3000'),

            $this->account('4000', 'Revenue', 'الإيرادات', 'revenue', 'credit', false, 400),
            $this->account('4100', 'Recycling Service Income', 'إيراد خدمات التدوير', 'revenue', 'credit', true, 410, '4000'),
            $this->account('4200', 'Stock Sales Income', 'إيراد مبيعات الستوك', 'revenue', 'credit', true, 420, '4000'),
            $this->account('4300', 'Other Income', 'إيرادات أخرى', 'revenue', 'credit', true, 430, '4000'),

            $this->account('5000', 'Cost of Goods Sold', 'تكلفة البضاعة المباعة', 'cogs', 'debit', false, 500),
            $this->account('5100', 'Stock Material Cost', 'تكلفة مواد الستوك', 'cogs', 'debit', true, 510, '5000'),
            $this->account('5200', 'Inventory Adjustments', 'تسويات المخزون', 'cogs', 'debit', true, 520, '5000'),

            $this->account('6000', 'Expenses', 'المصاريف', 'expense', 'debit', false, 600),
            $this->account('6100', 'Salaries Expense', 'مصاريف الرواتب', 'expense', 'debit', true, 610, '6000'),
            $this->account('6200', 'Rent Expense', 'مصاريف الإيجار', 'expense', 'debit', true, 620, '6000'),
            $this->account('6300', 'Electricity Expense', 'مصاريف الكهرباء', 'expense', 'debit', true, 630, '6000'),
            $this->account('6400', 'Maintenance Expense', 'مصاريف الصيانة', 'expense', 'debit', true, 640, '6000'),
            $this->account('6500', 'Social Security Expense', 'مصاريف الضمان الاجتماعي', 'expense', 'debit', true, 650, '6000'),
            $this->account('6600', 'Miscellaneous Expense', 'مصاريف متنوعة', 'expense', 'debit', true, 660, '6000'),
        ];
    }

    private function mappings(): array
    {
        return [
            'cash_default' => '1110',
            'bank_default' => '1210',
            'accounts_receivable_control' => '1300',
            'cheques_receivable' => '1400',
            'inventory_default' => '1500',
            'exchange_of_goods_clearing' => '1600',
            'accounts_payable_control' => '2100',
            'cheques_payable' => '2200',
            'accrued_expenses' => '2300',
            'salary_payable' => '2400',
            'social_security_payable' => '2500',
            'owner_capital' => '3100',
            'owner_withdrawals' => '3200',
            'retained_earnings' => '3300',
            'recycling_service_income' => '4100',
            'stock_sales_income' => '4200',
            'other_income' => '4300',
            'stock_material_cogs' => '5100',
            'salary_expense' => '6100',
            'rent_expense' => '6200',
            'electricity_expense' => '6300',
            'maintenance_expense' => '6400',
            'social_security_expense' => '6500',
            'miscellaneous_expense' => '6600',
        ];
    }
}
