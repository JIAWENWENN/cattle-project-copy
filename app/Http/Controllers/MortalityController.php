<?php

namespace App\Http\Controllers;

use App\Models\Cattle;
use App\Models\CattleHealthRecord;
use App\Models\MortalityCase;
use App\Models\MortalityCustomField;
use App\Models\PostmortemExamination;
use App\Models\MortalityApproval;
use App\Models\Estate;
use App\Models\GrazingBlock;
use App\Models\FieldLevelPermission;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowAssignment;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MortalityController extends Controller
{
    private const MORTALITY_WORKFLOW_MODULE = 'Mortality Records';

    private const MORTALITY_WORKFLOW_STEP_COUNT = 5;

    private function getMortalityWorkflowAssignments(): array
    {
        if (!Schema::hasTable('workflow_assignments')) {
            return [];
        }

        $assignment = WorkflowAssignment::query()
            ->where('module', self::MORTALITY_WORKFLOW_MODULE)
            ->first();

        if (!$assignment || !is_array($assignment->assignments)) {
            return [];
        }

        return $assignment->assignments;
    }

    private function getMortalityAssignmentKeyForStep(int $stepIndex): ?string
    {
        return match ($stepIndex) {
            0 => 'issued_by_user_ids',
            1 => 'verified_by_user_ids',
            2 => 'checked_by_user_ids',
            3 => 'witnessed_by_user_ids',
            4 => 'approved_by_user_ids',
            default => null,
        };
    }

    private function getMortalityStepNames(): array
    {
        return [
            0 => 'Issued',
            1 => 'Verified',
            2 => 'Checked',
            3 => 'Witnessed',
            4 => 'Approved',
        ];
    }

    private function getMortalityAssignedUserIdsForStep(int $stepIndex): array
    {
        $assignments = $this->getMortalityWorkflowAssignments();
        $key = $this->getMortalityAssignmentKeyForStep($stepIndex);
        if (!$key) {
            return [];
        }

        $ids = is_array($assignments[$key] ?? null) ? $assignments[$key] : [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function getUsersForMortalityWorkflowStep(int $stepIndex)
    {
        $assignedUserIds = $this->getMortalityAssignedUserIdsForStep($stepIndex);
        if (empty($assignedUserIds)) {
            return collect();
        }

        return User::whereIn('id', $assignedUserIds)->get();
    }

    private function userHasMortalityModulePermission($user, string $action): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $permissions = Permission::normalizePermissionList($user->hasPermission(self::MORTALITY_WORKFLOW_MODULE));

        return in_array('full', $permissions, true) || in_array($action, $permissions, true);
    }

    private function userCanUploadMortalityWorkflowStep($user, int $stepIndex): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $assignedUserIds = $this->getMortalityAssignedUserIdsForStep($stepIndex);

        return !empty($assignedUserIds) && in_array((int) $user->id, $assignedUserIds, true);
    }

    private function notifyMortalityWorkflowStepReady(MortalityCase $case, int $stepIndex, ?int $excludeUserId = null): void
    {
        $stepNames = $this->getMortalityStepNames();
        $stepName = $stepNames[$stepIndex] ?? 'Next Step';
        $caseRef = $case->lmc_no ?: ('LMC-' . $case->id);

        foreach ($this->getUsersForMortalityWorkflowStep($stepIndex) as $notifyUser) {
            if ($excludeUserId && (int) $notifyUser->id === $excludeUserId) {
                continue;
            }

            WorkflowNotificationService::createNotification(
                'mortality',
                'mortality',
                $case->id,
                'workflow_step_ready',
                'Mortality Workflow Ready',
                "Mortality record {$caseRef} is ready for '{$stepName}'.",
                (int) $notifyUser->id
            );
        }
    }

    private function notifyNextMortalityWorkflowStep(MortalityCase $case, int $completedStepIndex, int $uploaderId): void
    {
        $nextStepIndex = $completedStepIndex + 1;
        $stepNames = $this->getMortalityStepNames();
        $currentStepName = $stepNames[$completedStepIndex] ?? 'Step';
        $nextStepName = $stepNames[$nextStepIndex] ?? 'Next Step';
        $caseRef = $case->lmc_no ?: ('LMC-' . $case->id);

        foreach ($this->getUsersForMortalityWorkflowStep($nextStepIndex) as $notifyUser) {
            if ((int) $notifyUser->id === $uploaderId) {
                continue;
            }

            WorkflowNotificationService::createNotification(
                'mortality',
                'mortality',
                $case->id,
                'workflow_step_completed',
                'Mortality Workflow Step Completed',
                "The mortality workflow step '{$currentStepName}' for record {$caseRef} has been completed. Please proceed with '{$nextStepName}'.",
                (int) $notifyUser->id
            );
        }
    }

    private function notifyMortalityWorkflowReadyForCompletion(MortalityCase $case, int $uploaderId): void
    {
        $caseRef = $case->lmc_no ?: ('LMC-' . $case->id);

        foreach (User::where('role', 'admin')->get() as $adminUser) {
            if ((int) $adminUser->id === $uploaderId) {
                continue;
            }

            WorkflowNotificationService::createNotification(
                'mortality',
                'mortality',
                $case->id,
                'workflow_ready_for_completion',
                'Mortality Workflow Ready for Completion',
                "All workflow steps for mortality record {$caseRef} have been uploaded. Please mark the mortality workflow as completed.",
                (int) $adminUser->id
            );
        }
    }

    private function getMortalityWorkflowAssignmentForInertia(): ?array
    {
        $assignments = $this->getMortalityWorkflowAssignments();

        return empty($assignments) ? null : $assignments;
    }

    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = MortalityCase::query();

        if ($search) {
            $query->whereHas('cattle', function ($q) use ($search) {
                $q->where('tag_no', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('death_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('death_date', '<=', $dateTo);
        }

        $mortalityCases = $query->with(['cattle', 'postmortemExamination', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Mortality/History', [
            'mortalityCases' => $mortalityCases,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]
        ]);
    }

    public function create()
    {
        $user = Auth::user();

        $cattle = Cattle::where('status', 'Active')
            ->orderBy('tag_no')
            ->get(['id', 'tag_no', 'category', 'location_block', 'location_phase', 'operating_unit']);

        $estates = Estate::where('is_active', true)
            ->with('pastureBlocks')
            ->orderBy('name')
            ->get();

        // Calculate operating_unit from blocks if it's missing
        $cattle->transform(function ($c) use ($estates) {
            if (empty($c->operating_unit) && !empty($c->location_block)) {
                $foundEstate = $estates->first(function ($estate) use ($c) {
                    return collect($estate->pastureBlocks)->contains('name', $c->location_block);
                });
                if ($foundEstate) {
                    $c->operating_unit = $foundEstate->name;
                }
            }
            return $c;
        });

        // Clean estates to only required fields for frontend to minimize payload size
        $estatesPayload = $estates->map(function ($estate) {
            return ['id' => $estate->id, 'name' => $estate->name];
        });

        $blocks = GrazingBlock::select('block_id')
            ->distinct()
            ->orderBy('block_id')
            ->pluck('block_id');

        return Inertia::render('Mortality/Create', [
            'cattle' => $cattle,
            'estates' => $estatesPayload,
            'blocks' => $blocks,
            'customFields' => [
                'category' => MortalityCustomField::getOptionsWithIds('category'),
                'preliminary_cause' => MortalityCustomField::getOptionsWithIds('preliminary_cause'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'cattle_id' => 'required|exists:cattle,id',
            'lmc_no' => 'nullable|string|max:255',
            'category' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:255',
            'death_date' => 'required|date',
            'reported_by' => 'nullable|string|max:255',
            'time_of_death' => 'nullable',
            'cause_of_death' => 'nullable|string',
            'treatment' => 'nullable|string',
            'additional_info' => 'nullable|string',
        ]);

        $createdCase = null;
        $notifyWorkflow = false;

        DB::transaction(function () use ($validated, $request, &$createdCase, &$notifyWorkflow) {
            $mortalityCase = MortalityCase::create([
                'cattle_id' => $validated['cattle_id'],
                'lmc_no' => $validated['lmc_no'] ?? null,
                'category' => $validated['category'],
                'location' => $validated['location'] ?? null,
                'block' => $validated['block'] ?? null,
                'death_date' => $validated['death_date'],
                'initial_notes' => $validated['additional_info'] ?? null,
                'reported_by' => $validated['reported_by'] ?? null,
                'time_of_death' => $validated['time_of_death'] ?? null,
                'cause_of_death' => $validated['cause_of_death'] ?? null,
                'treatment' => $validated['treatment'] ?? null,
                'status' => 'pending',
                'current_step' => 'issued',
                'endorsement_step' => 0,
                'is_reopened' => false,
                'created_by' => Auth::id(),
            ]);

            // Handle post-mortem examination data - save if any PM field has value
            $pmFields = [
                'pm_examination_date', 'pm_external_skin', 'pm_external_eyes', 'pm_external_mouth',
                'pm_external_nostrils', 'pm_external_ears', 'pm_external_limbs', 'pm_external_anus',
                'pm_external_genital', 'pm_external_general', 'pm_subcutaneous', 'pm_heart',
                'pm_trachea', 'pm_lung', 'pm_diaphragma', 'pm_kidney', 'pm_reproductive_organ',
                'pm_joint', 'pm_bladder', 'pm_liver', 'pm_spleen', 'pm_stomachrumen',
                'pm_stomachreticulum', 'pm_stomachabomasum', 'pm_intestine_small', 'pm_intestine_colon'
            ];
            
            $hasPmData = false;
            foreach ($pmFields as $field) {
                if ($request->filled($field)) {
                    $hasPmData = true;
                    break;
                }
            }
            
            if ($hasPmData) {
                $notifyWorkflow = true;
                PostmortemExamination::create([
                    'mortality_case_id' => $mortalityCase->id,
                    'examination_date' => $request->pm_examination_date ?? now()->toDateString(),
                    'external_skin' => $request->pm_external_skin ?? null,
                    'external_eyes' => $request->pm_external_eyes ?? null,
                    'external_mouth' => $request->pm_external_mouth ?? null,
                    'external_nostrils' => $request->pm_external_nostrils ?? null,
                    'external_ears' => $request->pm_external_ears ?? null,
                    'external_limbs' => $request->pm_external_limbs ?? null,
                    'external_anus' => $request->pm_external_anus ?? null,
                    'external_genital' => $request->pm_external_genital ?? null,
                    'external_general' => $request->pm_external_general ?? null,
                    'subcutaneous_findings' => $request->pm_subcutaneous ?? null,
                    'heart_findings' => $request->pm_heart ?? null,
                    'trachea_findings' => $request->pm_trachea ?? null,
                    'lung_floating_test' => $request->pm_lung ?? null,
                    'diaphragma_test' => $request->pm_diaphragma ?? null,
                    'kidney_findings' => $request->pm_kidney ?? null,
                    'reproductive_organ_findings' => $request->pm_reproductive_organ ?? null,
                    'joint_findings' => $request->pm_joint ?? null,
                    'urinary_bladder_findings' => $request->pm_bladder ?? null,
                    'liver_findings' => $request->pm_liver ?? null,
                    'spleen_findings' => $request->pm_spleen ?? null,
                    'rumen_findings' => $request->pm_stomachrumen ?? null,
                    'reticulum_findings' => $request->pm_stomachreticulum ?? null,
                    'abomasum_findings' => $request->pm_stomachabomasum ?? null,
                    'small_intestine_findings' => $request->pm_intestine_small ?? null,
                    'colon_findings' => $request->pm_intestine_colon ?? null,
                    'performed_by' => Auth::id(),
                    'status' => 'completed',
                ]);

            }

            $createdCase = $mortalityCase->fresh();
        });

        if ($createdCase && $notifyWorkflow) {
            $this->notifyMortalityWorkflowStepReady($createdCase, 0, (int) Auth::id());
        }

        return redirect()->route('mortality.records')
            ->with('success', 'Mortality case created successfully.');
    }

    public function pmExamination(Request $request)
    {
        $user = Auth::user();

        $cases = MortalityCase::where('status', 'pm_examination')
            ->where('current_step', 'issued')
            ->with(['cattle', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Mortality/PMExam', [
            'cases' => $cases,
            'customFields' => [
                'preliminary_diagnosis' => MortalityCustomField::getOptionsWithIds('preliminary_diagnosis'),
                'final_cause' => MortalityCustomField::getOptionsWithIds('final_cause'),
                // Organ examination options
                'heart_options' => MortalityCustomField::getOptionsWithIds('heart_options'),
                'trachea_options' => MortalityCustomField::getOptionsWithIds('trachea_options'),
                'lung_floating_options' => MortalityCustomField::getOptionsWithIds('lung_floating_options'),
                'diaphragma_options' => MortalityCustomField::getOptionsWithIds('diaphragma_options'),
                'kidney_options' => MortalityCustomField::getOptionsWithIds('kidney_options'),
                'urinary_bladder_options' => MortalityCustomField::getOptionsWithIds('urinary_bladder_options'),
                'bladder_options' => MortalityCustomField::getOptionsWithIds('bladder_options'),
                'liver_options' => MortalityCustomField::getOptionsWithIds('liver_options'),
                'spleen_options' => MortalityCustomField::getOptionsWithIds('spleen_options'),
                'joint_options' => MortalityCustomField::getOptionsWithIds('joint_options'),
                'subcutaneous_options' => MortalityCustomField::getOptionsWithIds('subcutaneous_options'),
                'reproductive_organ_options' => MortalityCustomField::getOptionsWithIds('reproductive_organ_options'),
                'rumen_options' => MortalityCustomField::getOptionsWithIds('rumen_options'),
                'reticulum_options' => MortalityCustomField::getOptionsWithIds('reticulum_options'),
                'omasum_options' => MortalityCustomField::getOptionsWithIds('omasum_options'),
                'abomasum_options' => MortalityCustomField::getOptionsWithIds('abomasum_options'),
                'small_intestine_options' => MortalityCustomField::getOptionsWithIds('small_intestine_options'),
                'colon_options' => MortalityCustomField::getOptionsWithIds('colon_options'),
            ]
        ]);
    }

    public function pmExaminationDetail($id)
    {
        $case = MortalityCase::with(['cattle', 'postmortemExamination', 'creator'])
            ->findOrFail($id);

        return Inertia::render('Mortality/PMExamDetail', [
            'case' => $case,
            'customFields' => [
                'preliminary_diagnosis' => MortalityCustomField::getOptionsWithIds('preliminary_diagnosis'),
            ]
        ]);
    }

    public function storePmExamination(Request $request, $mortalityCaseId)
    {
        $validated = $request->validate([
            'examination_date' => 'required|date',
            'examination_time' => 'nullable',
            'external_skin' => 'nullable|string',
            'external_eyes' => 'nullable|string',
            'external_mouth' => 'nullable|string',
            'external_nostrils' => 'nullable|string',
            'external_ears' => 'nullable|string',
            'external_limbs' => 'nullable|string',
            'external_anus' => 'nullable|string',
            'external_genital' => 'nullable|string',
            'external_general' => 'nullable|string',
            'heart_findings' => 'nullable|string',
            'trachea_findings' => 'nullable|string',
            'lung_floating_test' => 'nullable|string',
            'lung_floating_test_details' => 'nullable|string',
            'diaphragma_test' => 'nullable|string',
            'diaphragma_test_details' => 'nullable|string',
            'kidney_findings' => 'nullable|string',
            'urinary_bladder_findings' => 'nullable|string',
            'bladder_findings' => 'nullable|string',
            'liver_findings' => 'nullable|string',
            'spleen_findings' => 'nullable|string',
            'joint_findings' => 'nullable|string',
            'subcutaneous_findings' => 'nullable|string',
            'reproductive_organ_findings' => 'nullable|string',
            'rumen_findings' => 'nullable|string',
            'reticulum_findings' => 'nullable|string',
            'omasum_findings' => 'nullable|string',
            'abomasum_findings' => 'nullable|string',
            'small_intestine_findings' => 'nullable|string',
            'colon_findings' => 'nullable|string',
            'preliminary_diagnosis' => 'nullable|string',
            'confirmed_cause_of_death' => 'nullable|string',
            'cause_of_death_category' => 'nullable|string',
            'additional_notes' => 'nullable|string',
            'recommendations' => 'nullable|string',
        ]);

        $case = null;

        DB::transaction(function () use ($validated, $mortalityCaseId, &$case) {
            $case = MortalityCase::findOrFail($mortalityCaseId);

            PostmortemExamination::create(array_merge(
                ['mortality_case_id' => $mortalityCaseId],
                $validated,
                ['performed_by' => Auth::id(), 'status' => 'completed']
            ));

            $case->update([
                'status' => 'under_review',
                'current_step' => 'verified',
                'endorsement_step' => 0,
            ]);

            MortalityApproval::create([
                'mortality_case_id' => $mortalityCaseId,
                'approver_id' => Auth::id(),
                'step' => 'verified',
                'action' => 'approved',
                'comments' => 'Post-mortem examination completed',
            ]);

            $case = $case->fresh();
        });

        if ($case) {
            $this->notifyMortalityWorkflowStepReady($case, 0, (int) Auth::id());
        }

        return redirect()->route('mortality.records')
            ->with('success', 'Post-mortem examination submitted successfully!');
    }

    public function pendingApprovals(Request $request)
    {
        $role = $request->get('role', '');
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $month = $request->get('month', '');
        $year = $request->get('year', '');
        $unit = $request->get('unit', '');

        $roleSteps = [
            'estate' => 'issued',
            'veterinary' => 'verified',
            'supervisor' => 'checked',
            'manager' => 'witness',
            'security' => 'approved',
        ];

        $query = MortalityCase::query();

        // Filter by status
        if ($status === 'pending') {
            $query->whereIn('status', ['pm_examination', 'under_review']);
        } elseif ($status === 'completed') {
            $query->where('status', 'completed');
        }
        // If no status filter, show all cases

        if ($role && isset($roleSteps[$role])) {
            $query->where('current_step', $roleSteps[$role])
                  ->whereIn('status', ['pm_examination', 'under_review']);
        }

        if ($search) {
            $query->whereHas('cattle', function ($q) use ($search) {
                $q->where('tag_no', 'like', "%{$search}%");
            });
        }

        if ($month) {
            $monthMap = [
                'Jan' => 1,
                'Feb' => 2,
                'Mar' => 3,
                'Apr' => 4,
                'May' => 5,
                'Jun' => 6,
                'Jul' => 7,
                'Aug' => 8,
                'Sept' => 9,
                'Oct' => 10,
                'Nov' => 11,
                'Dec' => 12,
            ];

            if (isset($monthMap[$month])) {
                $query->whereMonth('death_date', $monthMap[$month]);
            }
        }

        if ($year) {
            $query->whereYear('death_date', (int) $year);
        }

        if ($unit) {
            $query->where('location', $unit);
        }

        $availableYears = MortalityCase::query()
            ->whereNotNull('death_date')
            ->selectRaw('YEAR(death_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->values();

        $operatingUnits = MortalityCase::query()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->select('location')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->values();

        $cases = $query->with(['cattle', 'postmortemExamination', 'approvals'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Mortality/PendingApprovals', [
            'cases' => $cases,
            'currentRole' => $role,
            'availableYears' => $availableYears,
            'operatingUnits' => $operatingUnits,
            'mortalityWorkflowAssignment' => $this->getMortalityWorkflowAssignmentForInertia(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'month' => $month,
                'year' => $year,
                'unit' => $unit,
            ]
        ]);
    }

    public function approve(Request $request, $caseId)
    {
        $validated = $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comments' => 'nullable|string',
        ]);

        $case = MortalityCase::findOrFail($caseId);

        $steps = ['issued', 'verified', 'checked', 'witness', 'approved'];
        $currentIndex = array_search($case->current_step, $steps);
        $nextStep = $steps[$currentIndex + 1] ?? null;

        DB::transaction(function () use ($case, $validated, $nextStep) {
            MortalityApproval::create([
                'mortality_case_id' => $case->id,
                'approver_id' => Auth::id(),
                'step' => $case->current_step,
                'action' => $validated['action'],
                'comments' => $validated['comments'],
            ]);

            if ($validated['action'] === 'approved' && $nextStep) {
                $case->update([
                    'current_step' => $nextStep,
                    'status' => $nextStep === 'approved' ? 'completed' : 'under_review',
                ]);
            } elseif ($validated['action'] === 'rejected') {
                $case->update([
                    'status' => 'rejected',
                    'rejection_reason' => $validated['comments'],
                ]);
            } elseif ($validated['action'] === 'returned') {
                $case->update([
                    'status' => 'pm_examination',
                ]);
            }
        });

        if ($validated['action'] === 'approved' && isset($nextStep)) {
            $nextStepIndex = array_search($nextStep, $steps, true);
            if ($nextStepIndex !== false) {
                $this->notifyMortalityWorkflowStepReady($case->fresh(), (int) $nextStepIndex, (int) Auth::id());
            }
        }

        return redirect()->back()
            ->with('success', 'Action recorded successfully!');
    }

    public function show($id)
    {
        $case = MortalityCase::with([
            'cattle',
            'postmortemExamination',
            'approvals.approver',
            'creator'
        ])->findOrFail($id);

        return Inertia::render('Mortality/Show', [
            'case' => $case,
        ]);
    }

    public function edit($id)
    {
        $case = MortalityCase::with([
            'cattle',
            'postmortemExamination',
            'creator',
            'approvals'
        ])->findOrFail($id);

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $blocks = GrazingBlock::select('block_id')
            ->distinct()
            ->orderBy('block_id')
            ->pluck('block_id');

        return Inertia::render('Mortality/Edit', [
            'case' => $case,
            'estates' => $estates,
            'blocks' => $blocks,
            'customFields' => [
                'category' => MortalityCustomField::getOptionsWithIds('category'),
                'preliminary_cause' => MortalityCustomField::getOptionsWithIds('preliminary_cause'),
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:255',
            'death_date' => 'required|date',
            'reported_by' => 'nullable|string|max:255',
            'time_of_death' => 'nullable',
            'cause_of_death' => 'nullable|string',
            'treatment' => 'nullable|string',
            'additional_info' => 'nullable|string',
            'pm_examination_date' => 'nullable|date',
            'pm_examination_time' => 'nullable',
            'pm_external_skin' => 'nullable|string',
            'pm_external_eyes' => 'nullable|string',
            'pm_external_mouth' => 'nullable|string',
            'pm_external_nostrils' => 'nullable|string',
            'pm_external_ears' => 'nullable|string',
            'pm_external_limbs' => 'nullable|string',
            'pm_external_anus' => 'nullable|string',
            'pm_external_genital' => 'nullable|string',
            'pm_external_general' => 'nullable|string',
            'pm_subcutaneous' => 'nullable|string',
            'pm_heart' => 'nullable|string',
            'pm_trachea' => 'nullable|string',
            'pm_lung' => 'nullable|string',
            'pm_diaphragma' => 'nullable|string',
            'pm_kidney' => 'nullable|string',
            'pm_reproductive_organ' => 'nullable|string',
            'pm_joint' => 'nullable|string',
            'pm_bladder' => 'nullable|string',
            'pm_liver' => 'nullable|string',
            'pm_spleen' => 'nullable|string',
            'pm_stomachrumen' => 'nullable|string',
            'pm_stomachreticulum' => 'nullable|string',
            'pm_stomachabomasum' => 'nullable|string',
            'pm_intestine_small' => 'nullable|string',
            'pm_intestine_colon' => 'nullable|string',
        ]);

        $case = MortalityCase::findOrFail($id);

        if ($case->status === 'completed' && !$case->is_reopened) {
            return back()->withErrors(['error' => 'Completed mortality records can only be edited after the workflow is reopened.']);
        }
        
        $case->update([
            'category' => $validated['category'],
            'location' => $validated['location'] ?? null,
            'block' => $validated['block'] ?? null,
            'death_date' => $validated['death_date'],
            'initial_notes' => $validated['additional_info'] ?? null,
            'reported_by' => $validated['reported_by'] ?? null,
            'time_of_death' => $validated['time_of_death'] ?? null,
            'cause_of_death' => $validated['cause_of_death'] ?? null,
            'treatment' => $validated['treatment'] ?? null,
        ]);

        $pmFields = [
            'pm_examination_date', 'pm_external_skin', 'pm_external_eyes', 'pm_external_mouth',
            'pm_external_nostrils', 'pm_external_ears', 'pm_external_limbs', 'pm_external_anus',
            'pm_external_genital', 'pm_external_general', 'pm_subcutaneous', 'pm_heart',
            'pm_trachea', 'pm_lung', 'pm_diaphragma', 'pm_kidney', 'pm_reproductive_organ',
            'pm_joint', 'pm_bladder', 'pm_liver', 'pm_spleen', 'pm_stomachrumen',
            'pm_stomachreticulum', 'pm_stomachabomasum', 'pm_intestine_small', 'pm_intestine_colon'
        ];

        $hasPmData = false;
        foreach ($pmFields as $field) {
            if ($request->filled($field)) {
                $hasPmData = true;
                break;
            }
        }

        if ($hasPmData) {
            $pmData = [
                'mortality_case_id' => $case->id,
                'examination_date' => $request->pm_examination_date ?? now()->toDateString(),
                'examination_time' => $request->pm_examination_time ?? null,
                'external_skin' => $request->pm_external_skin ?? null,
                'external_eyes' => $request->pm_external_eyes ?? null,
                'external_mouth' => $request->pm_external_mouth ?? null,
                'external_nostrils' => $request->pm_external_nostrils ?? null,
                'external_ears' => $request->pm_external_ears ?? null,
                'external_limbs' => $request->pm_external_limbs ?? null,
                'external_anus' => $request->pm_external_anus ?? null,
                'external_genital' => $request->pm_external_genital ?? null,
                'external_general' => $request->pm_external_general ?? null,
                'subcutaneous_findings' => $request->pm_subcutaneous ?? null,
                'heart_findings' => $request->pm_heart ?? null,
                'trachea_findings' => $request->pm_trachea ?? null,
                'lung_floating_test' => $request->pm_lung ?? null,
                'diaphragma_test' => $request->pm_diaphragma ?? null,
                'kidney_findings' => $request->pm_kidney ?? null,
                'reproductive_organ_findings' => $request->pm_reproductive_organ ?? null,
                'joint_findings' => $request->pm_joint ?? null,
                'urinary_bladder_findings' => $request->pm_bladder ?? null,
                'liver_findings' => $request->pm_liver ?? null,
                'spleen_findings' => $request->pm_spleen ?? null,
                'rumen_findings' => $request->pm_stomachrumen ?? null,
                'reticulum_findings' => $request->pm_stomachreticulum ?? null,
                'abomasum_findings' => $request->pm_stomachabomasum ?? null,
                'small_intestine_findings' => $request->pm_intestine_small ?? null,
                'colon_findings' => $request->pm_intestine_colon ?? null,
                'performed_by' => Auth::id(),
                'status' => 'completed',
            ];

            $case->postmortemExamination()->updateOrCreate(
                ['mortality_case_id' => $case->id],
                $pmData
            );
        }

        \Illuminate\Support\Facades\Log::info('Mortality update debug', [
            'record_id' => $case->id,
            'status_before_update' => $case->status ?? 'unknown',
            'endorsement_step_before_update' => $case->endorsement_step ?? 0,
        ]);

        \Illuminate\Support\Facades\Log::info('FORCE RESTART workflow - status was: ' . ($case->status ?? 'unknown'));

        $case->update([
            'status' => 'pending',
            'current_step' => 'issued',
            'endorsement_step' => 0,
            'endorsement_documents' => null,
            'is_reopened' => false,
        ]);

        // Clear all endorsement approvals
        $case->approvals()->delete();

        return redirect()->route('mortality.records')
            ->with('success', 'Mortality case updated successfully. Workflow reset to step 1.');
    }

    public function workflow($id)
    {
        $case = MortalityCase::with([
            'cattle',
            'postmortemExamination',
            'approvals.approver',
            'creator'
        ])->findOrFail($id);

        return Inertia::render('Mortality/Workflow', [
            'case' => $case,
            'mortalityWorkflowAssignment' => $this->getMortalityWorkflowAssignmentForInertia(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function completedCases(Request $request)
    {
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = MortalityCase::where('status', 'completed');

        if ($search) {
            $query->whereHas('cattle', function ($q) use ($search) {
                $q->where('tag_no', 'like', "%{$search}%");
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $cases = $query->with(['cattle', 'postmortemExamination'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Mortality/Completed', [
            'cases' => $cases,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]
        ]);
    }

    public function getPendingCounts()
    {
        $counts = MortalityCase::select('current_step')
            ->whereIn('status', ['pm_examination', 'under_review'])
            ->groupBy('current_step')
            ->selectRaw('current_step, count(*) as count')
            ->pluck('count', 'current_step');

        return response()->json([
            'issued' => $counts['issued'] ?? 0,
            'verified' => $counts['verified'] ?? 0,
            'checked' => $counts['checked'] ?? 0,
            'witness' => $counts['witness'] ?? 0,
            'approved' => $counts['approved'] ?? 0,
            'total' => array_sum($counts->toArray()),
        ]);
    }

    public function storeCustomField(Request $request)
    {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'value' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('mortality_custom_fields')
                    ->where('field_type', $request->input('field_type')),
            ],
        ], [
            'value.unique' => 'This option already exists for the selected field type.',
        ]);

        MortalityCustomField::create([
            'field_type' => $validated['field_type'],
            'value' => $validated['value'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return redirect()->back()->with('success', 'Option added successfully!');
    }

    public function updateCustomField(Request $request, MortalityCustomField $customField)
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('mortality_custom_fields')
                    ->where('field_type', $customField->field_type)
                    ->ignore($customField->id),
            ],
        ], [
            'value.unique' => 'This option already exists for the selected field type.',
        ]);

        $customField->update(['value' => $validated['value']]);

        return redirect()->back()->with('success', 'Option updated successfully!');
    }

    public function destroyCustomField(MortalityCustomField $customField)
    {
        $customField->delete();

        return redirect()->back()->with('success', 'Option deleted successfully!');
    }

    public function getCustomFields(string $fieldType)
    {
        $fields = MortalityCustomField::where('field_type', $fieldType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'value', 'field_type']);

        return response()->json($fields);
    }

    /**
     * Upload endorsement document for a mortality case
     */
    public function uploadEndorsement(Request $request, $id)
    {
        $request->validate([
            'signed_document' => 'required|file|mimes:pdf|max:10240',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'step_index' => 'required|integer|min:0|max:4',
        ]);

        $case = MortalityCase::findOrFail($id);
        $user = Auth::user();
        $stepIndex = (int) $request->step_index;

        if ($stepIndex < 0 || $stepIndex >= self::MORTALITY_WORKFLOW_STEP_COUNT) {
            return back()->withErrors(['error' => 'Invalid workflow step']);
        }

        if (!$this->userCanUploadMortalityWorkflowStep($user, $stepIndex)) {
            return back()->withErrors(['error' => 'You do not have permission to upload for this step']);
        }

        // Get current endorsement documents as array for manipulation
        $rawDocs = $case->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }
        
        $currentStep = $case->endorsement_step ?? 0;

        // Block uploads if case is already completed
        if ($case->status === 'completed') {
            return back()->withErrors(['error' => 'This case has been completed. No further uploads are allowed.']);
        }

        // Check if can upload (current step or re-upload logic)
        $nextStepKey = strval($stepIndex + 1);
        
        // For steps 0-3: can upload at current step, OR re-upload if next person hasn't uploaded yet
        // For step 4 (manager): can upload/re-upload anytime until admin marks as completed
        if ($stepIndex === 4) {
            // Manager (last step) - can upload/re-upload until case is completed
            $canUpload = ($stepIndex <= $currentStep);
        } else {
            // Steps 0-3 - can upload at current step, OR re-upload if next hasn't uploaded
            $canUpload = ($stepIndex === $currentStep) || 
                         ($stepIndex < $currentStep && !isset($endorsementDocs[$nextStepKey]));
        }

        if (!$canUpload) {
            return back()->withErrors(['error' => 'Cannot upload at this step']);
        }

        // Store the file
        $file = $request->file('signed_document');
        $filename = 'endorsement_' . $case->id . '_step' . $stepIndex . '_' . time() . '.pdf';
        $path = $file->storeAs('mortality_endorsements', $filename, 'public');

        // Update endorsement documents - use string key for consistent JSON object
        $endorsementDocs[strval($stepIndex)] = [
            'name' => $request->name,
            'date' => $request->date,
            'file_path' => $path,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->toDateTimeString(),
        ];
        
        // Convert back to object for storage
        $endorsementDocsObject = (object) $endorsementDocs;

        // Move to next step if uploading at current step
        $newStep = $currentStep;
        if ($stepIndex === $currentStep && $stepIndex < 4) {
            $newStep = $stepIndex + 1;
        }

        // If manager (step 4) uploads, set step to 5 (all uploaded, awaiting admin completion)
        if ($stepIndex === 4) {
            $newStep = 5; // All steps done, awaiting admin to mark as completed
        }

        // Update case - DO NOT auto-complete, admin must manually mark as completed
        $case->update([
            'endorsement_documents' => $endorsementDocsObject,
            'endorsement_step' => $newStep,
        ]);

        if ($stepIndex === $currentStep && $stepIndex < 4) {
            $this->notifyNextMortalityWorkflowStep($case->fresh(), $stepIndex, (int) $user->id);
        } elseif ($stepIndex === 4 && $newStep === 5) {
            $this->notifyMortalityWorkflowReadyForCompletion($case->fresh(), (int) $user->id);
        }

        return back()->with('success', 'Document uploaded successfully');
    }

    /**
     * Admin: Mark case as completed (locks all uploads)
     */
    public function markAsCompleted($id)
    {
        $user = Auth::user();

        if (strtolower((string) ($user->role ?? '')) !== 'admin') {
            return back()->withErrors(['error' => 'Only administrators can mark mortality records as completed']);
        }

        $case = MortalityCase::findOrFail($id);
        
        // Check if all 5 steps are uploaded
        $rawDocs = $case->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        // Verify all 5 steps have documents
        for ($i = 0; $i < 5; $i++) {
            if (!isset($endorsementDocs[strval($i)])) {
                return back()->withErrors(['error' => 'All 5 endorsement steps must be completed before marking as completed']);
            }
        }

        $case->update([
            'status' => 'completed',
            'current_step' => 'approved',
            'is_reopened' => false,
        ]);

        if ($case->cattle) {
            $case->cattle->update(['status' => 'Deceased']);
        }

        $this->syncMortalityToCattle($case->fresh('cattle'));

        if ($case->created_by) {
            \App\Services\WorkflowNotificationService::createNotification(
                'mortality',
                'mortality',
                $case->id,
                'completed',
                'Mortality Record Completed',
                "Your mortality record LMC-{$case->id} has been marked as completed by an admin.",
                $case->created_by
            );
        }

        return back()->with('success', 'Case marked as completed. No further uploads are allowed.');
    }

    public function reopen($id)
    {
        $user = Auth::user();



        $case = MortalityCase::findOrFail($id);

        if (!in_array($case->status, ['completed', 'approved'], true)) {
            return back()->withErrors(['error' => 'Only completed or approved mortality cases can be reopened']);
        }

        $rawDocs = $case->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $has0 = isset($endorsementDocs['0']) || isset($endorsementDocs[0]);
        $has1 = isset($endorsementDocs['1']) || isset($endorsementDocs[1]);
        $has2 = isset($endorsementDocs['2']) || isset($endorsementDocs[2]);
        $has3 = isset($endorsementDocs['3']) || isset($endorsementDocs[3]);
        $has4 = isset($endorsementDocs['4']) || isset($endorsementDocs[4]);

        $newStep = 0;
        $newStatus = 'pending';

        if ($has0 && !$has1) {
            $newStep = 1;
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && !$has2) {
            $newStep = 2;
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && $has2 && !$has3) {
            $newStep = 3;
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && $has2 && $has3 && !$has4) {
            $newStep = 4;
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && $has2 && $has3 && $has4) {
            // All endorsement steps already uploaded; keep progress at full step.
            $newStep = 5;
            $newStatus = 'under_review';
        }

        $case->update([
            'status' => $newStatus,
            'endorsement_step' => $newStep,
            'is_reopened' => true,
        ]);

        if ($case->cattle) {
            $case->cattle->update(['status' => 'Active']);
        }

        \App\Models\CattleHealthRecord::where('source_type', 'mortality')
            ->where('source_id', $case->id)
            ->delete();

        return back()->with('success', 'Mortality case reopened successfully.');
    }

    /**
     * Admin: Delete a mortality case and restore cattle to Active status
     */
    public function destroy(MortalityCase $case)
    {
        $user = Auth::user();
        
        if ($case->status === 'completed') {
            return back()->withErrors(['error' => 'Completed mortality records cannot be deleted.']);
        }

        // Get the cattle before deleting the case
        $cattle = $case->cattle;

        DB::transaction(function () use ($case, $cattle) {
            // Delete related records first
            $case->approvals()->delete();
            $case->postmortemExamination()->delete();
            CattleHealthRecord::where('source_type', 'mortality')
                ->where('source_id', $case->id)
                ->delete();
            
            // Delete the mortality case
            $case->delete();
            
            // Restore cattle status to Active
            if ($cattle) {
                $cattle->update(['status' => 'Active']);
            }
        });

        return redirect()->back()->with('success', 'Mortality case deleted successfully. Cattle status restored to Active.');
    }

    private function syncMortalityToCattle(MortalityCase $case): void
    {
        if (!$case->cattle_id) {
            return;
        }

        CattleHealthRecord::updateOrCreate(
            [
                'source_type' => 'mortality',
                'source_id' => $case->id,
            ],
            [
                'cattle_id' => $case->cattle_id,
                'reference_no' => $case->lmc_no,
                'category' => $case->category,
                'operating_unit' => $case->location,
                'date' => $case->death_date,
                'description' => $case->cause_of_death,
                'treatment' => $case->treatment,
                'notes' => $case->initial_notes,
                'status' => $case->status,
                'metadata' => [
                    'block' => $case->block,
                    'reported_by' => $case->reported_by,
                    'time_of_death' => $case->time_of_death,
                ],
            ]
        );
    }

    /**
     * Download endorsement document
     */
    public function downloadEndorsement($id, $stepIndex)
    {
        $case = MortalityCase::findOrFail($id);
        $user = Auth::user();
        $stepIndex = (int) $stepIndex;

        if (!$this->userHasMortalityModulePermission($user, 'view')) {
            abort(403, 'You do not have permission to view this document');
        }

        if (strtolower((string) ($user->role ?? '')) !== 'admin') {
            $canViewOwn = $this->userCanUploadMortalityWorkflowStep($user, $stepIndex);
            $canViewPrevious = $this->userCanUploadMortalityWorkflowStep($user, $stepIndex + 1);
            if (!$canViewOwn && !$canViewPrevious) {
                abort(403, 'You can only view your own document or the previous person\'s document');
            }
        }

        // Get current endorsement documents as array
        $rawDocs = $case->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }
        $stepKey = strval($stepIndex);
        
        if (!isset($endorsementDocs[$stepKey])) {
            abort(404, 'Document not found');
        }

        $filePath = $endorsementDocs[$stepKey]['file_path'];
        
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($filePath);
    }

    /**
     * Generate and download LMC Endorsement Form PDF
     */
    public function downloadEndorsementForm($id)
    {
        $case = MortalityCase::with(['cattle', 'postmortemExamination', 'creator', 'approvals.approver'])
            ->findOrFail($id);

        // Workflow steps for endorsement
        $workflowSteps = [
            ['role' => 'livestock', 'label' => 'Issued by', 'role_name' => 'Sr. Assistant Livestock'],
            ['role' => 'security', 'label' => 'Verified by', 'role_name' => 'Sr. Assistant Security'],
            ['role' => 'supervisor', 'label' => 'Checked by', 'role_name' => 'Supervisor Livestock'],
            ['role' => 'penyelia', 'label' => 'Witnessed by', 'role_name' => 'Penyelia Security'],
            ['role' => 'manager', 'label' => 'Approved by', 'role_name' => 'Livestock Manager/OIC'],
        ];

        // Convert endorsement_documents to array, handling both object and array formats
        $rawDocs = $case->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $html = view('pdfs.lmc-form', [
            'case' => $case,
            'workflowSteps' => $workflowSteps,
            'endorsementDocs' => $endorsementDocs
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'LMC_Endorsement_Form_' . ($case->lmc_no ?? 'LMC-' . $case->id) . '.pdf';
        
        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
