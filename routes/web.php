<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CattleController;
use App\Http\Controllers\WeeklyCattleReturnController;
use App\Http\Controllers\DataInputController;
use App\Http\Controllers\FeedingController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\MortalityController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\CalvingController;
use App\Http\Controllers\CalvingChecklistController;
use App\Http\Controllers\HealthTreatmentController;
use App\Http\Controllers\VeterinaryContactController;
use App\Http\Controllers\PastureController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskNotificationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\FieldLevelPermissionController;
use App\Http\Controllers\DocumentEndorsementController;
use App\Http\Controllers\Analytics\PerformanceSummaryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Http\Request;

// ==========================================
// PUBLIC ROUTES
// ==========================================

// Redirect root to login
Route::get('/', function () {
    return redirect('/login');
});

// Debug file upload test route
Route::post('/test-upload', function (Request $request) {
    Log::info('TEST UPLOAD DEBUG', [
        'has_file' => $request->hasFile('signed_document'),
        'all_files' => $request->allFiles(),
        'php_files' => $_FILES,
        'content_type' => $request->header('Content-Type'),
        'all_input' => $request->all(),
        'php_input' => file_get_contents('php://input'),
    ]);

    return response()->json([
        'has_file' => $request->hasFile('signed_document'),
        'all_files' => array_keys($request->allFiles()),
        'php_files' => $_FILES,
        'content_type' => $request->header('Content-Type'),
    ]);
});

// ==========================================
// AUTHENTICATION ROUTES
// ==========================================

// Login routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');

// Logout route
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Fallback for accidental GET /logout requests
Route::get('/logout', function () {
    return redirect('/login');
});

