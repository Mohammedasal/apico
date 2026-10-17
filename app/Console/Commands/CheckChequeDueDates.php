<?php

namespace App\Console\Commands;

use App\Services\ChequeDueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckChequeDueDates extends Command
{
    protected $signature = 'apico:cheque-due-check';

    protected $description = 'Refresh cheque due-date alerts without changing settlement status';

    public function handle(ChequeDueService $service): int
    {
        $summary = $service->summary();
        Cache::forever('apico.cheque_due_summary', $summary);

        $this->table(
            ['Direction', 'Overdue', 'Due today', 'Upcoming', 'Scheduled', 'No due date', 'Settled'],
            collect(['incoming', 'outgoing'])->map(fn (string $direction) => [
                ucfirst($direction),
                $summary[$direction]['overdue'],
                $summary[$direction]['due_today'],
                $summary[$direction]['upcoming'],
                $summary[$direction]['scheduled'],
                $summary[$direction]['no_due_date'],
                $summary[$direction]['settled'],
            ])->all()
        );

        return self::SUCCESS;
    }
}
