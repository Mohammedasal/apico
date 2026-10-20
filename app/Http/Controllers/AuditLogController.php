<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\SimpleXlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $logs = $this->filteredQuery($search, $userId, $from, $to)
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'userId' => $userId,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request, SimpleXlsxExporter $exporter)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $availableColumns = $this->exportColumns();
        $columns = collect($availableColumns)
            ->only($request->input('columns', array_keys($availableColumns)))
            ->all() ?: $availableColumns;
        $rows = $this->filteredQuery($search, $userId, $from, $to)
            ->latest('created_at')->latest('id')->get()
            ->map(function (AuditLog $log) use ($columns) {
                $row = [
                    'time' => $log->created_at?->format('Y-m-d H:i:s'),
                    'user' => $log->user?->name ?? __('System'),
                    'action' => __(ucwords(str_replace('_', ' ', $log->action))),
                    'record' => class_basename($log->model_type).($log->model_id ? ' #'.$log->model_id : ''),
                    'before' => $log->before === null ? '' : json_encode($log->before, JSON_UNESCAPED_UNICODE),
                    'after' => $log->after === null ? '' : json_encode($log->after, JSON_UNESCAPED_UNICODE),
                    'ip_address' => $log->ip_address,
                ];

                return collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all();
            })->all();

        return $exporter->download(
            'audit-log-'.now()->format('Y-m-d').'.xlsx',
            array_values($columns),
            $rows,
            [[__('Audit Log'), ($from ?: __('Beginning')).' '.__('to').' '.($to ?: now()->toDateString())]]
        );
    }

    private function filteredQuery(string $search, ?int $userId, ?string $from, ?string $to): Builder
    {
        return AuditLog::query()->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('model_type', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));

                    if (ctype_digit($search)) {
                        $query->orWhere('model_id', (int) $search);
                    }
                });
            })
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));
    }

    private function exportColumns(): array
    {
        return [
            'time' => __('Time'),
            'user' => __('User'),
            'action' => __('Action'),
            'record' => __('Record'),
            'before' => __('Before'),
            'after' => __('After'),
            'ip_address' => __('IP Address'),
        ];
    }
}