// ==========================================
// AUTHENTICATED ROUTES
// ==========================================

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:Dashboard')->name('dashboard');

    // API for real-time user stats
    Route::get('/api/dashboard/user-stats', [DashboardController::class, 'userStats'])
        ->middleware('permission:Dashboard')
        ->name('dashboard.user-stats');

    // Admin Dashboard
    Route::get('/admin-dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:Dashboard')->name('admin.dashboard');

    // ==========================================
    // PROFILE ROUTES
    // ==========================================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Profile Photo Upload
    Route::post('/profile/photo', function (Request $request) {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:4096'
        ]);

        $user = auth()->user();

        if (! $request->hasFile('photo')) {
            return response()->json(['success' => false, 'message' => 'No file uploaded'], 400);
        }

        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('photo')->store('profile-photos', 'public');
        $user->profile_photo = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'photo_path' => $path,
            'photo_url' => Storage::url($path),
            'message' => 'Profile photo updated successfully.'
        ]);
    })->name('profile.photo.upload');

    // Delete current user profile photo
    Route::delete('/profile/photo', function () {
        $user = auth()->user();
        
        if (!$user->profile_photo) {
            return response()->json(['success' => true, 'message' => 'No profile photo to remove.']);
        }
        
        if (Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }
        
        $user->profile_photo = null;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Profile photo removed successfully.'
        ]);
    })->name('profile.photo.delete');

    // Get current user profile photo
    Route::get('/profile/photo', function () {
        $user = auth()->user();
        return response()->json([
            'photo_path' => $user->profile_photo,
            'photo_url' => $user->profile_photo ? Storage::url($user->profile_photo) : null,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role
        ]);
    })->name('profile.photo');

    // ==========================================
    // USER MANAGEMENT ROUTES
    // ==========================================
    Route::middleware('permission:Settings,view')->group(function () {
        // View Users
        Route::get('/users', function () {
            $users = \App\Models\User::all();
            $availableRoles = $users->pluck('role')->unique()->values()->toArray();

            return Inertia::render('UserManagement', [
                'users' => $users,
                'availableRoles' => $availableRoles
            ]);
        })->name('users.index');

        // Access Control Matrix
        Route::get('/access-control', function () {
            $users = \App\Models\User::all(['id', 'name', 'email', 'role']);
            $existingPermissions = \App\Models\Permission::all();
            $workflowAssignment = \App\Models\TreatmentWorkflowAssignment::first();
            $calvingWorkflowAssignment = \Illuminate\Support\Facades\Schema::hasTable('calving_workflow_assignments')
                ? \App\Models\CalvingWorkflowAssignment::first()
                : null;
            $transferWorkflowAssignment = \Illuminate\Support\Facades\Schema::hasTable('transfer_workflow_assignments')
                ? \App\Models\TransferWorkflowAssignment::first()
                : null;
            $workflowAssignments = \Illuminate\Support\Facades\Schema::hasTable('workflow_assignments')
                ? \App\Models\WorkflowAssignment::query()
                    ->get(['module', 'assignments'])
                    ->mapWithKeys(fn ($assignment) => [$assignment->module => $assignment->assignments ?? []])
                : [];

            return Inertia::render('AccessControlMatrix', [
                'users' => $users,
                'existingPermissions' => $existingPermissions,
                'workflowAssignment' => $workflowAssignment,
                'calvingWorkflowAssignment' => $calvingWorkflowAssignment,
                'transferWorkflowAssignment' => $transferWorkflowAssignment,
                'workflowAssignments' => $workflowAssignments,
            ]);
        })->name('access-control.index');

        // Field Level Permissions
        Route::get('/field-permissions', [FieldLevelPermissionController::class, 'index'])->name('field-permissions.index');
        Route::post('/field-permissions', [FieldLevelPermissionController::class, 'update'])->name('field-permissions.update');

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // Create User
    Route::post('/users', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|string',
            'status' => 'required|string'
        ]);

        \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'email_verified_at' => now()
        ]);

        return redirect()->route('users.index');
    })->middleware('permission:Settings,create')->name('users.store');

    // Update User
    Route::put('/users/{user}', function (Request $request, \App\Models\User $user) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8',
            'role' => 'required|string',
            'status' => 'required|string'
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        // Only update password if provided
        if (!empty($validated['password'])) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        return redirect()->route('users.index');
    })->middleware('permission:Settings,edit')->name('users.update');

    // Delete User
    Route::delete('/users/{user}', function (\App\Models\User $user) {
        $user->delete();
        return redirect()->route('users.index');
    })->middleware('permission:Settings,delete')->name('users.destroy');

    // ==========================================
    // PERMISSIONS ROUTES
    // ==========================================

    // Save Permissions
    Route::post('/permissions/save', function (Request $request) {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.user_id' => 'required|exists:users,id',
            'permissions.*.module' => 'required|string',
            'permissions.*.permission' => ['required'],
        ]);

        $modulePermissionOverrides = [
            'Dashboard' => ['no-access', 'view'],
            'Weekly Return' => ['no-access', 'view', 'full'],
        ];

        foreach ($validated['permissions'] as $perm) {
            $permissionList = \App\Models\Permission::normalizePermissionList($perm['permission']);
            if (isset($modulePermissionOverrides[$perm['module']])) {
                $permissionList = array_values(array_intersect($permissionList, $modulePermissionOverrides[$perm['module']]));
                if (in_array('full', $permissionList, true)) {
                    $permissionList = ['full'];
                } elseif (empty($permissionList)) {
                    $permissionList = ['no-access'];
                }
            }
            \App\Models\Permission::updateOrCreate(
                [
                    'user_id' => $perm['user_id'],
                    'module' => $perm['module']
                ],
                [
                    'permission' => \App\Models\Permission::encodePermissionList($permissionList)
                ]
            );
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    })->middleware('permission:Settings,edit')->name('permissions.save');

    Route::post('/permissions/treatment-workflow-assignment', function (Request $request) {
        $validated = $request->validate([
            'prepared_by_user_ids' => 'nullable|array',
            'prepared_by_user_ids.*' => 'integer|exists:users,id',
            'checked_by_user_ids' => 'nullable|array',
            'checked_by_user_ids.*' => 'integer|exists:users,id',
            'approved_by_user_ids' => 'nullable|array',
            'approved_by_user_ids.*' => 'integer|exists:users,id',
        ]);

        $preparedIds = array_values(array_unique(array_map('intval', $validated['prepared_by_user_ids'] ?? [])));
        $checkedIds = array_values(array_unique(array_map('intval', $validated['checked_by_user_ids'] ?? [])));
        $approvedIds = array_values(array_unique(array_map('intval', $validated['approved_by_user_ids'] ?? [])));

        \App\Models\TreatmentWorkflowAssignment::updateOrCreate(
            ['id' => 1],
            [
                'prepared_by_user_ids' => $preparedIds,
                'checked_by_user_ids' => $checkedIds,
                'approved_by_user_ids' => $approvedIds,
                // Backward compatibility (first selected user)
                'prepared_by_user_id' => $preparedIds[0] ?? null,
                'checked_by_user_id' => $checkedIds[0] ?? null,
                'approved_by_user_id' => $approvedIds[0] ?? null,
            ]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Treatment workflow assignment saved.']);
        }

        return redirect()->back()->with('success', 'Treatment workflow assignment saved.');
    })->middleware('permission:Settings,edit')->name('permissions.treatment-workflow-assignment.save');

    Route::post('/permissions/calving-workflow-assignment', function (Request $request) {
        if (!\Illuminate\Support\Facades\Schema::hasTable('calving_workflow_assignments')) {
            return response()->json([
                'success' => false,
                'message' => 'Calving workflow table is missing. Please run migrations first.'
            ], 422);
        }

        $validated = $request->validate([
            'issued_by_user_ids' => 'nullable|array',
            'issued_by_user_ids.*' => 'integer|exists:users,id',
            'verified_by_user_ids' => 'nullable|array',
            'verified_by_user_ids.*' => 'integer|exists:users,id',
            'checked_by_user_ids' => 'nullable|array',
            'checked_by_user_ids.*' => 'integer|exists:users,id',
            'witnessed_by_user_ids' => 'nullable|array',
            'witnessed_by_user_ids.*' => 'integer|exists:users,id',
            'approved_by_user_ids' => 'nullable|array',
            'approved_by_user_ids.*' => 'integer|exists:users,id',
        ]);

        $issuedIds = array_values(array_unique(array_map('intval', $validated['issued_by_user_ids'] ?? [])));
        $verifiedIds = array_values(array_unique(array_map('intval', $validated['verified_by_user_ids'] ?? [])));
        $checkedIds = array_values(array_unique(array_map('intval', $validated['checked_by_user_ids'] ?? [])));
        $witnessedIds = array_values(array_unique(array_map('intval', $validated['witnessed_by_user_ids'] ?? [])));
        $approvedIds = array_values(array_unique(array_map('intval', $validated['approved_by_user_ids'] ?? [])));

        \App\Models\CalvingWorkflowAssignment::updateOrCreate(
            ['id' => 1],
            [
                'issued_by_user_ids' => $issuedIds,
                'verified_by_user_ids' => $verifiedIds,
                'checked_by_user_ids' => $checkedIds,
                'witnessed_by_user_ids' => $witnessedIds,
                'approved_by_user_ids' => $approvedIds,
                // Backward compatibility (first selected user)
                'issued_by_user_id' => $issuedIds[0] ?? null,
                'verified_by_user_id' => $verifiedIds[0] ?? null,
                'checked_by_user_id' => $checkedIds[0] ?? null,
                'witnessed_by_user_id' => $witnessedIds[0] ?? null,
                'approved_by_user_id' => $approvedIds[0] ?? null,
            ]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Calving workflow assignment saved.']);
        }

        return redirect()->back()->with('success', 'Calving workflow assignment saved.');
    })->middleware('permission:Settings,edit')->name('permissions.calving-workflow-assignment.save');

    Route::post('/permissions/transfer-workflow-assignment', function (Request $request) {
        if (!\Illuminate\Support\Facades\Schema::hasTable('transfer_workflow_assignments')) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer workflow table is missing. Please run migrations first.'
            ], 422);
        }

        $validated = $request->validate([
            'issued_by_user_ids' => 'nullable|array',
            'issued_by_user_ids.*' => 'integer|exists:users,id',
            'approved_by_user_ids' => 'nullable|array',
            'approved_by_user_ids.*' => 'integer|exists:users,id',
            'transported_by_user_ids' => 'nullable|array',
            'transported_by_user_ids.*' => 'integer|exists:users,id',
            'witnessed_transit_by_user_ids' => 'nullable|array',
            'witnessed_transit_by_user_ids.*' => 'integer|exists:users,id',
            'verified_transit_by_user_ids' => 'nullable|array',
            'verified_transit_by_user_ids.*' => 'integer|exists:users,id',
            'witnessed_receive_by_user_ids' => 'nullable|array',
            'witnessed_receive_by_user_ids.*' => 'integer|exists:users,id',
            'received_by_user_ids' => 'nullable|array',
            'received_by_user_ids.*' => 'integer|exists:users,id',
            'completed_by_user_ids' => 'nullable|array',
            'completed_by_user_ids.*' => 'integer|exists:users,id',
        ]);

        $issuedIds = array_values(array_unique(array_map('intval', $validated['issued_by_user_ids'] ?? [])));
        $approvedIds = array_values(array_unique(array_map('intval', $validated['approved_by_user_ids'] ?? [])));
        $transportedIds = array_values(array_unique(array_map('intval', $validated['transported_by_user_ids'] ?? [])));
        $witnessedTransitIds = array_values(array_unique(array_map('intval', $validated['witnessed_transit_by_user_ids'] ?? [])));
        $verifiedTransitIds = array_values(array_unique(array_map('intval', $validated['verified_transit_by_user_ids'] ?? [])));
        $witnessedReceiveIds = array_values(array_unique(array_map('intval', $validated['witnessed_receive_by_user_ids'] ?? [])));
        $receivedIds = array_values(array_unique(array_map('intval', $validated['received_by_user_ids'] ?? [])));
        $completedIds = array_values(array_unique(array_map('intval', $validated['completed_by_user_ids'] ?? [])));

        \App\Models\TransferWorkflowAssignment::updateOrCreate(
            ['id' => 1],
            [
                'issued_by_user_ids' => $issuedIds,
                'approved_by_user_ids' => $approvedIds,
                'transported_by_user_ids' => $transportedIds,
                'witnessed_transit_by_user_ids' => $witnessedTransitIds,
                'verified_transit_by_user_ids' => $verifiedTransitIds,
                'witnessed_receive_by_user_ids' => $witnessedReceiveIds,
                'received_by_user_ids' => $receivedIds,
                'completed_by_user_ids' => $completedIds,
                'issued_by_user_id' => $issuedIds[0] ?? null,
                'approved_by_user_id' => $approvedIds[0] ?? null,
                'transported_by_user_id' => $transportedIds[0] ?? null,
                'witnessed_transit_by_user_id' => $witnessedTransitIds[0] ?? null,
                'verified_transit_by_user_id' => $verifiedTransitIds[0] ?? null,
                'witnessed_receive_by_user_id' => $witnessedReceiveIds[0] ?? null,
                'received_by_user_id' => $receivedIds[0] ?? null,
                'completed_by_user_id' => $completedIds[0] ?? null,
            ]
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Transfer workflow assignment saved.']);
        }

        return redirect()->back()->with('success', 'Transfer workflow assignment saved.');
    })->middleware('permission:Settings,edit')->name('permissions.transfer-workflow-assignment.save');

    Route::post('/permissions/workflow-assignment', function (Request $request) {
        if (!\Illuminate\Support\Facades\Schema::hasTable('workflow_assignments')) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow assignment table is missing. Please run migrations first.'
            ], 422);
        }

        $validated = $request->validate([
            'module' => 'required|string|max:255',
            'assignments' => 'required|array',
            'assignments.*' => 'array',
            'assignments.*.*' => 'integer|exists:users,id',
        ]);

        $assignments = collect($validated['assignments'])
            ->map(fn ($ids) => array_values(array_unique(array_map('intval', $ids ?? []))))
            ->all();

        \App\Models\WorkflowAssignment::updateOrCreate(
            ['module' => $validated['module']],
            ['assignments' => $assignments]
        );

        return response()->json(['success' => true, 'message' => 'Workflow assignment saved.']);
    })->middleware('permission:Settings,edit')->name('permissions.workflow-assignment.save');

    // ==========================================
    // CATTLE MANAGEMENT ROUTES
    // ==========================================

    // List Cattle
    Route::get('/cattle', [CattleController::class, 'index'])
        ->middleware('permission:Cattle Directory,view')
        ->name('cattle.index');

    // Create Cattle
    Route::post('/cattle', [CattleController::class, 'store'])
        ->middleware('permission:Cattle Directory,create')
        ->name('cattle.store');

    // Update Cattle
    Route::put('/cattle/{cattle}', [CattleController::class, 'update'])
        ->middleware('permission:Cattle Directory,edit')
        ->name('cattle.update');

    // Weekly Cattle Return Report
    Route::get('/cattle/weekly-return', [WeeklyCattleReturnController::class, 'index'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return');

    // Daily Operation Master List (DOML)
    Route::get('/cattle/daily-operation', [\App\Http\Controllers\DailyOperationController::class, 'index'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation');

    Route::post('/cattle/daily-operation', [\App\Http\Controllers\DailyOperationController::class, 'store'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation.store');

    Route::post('/cattle/daily-operation/workflow/upload', [\App\Http\Controllers\DailyOperationController::class, 'uploadWorkflowDocument'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation.workflow.upload');

    Route::post('/cattle/daily-operation/workflow/complete', [\App\Http\Controllers\DailyOperationController::class, 'markWorkflowCompleted'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation.workflow.complete');

    Route::post('/cattle/daily-operation/workflow/reopen', [\App\Http\Controllers\DailyOperationController::class, 'reopenWorkflow'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation.workflow.reopen');

    Route::get('/cattle/daily-operation/workflow/download/{stepIndex}', [\App\Http\Controllers\DailyOperationController::class, 'downloadWorkflowDocument'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation.workflow.download');

    Route::get('/cattle/daily-operation/export', [\App\Http\Controllers\DailyOperationController::class, 'export'])
        ->middleware('permission:Daily Operation DOML')
        ->name('cattle.daily-operation.export');

    Route::get('/cattle/weekly-return/workflow', [WeeklyCattleReturnController::class, 'workflow'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return.workflow');
    Route::post('/cattle/weekly-return/upload-endorsement', [WeeklyCattleReturnController::class, 'uploadEndorsement'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return.upload-endorsement');
    Route::get('/cattle/weekly-return/download-endorsement/{stepIndex}', [WeeklyCattleReturnController::class, 'downloadEndorsement'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return.download-endorsement');
    Route::get('/cattle/weekly-return/endorsement-form', [WeeklyCattleReturnController::class, 'downloadEndorsementForm'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return.endorsement-form');
    Route::post('/cattle/weekly-return/mark-completed', [WeeklyCattleReturnController::class, 'markCompleted'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return.mark-completed');
    Route::post('/cattle/weekly-return/reopen', [WeeklyCattleReturnController::class, 'reopenWorkflow'])
        ->middleware('permission:Weekly Return')
        ->name('cattle.weekly-return.reopen');

    // Performance Summary Report
    Route::get('/analytics/performance-summary', [App\Http\Controllers\Analytics\PerformanceSummaryController::class, 'index'])
        ->middleware(['auth', 'verified', 'permission:Performance Summary,view'])
        ->name('analytics.performance-summary');

    Route::get('/cattle/{cattle}', [CattleController::class, 'show'])
        ->middleware('permission:Cattle Directory,view')
        ->name('cattle.show');

    // Delete Cattle
    Route::delete('/cattle/{cattle}', [CattleController::class, 'destroy'])
        ->middleware('permission:Cattle Directory,delete')
        ->name('cattle.destroy');

    // Export Cattle
    Route::get('/export-cattle', [CattleController::class, 'export'])
        ->middleware('permission:Cattle Directory,view')
        ->name('cattle.export');

    // Test Export Route (temporary)
    Route::get('/test-export', function() {
        return 'Export route is working!';
    })->name('test.export');

    // Custom Fields (Cattle)
    Route::post('/custom-fields', [CattleController::class, 'storeCustomField'])
        ->middleware('permission:Cattle Directory,edit')
        ->name('custom-fields.store');
    Route::put('/custom-fields/{customField}', [CattleController::class, 'updateCustomField'])
        ->middleware('permission:Cattle Directory,edit')
        ->name('custom-fields.update');
    Route::delete('/custom-fields/{customField}', [CattleController::class, 'destroyCustomField'])
        ->middleware('permission:Cattle Directory,delete')
        ->name('custom-fields.destroy');
    Route::get('/custom-fields/{fieldType}', [CattleController::class, 'getCustomFields'])
        ->middleware('permission:Cattle Directory,view')
        ->name('custom-fields.index');

    // ==========================================
    // INVENTORY MANAGEMENT ROUTES
    // ==========================================

    // --- Medication Management ---
    Route::resource('medications', MedicationController::class)
        ->only(['index', 'create', 'show', 'edit'])
        ->middleware('permission:Inventory Medication Stock,view');

    Route::post('/medications/columns', [MedicationController::class, 'saveColumns'])
        ->middleware('permission:Inventory Medication Stock,edit')
        ->name('medications.columns');
    Route::post('/medications/bulk-delete', [MedicationController::class, 'bulkDelete'])
        ->middleware('permission:Inventory Medication Stock,delete')
        ->name('medications.bulk-delete');

    Route::post('/medications', [MedicationController::class, 'store'])->middleware('permission:Inventory Medication Stock,create')->name('medications.store');
    Route::put('/medications/{medication}', [MedicationController::class, 'update'])->middleware('permission:Inventory Medication Stock,edit')->name('medications.update');
    Route::patch('/medications/{medication}', [MedicationController::class, 'update'])->middleware('permission:Inventory Medication Stock,edit');
    Route::delete('/medications/{medication}', [MedicationController::class, 'destroy'])->middleware('permission:Inventory Medication Stock,delete')->name('medications.destroy');

    // --- Supplier Management ---
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->name('suppliers.store');

    Route::put('/suppliers/{id}', [SupplierController::class, 'update'])
        ->name('suppliers.update');

    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])
        ->name('suppliers.destroy');

    // ==========================================
    // PASTURE ROUTES
    // ==========================================

    Route::prefix('pasture')->name('pasture.')->group(function () {
        Route::get('/all', [PastureController::class, 'index'])->middleware('permission:Pasture,view')->name('all');
        Route::get('/all/{estate}', [PastureController::class, 'show'])->middleware('permission:Pasture,view')->name('show');
        Route::post('/operating-units', [PastureController::class, 'storeOperatingUnit'])->middleware('permission:Pasture,create')->name('operating-units.store');
        Route::put('/operating-units/{estate}', [PastureController::class, 'updateOperatingUnit'])->middleware('permission:Pasture,edit')->name('operating-units.update');
        Route::delete('/operating-units/{estate}', [PastureController::class, 'destroyOperatingUnit'])->middleware('permission:Pasture,delete')->name('operating-units.destroy');
        Route::post('/operating-units/{estate}/blocks', [PastureController::class, 'storeBlock'])->middleware('permission:Pasture,create')->name('blocks.store');
        Route::post('/blocks/{pastureBlock}/phases', [PastureController::class, 'storePhase'])->middleware('permission:Pasture,create')->name('phases.store');
    });

    // ==========================================
    // DATA INPUT (GRAZING) ROUTES
    // ==========================================

    Route::prefix('data-input')->name('data-input.')->group(function () {
        // Main page
        Route::get('/', [DataInputController::class, 'index'])->name('index');

        // Save grazing data
        Route::post('/save', [DataInputController::class, 'save'])->name('save');

        // Get estate data (AJAX)
        Route::get('/estate/{estateIndex}', [DataInputController::class, 'getEstateData'])->name('estate.data');

        // Estate CRUD
        Route::post('/estates', [DataInputController::class, 'storeEstate'])->name('estates.store');
        Route::put('/estates/{estate}', [DataInputController::class, 'updateEstate'])->name('estates.update');
        Route::delete('/estates/{estate}', [DataInputController::class, 'destroyEstate'])->name('estates.destroy');

        // Herd CRUD
        Route::post('/herds', [DataInputController::class, 'storeHerd'])->name('herds.store');
        Route::put('/herds/{herd}', [DataInputController::class, 'updateHerd'])->name('herds.update');
        Route::delete('/herds/{herd}', [DataInputController::class, 'destroyHerd'])->name('herds.destroy');
    });

    // ==========================================
    // HERD CARDS ROUTES
    // ==========================================

    Route::prefix('herd-cards')->name('herd-cards.')->group(function () {
        Route::get('/grazing-detail', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsIndex']);
        Route::get('/grazing-detail/create', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsCreate']);
        Route::get('/', [\App\Http\Controllers\HerdCardController::class, 'index'])->name('index');
        Route::get('/grazing-details', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsIndex'])->name('grazing-details.index');
        Route::get('/grazing-details/create', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsCreate'])->name('grazing-details.create');
        Route::post('/grazing-details', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsStore'])->name('grazing-details.store');
        Route::put('/grazing-details/{grazingData}', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsUpdate'])->name('grazing-details.update');
        Route::delete('/grazing-details/{grazingData}', [\App\Http\Controllers\HerdCardController::class, 'grazingDetailsDestroy'])->name('grazing-details.destroy');
        Route::get('/{estate}', [\App\Http\Controllers\HerdCardController::class, 'show'])->name('show');
        Route::put('/{estate}', [\App\Http\Controllers\HerdCardController::class, 'update'])->name('update');
        Route::patch('/{estate}/quick', [\App\Http\Controllers\HerdCardController::class, 'quickUpdate'])->name('quick-update');

        // Herd CRUD
        Route::post('/herds', [\App\Http\Controllers\HerdCardController::class, 'storeHerd'])->name('herds.store');
        Route::put('/herds/{herd}', [\App\Http\Controllers\HerdCardController::class, 'updateHerd'])->name('herds.update');
        Route::delete('/herds/{herd}', [\App\Http\Controllers\HerdCardController::class, 'destroyHerd'])->name('herds.destroy');

        // Estate CRUD
        Route::post('/estates', [\App\Http\Controllers\HerdCardController::class, 'storeEstate'])->name('estates.store');
        Route::put('/estates/{estate}', [\App\Http\Controllers\HerdCardController::class, 'updateEstate'])->name('estates.update');
        Route::delete('/estates/{estate}', [\App\Http\Controllers\HerdCardController::class, 'destroyEstate'])->name('estates.destroy');
        Route::post('/estates/bulk-destroy', [\App\Http\Controllers\HerdCardController::class, 'bulkDestroyEstates'])->name('estates.bulk-destroy');

        // Block CRUD
        Route::delete('/blocks/{block}', [\App\Http\Controllers\HerdCardController::class, 'destroyBlock'])->name('blocks.destroy');
        Route::post('/blocks/bulk-destroy', [\App\Http\Controllers\HerdCardController::class, 'bulkDestroyBlocks'])->name('blocks.bulk-destroy');
    });

    // ==========================================
    // DRIVER MANAGEMENT ROUTES
    // ==========================================

    Route::prefix('driver')->name('driver.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DriverController::class, 'index'])->middleware('permission:Driver,view')->name('index');
        Route::put('/{user}', [\App\Http\Controllers\DriverController::class, 'update'])->middleware('permission:Driver,edit')->name('update');

        Route::get('/shift-schedule', [\App\Http\Controllers\DeliveryRecordController::class, 'shiftSchedule'])->middleware('permission:Driver,view')->name('shift-schedule');


        Route::get('/delivery-history', [\App\Http\Controllers\DeliveryRecordController::class, 'index'])->middleware('permission:Driver,view')->name('delivery-history');
        Route::post('/delivery-history', [\App\Http\Controllers\DeliveryRecordController::class, 'store'])->middleware('permission:Driver,create')->name('delivery-history.store');
        Route::put('/delivery-history/{deliveryRecord}', [\App\Http\Controllers\DeliveryRecordController::class, 'update'])->middleware('permission:Driver,edit')->name('delivery-history.update');
        Route::delete('/delivery-history/{deliveryRecord}', [\App\Http\Controllers\DeliveryRecordController::class, 'destroy'])->middleware('permission:Driver,delete')->name('delivery-history.destroy');
        Route::post('/delivery-history/bulk-delete', [\App\Http\Controllers\DeliveryRecordController::class, 'bulkDelete'])->middleware('permission:Driver,delete')->name('delivery-history.bulk-delete');
    });

    // ==========================================
    // ACCESS DENIED ROUTE
    // ==========================================

    Route::get('/access-denied', function () {
        return Inertia::render('AccessDenied', [
            'message' => "Sorry, you don't have permission to access this page. If you think this is a mistake, please contact your admin."
        ]);
    })->name('access-denied');

    // ==========================================
    // MORTALITY ROUTES
    // ==========================================

    // Static routes MUST come before dynamic {id} routes
    Route::get('/mortality/create', [MortalityController::class, 'create'])->middleware('permission:Mortality Records,create')->name('mortality.create');
    Route::get('/mortality/history', [MortalityController::class, 'index'])->middleware('permission:Mortality Records,view')->name('mortality.history');
    Route::get('/mortality/completed', [MortalityController::class, 'completedCases'])->middleware('permission:Mortality Records,view')->name('mortality.completed');
    Route::get('/mortality/records', [MortalityController::class, 'pendingApprovals'])->middleware('permission:Mortality Records,view')->name('mortality.records');
    Route::get('/mortality/pending-approvals', fn() => redirect()->route('mortality.records'))->middleware('permission:Mortality Records,view')->name('mortality.pending-approvals');

    // PM Examination routes
    Route::get('/mortality/pm-examination', [MortalityController::class, 'pmExamination'])->middleware('permission:Mortality Records,view')->name('mortality.pm-exam');
    Route::get('/mortality/pm-exam', fn() => redirect()->route('mortality.pm-exam'))->middleware('permission:Mortality Records,view')->name('mortality.pm-examination');
    Route::get('/mortality/pm-examination/{id}', [MortalityController::class, 'pmExaminationDetail'])->name('mortality.pm-exam.detail');
    Route::post('/mortality/pm-examination/{mortalityCaseId}', [MortalityController::class, 'storePmExamination'])->middleware('permission:Mortality Records,edit')->name('mortality.pm-exam.submit');

    // Edit and Workflow routes (must come before {id} routes)
    Route::get('/mortality/{id}/edit', [MortalityController::class, 'edit'])->middleware('permission:Mortality Records,edit')->name('mortality.edit');
    Route::get('/mortality/{id}/workflow', [MortalityController::class, 'workflow'])->middleware('permission:Mortality Records,view')->name('mortality.workflow');

    // Custom fields routes
    Route::post('/mortality/custom-fields', [MortalityController::class, 'storeCustomField'])->name('mortality.custom-fields.store');
    Route::put('/mortality/custom-fields/{customField}', [MortalityController::class, 'updateCustomField'])->name('mortality.custom-fields.update');
    Route::delete('/mortality/custom-fields/{customField}', [MortalityController::class, 'destroyCustomField'])->name('mortality.custom-fields.destroy');
    Route::get('/mortality/custom-fields/{fieldType}', [MortalityController::class, 'getCustomFields'])->name('mortality.custom-fields.index');

    // API route
    Route::get('/api/mortality/pending-counts', [MortalityController::class, 'getPendingCounts'])->name('api.mortality.pending-counts');

    // Store route (POST to /mortality)
    Route::post('/mortality', [MortalityController::class, 'store'])->middleware('permission:Mortality Records,create')->name('mortality.store');

    // Approve route (must come before {id} to avoid conflict)
    Route::post('/mortality/{caseId}/approve', [MortalityController::class, 'approve'])->middleware('permission:Mortality Records,view')->name('mortality.approve');

    // Update route (PUT to /mortality/{id})
    Route::put('/mortality/{id}', [MortalityController::class, 'update'])->middleware('permission:Mortality Records,edit')->name('mortality.update');

    // Endorsement document upload/download for mortality cases
    Route::post('/mortality/{id}/upload-endorsement', [MortalityController::class, 'uploadEndorsement'])->middleware('permission:Mortality Records,view')->name('mortality.upload-endorsement');
    Route::get('/mortality/{id}/download-endorsement/{stepIndex}', [MortalityController::class, 'downloadEndorsement'])->middleware('permission:Mortality Records,view')->name('mortality.download-endorsement');
    Route::get('/mortality/{id}/endorsement-form', [MortalityController::class, 'downloadEndorsementForm'])->middleware('permission:Mortality Records,view')->name('mortality.endorsement-form');
    Route::post('/mortality/{id}/mark-completed', [MortalityController::class, 'markAsCompleted'])->middleware('permission:Mortality Records,view')->name('mortality.mark-completed');
    Route::post('/mortality/{id}/reopen', [MortalityController::class, 'reopen'])->middleware('permission:Mortality Records,view')->name('mortality.reopen');

    // Delete mortality case (admin only)
    Route::delete('/mortality/{case}', [MortalityController::class, 'destroy'])->middleware('permission:Mortality Records,delete')->name('mortality.destroy');

    // Dynamic {id} route MUST be LAST
    Route::get('/mortality/{id}', [MortalityController::class, 'show'])->middleware('permission:Mortality Records,view')->name('mortality.show');

    // ==========================================
    // CALVING MODULE ROUTES
    // ==========================================

    // Static routes MUST come before dynamic {calvingRecord} routes
    Route::get('/calving', [CalvingController::class, 'index'])->middleware('permission:Calving Record,view')->name('calving.index');
    Route::get('/calving/create', [CalvingController::class, 'create'])->middleware('permission:Calving Record,create')->name('calving.create');
    Route::post('/calving', [CalvingController::class, 'store'])->middleware('permission:Calving Record,create')->name('calving.store');
    Route::post('/calving/bulk-delete', [CalvingController::class, 'bulkDelete'])->middleware('permission:Calving Record,delete')->name('calving.bulk-delete');
    Route::get('/calving/export', [CalvingController::class, 'export'])->middleware('permission:Calving Record,view')->name('calving.export');
    Route::get('/calving/pending', [CalvingController::class, 'pending'])->name('calving.pending');
    Route::get('/api/calving/by-month', [CalvingController::class, 'apiByMonth'])->name('api.calving.by-month');

    Route::get('/calving/{calvingRecord}/edit', [CalvingController::class, 'edit'])->middleware('permission:Calving Record,edit')->whereNumber('calvingRecord')->name('calving.edit');
    Route::put('/calving/{calvingRecord}', [CalvingController::class, 'update'])->middleware('permission:Calving Record,edit')->whereNumber('calvingRecord')->name('calving.update');
    Route::delete('/calving/{calvingRecord}', [CalvingController::class, 'destroy'])->middleware('permission:Calving Record,delete')->whereNumber('calvingRecord')->name('calving.destroy');
    Route::post('/calving/{calvingRecord}/delete', [CalvingController::class, 'destroy'])->middleware('permission:Calving Record,delete')->whereNumber('calvingRecord')->name('calving.delete');

    Route::post('/calving/{calvingRecord}/issue', [CalvingController::class, 'issue'])->whereNumber('calvingRecord')->name('calving.issue');
    Route::post('/calving/{calvingRecord}/verify', [CalvingController::class, 'verify'])->whereNumber('calvingRecord')->name('calving.verify');
    Route::post('/calving/{calvingRecord}/witness', [CalvingController::class, 'witness'])->whereNumber('calvingRecord')->name('calving.witness');
    Route::post('/calving/{calvingRecord}/approve', [CalvingController::class, 'approve'])->whereNumber('calvingRecord')->name('calving.approve');
    Route::post('/calving/{calvingRecord}/reject', [CalvingController::class, 'reject'])->whereNumber('calvingRecord')->name('calving.reject');

    Route::get('/calving/{calvingRecord}/lcc', [CalvingController::class, 'lccDocument'])->middleware('permission:Calving Record,view')->whereNumber('calvingRecord')->name('calving.lcc');
    Route::get('/calving/{calvingRecord}/download-endorsement/{stepIndex}', [CalvingController::class, 'downloadEndorsement'])->middleware('permission:Calving Record,view')->whereNumber('calvingRecord')->name('calving.download-endorsement');
    Route::get('/calving/{calvingRecord}/download-endorsement-document/{stepIndex}', [CalvingController::class, 'downloadEndorsementDocument'])->middleware('permission:Calving Record,view')->whereNumber('calvingRecord')->name('calving.download-endorsement-document');
    Route::post('/calving/{calvingRecord}/upload-endorsement/{stepIndex}', [CalvingController::class, 'uploadEndorsement'])->whereNumber('calvingRecord')->name('calving.upload-endorsement');
    Route::post('/calving/{calvingRecord}/mark-completed', [CalvingController::class, 'markAsCompleted'])->whereNumber('calvingRecord')->name('calving.mark-completed');
    Route::post('/calving/{calvingRecord}/reopen', [CalvingController::class, 'reopen'])->whereNumber('calvingRecord')->name('calving.reopen');

    // Dynamic show route MUST be last among /calving/{calvingRecord} GET routes
    Route::get('/calving/{calvingRecord}', [CalvingController::class, 'show'])->middleware('permission:Calving Record,view')->whereNumber('calvingRecord')->name('calving.show');

    // ==========================================
    // CALVING CHECKLIST MODULE ROUTES
    // ==========================================

    // Calving Checklist Dashboard
    Route::get('/calving-checklist', [CalvingChecklistController::class, 'index'])->middleware('permission:Calving Checklist')->name('calving-checklist.index');

    // Create Calving Checklist Record
    Route::get('/calving-checklist/create', [CalvingChecklistController::class, 'create'])->name('calving-checklist.create');

    // Calving Checklist Pending Approvals
    Route::get('/calving-checklist/pending', [CalvingChecklistController::class, 'pending'])->name('calving-checklist.pending');

    // Export Calving Checklist Records (Generic/Old)
    Route::get('/calving-checklist/export', [CalvingChecklistController::class, 'export'])->name('calving-checklist.export');

    // Batch Verification Routes for Monthly Records (Static)
    Route::post('/calving-checklist/upload-batch-endorsement', [CalvingChecklistController::class, 'uploadBatchEndorsement'])->name('calving-checklist.upload-batch-endorsement');
    Route::get('/calving-checklist/download-batch-endorsement/{stepIndex}', [CalvingChecklistController::class, 'downloadBatchEndorsement'])->name('calving-checklist.download-batch-endorsement');
    Route::post('/calving-checklist/mark-batch-completed', [CalvingChecklistController::class, 'markBatchCompleted'])->name('calving-checklist.mark-batch-completed');
    Route::post('/calving-checklist/mark-batch-reopen', [CalvingChecklistController::class, 'reopenBatch'])->name('calving-checklist.mark-batch-reopen');
    Route::get('/calving-checklist/export-report', [CalvingChecklistController::class, 'exportReport'])->name('calving-checklist.export-report');

    // API: Get calving checklist pending counts (Static)
    Route::get('/api/calving-checklist/pending-counts', [CalvingChecklistController::class, 'getPendingCounts'])->name('api.calving-checklist.pending-counts');

    // Show Calving Checklist Record (Wildcard - Must be lower)
    Route::get('/calving-checklist/{calvingChecklist}', [CalvingChecklistController::class, 'show'])->name('calving-checklist.show');

    // Store Calving Checklist Record
    Route::post('/calving-checklist', [CalvingChecklistController::class, 'store'])->name('calving-checklist.store');

    // Edit Calving Checklist Record
    Route::get('/calving-checklist/{calvingChecklist}/edit', [CalvingChecklistController::class, 'edit'])->name('calving-checklist.edit');

    // Update Calving Checklist Record
    Route::put('/calving-checklist/{calvingChecklist}', [CalvingChecklistController::class, 'update'])->name('calving-checklist.update');

    // Delete Calving Checklist Record
    Route::delete('/calving-checklist/{calvingChecklist}', [CalvingChecklistController::class, 'destroy'])->name('calving-checklist.destroy');

    // Bulk Delete Calving Checklist Records
    Route::post('/calving-checklist/bulk-delete', [CalvingChecklistController::class, 'bulkDelete'])->name('calving-checklist.bulk-delete');

    // Action Routes
    Route::post('/calving-checklist/{calvingChecklist}/issue', [CalvingChecklistController::class, 'issue'])->name('calving-checklist.issue');
    Route::post('/calving-checklist/{calvingChecklist}/verify', [CalvingChecklistController::class, 'verify'])->name('calving-checklist.verify');
    Route::post('/calving-checklist/{calvingChecklist}/witness', [CalvingChecklistController::class, 'witness'])->name('calving-checklist.witness');
    Route::post('/calving-checklist/{calvingChecklist}/approve', [CalvingChecklistController::class, 'approve'])->name('calving-checklist.approve');
    Route::post('/calving-checklist/{calvingChecklist}/reject', [CalvingChecklistController::class, 'reject'])->name('calving-checklist.reject');

    // Endorsement Document Routes
    Route::post('/calving-checklist/{calvingChecklist}/upload-endorsement/{stepIndex}', [CalvingChecklistController::class, 'uploadEndorsement'])->name('calving-checklist.upload-endorsement');
    Route::get('/calving-checklist/{calvingChecklist}/download-endorsement/{stepIndex}', [CalvingChecklistController::class, 'downloadEndorsement'])->name('calving-checklist.download-endorsement');
    Route::get('/calving-checklist/{calvingChecklist}/endorsement-form', [CalvingChecklistController::class, 'downloadEndorsementForm'])->name('calving-checklist.endorsement-form');
    Route::post('/calving-checklist/{calvingChecklist}/mark-completed', [CalvingChecklistController::class, 'markAsCompleted'])->name('calving-checklist.mark-completed');

    // ==========================================
    // MORTALITY MODULE ROUTES (BLADE VIEWS)
    // ==========================================

    Route::prefix('transfer')->name('transfer.')->group(function () {
        Route::get('/ctv', [TransferController::class, 'ctvHistory'])->middleware('permission:Transfer CTV,view')->name('ctv.index');
        Route::get('/receival', [TransferController::class, 'receivalHistory'])->middleware('permission:Transfer Receival')->name('receival.index');
        Route::get('/siv', [TransferController::class, 'sivHistory'])->middleware('permission:Transfer SIV')->name('siv.index');

        Route::get('/create/ctv', [TransferController::class, 'createCtv'])->middleware('permission:Transfer CTV,create')->name('create-ctv');
        Route::get('/ctv/{id}/edit', [TransferController::class, 'editCtv'])->middleware('permission:Transfer CTV,edit')->name('ctv.edit');
        Route::get('/create/receival', [TransferController::class, 'createReceival'])->middleware('permission:Transfer Receival,create')->name('create-receival');
        Route::get('/receival/{id}/edit', [TransferController::class, 'editReceival'])->middleware('permission:Transfer Receival,edit')->name('receival.edit');
        Route::get('/create/siv', [TransferController::class, 'createSiv'])->middleware('permission:Transfer SIV,create')->name('create-siv');
        Route::get('/siv/{id}/edit', [TransferController::class, 'editSiv'])->middleware('permission:Transfer SIV,edit')->name('siv.edit');

        Route::post('/', [TransferController::class, 'store'])->name('store');
        Route::put('/ctv/{id}', [TransferController::class, 'updateCtv'])->middleware('permission:Transfer CTV,edit')->name('ctv.update');
        Route::put('/receival/{id}', [TransferController::class, 'updateReceival'])->middleware('permission:Transfer Receival,edit')->name('receival.update');
        Route::put('/siv/{id}', [TransferController::class, 'updateSiv'])->middleware('permission:Transfer SIV,edit')->name('siv.update');
        Route::delete('/ctv/{id}', [TransferController::class, 'destroyCtv'])->middleware('permission:Transfer CTV,delete')->name('ctv.destroy');
        Route::delete('/receival/{id}', [TransferController::class, 'destroyReceival'])->middleware('permission:Transfer Receival,delete')->name('receival.destroy');
        Route::delete('/siv/{id}', [TransferController::class, 'destroySiv'])->middleware('permission:Transfer SIV,delete')->name('siv.destroy');
        Route::post('/bulk-delete', [TransferController::class, 'bulkDelete'])->name('bulk-delete');

        Route::get('/ctv/{id}/workflow', [TransferController::class, 'ctvWorkflow'])->name('ctv.workflow');
        Route::get('/receival/{id}/workflow', [TransferController::class, 'receivalWorkflow'])->name('receival.workflow');
        Route::get('/siv/{id}/workflow', [TransferController::class, 'sivWorkflow'])->name('siv.workflow');
        Route::get('/{id}', [TransferController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [TransferController::class, 'approve'])->name('approve');
        Route::post('/{id}/upload-endorsement', [TransferController::class, 'uploadEndorsement'])->name('upload-endorsement');
        Route::get('/{id}/download-endorsement/{stepIndex}', [TransferController::class, 'downloadEndorsement'])->name('download-endorsement');
        Route::get('/{id}/endorsement-form/{stepIndex}', [TransferController::class, 'downloadEndorsementForm'])->name('endorsement-form');
        Route::post('/{id}/mark-completed', [TransferController::class, 'markAsCompleted'])->name('mark-completed');
        Route::post('/{id}/reopen', [TransferController::class, 'reopen'])->name('reopen');
    });

    // API route for pending counts
    Route::get('/api/transfer/pending-counts', [TransferController::class, 'getPendingCounts'])->name('api.transfer.pending-counts');





    // ==========================================
    // HEALTH MODULE ROUTES
    // ==========================================

    Route::prefix('health')->name('health.')->group(function () {
        // Treatment Records with Endorsement Workflow
        Route::get('/treatment', [HealthTreatmentController::class, 'index'])->middleware('permission:Treatment Record,view')->name('treatment.index');
        Route::get('/treatment/create', [HealthTreatmentController::class, 'create'])->middleware('permission:Treatment Record,create')->name('treatment.create');
        Route::get('/treatment/{id}/edit', [HealthTreatmentController::class, 'edit'])->middleware('permission:Treatment Record,edit')->name('treatment.edit');
        Route::post('/treatment', [HealthTreatmentController::class, 'store'])->middleware('permission:Treatment Record,create')->name('treatment.store');
        Route::put('/treatment/{id}', [HealthTreatmentController::class, 'update'])->middleware('permission:Treatment Record,edit')->whereNumber('id')->name('treatment.update');
        Route::get('/treatment/pending', [HealthTreatmentController::class, 'pendingApprovals'])->middleware('permission:Treatment Record,view')->name('treatment.pending');
        Route::get('/treatment/{id}', [HealthTreatmentController::class, 'show'])->middleware('permission:Treatment Record,view')->whereNumber('id')->name('treatment.show');
        Route::delete('/treatment/{id}', [HealthTreatmentController::class, 'destroy'])->middleware('permission:Treatment Record,delete')->whereNumber('id')->name('treatment.destroy');
        Route::post('/treatment/bulk-delete', [HealthTreatmentController::class, 'bulkDelete'])->middleware('permission:Treatment Record,delete')->name('treatment.bulk-delete');

        // Treatment Endorsement Routes
        Route::post('/treatment/{id}/upload-endorsement', [HealthTreatmentController::class, 'uploadEndorsement'])->middleware('permission:Treatment Record,edit')->whereNumber('id')->name('treatment.upload-endorsement');
        Route::delete('/treatment/{id}/endorsement/{stepIndex}', [HealthTreatmentController::class, 'deleteEndorsement'])->middleware('permission:Treatment Record,edit')->whereNumber('id')->name('treatment.delete-endorsement');
        Route::get('/treatment/{id}/download-endorsement/{stepIndex}', [HealthTreatmentController::class, 'downloadEndorsement'])->middleware('permission:Treatment Record,view')->whereNumber('id')->name('treatment.download-endorsement');
        Route::get('/treatment/{id}/endorsement-form', [HealthTreatmentController::class, 'downloadEndorsementForm'])->middleware('permission:Treatment Record,view')->whereNumber('id')->name('treatment.endorsement-form');
        Route::post('/treatment/{id}/mark-completed', [HealthTreatmentController::class, 'markAsCompleted'])->middleware('permission:Treatment Record,edit')->whereNumber('id')->name('treatment.mark-completed');
        Route::post('/treatment/{id}/reopen', [HealthTreatmentController::class, 'reopen'])->middleware('permission:Treatment Record,edit')->whereNumber('id')->name('treatment.reopen');

        // Treatment Monthly Workflow Routes (month/year/unit based)
        Route::get('/treatment/monthly-workflow', [HealthTreatmentController::class, 'getMonthlyWorkflow'])->middleware('permission:Treatment Record,view')->name('treatment.monthly-workflow.get');
        Route::post('/treatment/monthly-workflow/upload', [HealthTreatmentController::class, 'uploadMonthlyEndorsement'])->middleware('permission:Treatment Record,edit')->name('treatment.monthly-workflow.upload');
        Route::get('/treatment/monthly-workflow/download/{stepIndex}', [HealthTreatmentController::class, 'downloadMonthlyEndorsement'])->middleware('permission:Treatment Record,view')->name('treatment.monthly-workflow.download');
        Route::delete('/treatment/monthly-workflow/{stepIndex}', [HealthTreatmentController::class, 'deleteMonthlyEndorsement'])->middleware('permission:Treatment Record,edit')->name('treatment.monthly-workflow.delete');
        Route::post('/treatment/monthly-workflow/mark-completed', [HealthTreatmentController::class, 'markMonthlyCompleted'])->middleware('permission:Treatment Record,edit')->name('treatment.monthly-workflow.mark-completed');
        Route::post('/treatment/monthly-workflow/reopen', [HealthTreatmentController::class, 'reopenMonthly'])->middleware('permission:Treatment Record,edit')->name('treatment.monthly-workflow.reopen');

        // Treatment Export Report
        Route::get('/treatment/export/report', [HealthTreatmentController::class, 'exportReport'])->middleware('permission:Treatment Record,view')->name('treatment.export-report');



        // Veterinary Contact
        Route::get('/contact', [VeterinaryContactController::class, 'index'])->middleware('permission:Veterinary Contact,view')->name('contact.index');
        Route::get('/contact/create', [VeterinaryContactController::class, 'create'])->middleware('permission:Veterinary Contact,create')->name('contact.create');
        Route::post('/contact', [VeterinaryContactController::class, 'store'])->middleware('permission:Veterinary Contact,create')->name('contact.store');
        Route::get('/contact/{id}', [VeterinaryContactController::class, 'show'])->middleware('permission:Veterinary Contact,view')->name('contact.show');
        Route::get('/contact/{id}/edit', [VeterinaryContactController::class, 'edit'])->middleware('permission:Veterinary Contact,edit')->name('contact.edit');
        Route::put('/contact/{id}', [VeterinaryContactController::class, 'update'])->middleware('permission:Veterinary Contact,edit')->name('contact.update');
        Route::delete('/contact/{id}', [VeterinaryContactController::class, 'destroy'])->middleware('permission:Veterinary Contact,delete')->name('contact.destroy');



        // Treatment Codes Management
        Route::get('/treatment-codes', [\App\Http\Controllers\TreatmentCodeController::class, 'index'])->name('treatment-codes.index');
        Route::post('/treatment-codes', [\App\Http\Controllers\TreatmentCodeController::class, 'store'])->name('treatment-codes.store');
        Route::put('/treatment-codes/{id}', [\App\Http\Controllers\TreatmentCodeController::class, 'update'])->name('treatment-codes.update');
        Route::delete('/treatment-codes/{id}', [\App\Http\Controllers\TreatmentCodeController::class, 'destroy'])->name('treatment-codes.destroy');
    });

    // API route for treatment pending counts
    Route::get('/api/health/treatment/pending-counts', [HealthTreatmentController::class, 'getPendingCounts'])->name('api.health.treatment.pending-counts');

    // ==========================================
    // FEEDING MODULE ROUTES
    // ==========================================

    Route::prefix('feeding')->name('feeding.')->group(function () {
        Route::get('/', [FeedingController::class, 'index'])->middleware('permission:Feeding Record,view')->name('index');
        Route::get('/schedule', [FeedingController::class, 'schedule'])->middleware('permission:Feeding Record,view')->name('schedule');
        Route::get('/export-pdf', [FeedingController::class, 'exportPdf'])->middleware('permission:Feeding Record,view')->name('export-pdf');
        Route::post('/', [FeedingController::class, 'store'])->middleware('permission:Feeding Record,create')->name('store');
        Route::put('/{id}', [FeedingController::class, 'update'])->middleware('permission:Feeding Record,edit')->whereNumber('id')->name('update');
        Route::delete('/{id}', [FeedingController::class, 'destroy'])->middleware('permission:Feeding Record,delete')->whereNumber('id')->name('destroy');
        Route::post('/options', [FeedingController::class, 'storeOption'])->middleware('permission:Feeding Record,edit')->name('options.store');
        Route::put('/options/{option}', [FeedingController::class, 'updateOption'])->middleware('permission:Feeding Record,edit')->name('options.update');
        Route::delete('/options/{option}', [FeedingController::class, 'destroyOption'])->middleware('permission:Feeding Record,delete')->name('options.destroy');
    });

    // ==========================================
    // TASK ROUTES
    // ==========================================

    Route::prefix('task')->name('task.')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->middleware('permission:Task,view')->name('index');
        Route::post('/', [TaskController::class, 'store'])->middleware('permission:Task,create')->name('store');
        Route::delete('/bulk-delete', [TaskController::class, 'bulkDestroy'])->middleware('permission:Task,delete')->name('bulk-destroy');
        Route::put('/{task}', [TaskController::class, 'update'])->middleware('permission:Task,edit')->name('update');
        Route::delete('/{task}', [TaskController::class, 'destroy'])->middleware('permission:Task,delete')->name('destroy');

        Route::get('/calendar', [TaskController::class, 'calendar'])->middleware('permission:Task,view')->name('calendar');
        Route::get('/create', fn() => Inertia::render('Task/Create'))->middleware('permission:Task,create')->name('create');
        Route::get('/{id}', fn($id) => Inertia::render('Task/Show', ['id' => $id]))->middleware('permission:Task,view')->name('show');
    });

    Route::get('/task-notifications', [TaskNotificationController::class, 'index'])->name('task-notifications.index');
    Route::get('/task-notifications/page', [TaskNotificationController::class, 'page'])->name('task-notifications.page');
    Route::post('/task-notifications/{notification}/read', [TaskNotificationController::class, 'markAsRead'])->name('task-notifications.read');
    Route::post('/task-notifications/read-by-task', [TaskNotificationController::class, 'markTaskNotificationsRead'])->name('task-notifications.read-by-task');
    Route::post('/task-notifications/mark-all-read', [TaskNotificationController::class, 'markAllRead'])->name('task-notifications.mark-all-read');

    // --- Document Endorsement Routes ---
    Route::get('/document-endorsement', [DocumentEndorsementController::class, 'index'])->name('document-endorsement.index');
    Route::get('/document-endorsement/{id}', [DocumentEndorsementController::class, 'show'])->name('document-endorsement.show');
    Route::get('/document-endorsement/{id}/download', [DocumentEndorsementController::class, 'download'])->name('document-endorsement.download');
    Route::post('/document-endorsement/{id}/upload', [DocumentEndorsementController::class, 'uploadSigned'])->name('document-endorsement.upload');
    Route::get('/document-endorsement/create/{caseId}', [DocumentEndorsementController::class, 'createFromCase'])->name('document-endorsement.create');
});

// Include Breeze auth routes
require __DIR__.'/auth.php';
