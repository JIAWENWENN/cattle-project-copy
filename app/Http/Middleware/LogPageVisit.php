<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LogPageVisit
{
    private static ?bool $auditTableExists = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldLog($request, $response)) {
            return $response;
        }

        $user = Auth::user();

        if (!$this->canWriteAuditLog()) {
            return $response;
        }

        $this->safeCreate([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'event' => 'viewed',
            'auditable_type' => 'Page',
            'auditable_id' => null,
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => null,
            'new_values' => null,
        ]);

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if (!$request->isMethod('GET')) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        $ignoredPrefixes = ['api/', 'build/', 'storage/', 'vendor/'];

        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return $request->route() !== null;
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
            if ($this->isMissingTableError($e)) {
                self::$auditTableExists = false;
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
