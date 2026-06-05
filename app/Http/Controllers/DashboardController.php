<?php

namespace App\Http\Controllers;

use App\Models\Cattle;
use App\Models\CattleBreedingRecord;
use App\Models\Estate;
use App\Models\Herd;
use App\Models\AuditLog;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch custom field definitions for categories, conditions, statuses
        $customFieldCategories = \App\Models\CattleCustomField::where('field_type', 'category')->pluck('value')->toArray();
        $customFieldConditions = \App\Models\CattleCustomField::where('field_type', 'general_condition')->pluck('value')->toArray();
        $customFieldStatuses = \App\Models\CattleCustomField::where('field_type', 'status')->pluck('value')->toArray();
        if (empty($customFieldStatuses)) {
            $customFieldStatuses = ['Active', 'Sold', 'Deceased', 'Missing'];
        }

        // Build byCategory: start with all defined categories at 0, then overlay real counts
        $byCategoryDb = Cattle::selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
        $byCategory = [];
        foreach ($customFieldCategories as $cat) {
            $byCategory[$cat] = $byCategoryDb[$cat] ?? 0;
        }
        // Also include any DB categories not in custom fields
        foreach ($byCategoryDb as $cat => $count) {
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = $count;
            }
        }

        // Build byStatus: start with all defined statuses at 0, then overlay real counts
        $byStatusDb = Cattle::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
        $byStatus = [];
        foreach ($customFieldStatuses as $st) {
            $byStatus[$st] = $byStatusDb[$st] ?? 0;
        }
        foreach ($byStatusDb as $st => $count) {
            if (!isset($byStatus[$st])) {
                $byStatus[$st] = $count;
            }
        }

        // Build byCondition: start with all defined conditions at 0, then overlay real counts
        $byConditionDb = Cattle::selectRaw('general_condition, count(*) as count')
            ->whereNotNull('general_condition')
            ->groupBy('general_condition')
            ->pluck('count', 'general_condition')
            ->toArray();
        $byCondition = [];
        foreach ($customFieldConditions as $cond) {
            $byCondition[$cond] = $byConditionDb[$cond] ?? 0;
        }
        foreach ($byConditionDb as $cond => $count) {
            if (!isset($byCondition[$cond])) {
                $byCondition[$cond] = $count;
            }
        }

        $cattleStats = [
            'total' => Cattle::count(),
            'active' => Cattle::where('status', 'Active')->count(),
            'sold' => Cattle::where('status', 'Sold')->count(),
            'deceased' => Cattle::where('status', 'Deceased')->count(),
            'byCategory' => $byCategory,
            'byGender' => Cattle::selectRaw('gender, count(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray(),
            'byStatus' => $byStatus,
            'byCondition' => $byCondition,
            'byOwnership' => [],
        ];

        $breedingStats = [
            'total' => CattleBreedingRecord::count(),
            'pregnant' => CattleBreedingRecord::whereNotNull('breeding_date')
                ->whereNull('actual_calving_date')
                ->count(),
            'awaitingCalving' => CattleBreedingRecord::whereNotNull('expected_calving_date')
                ->whereNull('actual_calving_date')
                ->where('expected_calving_date', '<=', now()->addDays(30))
                ->count(),
            'byMethod' => CattleBreedingRecord::selectRaw('breeding_method, count(*) as count')
                ->whereNotNull('breeding_method')
                ->groupBy('breeding_method')
                ->pluck('count', 'breeding_method')
                ->toArray(),
        ];

        $calvingStats = [
            'total' => \App\Models\CalvingRecord::where('status', 'approved')->count(),
            'thisYear' => \App\Models\CalvingRecord::where('status', 'approved')
                ->whereYear('calving_date', now()->year)->count(),
            'thisMonth' => \App\Models\CalvingRecord::where('status', 'approved')
                ->whereMonth('calving_date', now()->month)
                ->whereYear('calving_date', now()->year)
                ->count(),
            'bySex' => \App\Models\CalvingRecord::selectRaw('sex, count(*) as count')
                ->where('status', 'approved')
                ->whereNotNull('sex')
                ->groupBy('sex')
                ->pluck('count', 'sex')
                ->toArray(),
            'recentCalvings' => \App\Models\CalvingRecord::where('status', 'approved')
                ->whereNotNull('calving_date')
                ->orderBy('calving_date', 'desc')
                ->limit(5)
                ->get(),
        ];

        $mortalityStats = [
            'total' => \App\Models\MortalityCase::where('status', 'completed')->count(),
            'thisMonth' => \App\Models\MortalityCase::where('status', 'completed')
                ->whereMonth('death_date', now()->month)
                ->whereYear('death_date', now()->year)
                ->count(),
            'thisYear' => \App\Models\MortalityCase::where('status', 'completed')
                ->whereYear('death_date', now()->year)
                ->count(),
            'mortalityRate' => Cattle::count() > 0
                ? round((\App\Models\MortalityCase::where('status', 'completed')->count() / Cattle::count()) * 100, 1)
                : 0,
            'byCause' => \App\Models\MortalityCase::where('status', 'completed')->whereNotNull('cause_of_death')->selectRaw('cause_of_death, count(*) as count')->groupBy('cause_of_death')->pluck('count', 'cause_of_death')->toArray(),
            'recentMortality' => \App\Models\MortalityCase::where('status', 'completed')->orderBy('death_date', 'desc')->limit(5)->get(),
        ];

        $healthStats = [
            'total' => \App\Models\Treatment::where('status', 'completed')->count(),
            'pendingVaccinations' => \App\Models\Treatment::where('category', 'Vaccination')->where('status', 'pending')->count(),
            'overdueTraatments' => \App\Models\Treatment::where('follow_up_required', true)->where('follow_up_done', false)->where('follow_up_date', '<', now())->count(),
            'underObservation' => Cattle::whereIn('general_condition', ['Poor', 'Sick'])->count(),
            'healthy' => Cattle::where('general_condition', 'Good')->orWhere('general_condition', 'Excellent')->count(),
        ];

        $inventoryStats = [
            'medications' => [
                'total' => Medication::count(),
                'totalStock' => Medication::sum('stock') ?? 0,
                'lowStock' => Medication::where('status', 'low')->count(),
                'outOfStock' => Medication::where('status', 'out_of_stock')->count(),
                'expiringSoon' => Medication::whereNotNull('expiry_date')
                    ->where('expiry_date', '>', now())
                    ->where('expiry_date', '<=', now()->addDays(30))
                    ->count(),
                'expired' => Medication::whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now())
                    ->count(),
                'criticalItems' => Medication::whereIn('status', ['low', 'out_of_stock', 'expired'])
                    ->orWhere(function ($q) {
                        $q->whereNotNull('expiry_date')->where('expiry_date', '<', now());
                    })
                    ->orWhere(function ($q) {
                        $q->whereNotNull('expiry_date')
                            ->where('expiry_date', '>', now())
                            ->where('expiry_date', '<=', now()->addDays(30));
                    })
                    ->orderBy('stock', 'asc')
                    ->limit(5)
                    ->get(['id', 'name', 'category', 'stock']),
            ],
        ];

        $criticalMeds = Medication::where('stock', '<', 50)
            ->orWhere(function ($q) {
                $q->where('expiry_date', '<=', now()->addDays(30))
                  ->where('expiry_date', '>', now());
            })
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get(['id', 'name', 'category', 'stock', 'expiry_date'])
            ->map(function ($item) {
                $isExpiring = $item->expiry_date && $item->expiry_date <= now()->addDays(30) && $item->expiry_date > now();
                return [
                    'id' => 'med-' . $item->id,
                    'name' => $item->name,
                    'type' => 'Medication',
                    'category' => $item->category,
                    'currentStock' => $item->stock,
                    'status' => $item->stock <= 0 ? 'critical' : ($isExpiring ? 'expiring' : 'low'),
                    'expiry_date' => $item->expiry_date,
                ];
            });

        $stockAlerts = $criticalMeds->values()->toArray();

        $operatingUnits = Estate::where('is_active', true)
            ->with('pastureBlocks:id,estate_id,name')
            ->withCount([
                'pastureBlocks as block_count',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'area']);

        foreach ($operatingUnits as $estate) {
            $blockNames = $estate->pastureBlocks
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();

            $estate->cattle_count = $blockNames->isEmpty()
                ? Cattle::where('location_block', $estate->name)->count()
                : Cattle::whereIn('location_block', $blockNames)
                    ->orWhere('location_block', $estate->name)
                    ->count();

            unset($estate->pastureBlocks);
        }

        $estatesStats = [
            'totalEstates' => Estate::count(),
            'activeEstates' => Estate::where('is_active', true)->count(),
            'totalArea' => Estate::where('is_active', true)->sum('area'),
            'totalHerds' => Herd::count(),
            'estates' => $operatingUnits,
            'operatingUnits' => $operatingUnits,
        ];

        $pendingApprovals = $this->collectPendingApprovals();
        $pendingApprovalRoles = collect($pendingApprovals)
            ->pluck('submitted_by')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $activityRaw = AuditLog::with('user:id,name,email,role')
            ->latest()
            ->limit(300)
            ->get();

        $activityLog = $activityRaw->map(function ($log) {
            $module = $this->resolveActivityModule($log);
            $action = $this->resolveActivityAction($log);

            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name ?? $log->user_name ?? 'System',
                'user_email' => $log->user?->email ?? $log->user_email ?? '-',
                'role' => $log->user?->role ?? 'Staff',
                'module' => $module,
                'action' => $action,
                'details' => $this->resolveActivityDetails($log, $action),
                'created_at' => $log->created_at,
            ];
        })->values()->toArray();

        $activitySummary = [
            'today' => AuditLog::whereDate('created_at', now()->toDateString())->count(),
            'this_week' => AuditLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => AuditLog::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        $userStats = [
            'total' => User::count(),
            'active' => User::where('status', 'Active')->count(),
            'inactive' => User::where('status', '!=', 'Active')->count(),
            'byRole' => User::selectRaw('role, count(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray(),
            'recentUsers' => User::latest()->limit(10)->get(['id', 'name', 'email', 'role', 'status', 'created_at', 'last_login_at']),
            'recentActivity' => AuditLog::where('user_id', '!=', null)
                ->select('user_id')
                ->selectRaw('MAX(created_at) as last_activity')
                ->groupBy('user_id')
                ->orderByDesc('last_activity')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $user = User::find($item->user_id);
                    return [
                        'user_id' => $item->user_id,
                        'user_name' => $user?->name ?? 'Unknown',
                        'user_email' => $user?->email ?? '',
                'last_activity' => $item->last_activity,
                        'last_login' => $user?->last_login_at,
                    ];
                })
                ->toArray(),
        ];

        $upcomingEvents = collect([]);

        // 1. Expected Calvings
        $expectedCalvings = \App\Models\CattleBreedingRecord::whereNotNull('expected_calving_date')
            ->whereNull('actual_calving_date')
            ->where('expected_calving_date', '>=', now())
            ->with('cattle')
            ->get();
        foreach ($expectedCalvings as $calving) {
            $upcomingEvents->push([
                'type' => 'Expected Calving',
                'title' => 'Cow #' . ($calving->cattle->tag_no ?? 'Unknown'),
                'date' => $calving->expected_calving_date,
                'icon' => 'Baby',
                'color' => 'pink',
            ]);
        }

        // 2. Vaccination Due
        $vaccinations = \App\Models\Treatment::where('category', 'Vaccination')
            ->where('status', 'pending')
            ->get();
        $vaccinationCount = $vaccinations->count();
        if ($vaccinationCount > 0) {
            $earliest = $vaccinations->min('date') ?? now()->toDateString();
            $upcomingEvents->push([
                'type' => 'Vaccination Due',
                'title' => $vaccinationCount . ' cattle',
                'date' => $earliest,
                'icon' => 'Activity',
                'color' => 'blue',
            ]);
        }

        // 3. Medication Expiry
        $expiringMeds = \App\Models\Medication::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->get();
        foreach ($expiringMeds as $med) {
            $upcomingEvents->push([
                'type' => 'Medication Expiry',
                'title' => $med->name,
                'date' => $med->expiry_date,
                'icon' => 'AlertTriangle',
                'color' => 'amber',
            ]);
        }

        // 4. Weaning Due
        $weaningDate = now()->subMonths(6);
        $weaningCalvesCount = \App\Models\Cattle::where('category', 'Calves')
            ->whereNotNull('birth_date')
            ->where('birth_date', '<=', $weaningDate)
            ->count();
        if ($weaningCalvesCount > 0) {
            $upcomingEvents->push([
                'type' => 'Weaning Due',
                'title' => $weaningCalvesCount . ' calves',
                'date' => now()->toDateString(),
                'icon' => 'Beef',
                'color' => 'purple',
            ]);
        }

        // 5. Scheduled Transfer
        $pendingTransfers = \App\Models\TransferDocument::whereIn('status', ['pending', 'in_progress'])
            ->where('date', '>=', now()->toDateString())
            ->get();
        foreach ($pendingTransfers as $transfer) {
            $upcomingEvents->push([
                'type' => 'Scheduled Transfer',
                'title' => ($transfer->total_cattle ?? 0) . ' cattle to ' . ($transfer->to_location ?? 'Unknown'),
                'date' => $transfer->date,
                'icon' => 'Truck',
                'color' => 'blue',
            ]);
        }

        $upcomingEvents = $upcomingEvents->sortBy('date')->take(6)->values()->toArray();

        // Only count CTV transfers with completed workflow that are not reopened
        $ctvCompletedQuery = fn() => \App\Models\TransferDocument::where('type', \App\Models\TransferDocument::TYPE_CTV)
            ->where('status', \App\Models\TransferDocument::STATUS_COMPLETED)
            ->where(function ($q) {
                $q->where('is_reopened', false)->orWhereNull('is_reopened');
            });

        $ctvBaseQuery = fn() => \App\Models\TransferDocument::where('type', \App\Models\TransferDocument::TYPE_CTV);

        $transferStats = [
            'total' => $ctvCompletedQuery()->count(),
            'pending' => $ctvBaseQuery()->whereIn('status', ['pending', 'in_progress'])->count(),
            'completed' => $ctvCompletedQuery()->count(),
            'thisMonth' => $ctvCompletedQuery()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'inTransit' => $ctvBaseQuery()->whereIn('current_step', ['transported', 'witness_transit', 'verified_transit'])->count(),
            'byType' => \App\Models\TransferDocument::where('type', \App\Models\TransferDocument::TYPE_CTV)->selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type')->toArray(),
            'recentTransfers' => $ctvCompletedQuery()->latest('date')->limit(5)->get(),
            'pendingTransfers' => $ctvBaseQuery()->whereIn('status', ['pending', 'in_progress'])->latest('created_at')->limit(5)->get(),
        ];

        return Inertia::render('AdminDashboard', [
            'cattleStats' => $cattleStats,
            'breedingStats' => $breedingStats,
            'calvingStats' => $calvingStats,
            'mortalityStats' => $mortalityStats,
            'healthStats' => $healthStats,
            'inventoryStats' => $inventoryStats,
            'stockAlerts' => $stockAlerts,
            'estatesStats' => $estatesStats,
            'pendingApprovals' => $pendingApprovals,
            'pendingApprovalRoles' => $pendingApprovalRoles,
            'activityLog' => $activityLog,
            'activitySummary' => $activitySummary,
            'userStats' => $userStats,
            'upcomingEvents' => $upcomingEvents,
            'transferStats' => $transferStats,
        ]);
    }

    private function collectPendingApprovals(): array
    {
        $mortality = \App\Models\MortalityCase::query()
            ->with('creator:id,name,role')
            ->where('status', '!=', 'completed')
            ->select(['id', 'lmc_no', 'created_by', 'created_at', 'status', 'current_step'])
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Mortality',
                    'reference' => $item->lmc_no ?: ('MOR-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT)),
                    'submitted_by' => $item->creator?->role ? ucwords(str_replace('_', ' ', $item->creator->role)) : 'Unknown',
                    'submitted_by_name' => $item->creator?->name ?? 'Unknown',
                    'date' => optional($item->created_at)?->toDateString(),
                    'status' => $this->formatWorkflowStep($item->current_step ?: $item->status),
                    'action_url' => '/mortality/' . $item->id . '/workflow',
                ];
            });

        $calving = \App\Models\CalvingRecord::query()
            ->with('creator:id,name,role')
            ->where('status', '!=', 'completed')
            ->select(['id', 'lcc_running_number', 'created_by', 'calving_date', 'created_at', 'status'])
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Calving',
                    'reference' => $item->lcc_running_number ?: ('CLV-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT)),
                    'submitted_by' => $item->creator?->role ? ucwords(str_replace('_', ' ', $item->creator->role)) : 'Unknown',
                    'submitted_by_name' => $item->creator?->name ?? 'Unknown',
                    'date' => optional($item->calving_date ?: $item->created_at)?->toDateString(),
                    'status' => $this->formatWorkflowStep($item->status),
                    'action_url' => '/calving/' . $item->id,
                ];
            });

        $transfer = \App\Models\TransferDocument::query()
            ->with('creator:id,name,role')
            ->where('status', '!=', \App\Models\TransferDocument::STATUS_COMPLETED)
            ->select(['id', 'document_no', 'created_by', 'date', 'created_at', 'status', 'current_step', 'type'])
            ->get()
            ->map(function ($item) {
                $typePrefix = match($item->type) {
                    \App\Models\TransferDocument::TYPE_CTV => 'ctv',
                    \App\Models\TransferDocument::TYPE_RECEIVAL => 'receival',
                    \App\Models\TransferDocument::TYPE_SIV => 'siv',
                    default => 'ctv',
                };
                return [
                    'type' => 'Transfer',
                    'reference' => $item->document_no ?: ('TRF-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT)),
                    'submitted_by' => $item->creator?->role ? ucwords(str_replace('_', ' ', $item->creator->role)) : 'Unknown',
                    'submitted_by_name' => $item->creator?->name ?? 'Unknown',
                    'date' => optional($item->date ?: $item->created_at)?->toDateString(),
                    'status' => $this->formatWorkflowStep($item->current_step ?: $item->status),
                    'action_url' => '/transfer/' . $typePrefix . '/' . $item->id . '/workflow',
                ];
            });

        $health = \App\Models\Treatment::query()
            ->with('creator:id,name,role')
            ->where('status', '!=', 'completed')
            ->select(['id', 'treatment_no', 'created_by', 'date', 'created_at', 'status', 'current_step'])
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Health',
                    'reference' => $item->treatment_no ?: ('TRT-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT)),
                    'submitted_by' => $item->creator?->role ? ucwords(str_replace('_', ' ', $item->creator->role)) : 'Unknown',
                    'submitted_by_name' => $item->creator?->name ?? 'Unknown',
                    'date' => optional($item->date ?: $item->created_at)?->toDateString(),
                    'status' => $this->formatWorkflowStep($item->current_step ?: $item->status),
                    'action_url' => '/health/treatment/' . $item->id,
                ];
            });

        return (new Collection())
            ->merge($mortality)
            ->merge($calving)
            ->merge($transfer)
            ->merge($health)
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    private function formatWorkflowStep(?string $value): string
    {
        if (!$value) {
            return 'Pending';
        }

        $normalized = strtolower(trim($value));
        $map = [
            'pending' => 'Step 1 - Issued By',
            'issued' => 'Step 1 - Issued By',
            'prepared' => 'Step 1 - Prepared By',
            'verified' => 'Step 2 - Verified By',
            'checked' => 'Step 3 - Checked By',
            'witness' => 'Step 4 - Witnessed By',
            'witnessed' => 'Step 4 - Witnessed By',
            'approved' => 'Step 5 - Approved By',
            'transported' => 'Step 3 - Transported By',
            'witness_transit' => 'Step 4 - Witness Transit',
            'verified_transit' => 'Step 5 - Verified Transit',
            'witness_receive' => 'Step 6 - Witness Receive',
            'received' => 'Step 7 - Received By',
            'under_review' => 'Under Review',
            'pm_examination' => 'PM Examination',
            'in_progress' => 'In Progress',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
        ];

        return $map[$normalized] ?? ucwords(str_replace('_', ' ', $normalized));
    }

    private function resolveActivityModule(AuditLog $log): string
    {
        $route = strtolower((string) $log->route_name);
        $type = strtolower((string) $log->auditable_type);
        $url = strtolower((string) $log->url);
        $combined = "{$route} {$type} {$url}";

        $map = [
            'cattle' => 'Cattle',
            'breeding' => 'Breeding',
            'calving' => 'Calving',
            'mortality' => 'Mortality',
            'inventory' => 'Inventory',
            'feed' => 'Inventory',
            'stock' => 'Inventory',
            'treatment' => 'Health',
            'health' => 'Health',
            'medication' => 'Health',
            'driver' => 'Driver',
            'delivery' => 'Driver',
            'transfer' => 'Transfer',
            'approval' => 'Approval',
            'permission' => 'Settings',
            'user' => 'Users',
            'task' => 'Task',
            'estate' => 'Groups',
            'herd' => 'Groups',
            'grazing' => 'Pasture',
        ];

        foreach ($map as $needle => $module) {
            if (str_contains($combined, $needle)) {
                return $module;
            }
        }

        return 'System';
    }

    private function resolveActivityAction(AuditLog $log): string
    {
        $route = strtolower((string) $log->route_name);

        if (str_contains($route, 'approve')) return 'APPROVE';
        if (str_contains($route, 'reject')) return 'REJECT';

        return match (strtolower((string) $log->event)) {
            'created' => 'CREATE',
            'updated' => 'UPDATE',
            'deleted' => 'DELETE',
            'viewed' => 'VIEW',
            default => strtoupper((string) $log->event ?: 'SYSTEM'),
        };
    }

    private function resolveActivityDetails(AuditLog $log, string $action): string
    {
        $newValues = is_array($log->new_values) ? $log->new_values : [];
        $oldValues = is_array($log->old_values) ? $log->old_values : [];

        $identifier = $newValues['tag_no']
            ?? $newValues['treatment_no']
            ?? $newValues['lcc_running_number']
            ?? $oldValues['tag_no']
            ?? $oldValues['treatment_no']
            ?? $oldValues['lcc_running_number']
            ?? ($log->auditable_id ? ('#' . $log->auditable_id) : null);

        $prefix = match ($action) {
            'CREATE' => 'Added',
            'UPDATE' => 'Updated',
            'DELETE' => 'Deleted',
            'APPROVE' => 'Approved',
            'REJECT' => 'Rejected',
            'VIEW' => 'Viewed',
            default => 'Activity',
        };

        $target = class_basename((string) $log->auditable_type);
        if ($target === 'Page') {
            $target = (string) ($log->route_name ?: 'Page');
        }

        return $identifier ? "{$prefix} {$identifier}" : "{$prefix} {$target}";
    }

    public function userStats(Request $request)
    {
        $userStats = [
            'total' => User::count(),
            'active' => User::where('status', 'Active')->count(),
            'inactive' => User::where('status', '!=', 'Active')->count(),
            'byRole' => User::selectRaw('role, count(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray(),
            'recentUsers' => User::latest()->limit(10)->get(['id', 'name', 'email', 'role', 'status', 'created_at', 'last_login_at']),
            'recentActivity' => AuditLog::where('user_id', '!=', null)
                ->select('user_id')
                ->selectRaw('MAX(created_at) as last_activity')
                ->groupBy('user_id')
                ->orderByDesc('last_activity')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $user = User::find($item->user_id);
                    return [
                        'user_id' => $item->user_id,
                        'user_name' => $user?->name ?? 'Unknown',
                        'user_email' => $user?->email ?? '',
                        'role' => $user?->role ?? 'Staff',
                        'last_activity' => $item->last_activity,
                        'last_login' => $user?->last_login_at,
                    ];
                })
                ->toArray(),
        ];

        return response()->json($userStats);
    }
}
