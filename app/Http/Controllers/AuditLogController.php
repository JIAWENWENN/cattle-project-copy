<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->latest();

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }

        if ($request->filled('model')) {
            $query->where('auditable_type', $request->string('model'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        $models = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->values();

        return Inertia::render('AuditLogs', [
            'logs' => $logs,
            'models' => $models,
            'filters' => [
                'search' => (string) $request->string('search'),
                'event' => (string) $request->string('event'),
                'model' => (string) $request->string('model'),
                'user_id' => (string) $request->string('user_id'),
                'date_from' => (string) $request->string('date_from'),
                'date_to' => (string) $request->string('date_to'),
            ],
        ]);
    }
}

