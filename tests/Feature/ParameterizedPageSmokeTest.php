<?php

namespace Tests\Feature;

use App\Models\ChequeOut;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Material;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParameterizedPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_parameterized_html_page_renders_in_english_and_arabic(): void
    {
        $this->seed(AccountingSeeder::class);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Smoke Customer', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Smoke Supplier', 'status' => 'active']);
        $material = Material::create(['name' => 'Smoke Material', 'is_active' => true]);
        $recycleIn = RecycleIn::create(['date' => '2026-01-01', 'customer_id' => $customer->id, 'weight_kg' => 10, 'rate_per_kg' => 0, 'total_amount' => 0]);
        $recycleOut = RecycleOut::create(['date' => '2026-01-02', 'customer_id' => $customer->id, 'weight_kg' => 10, 'recycled_out_kg' => 10, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 10]);
        $payment = Payment::create(['date' => '2026-01-03', 'customer_id' => $customer->id, 'amount' => 5, 'payment_type' => 'cash']);
        $purchase = StockPurchase::create(['date' => '2026-01-04', 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'weight_kg' => 20, 'cost_per_kg' => 1, 'total_cost' => 20]);
        $sale = StockSale::create(['date' => '2026-01-05', 'customer_id' => $customer->id, 'weight_kg' => 5, 'selling_price_per_kg' => 2, 'sales_value' => 10, 'net_profit' => 5]);
        $supplierPayment = SupplierPayment::create(['date' => '2026-01-06', 'supplier_id' => $supplier->id, 'amount' => 5, 'payment_type' => 'cash']);
        $cheque = ChequeOut::create(['payee' => 'Payee', 'due_date' => '2026-02-01', 'amount' => 10, 'status' => 'pending']);
        $employee = Employee::create(['name_en' => 'Employee', 'base_salary' => 300, 'is_active' => true]);
        $category = ExpenseCategory::firstOrFail();
        $expense = ExpenseVoucher::create(['voucher_no' => 'EV-SMOKE', 'expense_date' => '2026-01-01', 'expense_category_id' => $category->id, 'amount' => 10, 'paid_amount' => 10, 'payment_status' => 'paid', 'payment_type' => 'cash', 'status' => 'posted']);
        $payroll = PayrollRun::create(['period_month' => 1, 'period_year' => 2026, 'payroll_date' => '2026-01-31', 'status' => 'draft']);
        $journal = JournalEntry::create(['entry_no' => 'JE-SMOKE', 'entry_date' => '2026-01-01', 'status' => 'draft', 'is_auto' => false]);
        $account = $category->defaultAccount;

        $routes = [
            ['customers.show', $customer],
            ['customers.edit', $customer],
            ['suppliers.show', $supplier],
            ['suppliers.edit', $supplier],
            ['materials.edit', $material],
            ['supplier-payments.edit', $supplierPayment],
            ['operations.edit', ['recycle-in', $recycleIn->id]],
            ['operations.edit', ['recycle-out', $recycleOut->id]],
            ['operations.edit', ['payments', $payment->id]],
            ['operations.edit', ['stock-purchases', $purchase->id]],
            ['operations.edit', ['stock-sales', $sale->id]],
            ['cheques-out.edit', $cheque],
            ['accounting.accounts.edit', $account],
            ['accounting.employees.edit', $employee],
            ['accounting.expense-categories.edit', $category],
            ['accounting.expenses.show', $expense],
            ['accounting.expenses.edit', $expense],
            ['accounting.journals.show', $journal],
            ['accounting.payroll.show', $payroll],
            ['accounting.payroll.edit', $payroll],
            ['users.edit', $admin],
            ['reports.customer-statement', $customer],
        ];

        foreach (['en', 'ar'] as $locale) {
            foreach ($routes as [$name, $parameters]) {
                $response = $this->actingAs($admin)
                    ->withSession(['locale' => $locale])
                    ->get(route($name, $parameters));

                $this->assertTrue(
                    $response->isSuccessful(),
                    sprintf('%s returned HTTP %d in %s.', $name, $response->getStatusCode(), $locale)
                );
            }
        }
    }
}
