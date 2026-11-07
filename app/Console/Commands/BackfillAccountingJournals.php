<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RecycleOut;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\SupplierPayment;
use App\Services\AccountingPostingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackfillAccountingJournals extends Command
{
    protected $signature = 'apico:accounting-backfill
        {--from=2026-01-01 : First source date to post}
        {--to= : Last source date to post}
        {--commit : Create missing journals; without this option the command is a dry run}';

    protected $description = 'Create missing operational journals idempotently';

    public function handle(AccountingPostingService $posting): int
    {
        $from = (string) $this->option('from');
        $to = (string) ($this->option('to') ?: today()->toDateString());

        if (Setting::where('key', 'accounting_enabled')->value('value') !== '1') {
            $this->error('Accounting must be enabled before running the backfill.');

            return self::FAILURE;
        }

        $sources = $this->sources($from, $to);
        $missingBefore = $sources->filter(fn (Model $source) => $this->activeEntryCount($source) < $this->expectedEntryCount($source));
        $this->table(['Metric', 'Value'], [
            ['Date range', $from.' to '.$to],
            ['Eligible sources', $sources->count()],
            ['Sources missing journals', $missingBefore->count()],
            ['Mode', $this->option('commit') ? 'COMMIT' : 'DRY RUN'],
        ]);

        if (! $this->option('commit')) {
            $this->warn('Dry run only. Add --commit after reviewing the counts.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($posting, $sources, $from) {
            $posting->postOpeningBalances($from);
            $sources->each(function (Model $source) use ($posting) {
                $posting->postOperational($source);

                if ($source instanceof Payment && $source->payment_type === 'cheque' && $source->cheque_status !== 'pending') {
                    $posting->repostIncomingChequeSettlement($source);
                }
                if ($source instanceof SupplierPayment && $source->payment_type === 'cheque' && $source->cheque_status !== 'pending') {
                    $posting->repostSupplierChequeSettlement($source);
                }
            });
        });

        $missingAfter = $sources->filter(fn (Model $source) => $this->activeEntryCount($source) < $this->expectedEntryCount($source));
        $this->info('Accounting backfill completed.');
        $this->line('Sources still missing journals: '.$missingAfter->count());

        return $missingAfter->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    private function sources(string $from, string $to)
    {
        return collect([
            RecycleOut::class,
            Payment::class,
            StockPurchase::class,
            StockSale::class,
            SupplierPayment::class,
        ])->flatMap(fn (string $model) => $model::whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->orderBy('id')
            ->get())
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values();
    }

    private function expectedEntryCount(Model $source): int
    {
        return match (true) {
            $source instanceof RecycleOut => abs((float) $source->total_amount) >= 0.0005 ? 1 : 0,
            $source instanceof Payment => abs((float) $source->amount) >= 0.0005 ? 1 : 0,
            $source instanceof SupplierPayment => abs((float) $source->amount) >= 0.0005 ? 1 : 0,
            $source instanceof StockPurchase => abs((float) $source->total_cost) >= 0.0005 ? 1 : 0,
            $source instanceof StockSale => (abs((float) $source->sales_value) >= 0.0005 ? 1 : 0)
                + (abs((float) $source->weight_kg) >= 0.0005 ? 1 : 0),
            default => 0,
        };
    }

    private function activeEntryCount(Model $source): int
    {
        return JournalEntry::where('source_module', 'operations')
            ->where('source_type', class_basename($source))
            ->where('source_id', $source->getKey())
            ->where('status', 'posted')
            ->whereNull('reversal_of_journal_entry_id')
            ->count();
    }
}
