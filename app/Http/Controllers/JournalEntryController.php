<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AccountingPostingService;
use App\Services\FormSubmissionGuard;
use App\Services\SimpleXlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        return view('accounting.journals.index', [
            'entries' => $this->filteredQuery($request)
                ->latest('entry_date')
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $request->only(['from', 'to', 'status', 'source_module']),
        ]);
    }

    public function export(Request $request, SimpleXlsxExporter $exporter)
    {
        $availableColumns = $this->exportColumns();
        $columns = collect($availableColumns)
            ->only($request->input('columns', array_keys($availableColumns)))
            ->all() ?: $availableColumns;
        $rows = $this->filteredQuery($request)->latest('entry_date')->latest('id')->get()
            ->map(function (JournalEntry $entry) use ($columns) {
                $row = [
                    'date' => $entry->entry_date?->toDateString(),
                    'entry_no' => $entry->entry_no,
                    'memo' => app()->getLocale() === 'ar' ? ($entry->memo_ar ?: $entry->memo_en) : ($entry->memo_en ?: $entry->memo_ar),
                    'source' => trim(($entry->source_module ?? '').($entry->source_id ? ' #'.$entry->source_id : '')),
                    'debit' => number_format($entry->total_debit, 3, '.', ''),
                    'credit' => number_format($entry->total_credit, 3, '.', ''),
                    'status' => __(ucfirst($entry->status)),
                    'created_by' => $entry->creator?->name ?? __('System'),
                ];

                return collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all();
            })->all();

        return $exporter->download(
            'journal-entries-'.now()->format('Y-m-d').'.xlsx',
            array_values($columns),
            $rows,
            [[__('Journal Entries'), ($request->input('from') ?: __('Beginning')).' '.__('to').' '.($request->input('to') ?: now()->toDateString())]]
        );
    }

    public function show(JournalEntry $journal)
    {
        return view('accounting.journals.show', [
            'journal' => $journal->load(['lines.account', 'lines.customer', 'lines.supplier', 'creator', 'poster']),
        ]);
    }

    public function create()
    {
        return view('accounting.journals.form', [
            'accounts' => ChartOfAccount::where('is_active', true)->where('is_posting', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, AccountingPostingService $posting, FormSubmissionGuard $forms)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'memo_en' => ['nullable', 'string', 'max:255'],
            'memo_ar' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'lines.*.description_en' => ['nullable', 'string', 'max:255'],
            'lines.*.description_ar' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['account_id'] ?? null))
            ->values()
            ->all();
        $forms->consume('manual-journal', $request->input('_submission_token'));
        $journal = $posting->createPostedEntry([
            'entry_date' => $data['entry_date'],
            'memo_en' => $data['memo_en'] ?? null,
            'memo_ar' => $data['memo_ar'] ?? null,
            'source_module' => 'manual',
            'source_type' => 'ManualJournal',
            'is_auto' => false,
        ], $lines, $request->user());

        return redirect()->route('accounting.journals.show', $journal)->with('status', __('Journal posted.'));
    }

    public function reverse(JournalEntry $journal, Request $request, AccountingPostingService $posting)
    {
        $reversal = $posting->reverse($journal, $request->user());

        return redirect()->route('accounting.journals.show', $reversal)->with('status', __('Journal reversed.'));
    }

    private function filteredQuery(Request $request): Builder
    {
        return JournalEntry::with(['lines', 'creator'])
            ->when($request->input('from'), fn ($query, $from) => $query->whereDate('entry_date', '>=', $from))
            ->when($request->input('to'), fn ($query, $to) => $query->whereDate('entry_date', '<=', $to))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('source_module'), fn ($query, $source) => $query->where('source_module', $source));
    }

    private function exportColumns(): array
    {
        return [
            'date' => __('Date'),
            'entry_no' => __('Entry No.'),
            'memo' => __('Memo'),
            'source' => __('Source'),
            'debit' => __('Debit'),
            'credit' => __('Credit'),
            'status' => __('Status'),
            'created_by' => __('Created By'),
        ];
    }
}
