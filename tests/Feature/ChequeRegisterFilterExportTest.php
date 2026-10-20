<?php

namespace Tests\Feature;

use App\Models\ChequeOut;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\FormSubmissionGuard;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ChequeRegisterFilterExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_cheque_screen_and_export_share_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $included = Customer::create(['name' => 'Included Cheque Customer', 'status' => 'active']);
        $excluded = Customer::create(['name' => 'Excluded Cheque Customer', 'status' => 'active']);
        $this->cheque($included, '2026-07-10', 125, 'IN-125');
        $this->cheque($excluded, '2026-08-10', 500, 'OUT-500');

        $filters = [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'min_amount' => 100,
            'max_amount' => 200,
            'q' => 'Included',
            'sort' => 'amount',
            'direction' => 'desc',
        ];

        $this->actingAs($admin)->get(route('cheques-in.index', $filters))
            ->assertOk()
            ->assertSee('Included Cheque Customer')
            ->assertDontSee('Excluded Cheque Customer');

        $response = $this->get(route('cheques-in.export', $filters + [
            'columns' => ['due_date', 'customer', 'amount'],
        ]));
        $response->assertOk()->assertDownload('cheques-in-'.now()->format('Y-m-d').'.xlsx');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($worksheet);
        $this->assertStringContainsString('Included Cheque Customer', $worksheet);
        $this->assertStringNotContainsString('Excluded Cheque Customer', $worksheet);
        $this->assertStringNotContainsString('Cheque No.', $worksheet);
    }

    public function test_incoming_cheque_rejects_a_stale_settlement_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $payment = $this->cheque($customer, '2026-07-10', 125, 'CH-125');
        $version = app(FormSubmissionGuard::class)->version($payment);
        $payment->notes = 'Changed by another user';
        $payment->setUpdatedAt(now()->addMinute());
        $payment->saveQuietly();

        $this->actingAs($admin)->put(route('cheques-in.update', $payment), [
            '_record_version' => $version,
            'cheque_status' => 'cancelled',
            'cheque_settlement_date' => '2026-07-11',
        ])->assertSessionHasErrors('form');

        $this->assertSame('pending', $payment->fresh()->cheque_status);
    }

    public function test_outgoing_cheque_screen_and_export_filter_all_sources_consistently(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Included Supplier', 'status' => 'active']);
        $this->seed(AccountingSeeder::class);
        $category = ExpenseCategory::firstOrFail();
        $category->update(['name_en' => 'Excluded Expense', 'name_ar' => 'Excluded Expense']);
        ChequeOut::create([
            'payee' => 'Excluded Manual Payee',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'amount' => 200,
            'status' => 'pending',
        ]);
        SupplierPayment::create([
            'date' => '2026-07-01',
            'supplier_id' => $supplier->id,
            'amount' => 350,
            'payment_type' => 'cheque',
            'reference_no' => 'SUP-350',
            'cheque_due_date' => '2026-07-20',
            'cheque_status' => 'pending',
        ]);
        ExpenseVoucher::create([
            'voucher_no' => 'EXP-1',
            'expense_date' => '2026-07-01',
            'expense_category_id' => $category->id,
            'amount' => 450,
            'paid_amount' => 450,
            'payment_status' => 'paid',
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-07-25',
            'cheque_status' => 'pending',
            'status' => 'posted',
        ]);

        $filters = [
            'source' => 'supplier',
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'min_amount' => 300,
            'max_amount' => 400,
            'q' => 'Included',
        ];
        $this->actingAs($admin)->get(route('cheques-out.index', $filters))
            ->assertOk()
            ->assertSee('Included Supplier')
            ->assertDontSee('Excluded Manual Payee')
            ->assertDontSee('Excluded Expense');

        $response = $this->get(route('cheques-out.export', $filters + [
            'columns' => ['source', 'payee', 'amount'],
        ]));
        $response->assertOk()->assertDownload('cheques-out-'.now()->format('Y-m-d').'.xlsx');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($worksheet);
        $this->assertStringContainsString('Included Supplier', $worksheet);
        $this->assertStringNotContainsString('Excluded Manual Payee', $worksheet);
        $this->assertStringNotContainsString('Excluded Expense', $worksheet);
        $this->assertStringNotContainsString('Cheque No.', $worksheet);
    }

    public function test_manual_outgoing_cheque_rejects_a_stale_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $cheque = ChequeOut::create([
            'payee' => 'Payee',
            'due_date' => '2026-07-15',
            'amount' => 200,
            'status' => 'pending',
        ]);
        $version = app(FormSubmissionGuard::class)->version($cheque);
        $cheque->notes = 'Changed elsewhere';
        $cheque->setUpdatedAt(now()->addMinute());
        $cheque->saveQuietly();

        $this->actingAs($admin)->put(route('cheques-out.update', $cheque), [
            '_record_version' => $version,
            'payee' => 'Overwritten Payee',
            'due_date' => '2026-07-15',
            'amount' => 200,
            'status' => 'pending',
        ])->assertSessionHasErrors('form');

        $this->assertSame('Payee', $cheque->fresh()->payee);
    }

    private function cheque(Customer $customer, string $dueDate, float $amount, string $reference): Payment
    {
        return Payment::create([
            'date' => '2026-07-01',
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_type' => 'cheque',
            'reference_no' => $reference,
            'cheque_due_date' => $dueDate,
            'cheque_status' => 'pending',
        ]);
    }
}
