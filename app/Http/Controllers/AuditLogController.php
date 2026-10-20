<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
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
        $logs = AuditLog::query()
            ->with('user')
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
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
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
}
