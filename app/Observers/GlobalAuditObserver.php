<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class GlobalAuditObserver
{
    private static ?bool $auditTableExists = null;

    private array $ignoredAttributes = [
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        if ($this->shouldSkip($model) || !$this->canWriteAuditLog()) {
            return;
        }

        $newValues = $this->sanitizeAttributes($model->getAttributes());

        $this->safeCreate($this->basePayload($model, 'created') + [
            'new_values' => $newValues,
        ]);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model) || !$this->canWriteAuditLog()) {
            return;
        }

        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $oldValues = [];
        $newValues = [];
        $original = $model->getOriginal();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $this->ignoredAttributes, true)) {
                continue;
            }

            $oldValues[$field] = $original[$field] ?? null;
            $newValues[$field] = $model->getAttribute($field);
        }

        if (empty($newValues)) {
            return;
        }

        $this->safeCreate($this->basePayload($model, 'updated') + [
            'old_values' => $this->sanitizeAttributes($oldValues),
            'new_values' => $this->sanitizeAttributes($newValues),
        ]);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model) || !$this->canWriteAuditLog()) {
            return;
        }

        $oldValues = $this->sanitizeAttributes($model->getOriginal());

        $this->safeCreate($this->basePayload($model, 'deleted') + [
            'old_values' => $oldValues,
        ]);
    }

    private function shouldSkip(Model $model): bool
    {
        if ($model instanceof AuditLog) {
            return true;
        }

        if (app()->runningInConsole()) {
            return true;
        }

        return false;
    }

    private function sanitizeAttributes(array $attributes): array
    {
        foreach ($this->ignoredAttributes as $ignoredAttribute) {
            unset($attributes[$ignoredAttribute]);
        }

        return $attributes;
    }

    private function basePayload(Model $model, string $event): array
    {
        $request = request();
        $user = Auth::user();

        return [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'event' => $event,
            'auditable_type' => class_basename($model),
            'auditable_id' => $model->getKey(),
            'route_name' => $request?->route()?->getName(),
            'method' => $request?->method(),
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];
    }

    private function canWriteAuditLog(): bool
    {
        if (self::$auditTableExists === null) {
            self::$auditTableExists = Schema::hasTable('audit_logs');
        }

        return self::$auditTableExists;
    }

    private function safeCreate(array $payload): void
    {
        try {
            AuditLog::create($payload);
        } catch (\Throwable $e) {
            // If table was removed/not migrated after boot, disable logging for this request lifecycle.
            if ($this->isMissingTableError($e)) {
                self::$auditTableExists = false;
                return;
            }
        }
    }

    private function isMissingTableError(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Base table or view not found')
            || str_contains($message, "Table '")
            || str_contains($message, '1146');
    }
}
