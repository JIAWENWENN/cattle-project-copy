<?php

namespace App\Http\Controllers;

use App\Models\CalvingChecklist;
use App\Models\CalvingMonthlyWorkflow;
use App\Models\CalvingRecord;
use App\Models\Cattle;
use App\Models\CattleCustomField;
use App\Models\CalvingWorkflowAssignment;
use App\Models\Estate;
use App\Models\PastureBlock;
use App\Models\PasturePhase;
use App\Models\WorkflowAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CalvingChecklistController extends Controller
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ISSUED = 'issued';
    const STATUS_VERIFIED = 'verified';
    const STATUS_CHECKED = 'checked';
    const STATUS_WITNESSED = 'witnessed';

    /**
     * Convert month format from YYYY-MM to Mmm/YYYY for database queries
     */
    private function convertMonthToStorageFormat($monthStr)
    {
        if (!$monthStr) return $monthStr;
        
        // If already in Mmm/YYYY format, return as is
        if (preg_match('/^[A-Za-z]{3,4}\/\d{4}$/', $monthStr)) {
            return $monthStr;
        }
        
        // Convert YYYY-MM to Mmm/YYYY
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthStr, $matches)) {
            $year = $matches[1];
            $monthNum = intval($matches[2]);
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
            if ($monthNum >= 1 && $monthNum <= 12) {
                return $monthNames[$monthNum - 1] . '/' . $year;
            }
        }
        
        return $monthStr;
    }

    /**
     * Resolve workflow assignees from calving_workflow_assignments, with legacy
     * workflow_assignments rows saved when Calving Checklist used the generic ACM path.
     */
    private function getResolvedWorkflowAssignment(): array
    {
        $data = [];

        if (Schema::hasTable('calving_workflow_assignments')) {
            $assignment = CalvingWorkflowAssignment::first();
            if ($assignment) {
                $data = $assignment->toArray();
            }
        }

        if (Schema::hasTable('workflow_assignments')) {
            $legacy = WorkflowAssignment::where('module', 'Calving Checklist')->first();
            if ($legacy && is_array($legacy->assignments)) {
                foreach ([
                    'issued_by_user_ids',
                    'verified_by_user_ids',
                    'checked_by_user_ids',
                    'witnessed_by_user_ids',
                    'approved_by_user_ids',
                ] as $key) {
                    $legacyIds = array_values(array_filter(array_map(
                        'intval',
                        is_array($legacy->assignments[$key] ?? null) ? $legacy->assignments[$key] : []
                    )));
                    if (empty($legacyIds)) {
                        continue;
                    }
                    $existing = is_array($data[$key] ?? null) ? array_map('intval', $data[$key]) : [];
                    $data[$key] = array_values(array_unique(array_merge($existing, $legacyIds)));
                }
            }
        }

        return $data;
    }

    private function getAssignedUserIdsForWorkflowStep(int $stepIndex, ?array $assignment = null): array
    {
        $assignment = $assignment ?? $this->getResolvedWorkflowAssignment();
        if (empty($assignment)) {
            return [];
        }

        $key = match ($stepIndex) {
            0 => 'issued_by_user_ids',
            1 => 'verified_by_user_ids',
            2 => 'witnessed_by_user_ids',
            3 => 'approved_by_user_ids',
            default => null,
        };

        if (!$key) {
            return [];
        }

        $ids = is_array($assignment[$key] ?? null) ? $assignment[$key] : [];
        $legacyKey = str_replace('_user_ids', '_user_id', $key);
        if (empty($ids) && !empty($assignment[$legacyKey])) {
            $ids = [$assignment[$legacyKey]];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function getOperatingUnitsWithStructure(): array
    {
        return Estate::where('is_active', true)
            ->with(['pastureBlocks.phases'])
            ->orderBy('name')
            ->get()
            ->map(function ($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'blocks' => $estate->pastureBlocks->map(function ($block) {
                        return [
                            'id' => $block->id,
                            'name' => $block->name,
                            'phases' => $block->phases->map(function ($phase) {
                                return [
                                    'id' => $phase->id,
                                    'name' => $phase->name,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeLocationOptionName(?string $name): ?string
    {
        $normalized = ucwords(strtolower(trim((string) $name)));
        return $normalized !== '' ? $normalized : null;
    }

    private function getAllBlocks(): array
    {
        return collect(PastureBlock::orderBy('name')->pluck('name'))
            ->merge(DB::table('cattle')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('location_block'))
            ->merge(DB::table('calving_records')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('location_block'))
            ->merge(DB::table('calving_checklists')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('location_block'))
            ->map(fn ($name) => $this->normalizeLocationOptionName($name))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function getAllPhases(): array
    {
        return collect(PasturePhase::orderBy('name')->pluck('name'))
            ->merge(DB::table('cattle')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('location_phase'))
            ->merge(DB::table('calving_records')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('location_phase'))
            ->merge(DB::table('calving_checklists')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('location_phase'))
            ->map(fn ($name) => $this->normalizeLocationOptionName($name))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function getFormLocationPayload(): array
    {
        return [
            'operatingUnitsWithStructure' => $this->getOperatingUnitsWithStructure(),
            'allBlocks' => $this->getAllBlocks(),
            'allPhases' => $this->getAllPhases(),
        ];
    }

    /**
     * Lookup calving record genealogy by calf tag (latest record wins).
     */
    private function getCalvingRecordsLookup(): array
    {
        return CalvingRecord::query()
            ->select('tag_no', 'lcc_running_number', 'dam_tag_no', 'dam_colour', 'sire_tag_no', 'sire_colour')
            ->whereNotNull('tag_no')
            ->where('tag_no', '!=', '')
            ->orderByDesc('id')
            ->get()
            ->unique('tag_no')
            ->keyBy('tag_no')
            ->map(fn (CalvingRecord $record) => [
                'lcc_running_number' => $record->lcc_running_number,
                'dam_tag_no' => $record->dam_tag_no,
                'dam_colour' => $record->dam_colour,
                'sire_tag_no' => $record->sire_tag_no,
                'sire_colour' => $record->sire_colour,
            ])
            ->all();
    }

    /**
     * Merge linked calving record fields into cattle data (matches cattle directory sync).
     */
    private function mergeCalvingRecordIntoCattle(Cattle $cattle): array
    {
        $data = $cattle->toArray();

        $calvingRecord = null;
        if (!empty($cattle->calving_record_id)) {
            $calvingRecord = CalvingRecord::find($cattle->calving_record_id);
        }
        if (!$calvingRecord && !empty($cattle->tag_no)) {
            $calvingRecord = CalvingRecord::where('tag_no', $cattle->tag_no)
                ->orderByDesc('id')
                ->first();
        }

        if (!$calvingRecord) {
            return $data;
        }

        $isCompleted = $calvingRecord->status === 'completed';

        $apply = function (string $cattleKey, ?string $calvingValue, bool $onlyIfCattleEmpty = true) use (&$data, $isCompleted) {
            $calvingValue = is_string($calvingValue) ? trim($calvingValue) : '';
            if ($calvingValue === '') {
                return;
            }
            $cattleValue = trim((string) ($data[$cattleKey] ?? ''));
            if ($isCompleted || !$onlyIfCattleEmpty || $cattleValue === '') {
                $data[$cattleKey] = $calvingValue;
            }
        };

        $apply('dam_tag', $calvingRecord->dam_tag_no);
        $apply('dam_colour', $calvingRecord->dam_colour);
        $apply('sire_tag', $calvingRecord->sire_tag_no);
        $apply('sire_coat_colour', $calvingRecord->sire_colour);
        $apply('lcc_running_number', $calvingRecord->lcc_running_number);

        if ($isCompleted && !empty($calvingRecord->colour)) {
            $data['coat_colour'] = $calvingRecord->colour;
        }

        return $data;
    }

    private function getCattleForChecklistForms()
    {
        return Cattle::select(
            'id',
            'tag_no',
            'category',
            'coat_colour',
            'gender',
            'operating_unit',
            'location_block',
            'location_phase',
            'dam_tag',
            'dam_colour',
            'sire_tag',
            'sire_coat_colour',
            'birth_date',
            'general_condition',
            'status',
            'lcc_running_number',
            'calving_record_id'
        )
            ->where('status', '!=', 'Deceased')
            ->orderBy('tag_no')
            ->get()
            ->map(fn (Cattle $cattle) => $this->mergeCalvingRecordIntoCattle($cattle))
            ->values();
    }

    public function index(Request $request)
    {
        // Scope is optional on listing page; only apply when user has selected it.
        $monthYear = $request->filled('month') ? $request->get('month') : null;
        $operatingUnit = $request->filled('unit') ? $request->get('unit') : null;
        
        // Convert month format if needed (YYYY-MM to Mmm/YYYY) - only if month is provided
        if ($monthYear) {
            $monthYear = $this->convertMonthToStorageFormat($monthYear);
        }

        $query = CalvingChecklist::query();

        // Only filter by month if explicitly requested
        if ($monthYear) {
            $query->forMonth($monthYear);
        }

        if ($operatingUnit) {
            $query->where('operating_unit', $operatingUnit);
        }

        $checklistRecords = $query->orderBy('calving_date', 'asc')->get();

        // Get or create the monthly workflow for this month/unit (only if month is specified)
        $monthlyWorkflow = null;
        if ($monthYear && $operatingUnit) {
            $monthlyWorkflow = CalvingMonthlyWorkflow::firstOrCreate(
                ['month_year' => $monthYear, 'operating_unit' => $operatingUnit]
            );
        }

        // Calculate stats - if no month filter, show stats for all records
        if ($monthYear) {
            $monthRecords = CalvingChecklist::forMonth($monthYear)->get();
        } else {
            $monthRecords = CalvingChecklist::all();
        }
        $stats = [
            'total' => $monthRecords->count(),
            'pending' => $monthRecords->where('status', self::STATUS_PENDING)->count(),
            'approved' => $monthRecords->where('status', self::STATUS_APPROVED)->count(),
            'this_month' => $monthRecords->count(),
            'male_calves' => $monthRecords->where('sex', 'MC')->count(),
            'female_calves' => $monthRecords->where('sex', 'FC')->count(),
        ];

        $availableMonths = CalvingChecklist::distinct()->pluck('month_year')->filter()->values()->toArray();
        if (empty($availableMonths)) {
            $availableMonths = [date('M/Y')];
        }
        
        $operatingUnits = CalvingChecklist::distinct()->pluck('operating_unit')->filter()->values()->toArray();
        if (empty($operatingUnits)) {
            $operatingUnits = ['DEFAULT UNIT'];
        }

        $completedWorkflowScopeKeys = CalvingMonthlyWorkflow::query()
            ->where('is_completed', true)
            ->get(['month_year', 'operating_unit'])
            ->map(fn ($workflow) => trim((string) $workflow->month_year) . '|' . trim((string) $workflow->operating_unit))
            ->unique()
            ->values()
            ->all();

        return Inertia::render('CalvingChecklist/Index', [
            'checklistRecords' => $checklistRecords,
            'stats' => $stats,
            'monthYear' => $monthYear ?: 'All Records',
            'operatingUnit' => $operatingUnit ?: '',
            'availableMonths' => $availableMonths,
            'operatingUnits' => $operatingUnits,
            'monthlyWorkflow' => $monthlyWorkflow,
            'docWorkflowSteps' => CalvingMonthlyWorkflow::WORKFLOW_STEPS,
            'userRole' => auth()->user()?->role ?? '',
            'workflowAssignment' => $this->getResolvedWorkflowAssignment() ?: null,
            'completedWorkflowScopeKeys' => $completedWorkflowScopeKeys,
        ]);
    }

    public function create(Request $request)
    {
        $monthYear = $request->get('month', 'Sept/2024');
        $operatingUnit = $request->get('unit', '');
        
        // Convert month format if needed (YYYY-MM to Mmm/YYYY)
        $monthYear = $this->convertMonthToStorageFormat($monthYear);

        $cattle = $this->getCattleForChecklistForms();
        $calvingRecords = $this->getCalvingRecordsLookup();

        // Get operating units from active estates
        $operatingUnits = Estate::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        // Get custom field options from CattleCustomField
        $coatColours = collect(array_merge(
            CattleCustomField::getOptionsWithIds('coat_colour'),
            CattleCustomField::getOptionsWithIds('calving_colour')
        ))->unique('value')->values()->toArray();

        return Inertia::render('CalvingChecklist/Create', [
            'monthYear' => $monthYear,
            'operatingUnit' => $operatingUnit,
            'operatingUnits' => $operatingUnits,
            'cattle' => $cattle,
            'calvingRecords' => $calvingRecords,
            'customFields' => [
                'coat_colour' => $coatColours,
            ],
            ...$this->getFormLocationPayload(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'month_year' => 'required|string|max:50',
                'operating_unit' => 'required|string|max:255',
                'tag_no' => 'required|string|max:50',
                'lcc_running_number' => 'nullable|string|max:50',
                'calving_date' => 'nullable|date',
                'sex' => 'required|in:MC,FC',
                'colour' => 'required|string|max:100',
                'general_condition' => 'nullable|string|max:100',
                'dam_tag_no' => 'required|string|max:50',
                'dam_colour' => 'nullable|string|max:100',
                'sire_tag_no' => 'nullable|string|max:50',
                'sire_colour' => 'nullable|string|max:100',
                'worker_name' => 'nullable|string|max:255',
                'herd' => 'nullable|string|max:100',
                'location_block' => 'nullable|string|max:100',
                'location_phase' => 'nullable|string|max:100',
                'times_of_pregnancy' => 'nullable|string|max:50',
                'location' => 'nullable|string|max:255',
                'week' => 'nullable|max:50',
                'treatment_iodine' => 'nullable|boolean',
                'treatment_woundsarex' => 'nullable|boolean',
                'colostrum_feeding_24h' => 'nullable|boolean',
                'mamumune' => 'nullable|boolean',
                'tagging_checklist_date' => 'nullable|date',
                'remarks' => 'nullable|string',
            ]);

            $checklistRecord = CalvingChecklist::create([
                'company_name' => 'SAWIT KINABALU FARM PRODUCTS SDN BHD',
                'company_no' => '465571-P',
                'ownership' => 'Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)',
                'form_no' => 'FORM 1B',
                'mcc_no' => '00023',
                ...$validated,
                'status' => CalvingChecklist::STATUS_PENDING,
                'workflow_status' => 'pending',
                'endorsement_step' => 0,
            ]);

            // Reset monthly workflow if exists and not completed
            $workflow = CalvingMonthlyWorkflow::where('month_year', $checklistRecord->month_year)
                ->where('operating_unit', $checklistRecord->operating_unit)
                ->first();

            if ($workflow) {
                $workflow->update([
                    'endorsement_step' => 0,
                    'status' => 'pending',
                    'endorsement_documents' => null,
                    'is_completed' => false,
                    'completed_at' => null,
                ]);
            }

            // Reopen all records in the same monthly scope and reset endorsement progress.
            CalvingChecklist::where('month_year', $checklistRecord->month_year)
                ->where('operating_unit', $checklistRecord->operating_unit)
                ->update([
                    'status' => self::STATUS_PENDING,
                    'workflow_status' => 'pending',
                    'endorsement_step' => 0,
                    'endorsement_documents' => null,
                    'is_completed' => false,
                    'completed_at' => null,
                ]);

            DB::commit();

            return redirect()->route('calving-checklist.index')
                ->with('success', 'Calving checklist record created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating calving checklist record: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create record: ' . $e->getMessage());
        }
    }

    public function show(CalvingChecklist $calvingChecklist)
    {
        $userRole = auth()->user()?->role ?? '';

        return Inertia::render('CalvingChecklist/Show', [
            'checklistRecord' => $calvingChecklist,
            'userRole' => $userRole,
            'docWorkflowSteps' => CalvingChecklist::DOC_WORKFLOW_STEPS,
        ]);
    }

    public function edit(CalvingChecklist $calvingChecklist)
    {
        $user = auth()->user();

        if (!$calvingChecklist->canEdit($user)) {
            return redirect()->route('calving-checklist.show', $calvingChecklist->id)
                ->with('error', 'You do not have permission to edit this record.');
        }

        $workflow = CalvingMonthlyWorkflow::where('month_year', $calvingChecklist->month_year)
            ->where('operating_unit', $calvingChecklist->operating_unit)
            ->first();

        if ($workflow && $workflow->is_completed) {
            return redirect()->route('calving-checklist.index')
                ->with('error', 'Completed calving checklist records cannot be edited.');
        }

        $coatColours = collect(array_merge(
            CattleCustomField::getOptionsWithIds('coat_colour'),
            CattleCustomField::getOptionsWithIds('calving_colour')
        ))->unique('value')->values()->toArray();

        return Inertia::render('CalvingChecklist/Edit', [
            'checklistRecord' => $calvingChecklist,
            'cattle' => $this->getCattleForChecklistForms(),
            'calvingRecords' => $this->getCalvingRecordsLookup(),
            'customFields' => [
                'coat_colour' => $coatColours,
            ],
            ...$this->getFormLocationPayload(),
        ]);
    }

    public function update(Request $request, CalvingChecklist $calvingChecklist)
    {
        try {
            if (!$calvingChecklist->canEdit(auth()->user())) {
                return back()->withInput()->with('error', 'You do not have permission to edit this record.');
            }

            DB::beginTransaction();
            $originalTagNo = $calvingChecklist->tag_no;

            // Check if monthly workflow is completed
            $workflow = CalvingMonthlyWorkflow::where('month_year', $calvingChecklist->month_year)
                ->where('operating_unit', $calvingChecklist->operating_unit)
                ->first();

            if ($workflow && $workflow->is_completed) {
                return back()->withInput()->with('error', 'Completed calving checklist records cannot be edited.');
            }

            $validated = $request->validate([
                'month_year' => 'required|string|max:50',
                'operating_unit' => 'required|string|max:255',
                'tag_no' => 'required|string|max:50',
                'lcc_running_number' => 'nullable|string|max:50',
                'calving_date' => 'nullable|date',
                'sex' => 'required|in:MC,FC',
                'colour' => 'required|string|max:100',
                'general_condition' => 'nullable|string|max:100',
                'dam_tag_no' => 'required|string|max:50',
                'dam_colour' => 'nullable|string|max:100',
                'sire_tag_no' => 'nullable|string|max:50',
                'sire_colour' => 'nullable|string|max:100',
                'worker_name' => 'nullable|string|max:255',
                'herd' => 'nullable|string|max:100',
                'location_block' => 'nullable|string|max:100',
                'location_phase' => 'nullable|string|max:100',
                'times_of_pregnancy' => 'nullable|string|max:50',
                'location' => 'nullable|string|max:255',
                'week' => 'nullable|max:50',
                'treatment_iodine' => 'nullable|boolean',
                'treatment_woundsarex' => 'nullable|boolean',
                'colostrum_feeding_24h' => 'nullable|boolean',
                'mamumune' => 'nullable|boolean',
                'tagging_checklist_date' => 'nullable|date',
                'remarks' => 'nullable|string',
            ]);

            $calvingChecklist->update($validated);
            $this->syncCattleFromCalvingChecklist($calvingChecklist->fresh(), $originalTagNo);

            // Reset monthly workflow if exists and not completed
            $workflow = CalvingMonthlyWorkflow::where('month_year', $calvingChecklist->month_year)
                ->where('operating_unit', $calvingChecklist->operating_unit)
                ->first();

            if ($workflow && !$workflow->is_completed) {
                $workflow->update([
                    'endorsement_step' => 0,
                    'status' => 'pending',
                    'endorsement_documents' => null,
                ]);
            }

            DB::commit();

            return redirect()->route('calving-checklist.index')
                ->with('success', 'Calving checklist record updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating calving checklist record: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Failed to update record. Please try again.');
        }
    }

    private function syncCattleFromCalvingChecklist(CalvingChecklist $calvingChecklist, ?string $oldTagNo = null): void
    {
        $cattle = null;

        if (!empty($calvingChecklist->tag_no)) {
            $cattle = Cattle::where('tag_no', $calvingChecklist->tag_no)->first();
        }

        if (!$cattle && !empty($oldTagNo)) {
            $cattle = Cattle::where('tag_no', $oldTagNo)->first();
        }

        if (!$cattle) {
            return;
        }

        if (!empty($calvingChecklist->tag_no) && $cattle->tag_no !== $calvingChecklist->tag_no) {
            $tagConflict = Cattle::where('tag_no', $calvingChecklist->tag_no)
                ->where('id', '!=', $cattle->id)
                ->exists();

            if ($tagConflict) {
                throw new \RuntimeException("Unable to sync cattle profile: tag number '{$calvingChecklist->tag_no}' is already used by another cattle record.");
            }
        }

        $cattle->update([
            'tag_no' => $calvingChecklist->tag_no,
            'lcc_running_number' => $calvingChecklist->lcc_running_number,
            'coat_colour' => $calvingChecklist->colour,
            'birth_date' => $calvingChecklist->calving_date,
            'gender' => $calvingChecklist->sex === 'MC' ? 'Male' : 'Female',
            'general_condition' => $calvingChecklist->general_condition,
            'ownership' => $calvingChecklist->ownership,
            'location_block' => $calvingChecklist->location_block,
            'operating_unit' => $calvingChecklist->operating_unit,
            'location_phase' => $calvingChecklist->location_phase,
            'dam_tag' => $calvingChecklist->dam_tag_no,
            'dam_colour' => $calvingChecklist->dam_colour,
            'sire_tag' => $calvingChecklist->sire_tag_no,
            'sire_coat_colour' => $calvingChecklist->sire_colour,
            'remarks' => $calvingChecklist->remarks,
        ]);
    }

    public function destroy(CalvingChecklist $calvingChecklist)
    {
        try {
            // Check if monthly workflow is completed
            $workflow = CalvingMonthlyWorkflow::where('month_year', $calvingChecklist->month_year)
                ->where('operating_unit', $calvingChecklist->operating_unit)
                ->first();

            if ($workflow && $workflow->is_completed) {
                return back()->with('error', 'Completed calving checklist records cannot be deleted.');
            }
            // Delete endorsement documents
            $docs = $calvingChecklist->endorsement_documents ?? [];
            foreach ($docs as $doc) {
                if (isset($doc['path']) && Storage::exists($doc['path'])) {
                    Storage::delete($doc['path']);
                }
            }

            $monthYear = $calvingChecklist->month_year;
            $operatingUnit = $calvingChecklist->operating_unit;

            $calvingChecklist->delete();

            // Reset monthly workflow if exists and not completed
            $workflow = CalvingMonthlyWorkflow::where('month_year', $monthYear)
                ->where('operating_unit', $operatingUnit)
                ->first();

            if ($workflow && !$workflow->is_completed) {
                $workflow->update([
                    'endorsement_step' => 0,
                    'status' => 'pending',
                    'endorsement_documents' => null,
                ]);
            }

            return redirect()->route('calving-checklist.index')
                ->with('success', 'Calving checklist record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting calving checklist record: ' . $e->getMessage());

            return back()->with('error', 'Failed to delete record. Please try again.');
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:calving_checklists,id',
            ]);

            $ids = $validated['ids'];
            $count = count($ids);

            // Check if any record belongs to a completed workflow
            $records = CalvingChecklist::whereIn('id', $ids)->get();
            foreach ($records as $record) {
                $workflow = CalvingMonthlyWorkflow::where('month_year', $record->month_year)
                    ->where('operating_unit', $record->operating_unit)
                    ->first();
                    
                if ($workflow && $workflow->is_completed) {
                    return back()->with('error', 'One or more records belong to a completed monthly workflow and cannot be deleted.');
                }
            }
            
            // Collect month_year and operating_units to reset workflows
            $workflowsToReset = [];
            
            foreach ($records as $record) {
                $docs = $record->endorsement_documents ?? [];
                foreach ($docs as $doc) {
                    if (isset($doc['path']) && Storage::exists($doc['path'])) {
                        Storage::delete($doc['path']);
                    }
                }
                $key = $record->month_year . '|' . $record->operating_unit;
                $workflowsToReset[$key] = [
                    'month_year' => $record->month_year,
                    'operating_unit' => $record->operating_unit
                ];
            }

            // Delete records
            CalvingChecklist::whereIn('id', $ids)->delete();

            // Reset monthly workflows
            foreach ($workflowsToReset as $wf) {
                $workflow = CalvingMonthlyWorkflow::where('month_year', $wf['month_year'])
                    ->where('operating_unit', $wf['operating_unit'])
                    ->first();

                if ($workflow && !$workflow->is_completed) {
                    $workflow->update([
                        'endorsement_step' => 0,
                        'status' => 'pending',
                        'endorsement_documents' => null,
                    ]);
                }
            }

            Log::info('Bulk deleted calving checklist records', [
                'count' => $count,
                'ids' => $ids,
                'deleted_by' => auth()->id(),
            ]);

            return redirect()->route('calving-checklist.index')
                ->with('success', "{$count} calving checklist record(s) deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Error bulk deleting calving checklist records: ' . $e->getMessage());

            return back()->with('error', 'Failed to delete records. Please try again.');
        }
    }

    // Document endorsement workflow actions
    public function uploadEndorsement(Request $request, CalvingChecklist $calvingChecklist, $stepIndex)
    {
        try {
            $user = auth()->user();
            $userRole = $user?->role ?? '';

            // Validate request
            $validated = $request->validate([
                'signed_document' => 'required|file|mimes:pdf|max:10240', // Max 10MB
                'name' => 'required|string|max:255',
                'date' => 'required|date',
            ]);

            // Check if user can upload for this step
            $currentStep = $calvingChecklist->endorsement_step ?? 0;
            
            $canAct = false;
            if ($userRole === 'admin') {
                $canAct = true;
            } else {
                $ids = $this->getAssignedUserIdsForWorkflowStep((int) $stepIndex);
                if (in_array(auth()->id(), $ids, true)) {
                    $canAct = true;
                }
            }

            if (!$canAct || ((int)$stepIndex !== $currentStep && (int)$stepIndex !== $currentStep - 1 && $userRole !== 'admin')) {
                return back()->with('error', 'You cannot upload for this step.');
            }

            // Store the file
            $file = $request->file('signed_document');
            $filename = "calving_checklist_{$calvingChecklist->id}_step{$stepIndex}_" . time() . '.pdf';
            $path = $file->storeAs('calving-endorsements', $filename);

            // Update endorsement documents
            $calvingChecklist->setStepDocument($stepIndex, [
                'name' => $validated['name'],
                'date' => $validated['date'],
                'filename' => $filename,
                'path' => $path,
                'uploaded_at' => now()->toISOString(),
                'uploaded_by' => $user->name,
            ]);

            // Move to next step if uploading for the current step
            if ((int)$stepIndex === $currentStep) {
                $calvingChecklist->increment('endorsement_step');
                
                // Update status based on step
                $statusMap = [
                    0 => self::STATUS_ISSUED,
                    1 => self::STATUS_VERIFIED,
                    2 => self::STATUS_WITNESSED,
                    3 => self::STATUS_APPROVED,
                ];
                
                if (isset($statusMap[$currentStep + 1])) {
                    $calvingChecklist->status = $statusMap[$currentStep + 1];
                    $calvingChecklist->workflow_status = $statusMap[$currentStep + 1];
                }
            }

            $calvingChecklist->save();

            return back()->with('success', 'Document uploaded successfully.');
        } catch (\Exception $e) {
            Log::error('Error uploading endorsement: ' . $e->getMessage());

            return back()->with('error', 'Failed to upload document. Please try again.');
        }
    }

    public function downloadEndorsement(CalvingChecklist $calvingChecklist, $stepIndex)
    {
        $user = auth()->user();

        // Check if user can download
        if (!$calvingChecklist->canDownloadPrevious($user) &&
            !$calvingChecklist->canViewOwnDocument($user) &&
            strtolower((string) ($user?->role ?? '')) !== 'admin') {
            abort(403, 'Unauthorized');
        }

        $doc = $calvingChecklist->getStepDocument($stepIndex);
        
        if (!$doc || !isset($doc['path'])) {
            abort(404, 'Document not found');
        }

        if (!Storage::exists($doc['path'])) {
            abort(404, 'File not found');
        }

        return Storage::download($doc['path'], $doc['filename'] ?? "calving_endorsement_step{$stepIndex}.pdf");
    }

    public function downloadEndorsementForm(CalvingChecklist $record)
    {
        $currentDate = now()->format('d/m/Y');
        $endorsementDocs = $record->endorsement_documents ?? [];
        $stepLabels = ['1. Issued by', '2. Verified by', '3. Witnessed by', '4. Approved by'];
        $roleLabels = ['Sr. Assistant Livestock', 'Sr. Assistant Security', 'Penyelia Security', 'Livestock Manager/OIC'];
        $byFields = ['issued_by', 'verified_by', 'witnessed_by', 'approved_by'];

        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>LCC Calving Checklist - ' . ($record->lcc_running_number ?: $record->id) . '</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.3; color: #333; }
                .page { width: 210mm; min-height: 297mm; padding: 10mm; margin: 0 auto; background: white; }
                .header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #34554a; }
                .header h1 { font-size: 16px; color: #34554a; margin-bottom: 3px; }
                .header .sub { font-size: 11px; color: #666; }
                .company-info { font-size: 8px; color: #888; margin-bottom: 10px; text-align: center; }
                .form-badge { display: inline-block; padding: 2px 8px; background: #34554a; color: white; font-size: 9px; border-radius: 3px; margin-bottom: 10px; }
                .section { margin-bottom: 12px; }
                .section-title { background: #34554a; color: white; padding: 4px 8px; font-size: 10px; font-weight: bold; margin-bottom: 6px; }
                .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
                .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
                .grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 6px; }
                .field { margin-bottom: 4px; }
                .field label { font-weight: bold; color: #666; font-size: 8px; text-transform: uppercase; display: block; }
                .field .value { padding: 3px 5px; background: #f5f5f5; border-radius: 3px; font-size: 9px; min-height: 18px; }
                .checkbox-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 8px; }
                .checkbox-item { display: flex; align-items: center; gap: 5px; font-size: 9px; }
                .checkbox-item .box { width: 14px; height: 14px; border: 1px solid #333; display: flex; align-items: center; justify-content: center; font-size: 10px; }
                .checkbox-item.checked .box::after { content: "✓"; font-weight: bold; }
                .endorsement-section { margin-top: 15px; page-break-inside: avoid; }
                .endorsement-title { background: #e8e8e8; padding: 6px 8px; font-weight: bold; font-size: 10px; margin-bottom: 8px; border-left: 4px solid #34554a; }
                .endorsement-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
                .endorsement-box { border: 1px solid #ccc; padding: 6px; min-height: 70px; background: #fafafa; font-size: 8px; }
                .endorsement-box.filled { background: #f0f8f0; border-color: #34554a; }
                .endorsement-box h5 { font-size: 7px; text-transform: uppercase; color: #34554a; margin-bottom: 4px; border-bottom: 1px dashed #ccc; padding-bottom: 2px; }
                .endorsement-box .name-line { margin-bottom: 2px; }
                .endorsement-box .name-line span { border-bottom: 1px solid #333; display: inline-block; min-width: 60px; }
                .endorsement-box .date-line { margin-bottom: 2px; }
                .endorsement-box .date-line span { border-bottom: 1px solid #333; display: inline-block; min-width: 50px; }
                .signature-area { margin-top: 4px; height: 30px; display: flex; align-items: flex-end; }
                .signature-area img { max-height: 28px; max-width: 100%; }
                .signature-area .empty-sig { color: #999; font-size: 7px; font-style: italic; }
                .footer { margin-top: 15px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 7px; color: #999; text-align: center; }
                .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 50px; color: rgba(200, 200, 200, 0.1); z-index: -1; pointer-events: none; }
            </style>
        </head>
        <body>
            <div class="watermark">' . $currentDate . '</div>
            <div class="page">
                <div class="header">
                    <h1>LIVESTOCK CALVING CHECKLIST (LCC)</h1>
                    <div class="sub">' . ($record->company_name ?? 'SAWIT KINABALU FARM PRODUCTS SDN BHD') . '</div>
                </div>
                <div class="company-info">
                    ' . ($record->ownership ?? 'Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)') . '<br>
                    MCC No.: ' . ($record->mcc_no ?? '00023') . ' | ' . ($record->form_no ?? 'FORM 1B') . '
                </div>
                <div style="text-align: center; margin-bottom: 15px;">
                    <span class="form-badge">LCC Running No.: ' . ($record->lcc_running_number ?: $record->id) . '</span>
                </div>

                <div class="section">
                    <div class="section-title">RECORD INFORMATION</div>
                    <div class="grid-4">
                        <div class="field"><label>Month/Year</label><div class="value">' . $record->month_year . '</div></div>
                        <div class="field"><label>Operating Unit</label><div class="value">' . $record->operating_unit . '</div></div>
                        <div class="field"><label>Week</label><div class="value">' . ($record->week ?? '-') . '</div></div>
                        <div class="field"><label>Calving Date</label><div class="value">' . ($record->calving_date ? \Carbon\Carbon::parse($record->calving_date)->format('d/m/Y') : '-') . '</div></div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">CALF INFORMATION</div>
                    <div class="grid-4">
                        <div class="field"><label>Colour</label><div class="value">' . $record->colour . '</div></div>
                        <div class="field"><label>Tag (Calf)</label><div class="value"><strong>' . $record->tag_no . '</strong></div></div>
                        <div class="field"><label>Tag No (Dam)</label><div class="value">' . ($record->dam_tag_no ?: '-') . '</div></div>
                        <div class="field"><label>Sex</label><div class="value">' . $record->sex . '</div></div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">LOCATION & PREGNANCY</div>
                    <div class="grid-3">
                        <div class="field"><label>Times of Pregnancy</label><div class="value">' . ($record->times_of_pregnancy ?: '-') . '</div></div>
                        <div class="field"><label>Location</label><div class="value">' . ($record->location ?: '-') . '</div></div>
                        <div class="field"><label>Block</label><div class="value">' . ($record->location_block ?: '-') . '</div></div>
                        <div class="field"><label>Phase</label><div class="value">' . ($record->location_phase ?: '-') . '</div></div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">POST-CALVING CHECKLIST</div>
                    <div class="grid-2" style="margin-bottom: 10px;">
                        <div class="field"><label>General Condition</label><div class="value">' . ($record->general_condition ?? 'Good') . '</div></div>
                        <div class="field"><label>Tagging Checklist Date</label><div class="value">' . ($record->tagging_checklist_date ? \Carbon\Carbon::parse($record->tagging_checklist_date)->format('d/m/Y') : '-') . '</div></div>
                    </div>
                    <div class="field" style="margin-bottom: 6px;"><label>Treatment Given</label></div>
                    <div class="checkbox-grid">
                        <div class="checkbox-item ' . ($record->treatment_iodine ? 'checked' : '') . '"><div class="box"></div><span>Iodine</span></div>
                        <div class="checkbox-item ' . ($record->treatment_woundsarex ? 'checked' : '') . '"><div class="box"></div><span>Woundsarex</span></div>
                        <div class="checkbox-item ' . ($record->colostrum_feeding_24h ? 'checked' : '') . '"><div class="box"></div><span>Colostrum Feeding (24H)</span></div>
                        <div class="checkbox-item ' . ($record->mamumune ? 'checked' : '') . '"><div class="box"></div><span>Mamumune</span></div>
                    </div>
                </div>';

        if ($record->remarks) {
            $html .= '
                <div class="section">
                    <div class="section-title">REMARKS</div>
                    <div class="value">' . $record->remarks . '</div>
                </div>';
        }

        $html .= '
                <div class="endorsement-section">
                    <div class="endorsement-title">ENDORSEMENT SECTION</div>
                    <div class="endorsement-grid">';

        for ($i = 0; $i < 4; $i++) {
            $doc = $endorsementDocs[$i] ?? null;
            $isFilled = $doc && isset($doc['path']);
            $byField = $byFields[$i];
            $name = $record->{$byField . '_name'} ?? '';
            $date = $record->{$byField . '_date'} ? \Carbon\Carbon::parse($record->{$byField . '_date'})->format('d/m/Y') : '';

            $html .= '
                        <div class="endorsement-box ' . ($isFilled ? 'filled' : '') . '">
                            <h5>' . $stepLabels[$i] . '<br><small>' . $roleLabels[$i] . '</small></h5>
                            <div class="name-line">Name: <span>' . $name . '</span></div>
                            <div class="date-line">Date: <span>' . $date . '</span></div>
                            <div class="signature-area">';
            if ($isFilled) {
                $filePath = storage_path('app/' . $doc['path']);
                if (file_exists($filePath)) {
                    $html .= '<img src="' . $filePath . '" alt="Signature">';
                } else {
                    $html .= '<span class="empty-sig">[Document Uploaded]</span>';
                }
            } else {
                $html .= '<span class="empty-sig">[Awaiting Signature]</span>';
            }
            $html .= '
                            </div>
                        </div>';
        }

        $html .= '
                    </div>
                </div>

                <div class="footer">
                    <p>Document ID: ' . $record->id . ' | Generated on: ' . $currentDate . ' | Status: ' . ucfirst($record->workflow_status ?? 'pending') . ' | Current Step: ' . (($record->endorsement_step ?? 0) + 1) . ' of 4</p>
                    <p>SAWIT KINABALU FARM PRODUCTS SDN BHD - Livestock Calving Documentation System</p>
                </div>
            </div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'LCC_' . ($record->lcc_running_number ?? $record->id) . '.pdf';

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function markAsCompleted(Request $request, CalvingChecklist $calvingChecklist)
    {
        try {
            $user = auth()->user();
            $userRole = $user?->role ?? '';

            // Only admin can mark as completed
            if ($userRole !== 'admin') {
                return back()->with('error', 'Only admin can mark this record as completed.');
            }

            // Check if all steps are uploaded
            if (!$calvingChecklist->allStepsUploaded()) {
                return back()->with('error', 'All endorsement documents must be uploaded before marking as completed.');
            }

$calvingChecklist->update([
            'is_completed' => true,
            'completed_at' => now(),
            'workflow_status' => 'completed',
        ]);

        // Notify the creator that the checklist is completed
        if ($calvingChecklist->created_by) {
            \App\Services\WorkflowNotificationService::createNotification(
                'calving_checklist',
                'calving_checklist',
                $calvingChecklist->id,
                'completed',
                'Calving Checklist Completed',
                "Your calving checklist #{$calvingChecklist->id} has been completed.",
                $calvingChecklist->created_by
            );
        }

        return back()->with('success', 'Record marked as completed successfully.');
        } catch (\Exception $e) {
            Log::error('Error marking as completed: ' . $e->getMessage());

            return back()->with('error', 'Failed to mark as completed. Please try again.');
        }
    }

    public function pending(Request $request)
    {
        $userRole = auth()->user()?->role ?? '';

        $query = CalvingChecklist::query();
        $query->where('is_completed', false)
              ->whereNotIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED]);

        $checklistRecords = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('CalvingChecklist/Pending', [
            'checklistRecords' => $checklistRecords,
            'userRole' => $userRole,
            'workflowAssignment' => $this->getResolvedWorkflowAssignment() ?: null,
        ]);
    }

    public function export(Request $request)
    {
        $monthYear = $request->get('month', 'Sept/2024');
        
        // Convert month format if needed (YYYY-MM to Mmm/YYYY)
        $monthYear = $this->convertMonthToStorageFormat($monthYear);
        
        $records = CalvingChecklist::forMonth($monthYear)->get();

        $csvData = [];
        $csvData[] = [
            'Week', 'Date', 'Tag No.', 'Sex', 'Colour', 'Dam Tag No.',
            'Times of Pregnancy', 'Location', 'Block', 'Phase', 'General Condition',
            'Iodine Treatment', 'Woundsarex Treatment', 'Colostrum Feeding (24H)',
            'Mamumune', 'Tagging Checklist Date', 'LCC Running Number', 'Status'
        ];

        foreach ($records as $record) {
            $csvData[] = [
                $record->week,
                $record->calving_date ? $record->calving_date->format('d/m/Y') : '',
                $record->tag_no,
                $record->sex,
                $record->colour,
                $record->dam_tag_no,
                $record->times_of_pregnancy,
                $record->location,
                $record->location_block,
                $record->location_phase,
                $record->general_condition,
                $record->treatment_iodine ? 'Yes' : 'No',
                $record->treatment_woundsarex ? 'Yes' : 'No',
                $record->colostrum_feeding_24h ? 'Yes' : 'No',
                $record->mamumune ? 'Yes' : 'No',
                $record->tagging_checklist_date ? $record->tagging_checklist_date->format('d/m/Y') : '',
                $record->lcc_running_number,
                $record->status,
            ];
        }

        $filename = "calving_checklist_{$monthYear}.csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $file = fopen('php://output', 'w');
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        exit;
    }

    // API endpoint for pending counts
    // Batch Verification and Export Methods
    public function uploadBatchEndorsement(Request $request)
    {
        try {
            $user = auth()->user();
            $userRole = $user?->role ?? '';

            $validated = $request->validate([
                'month_year' => 'required|string',
                'operating_unit' => 'required|string',
                'step_index' => 'required|integer',
                'signed_document' => 'required|file|mimes:pdf|max:10240',
                'name' => 'required|string|max:255',
                'date' => 'required|date',
            ]);

            $workflow = CalvingMonthlyWorkflow::where('month_year', $validated['month_year'])
                ->where('operating_unit', $validated['operating_unit'])
                ->firstOrFail();

            // Check if user can upload for this step
            $currentStep = $workflow->endorsement_step ?? 0;
            
            $canAct = false;
            if ($userRole === 'admin') {
                $canAct = true;
            } else {
                $perms = \App\Models\Permission::normalizePermissionList($user->hasPermission('Calving Checklist'));
                $hasViewPerm = in_array('full', $perms, true) || in_array('view', $perms, true);

                if ($hasViewPerm) {
                    $ids = $this->getAssignedUserIdsForWorkflowStep((int) $validated['step_index']);
                    if (in_array(auth()->id(), $ids, true)) {
                        $canAct = true;
                    }
                }
            }

            if (!$canAct || ((int)$validated['step_index'] !== $currentStep && (int)$validated['step_index'] !== $currentStep - 1 && $userRole !== 'admin')) {
                return back()->with('error', 'You cannot upload for this step.');
            }

            // Store the file
            $file = $request->file('signed_document');
            $filename = "calving_batch_{$workflow->id}_step{$validated['step_index']}_" . time() . '.pdf';
            $path = $file->storeAs('calving-batch-endorsements', $filename);

            // Update endorsement documents
            $workflow->setStepDocument($validated['step_index'], [
                'name' => $validated['name'],
                'date' => $validated['date'],
                'filename' => $filename,
                'path' => $path,
                'uploaded_at' => now()->toISOString(),
                'uploaded_by' => $user->name,
            ]);

            // Move to next step if uploading for the current step
            if ((int)$validated['step_index'] === $currentStep) {
                $workflow->increment('endorsement_step');
                $nextStep = $currentStep + 1;
                
                // Update status based on step
                $statusMap = [
                    1 => 'issued',
                    2 => 'verified',
                    3 => 'witnessed',
                    4 => 'approved',
                ];
                
                if (isset($statusMap[$nextStep])) {
                    $workflow->status = $statusMap[$nextStep];
                }

                // Send notifications
                if ($nextStep < 5) {
                    $nextStepIds = $this->getAssignedUserIdsForWorkflowStep($nextStep);
                    $stepNames = [
                        1 => 'Verified',
                        2 => 'Witnessed',
                        3 => 'Approved',
                    ];
                    $stepName = $stepNames[$nextStep] ?? 'Processed';

                    foreach ($nextStepIds as $nextUserId) {
                        \App\Models\TaskNotification::create([
                            'user_id' => $nextUserId,
                            'title' => "Calving Checklist Ready to be {$stepName}",
                            'message' => "Calving Checklist for {$workflow->month_year} ({$workflow->operating_unit}) is ready to be {$stepName}.",
                            'type' => 'calving_checklist_workflow',
                            'is_read' => false,
                        ]);
                    }
                } elseif ($nextStep === 4) {
                    // All steps done. Notify admin to mark as completed.
                    $adminIds = \App\Models\User::where('role', 'admin')->pluck('id')->toArray();
                    foreach ($adminIds as $adminId) {
                        \App\Models\TaskNotification::create([
                            'user_id' => $adminId,
                            'title' => 'Calving Checklist Ready for Completion',
                            'message' => "Calving Checklist for {$workflow->month_year} ({$workflow->operating_unit}) has finished all workflow steps and is ready to be marked as completed.",
                            'type' => 'calving_checklist_workflow',
                            'is_read' => false,
                        ]);
                    }
                }
            }

            $workflow->save();

            return back()->with('success', 'Monthly document uploaded successfully.');
        } catch (\Exception $e) {
            Log::error('Error uploading batch endorsement: ' . $e->getMessage());
            return back()->with('error', 'Failed to upload document. Please try again.');
        }
    }

    public function downloadBatchEndorsement(Request $request, $stepIndex)
    {
        $month = $request->get('month');
        $unit = $request->get('unit');
        
        // Convert month format if needed (YYYY-MM to Mmm/YYYY)
        $month = $this->convertMonthToStorageFormat($month);

        $workflow = CalvingMonthlyWorkflow::where('month_year', $month)
            ->where('operating_unit', $unit)
            ->firstOrFail();

        $doc = $workflow->getStepDocument($stepIndex);
        
        if (!$doc || !isset($doc['path']) || !Storage::exists($doc['path'])) {
            abort(404, 'Document not found');
        }

        return Storage::download($doc['path'], $doc['filename'] ?? "calving_batch_endorsement_step{$stepIndex}.pdf");
    }

    public function markBatchCompleted(Request $request)
    {
        try {
            $month = $request->get('month');
            $unit = $request->get('unit');
            $userRole = auth()->user()?->role ?? '';
            
            // Convert month format if needed (YYYY-MM to Mmm/YYYY)
            $month = $this->convertMonthToStorageFormat($month);

            if ($userRole !== 'admin') {
                return back()->with('error', 'Only admin can mark as completed.');
            }

            $workflow = CalvingMonthlyWorkflow::where('month_year', $month)
                ->where('operating_unit', $unit)
                ->firstOrFail();

            if (!$workflow->allStepsUploaded()) {
                return back()->with('error', 'All endorsement documents must be uploaded.');
            }

            $workflow->update([
                'is_completed' => true,
                'completed_at' => now(),
                'status' => 'completed',
            ]);

            // Optional: Update all individual records in this batch to completed?
            CalvingChecklist::where('month_year', $month)
                ->where('operating_unit', $unit)
                ->update(['status' => 'completed', 'workflow_status' => 'completed', 'is_completed' => true]);

            return back()->with('success', 'Monthly record marked as completed successfully.');
        } catch (\Exception $e) {
            Log::error('Error marking batch completed: ' . $e->getMessage());
            return back()->with('error', 'Failed to complete. Please try again.');
        }
    }

    public function reopenBatch(Request $request)
    {
        try {
            $month = $request->get('month');
            $unit = $request->get('unit');

            if (!$month || !$unit) {
                return back()->with('error', 'Month and unit are required to reopen.');
            }

            $month = $this->convertMonthToStorageFormat($month);
            $userRole = auth()->user()?->role ?? '';
            if ($userRole !== 'admin') {
                return back()->with('error', 'Only admin can reopen.');
            }

            $workflow = CalvingMonthlyWorkflow::where('month_year', $month)
                ->where('operating_unit', $unit)
                ->first();

            if (!$workflow) {
                return back()->with('error', 'No workflow record found for this month and unit.');
            }

            $workflow->update([
                'is_completed' => false,
                'completed_at' => null,
                'status' => 'pending',
            ]);

            CalvingChecklist::where('month_year', $month)
                ->where('operating_unit', $unit)
                ->update(['status' => 'pending', 'workflow_status' => 'pending', 'is_completed' => false]);

            return back()->with('success', 'Monthly record reopened successfully.');
        } catch (\Exception $e) {
            Log::error('Error reopening batch: ' . $e->getMessage());
            return back()->with('error', 'Failed to reopen. Please try again.');
        }
    }

    public function exportReport(Request $request)
    {
        // This will generate the same PDF as individual but with a list of all records
        $monthYear = $request->get('month', 'Sept/2024');
        $operatingUnit = $request->get('unit', 'SKLIC BREEDLOT');
        $layout = $request->get('layout');
        $hideEndorsement = $request->boolean('hide_endorsement', false);
        
        // Convert month format if needed (YYYY-MM to Mmm/YYYY)
        $monthYear = $this->convertMonthToStorageFormat($monthYear);
        
        $records = CalvingChecklist::forMonth($monthYear)
            ->where('operating_unit', $operatingUnit)
            ->orderBy('calving_date', 'asc')
            ->get();

        $workflow = CalvingMonthlyWorkflow::where('month_year', $monthYear)
            ->where('operating_unit', $operatingUnit)
            ->first();

        if ($layout === 'form') {
            $html = view('pdfs.calving-checklist-monthly-form', [
                'monthYear' => $monthYear,
                'operatingUnit' => $operatingUnit,
                'records' => $records,
                'workflow' => $workflow,
                'hideEndorsement' => $hideEndorsement,
            ])->render();

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->set_option('isHtml5ParserEnabled', true);
            $dompdf->set_option('isRemoteEnabled', true);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = "Monthly_Calving_Checklist_Form_{$operatingUnit}_{$monthYear}.pdf";
            $filename = str_replace(['/', ' '], '_', $filename);

            return response($dompdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        // Generate a report PDF
        $currentDate = now()->format('d/m/Y');
        $stats = [
            'total' => $records->count(),
            'male' => $records->where('sex', 'MC')->count(),
            'female' => $records->where('sex', 'FC')->count(),
        ];
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 9px; line-height: 1.4; color: #333; }
                .page { padding: 10px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #34554a; padding-bottom: 10px; }
                .header h1 { font-size: 18px; color: #34554a; margin: 0; }
                .header p { margin: 5px 0 0; color: #666; font-size: 11px; }
                
                .stats-container { margin-bottom: 20px; display: table; width: 100%; border-spacing: 10px; margin-left: -10px; }
                .stat-box { display: table-cell; background: #fff; border: 1px solid #eee; padding: 12px; border-radius: 8px; text-align: center; width: 25%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
                .stat-box .label { font-size: 8px; color: #888; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; }
                .stat-box .value { font-size: 18px; font-weight: bold; color: #34554a; }
                
                table { width: 100%; border-collapse: collapse; margin-top: 20px; border-radius: 8px; overflow: hidden; }
                th { background-color: #34554a; color: white; border: 1px solid #34554a; padding: 8px 4px; text-align: left; text-transform: uppercase; font-size: 8px; }
                td { border: 1px solid #eee; padding: 8px 4px; font-size: 8px; }
                tr:nth-child(even) { background-color: #fcfcfc; }
                
                .endorsement-table { margin-top: 40px; width: 100%; }
                .endorsement-box { border: 1px solid #eee; padding: 10px; border-radius: 8px; height: 100px; vertical-align: top; width: 20%; background: #fafafa; }
                .endorsement-label { font-weight: bold; color: #34554a; font-size: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; text-transform: uppercase; }
                .signature-info { position: relative; height: 60px; }
                .signature-text { font-style: italic; color: #777; font-size: 8px; bottom: 0; position: absolute; }
                .signature-line { border-top: 1px solid #ccc; margin-top: 45px; }

                .badge { padding: 3px 6px; border-radius: 10px; font-size: 7px; text-transform: uppercase; font-weight: bold; }
                .badgm-male { background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb; }
                .badge-female { background: #fce4ec; color: #c2185b; border: 1px solid #f8bbd0; }
                
                .footer { margin-top: 40px; font-size: 8px; color: #bbb; text-align: center; border-top: 1px solid #eee; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="page">
                <div class="header">
                    <p style="text-align: right; color: #999; font-size: 8px; margin: 0;">LCC-BATCH-'.str_replace(['/', ' '], '-', $monthYear).'-'.str_replace(' ', '-', $operatingUnit).'</p>
                    <h1>Monthly Calving Checklist Report</h1>
                    <p>Operating Unit: <strong>' . $operatingUnit . '</strong> | Period: <strong>' . $monthYear . '</strong></p>
                </div>

                <div class="stats-container">
                    <div class="stat-box">
                        <div class="label">Total Calves</div>
                        <div class="value">' . $stats['total'] . '</div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Male Calves</div>
                        <div class="value">' . $stats['male'] . '</div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Female Calves</div>
                        <div class="value">' . $stats['female'] . '</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th style="width: 60px;">Date</th>
                            <th style="width: 70px;">Calf Tag</th>
                            <th style="width: 70px;">Dam Tag</th>
                            <th style="width: 40px;">Sex</th>
                            <th style="width: 60px;">Colour</th>
                            <th>Location/Block</th>
                            <th style="width: 60px;">Condition</th>
                            <th>Treatments</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($records as $index => $record) {
            $treatments = [];
            if ($record->treatment_iodine) $treatments[] = 'Iodine';
            if ($record->treatment_woundsarex) $treatments[] = 'Woundsarex';
            if ($record->colostrum_feeding_24h) $treatments[] = 'Colostrum';
            if ($record->mamumune) $treatments[] = 'Mamumune';
            
            $sexBadge = $record->sex === 'MC' ? 'badgm-male' : 'badge-female';
            $sexLabel = $record->sex === 'MC' ? 'Male' : 'Female';

            $html .= '<tr>
                <td style="text-align: center;">' . ($index + 1) . '</td>
                <td>' . ($record->calving_date ? $record->calving_date->format('d/m/Y') : '-') . '</td>
                <td style="font-weight: bold;">' . $record->tag_no . '</td>
                <td>' . $record->dam_tag_no . '</td>
                <td style="text-align: center;"><span class="badge ' . $sexBadge . '">' . $sexLabel . '</span></td>
                <td>' . $record->colour . '</td>
                <td>' . $record->location . ' / ' . $record->location_block . '</td>
                <td>' . $record->general_condition . '</td>
                <td>' . (implode(', ', $treatments) ?: 'None') . '</td>
            </tr>';
        }

        if ($records->isEmpty()) {
            $html .= '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #999;">No records found for this period.</td></tr>';
        }

        $html .= '
                    </tbody>
                </table>

                <table class="endorsement-table">
                    <tr>';
        
        $steps = CalvingMonthlyWorkflow::WORKFLOW_STEPS;
        $endorsements = $workflow ? ($workflow->endorsement_documents ?? []) : [];

        foreach ($steps as $index => $step) {
            $info = $endorsements[$index] ?? null;
            $html .= '
            <td class="endorsement-box">
                <div class="endorsement-label">' . $step['label'] . '</div>
                <div class="signature-info">
                    ' . ($info ? '<div style="text-align: center; padding-top: 5px;"><span style="color: #2e7d32; font-weight: bold; font-size: 10px;">SIGNED</span></div>' : '') . '
                    <div class="signature-line"></div>
                    <div class="signature-text">
                        ' . ($info ? $info['name'] . '<br>' . $info['date'] : 'Pending...') . '
                    </div>
                </div>
                <div style="font-size: 7px; color: #999; margin-top: 5px;">' . $step['role_name'] . '</div>
            </td>';
        }

        $html .= '
                    </tr>
                </table>

                <div class="footer">
                    <p>Generated by BovineFlow on ' . $currentDate . ' | Report Type: Monthly Batch Checklist</p>
                </div>
            </div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isHtml5ParserEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "Monthly_Calving_Checklist_{$operatingUnit}_{$monthYear}.pdf";
        $filename = str_replace(['/', ' '], '_', $filename);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function getPendingCounts()
    {
        $userRole = auth()->user()?->role ?? '';

        $query = CalvingChecklist::where('is_completed', false)
            ->whereNotIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED]);

        if ($userRole && $userRole !== 'admin') {
            // Count records pending for this user's role
            $currentStep = 0;
            foreach (CalvingChecklist::DOC_WORKFLOW_STEPS as $index => $step) {
                if ($step['role'] === $userRole) {
                    $currentStep = $index;
                    break;
                }
            }
            $query->where('endorsement_step', $currentStep);
        }

        $pendingCount = $query->count();

        return response()->json([
            'pending_count' => $pendingCount,
        ]);
    }
}
