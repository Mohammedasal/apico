<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\FormSubmissionGuard;
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
