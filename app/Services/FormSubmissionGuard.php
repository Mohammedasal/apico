<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormSubmissionGuard
{
    private const SESSION_KEY = 'form_submission_tokens';

    public function issue(string $scope): string
    {
        $this->prune();
        $token = Str::random(48);
        session()->put(self::SESSION_KEY.'.'.$this->scopeKey($scope).'.'.$token, now()->addHours(4)->timestamp);

        return $token;
    }

    public function consume(string $scope, mixed $token): void
    {
        if (! is_string($token) || $token === '') {
            return;
        }

        $this->prune();
        $key = self::SESSION_KEY.'.'.$this->scopeKey($scope).'.'.$token;

        if (! session()->has($key)) {
            throw ValidationException::withMessages([
                'form' => __('This form was already submitted or has expired. Refresh the page and review the saved records.'),
            ]);
        }

        session()->forget($key);
    }

    public function version(Model $model): string
    {
        return hash_hmac('sha256', $this->versionPayload($model), (string) config('app.key'));
    }

    public function assertFresh(Model $model, mixed $version): void
    {
        if (! is_string($version) || $version === '') {
            return;
        }

        if (! hash_equals($this->version($model), $version)) {
            throw ValidationException::withMessages([
                'form' => __('This record changed after you opened it. Reload the page before saving your changes.'),
            ]);
        }
    }

    private function versionPayload(Model $model): string
    {
        return implode('|', [
            $model::class,
            (string) $model->getKey(),
            (string) $model->getRawOriginal($model->getUpdatedAtColumn()),
        ]);
    }

    private function prune(): void
    {
        $tokens = session(self::SESSION_KEY, []);
        $now = now()->timestamp;

        foreach ($tokens as $scope => $scopeTokens) {
            foreach ((array) $scopeTokens as $token => $expiresAt) {
                if ((int) $expiresAt < $now) {
                    unset($tokens[$scope][$token]);
                }
            }

            if (($tokens[$scope] ?? []) === []) {
                unset($tokens[$scope]);
            }
        }

        session()->put(self::SESSION_KEY, $tokens);
    }

    private function scopeKey(string $scope): string
    {
        return hash('sha256', $scope);
    }
}
