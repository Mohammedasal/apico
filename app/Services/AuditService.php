<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(string $action, Model $model, ?array $before = null, ?array $after = null): AuditLog
    {
        return $this->recordEvent($action, $model::class, $model->getKey(), $before, $after);
    }

    public function recordEvent(
        string $action,
        string $modelType,
        int|string|null $modelId = null,
        ?array $before = null,
        ?array $after = null
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
