<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(string $action, Model $model, ?array $before = null, ?array $after = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
