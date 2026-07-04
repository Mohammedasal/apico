<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPostingService
{
    public function __construct(
        private readonly ApicoCalculator $calculator,
        private readonly AuditService $audit,
    ) {}

    public function postOperational(Model $source, ?User $user = null): Collection
    {
        if (! $this->automaticPostingEnabled()) {
            return collect();
        }

        if ($source instanceof RecycleIn) {
            return collect();
        }

        return DB::transaction(function () use ($source, $user) {
            return match (true) {
                $source instanceof RecycleOut => $this->postRecycleOut($source, $user),
                $source instanceof Payment => $this->postCustomerPayment($source, $user),
                $source instanceof SupplierPayment => $this->postSupplierPayment($source, $user),
                $source instanceof StockPurchase => $this->postStockPurchase($source, $user),
                $source instanceof StockSale => $this->postStockSale($source, $user),
                default => throw new \InvalidArgumentException('Unsupported accounting source: '.$source::class),
            };
        });
    }

    public function repostOperational(Model $source, ?User $user = null): Collection
    {
        if (! $this->automaticPostingEnabled()) {
            return collect();
        }

        return DB::transaction(function () use ($source, $user) {
            $this->activeSourceEntries($source, 'operations')->each(
                fn (JournalEntry $entry) => $this->reverse($entry, $user)
            );

            return $this->postOperational($source, $user);
        });
    }

    public function reverseOperational(Model $source, ?User $user = null): Collection
    {
        if (! $this->automaticPostingEnabled()) {
            return collect();
        }

        return DB::transaction(fn () => $this->activeSourceEntries($source, 'operations')
            ->map(fn (JournalEntry $entry) => $this->reverse($entry, $user)));
    }

    public function postExpenseVoucher(ExpenseVoucher $voucher, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($voucher, $user) {
            if ($voucher->status !== 'posted') {
                throw ValidationException::withMessages(['status' => 'Only posted expense vouchers create journal entries.']);
            }

            $existing = $this->activeSourceEntries($voucher, 'accounting')
                ->where('posting_type', 'expense_voucher')
                ->first();

            if ($existing) {
                return $existing->load('lines.account');
            }

            $voucher->loadMissing(['category.defaultAccount', 'cashAccount.chartAccount', 'bankAccount.chartAccount', 'payableAccount']);
            $expenseAccount = $voucher->category->defaultAccount;
            $this->assertPostingAccount($expenseAccount);

            $amount = round((float) $voucher->amount, 3);
            $paidAmount = round((float) $voucher->paid_amount, 3);
            $unpaidAmount = round($amount - $paidAmount, 3);
            $lines = [$this->line($expenseAccount->id, $amount, 0)];

            if ($paidAmount > 0) {
                $lines[] = $this->line(
                    $this->expenseSettlementAccount($voucher)->id,
                    0,
                    $paidAmount,
                    bankAccountId: $voucher->bank_account_id,
                    cashAccountId: $voucher->cash_account_id
                );
            }

            if ($unpaidAmount > 0) {
                $payable = $voucher->payableAccount ?? $this->mappedAccount('accrued_expenses');
                $this->assertPostingAccount($payable);
                $lines[] = $this->line($payable->id, 0, $unpaidAmount);
            }

            return $this->createPostedEntry([
                'entry_date' => $voucher->expense_date->toDateString(),
                'memo_en' => 'Expense voucher '.$voucher->voucher_no,
                'memo_ar' => 'سند مصروف '.$voucher->voucher_no,
                'source_module' => 'accounting',
                'source_type' => class_basename($voucher),
                'source_id' => $voucher->id,
                'posting_type' => 'expense_voucher',
                'is_auto' => true,
            ], $lines, $user);
        });
    }

    public function repostExpenseVoucher(ExpenseVoucher $voucher, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($voucher, $user) {
            $this->activeSourceEntries($voucher, 'accounting')->each(
                fn (JournalEntry $entry) => $this->reverse($entry, $user)
            );

            return $this->postExpenseVoucher($voucher, $user);
        });
    }

    public function reverseExpenseVoucher(ExpenseVoucher $voucher, ?User $user = null): Collection
    {
        return DB::transaction(fn () => $this->activeSourceEntries($voucher, 'accounting')
            ->map(fn (JournalEntry $entry) => $this->reverse($entry, $user)));
    }

    public function createPostedEntry(array $header, array $lines, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines, $user) {
            $date = Carbon::parse($header['entry_date'])->toDateString();
            $this->assertPeriodOpen($date);
            $lines = $this->validatedLines($lines);

            $entry = JournalEntry::create([
                'entry_no' => $header['entry_no'] ?? $this->nextEntryNumber($date),
                'entry_date' => $date,
                'memo_en' => $header['memo_en'] ?? null,
                'memo_ar' => $header['memo_ar'] ?? null,
                'source_module' => $header['source_module'] ?? null,
                'source_type' => $header['source_type'] ?? null,
                'source_id' => $header['source_id'] ?? null,
                'posting_type' => $header['posting_type'] ?? null,
                'status' => 'posted',
                'is_auto' => (bool) ($header['is_auto'] ?? false),
                'posted_at' => now(),
                'posted_by' => $user?->id,
                'reversal_of_journal_entry_id' => $header['reversal_of_journal_entry_id'] ?? null,
                'created_by' => $user?->id,
            ]);

            $entry->lines()->createMany($lines);
            $entry->load('lines.account');
            $this->audit->record('journal_posted', $entry, null, $entry->toArray());

            return $entry;
        });
    }

    public function reverse(JournalEntry $entry, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $user) {
            $entry->load('lines');

            if ($entry->status === 'reversed') {
                return $entry->reversals()->latest('id')->firstOrFail();
            }

            if ($entry->status !== 'posted') {
                throw ValidationException::withMessages(['journal' => 'Only posted journal entries can be reversed.']);
            }

            $reversalDate = AccountingPeriod::isLocked($entry->entry_date->toDateString())
                ? now()->toDateString()
                : $entry->entry_date->toDateString();

            $reversal = $this->createPostedEntry([
                'entry_date' => $reversalDate,
                'memo_en' => 'Reversal of '.$entry->entry_no,
                'memo_ar' => 'عكس القيد '.$entry->entry_no,
                'source_module' => $entry->source_module,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'posting_type' => 'reversal:'.$entry->id,
                'is_auto' => $entry->is_auto,
                'reversal_of_journal_entry_id' => $entry->id,
            ], $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'description_en' => 'Reversal: '.$line->description_en,
                'description_ar' => 'عكس: '.$line->description_ar,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
                'material_id' => $line->material_id,
                'employee_id' => $line->employee_id,
                'bank_account_id' => $line->bank_account_id,
                'cash_account_id' => $line->cash_account_id,
                'cheque_id' => $line->cheque_id,
                'cost_center_id' => $line->cost_center_id,
            ])->all(), $user);

            $before = $entry->toArray();
            $entry->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
            $this->audit->record('journal_reversed', $entry, $before, $entry->fresh()->toArray());

            return $reversal;
        });
    }

    public function mappedAccount(string $key, ?string $entityType = null, ?int $entityId = null): ChartOfAccount
    {
        $mapping = AccountMapping::with('account')
            ->where('mapping_key', $key)
            ->where('is_active', true)
            ->when(
                $entityType && $entityId,
                fn ($query) => $query->where(function ($query) use ($entityType, $entityId) {
                    $query->where(fn ($query) => $query
                        ->where('entity_type', $entityType)
                        ->where('entity_id', $entityId))
                        ->orWhereNull('entity_type');
                })->orderByRaw('CASE WHEN entity_type IS NULL THEN 1 ELSE 0 END'),
                fn ($query) => $query->whereNull('entity_type')->whereNull('entity_id')
            )
            ->first();

        if (! $mapping) {
            throw ValidationException::withMessages(['account_mapping' => "Missing accounting mapping: {$key}."]);
        }

        $this->assertPostingAccount($mapping->account);

        return $mapping->account;
    }

    private function postRecycleOut(RecycleOut $source, ?User $user): Collection
    {
        $amount = round((float) $source->total_amount, 3);

        if ($amount <= 0) {
            return collect();
        }

        return collect([$this->postSourceEntry($source, 'recycle_out_revenue', $amount, [
            $this->line($this->mappedAccount('accounts_receivable_control')->id, $amount, 0, customerId: $source->customer_id, materialId: $source->material_id),
            $this->line($this->mappedAccount('recycling_service_income')->id, 0, $amount, customerId: $source->customer_id, materialId: $source->material_id),
        ], $user)]);
    }

    private function postCustomerPayment(Payment $source, ?User $user): Collection
    {
        $amount = abs(round((float) $source->amount, 3));
        $destination = $this->paymentAssetAccount($source);
        $ar = $this->mappedAccount('accounts_receivable_control');
        $positive = (float) $source->amount >= 0;

        return collect([$this->postSourceEntry($source, 'customer_payment', $amount, [
            $this->line($positive ? $destination->id : $ar->id, $amount, 0, customerId: $source->customer_id, bankAccountId: $source->bank_account_id, cashAccountId: $source->cash_account_id),
            $this->line($positive ? $ar->id : $destination->id, 0, $amount, customerId: $source->customer_id, bankAccountId: $source->bank_account_id, cashAccountId: $source->cash_account_id),
        ], $user)]);
    }

    private function postSupplierPayment(SupplierPayment $source, ?User $user): Collection
    {
        $amount = abs(round((float) $source->amount, 3));
        $paymentAccount = $this->supplierPaymentCreditAccount($source);
        $ap = $this->mappedAccount('accounts_payable_control');
        $positive = (float) $source->amount >= 0;

        return collect([$this->postSourceEntry($source, 'supplier_payment', $amount, [
            $this->line($positive ? $ap->id : $paymentAccount->id, $amount, 0, supplierId: $source->supplier_id, bankAccountId: $source->bank_account_id, cashAccountId: $source->cash_account_id),
            $this->line($positive ? $paymentAccount->id : $ap->id, 0, $amount, supplierId: $source->supplier_id, bankAccountId: $source->bank_account_id, cashAccountId: $source->cash_account_id),
        ], $user)]);
    }

    private function postStockPurchase(StockPurchase $source, ?User $user): Collection
    {
        $amount = round((float) $source->total_cost, 3);
        $inventory = $this->mappedAccount('inventory_default', $source->material_id ? 'material' : null, $source->material_id);

        return collect([$this->postSourceEntry($source, 'stock_purchase', $amount, [
            $this->line($inventory->id, $amount, 0, supplierId: $source->supplier_id, materialId: $source->material_id),
            $this->line($this->mappedAccount('accounts_payable_control')->id, 0, $amount, supplierId: $source->supplier_id, materialId: $source->material_id),
        ], $user)]);
    }

    private function postStockSale(StockSale $source, ?User $user): Collection
    {
        $revenue = round((float) $source->sales_value, 3);
        $inventory = $this->mappedAccount('inventory_default', $source->material_id ? 'material' : null, $source->material_id);
        $costPerKg = $this->calculator->weightedAverageStockCost(
            $source->material_id,
            $source->date->toDateString(),
            $source->id
        );
        $cogs = round((float) $source->weight_kg * $costPerKg, 3);

        if ($cogs <= 0) {
            throw ValidationException::withMessages(['accounting' => 'Stock sale material cost could not be calculated.']);
        }

        return collect([
            $this->postSourceEntry($source, 'stock_sale_revenue', $revenue, [
                $this->line($this->mappedAccount('accounts_receivable_control')->id, $revenue, 0, customerId: $source->customer_id, materialId: $source->material_id),
                $this->line($this->mappedAccount('stock_sales_income')->id, 0, $revenue, customerId: $source->customer_id, materialId: $source->material_id),
            ], $user),
            $this->postSourceEntry($source, 'stock_sale_cogs', $cogs, [
                $this->line($this->mappedAccount('stock_material_cogs')->id, $cogs, 0, customerId: $source->customer_id, materialId: $source->material_id),
                $this->line($inventory->id, 0, $cogs, customerId: $source->customer_id, materialId: $source->material_id),
            ], $user),
        ]);
    }

    private function postSourceEntry(Model $source, string $postingType, float $amount, array $lines, ?User $user): JournalEntry
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['accounting' => 'Accounting amount must be greater than zero.']);
        }

        $sourceType = class_basename($source);
        $existing = JournalEntry::where([
            'source_module' => 'operations',
            'source_type' => $sourceType,
            'source_id' => $source->getKey(),
            'posting_type' => $postingType,
            'status' => 'posted',
        ])->first();

        if ($existing) {
            return $existing->load('lines.account');
        }

        return $this->createPostedEntry([
            'entry_date' => $source->date->toDateString(),
            'memo_en' => $this->sourceMemo($sourceType, $source->getKey()),
            'memo_ar' => 'قيد تلقائي للمصدر '.$sourceType.' #'.$source->getKey(),
            'source_module' => 'operations',
            'source_type' => $sourceType,
            'source_id' => $source->getKey(),
            'posting_type' => $postingType,
            'is_auto' => true,
        ], $lines, $user);
    }

    private function paymentAssetAccount(Payment $payment): ChartOfAccount
    {
        return match ($payment->payment_type) {
            'cash' => $payment->cashAccount?->chartAccount ?? $this->mappedAccount('cash_default'),
            'bank_transfer' => $payment->bankAccount?->chartAccount ?? $this->mappedAccount('bank_default'),
            'cheque' => $this->mappedAccount('cheques_receivable'),
            'exchange_of_goods' => $this->mappedAccount('exchange_of_goods_clearing'),
            default => throw ValidationException::withMessages(['payment_type' => 'Unsupported payment type for accounting.']),
        };
    }

    private function supplierPaymentCreditAccount(SupplierPayment $payment): ChartOfAccount
    {
        return match ($payment->payment_type) {
            'cash' => $payment->cashAccount?->chartAccount ?? $this->mappedAccount('cash_default'),
            'bank_transfer' => $payment->bankAccount?->chartAccount ?? $this->mappedAccount('bank_default'),
            'cheque' => $this->mappedAccount('cheques_payable'),
            'exchange_of_goods' => $this->mappedAccount('exchange_of_goods_clearing'),
            default => throw ValidationException::withMessages(['payment_type' => 'Unsupported payment type for accounting.']),
        };
    }

    private function expenseSettlementAccount(ExpenseVoucher $voucher): ChartOfAccount
    {
        return match ($voucher->payment_type) {
            'cash' => $voucher->cashAccount?->chartAccount ?? $this->mappedAccount('cash_default'),
            'bank_transfer' => $voucher->bankAccount?->chartAccount ?? $this->mappedAccount('bank_default'),
            'cheque' => $this->mappedAccount('cheques_payable'),
            default => throw ValidationException::withMessages(['payment_type' => 'Paid expenses require cash, bank transfer, or cheque payment type.']),
        };
    }

    private function activeSourceEntries(Model $source, string $sourceModule): Collection
    {
        return JournalEntry::with('lines')
            ->where('source_module', $sourceModule)
            ->where('source_type', class_basename($source))
            ->where('source_id', $source->getKey())
            ->where('status', 'posted')
            ->whereNull('reversal_of_journal_entry_id')
            ->get();
    }

    private function validatedLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal entry requires at least two lines.']);
        }

        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as &$line) {
            $line['debit'] = round((float) ($line['debit'] ?? 0), 3);
            $line['credit'] = round((float) ($line['credit'] ?? 0), 3);

            if (($line['debit'] > 0 && $line['credit'] > 0) || ($line['debit'] <= 0 && $line['credit'] <= 0)) {
                throw ValidationException::withMessages(['lines' => 'Each journal line must contain either a debit or a credit amount.']);
            }

            $account = ChartOfAccount::findOrFail($line['account_id']);
            $this->assertPostingAccount($account);
            $debit += $line['debit'];
            $credit += $line['credit'];
        }

        if (round($debit, 3) !== round($credit, 3)) {
            throw ValidationException::withMessages(['lines' => 'Journal entry debits and credits must balance.']);
        }

        return $lines;
    }

    private function assertPostingAccount(ChartOfAccount $account): void
    {
        if (! $account->is_active || ! $account->is_posting) {
            throw ValidationException::withMessages(['account' => "Account {$account->code} must be active and posting-enabled."]);
        }
    }

    private function assertPeriodOpen(string $date): void
    {
        if (AccountingPeriod::isLocked($date)) {
            throw ValidationException::withMessages(['entry_date' => 'The accounting period is locked.']);
        }
    }

    private function nextEntryNumber(string $date): string
    {
        $nextId = ((int) JournalEntry::max('id')) + 1;

        return 'JE-'.Carbon::parse($date)->format('Ym').'-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    private function sourceMemo(string $sourceType, int $sourceId): string
    {
        return "Automatic posting for {$sourceType} #{$sourceId}";
    }

    private function line(
        int $accountId,
        float $debit,
        float $credit,
        ?int $customerId = null,
        ?int $supplierId = null,
        ?int $materialId = null,
        ?int $bankAccountId = null,
        ?int $cashAccountId = null,
    ): array {
        return [
            'account_id' => $accountId,
            'debit' => round($debit, 3),
            'credit' => round($credit, 3),
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'material_id' => $materialId,
            'bank_account_id' => $bankAccountId,
            'cash_account_id' => $cashAccountId,
        ];
    }

    private function automaticPostingEnabled(): bool
    {
        return Setting::where('key', 'accounting_enabled')->value('value') === '1';
    }
}
