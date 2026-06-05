<?php

namespace App\Http\Controllers;

use App\Models\CalvingRecord;
use App\Models\CalvingWorkflowAssignment;
use App\Models\User;
use App\Models\Cattle;
use App\Models\CattleCustomField;
use App\Models\Estate;
use App\Models\PastureBlock;
use App\Models\PasturePhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class CalvingController extends Controller
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ISSUED = 'issued';
    const STATUS_VERIFIED = 'verified';
    const STATUS_WITNESSED = 'witnessed';

    private function getDefaultOperatingUnits(): array
    {
        return Estate::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    private function getAvailableOperatingUnits(): array
    {
        return Estate::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Convert month format from YYYY-MM to Mmm/YYYY for database queries.
     */
    private function convertMonthToStorageFormat($monthStr)
    {
        if (!$monthStr) return $monthStr;

        // Already storage format (e.g. Sept/2024)
        if (preg_match('/^[A-Za-z]{3,4}\/\d{4}$/', $monthStr)) {
            return $monthStr;
        }

        // Convert from YYYY-MM
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

    public function index(Request $request)
    {
        $monthYear = $request->get('month', '');
        $operatingUnit = $request->get('unit', '');
        $myRecords = $request->get('my_records', false);

        if ($monthYear) {
            $monthYear = $this->convertMonthToStorageFormat($monthYear);
        }

        $query = CalvingRecord::with('creator');

        // Filter by user's own records if requested
        if ($myRecords) {
            $query->where('created_by', Auth::id());
        }

        if ($monthYear) {
            $query->forMonth($monthYear);
        }

        if ($operatingUnit) {
            $query->where('operating_unit', $operatingUnit);
        }

        $calvingRecords = $query->orderBy('calving_date', 'asc')->get();

        // Get stats for all records (not just filtered month)
        $allRecords = CalvingRecord::query();
        if ($myRecords) {
            $allRecords->where('created_by', Auth::id());
        }
        if ($operatingUnit) {
            $allRecords->where('operating_unit', $operatingUnit);
        }
        $allRecords = $allRecords->get();

        $stats = [
            'total' => $allRecords->count(),
            'pending' => $allRecords->where('status', self::STATUS_PENDING)->count(),
            'approved' => $allRecords->where('status', self::STATUS_APPROVED)->count(),
            'this_month' => $allRecords->count(),
            'male_calves' => $allRecords->where('sex', 'MC')->count(),
            'female_calves' => $allRecords->where('sex', 'FC')->count(),
        ];

        $availableMonths = CalvingRecord::distinct()->pluck('month_year')->filter()->values()->toArray();
        if (empty($availableMonths)) {
            $availableMonths = [date('M/Y')];
        }

        $operatingUnits = $this->getAvailableOperatingUnits();

        return Inertia::render('Calving/Index', [
            'calvingRecords' => $calvingRecords,
            'stats' => $stats,
            'monthYear' => $monthYear,
            'operatingUnit' => $operatingUnit,
            'myRecords' => (bool) $myRecords,
            'availableMonths' => $availableMonths,
            'operatingUnits' => $operatingUnits,
            'workflowAssignment' => Schema::hasTable('calving_workflow_assignments')
                ? CalvingWorkflowAssignment::first()
                : null,
        ]);
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

    public function create(Request $request)
    {
        $monthYear = $request->get('month', 'Sept/2024');
        $operatingUnit = $request->get('unit', '');
        $operatingUnits = $this->getAvailableOperatingUnits();

        $this->ensureDefaultCalvingColours();

        return Inertia::render('Calving/Create', [
            'monthYear' => $monthYear,
            'operatingUnit' => $operatingUnit,
            'operatingUnits' => $operatingUnits,
            'estates' => Estate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'operatingUnitsWithStructure' => $this->getOperatingUnitsWithStructure(),
            'allBlocks' => \App\Models\PastureBlock::orderBy('name')->pluck('name')->unique()->values()->toArray(),
            'allPhases' => \App\Models\PasturePhase::orderBy('name')->pluck('name')->unique()->values()->toArray(),
            'locationBlocks' => collect(\DB::table('pasture_blocks')->select('name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('cattle')->select('location_block as name')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('calving_records')->select('location_block as name')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('name'))
                ->map(fn ($name) => ucwords(strtolower(trim($name))))
                ->filter()
                ->unique()
                ->sort()
                ->map(fn ($name, $idx) => ['id' => 'block-' . $idx, 'value' => $name])
                ->values()
                ->all(),
            'locationPhases' => collect(\DB::table('pasture_phases')->select('name as name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('cattle')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('calving_records')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
                ->map(fn ($name) => ucwords(strtolower(trim($name))))
                ->filter()
                ->unique()
                ->sort()
                ->map(fn ($name, $idx) => ['id' => 'phase-' . $idx, 'value' => $name])
                ->values()
                ->all(),
            'customFields' => [
                'calving_colour' => CattleCustomField::where('field_type', 'calving_colour')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'value'])
                    ->toArray(),
                'calving_dam_colour' => CattleCustomField::where('field_type', 'calving_dam_colour')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'value'])
                    ->toArray(),
                'calving_sire_colour' => CattleCustomField::where('field_type', 'calving_sire_colour')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'value'])
                    ->toArray(),
            ],
        ]);
    }

    private function ensureDefaultCalvingColours(): void
    {
        $defaults = [
            'Kelabu (Grey)',
            'Madu (Honey)',
            'Merah (Red)',
            'Hitam (Black)',
            'Berjalur (Stripe)',
        ];

        $fieldTypes = ['calving_colour', 'calving_dam_colour', 'calving_sire_colour'];

        foreach ($fieldTypes as $fieldType) {
            $hasAnyOption = CattleCustomField::where('field_type', $fieldType)->exists();
            if ($hasAnyOption) {
                continue;
            }

            foreach ($defaults as $index => $value) {
                CattleCustomField::create([
                    'field_type' => $fieldType,
                    'value' => $value,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            Log::info('Calving store request received', [
                'all_input' => $request->all(),
                'content_type' => $request->header('Content-Type')
            ]);

            $validated = $request->validate([
                'tag_no' => 'required|string|max:50',
                'lcc_running_number' => 'nullable|string|max:50',
                'cattle_no_request_form' => 'nullable|string|max:100',
                'calving_date' => 'nullable|date',
                'operating_unit' => 'required|string|max:255',
                'sex' => 'required|in:MC,FC',
                'colour' => 'required|string|max:100',
                'general_condition' => 'nullable|string|max:100',
                'dam_tag_no' => 'nullable|string|max:50',
                'dam_colour' => 'nullable|string|max:100',
                'sire_tag_no' => 'nullable|string|max:50',
                'sire_colour' => 'nullable|string|max:100',
                'worker_name' => 'nullable|string|max:255',
                'location_block' => 'nullable|string|max:100',
                'location_phase' => 'nullable|string|max:100',
                'remarks' => 'nullable|string',
            ]);

            Log::info('Calving validation passed', $validated);

            $calvingRecord = CalvingRecord::create([
                'company_name' => 'SAWIT KINABALU FARM PRODUCTS SDN BHD',
                'company_no' => '465571-P',
                'month_year' => date('M/Y'),
                ...$validated,
                'status' => CalvingRecord::STATUS_PENDING,
                'created_by' => Auth::id(),
            ]);

            Log::info('Calving record created', ['id' => $calvingRecord->id]);

            DB::commit();

            Log::info('Calving record committed to database');

            return to_route('calving.index')
                ->with('success', 'Calving record created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating calving record: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);

            return back()->withInput()->with('error', 'Failed to create calving record. Please try again. Error: ' . $e->getMessage());
        }
    }

    public function show(CalvingRecord $calvingRecord)
    {
        return Inertia::render('Calving/Show', [
            'calvingRecord' => $calvingRecord,
        ]);
    }

    public function edit(CalvingRecord $calvingRecord)
    {
        $operatingUnits = $this->getAvailableOperatingUnits();

        $this->ensureDefaultCalvingColours();

        return Inertia::render('Calving/Edit', [
            'calvingRecord' => $calvingRecord,
            'operatingUnits' => $operatingUnits,
            'estates' => Estate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'operatingUnitsWithStructure' => $this->getOperatingUnitsWithStructure(),
            'allBlocks' => \App\Models\PastureBlock::orderBy('name')->pluck('name')->unique()->values()->toArray(),
            'allPhases' => \App\Models\PasturePhase::orderBy('name')->pluck('name')->unique()->values()->toArray(),
            'locationBlocks' => collect(\DB::table('pasture_blocks')->select('name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('cattle')->select('location_block as name')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('calving_records')->select('location_block as name')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('name'))
                ->map(fn ($name) => ucwords(strtolower(trim($name))))
                ->filter()
                ->unique()
                ->sort()
                ->map(fn ($name, $idx) => ['id' => 'block-' . $idx, 'value' => $name])
                ->values()
                ->all(),
            'locationPhases' => collect(\DB::table('pasture_phases')->select('name as name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('cattle')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
                ->merge(\DB::table('calving_records')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
                ->map(fn ($name) => ucwords(strtolower(trim($name))))
                ->filter()
                ->unique()
                ->sort()
                ->map(fn ($name, $idx) => ['id' => 'phase-' . $idx, 'value' => $name])
                ->values()
                ->all(),
            'customFields' => [
                'calving_colour' => CattleCustomField::where('field_type', 'calving_colour')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'value'])
                    ->toArray(),
                'calving_dam_colour' => CattleCustomField::where('field_type', 'calving_dam_colour')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'value'])
                    ->toArray(),
                'calving_sire_colour' => CattleCustomField::where('field_type', 'calving_sire_colour')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'value'])
                    ->toArray(),
            ],
        ]);
    }

    public function update(Request $request, CalvingRecord $calvingRecord)
    {
        try {
            if ($calvingRecord->status === 'completed' && !$calvingRecord->is_reopened) {
                return back()->withInput()->with('error', 'Completed calving records can only be edited after the workflow is reopened.');
            }

            DB::beginTransaction();

            $originalLccFields = $calvingRecord->only([
                'tag_no',
                'lcc_running_number',
                'calving_date',
                'sex',
                'colour',
                'general_condition',
                'dam_tag_no',
                'dam_colour',
                'sire_tag_no',
                'sire_colour',
                'location_block',
                'location_phase',
                'operating_unit',
                'ownership',
            ]);

            $statusBeforeUpdate = $calvingRecord->status;

            $validated = $request->validate([
                'tag_no' => 'required|string|max:50',
                'lcc_running_number' => 'nullable|string|max:50',
                'cattle_no_request_form' => 'nullable|string|max:100',
                'calving_date' => 'nullable|date',
                'operating_unit' => 'required|string|max:255',
                'sex' => 'required|in:MC,FC',
                'colour' => 'required|string|max:100',
                'general_condition' => 'nullable|string|max:100',
                'dam_tag_no' => 'nullable|string|max:50',
                'dam_colour' => 'nullable|string|max:100',
                'sire_tag_no' => 'nullable|string|max:50',
                'sire_colour' => 'nullable|string|max:100',
                'worker_name' => 'nullable|string|max:255',
                'location_block' => 'nullable|string|max:100',
                'location_phase' => 'nullable|string|max:100',
                'remarks' => 'nullable|string',
            ]);

            Log::info('Validation passed, updating record', ['validated' => $validated]);
            $calvingRecord->update($validated);
            $calvingRecord->refresh();

            Log::info('Calving update debug', [
                'record_id' => $calvingRecord->id,
                'status_before_update' => $statusBeforeUpdate,
                'status_after_update' => $calvingRecord->status,
                'endorsement_step_before_update' => $calvingRecord->endorsement_step,
            ]);

            // Always restart workflow from the first step (pending) when changes are made
            Log::info('FORCE RESTART workflow - status was: ' . $statusBeforeUpdate);
            $calvingRecord->update([
                'status' => 'pending',
                'endorsement_step' => 0,
                'endorsement_documents' => null,
                'is_reopened' => false,
                // Clear all endorsement signatures
                'issued_by_name' => null,
                'issued_by_signature' => false,
                'issued_by_date' => null,
                'issued_at' => null,
                'verified_by_name' => null,
                'verified_by_signature' => false,
                'verified_by_date' => null,
                'verified_at' => null,
                'checked_by_name' => null,
                'checked_by_signature' => false,
                'checked_by_date' => null,
                'checked_at' => null,
                'witnessed_by_name' => null,
                'witnessed_by_signature' => false,
                'witnessed_by_date' => null,
                'witnessed_at' => null,
                'approved_by_name' => null,
                'approved_by_signature' => false,
                'approved_by_date' => null,
                'approved_at' => null,
            ]);

            $this->removeSyncedCattleForCalving($calvingRecord);

            $calvingRecord->refresh();
            Log::info('After restart - status: ' . $calvingRecord->status . ', step: ' . $calvingRecord->endorsement_step);

            DB::commit();

            return redirect()->route('calving.index', ['t' => time()])
                ->with('success', 'Calving record updated successfully. Workflow reset to step 1.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating calving record: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Failed to update calving record. Please try again.');
        }
    }

    public function destroy(CalvingRecord $calvingRecord)
    {
        try {
            $this->removeSyncedCattleForCalving($calvingRecord);
            
            $calvingRecord->delete();

            return redirect()->route('calving.index')
                ->with('success', 'Calving record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting calving record: ' . $e->getMessage());

            return back()->with('error', 'Failed to delete calving record. Please try again.');
        }
    }

    private function hasCoreLccFieldChanges(array $original, array $updated): bool
    {
        $trackedKeys = [
            'tag_no',
            'lcc_running_number',
            'calving_date',
            'sex',
            'colour',
            'general_condition',
            'dam_tag_no',
            'dam_colour',
            'sire_tag_no',
            'sire_colour',
            'location_block',
            'location_phase',
            'ownership',
            'operating_unit',
        ];

        foreach ($trackedKeys as $key) {
            if (!array_key_exists($key, $updated)) {
                continue;
            }

            $before = $original[$key] ?? null;
            $after = $updated[$key] ?? null;

            if ($key === 'calving_date') {
                $before = $before ? (string) \Carbon\Carbon::parse($before)->toDateString() : null;
                $after = $after ? (string) \Carbon\Carbon::parse($after)->toDateString() : null;
            } else {
                $before = is_null($before) ? null : trim((string) $before);
                $after = is_null($after) ? null : trim((string) $after);
            }

            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    private function syncCattleFromCalving(CalvingRecord $calvingRecord, ?string $oldTagNo = null, bool $createIfMissing = false): void
    {
        $cattle = Cattle::where('calving_record_id', $calvingRecord->id)->first();

        if (!$cattle && !empty($oldTagNo)) {
            $cattle = Cattle::where('tag_no', $oldTagNo)->first();
        }

        if (!$cattle) {
            $cattle = Cattle::where('tag_no', $calvingRecord->tag_no)->first();
        }

        if (!$cattle && $createIfMissing) {
            $cattle = Cattle::create([
                'tag_no' => $calvingRecord->tag_no,
                'lcc_running_number' => $calvingRecord->lcc_running_number,
                'calving_record_id' => $calvingRecord->id,
                'category' => 'Calf',
                'breed' => 'Local',
                'coat_colour' => $calvingRecord->colour,
                'birth_date' => $calvingRecord->calving_date,
                'gender' => $calvingRecord->sex === 'MC' ? 'Male' : 'Female',
                'general_condition' => $calvingRecord->general_condition,
                'ownership' => $calvingRecord->ownership,
                'location_block' => $calvingRecord->operating_unit ?? $calvingRecord->location_block,
                'location_phase' => $calvingRecord->location_phase,
                'dam_tag' => $calvingRecord->dam_tag_no,
                'dam_colour' => $calvingRecord->dam_colour,
                'sire_tag' => $calvingRecord->sire_tag_no,
                'sire_coat_colour' => $calvingRecord->sire_colour,
                'status' => 'Active',
                'remarks' => "Synced from Calving Record #{$calvingRecord->id}",
            ]);
        }

        if (!$cattle) {
            return;
        }

        if (!empty($calvingRecord->tag_no) && $cattle->tag_no !== $calvingRecord->tag_no) {
            $tagConflict = Cattle::where('tag_no', $calvingRecord->tag_no)
                ->where('id', '!=', $cattle->id)
                ->exists();

            if ($tagConflict) {
                throw new \RuntimeException("Unable to sync cattle profile: tag number '{$calvingRecord->tag_no}' is already used by another cattle record.");
            }
        }

        $cattle->update([
            'calving_record_id' => $calvingRecord->id,
            'lcc_running_number' => $calvingRecord->lcc_running_number,
            'tag_no' => $calvingRecord->tag_no,
            'coat_colour' => $calvingRecord->colour,
            'birth_date' => $calvingRecord->calving_date,
            'gender' => $calvingRecord->sex === 'MC' ? 'Male' : 'Female',
            'general_condition' => $calvingRecord->general_condition,
            'ownership' => $calvingRecord->ownership,
            'location_block' => $calvingRecord->operating_unit ?? $calvingRecord->location_block,
            'location_phase' => $calvingRecord->location_phase,
            'dam_tag' => $calvingRecord->dam_tag_no,
            'dam_colour' => $calvingRecord->dam_colour,
            'sire_tag' => $calvingRecord->sire_tag_no,
            'sire_coat_colour' => $calvingRecord->sire_colour,
            'remarks' => $calvingRecord->remarks,
        ]);
    }

    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:calving_records,id',
            ]);

            $ids = $validated['ids'];
            $count = count($ids);

            // Delete records
            CalvingRecord::whereIn('id', $ids)->delete();

            Log::info('Bulk deleted calving records', [
                'count' => $count,
                'ids' => $ids,
                'deleted_by' => Auth::id(),
            ]);

            return redirect()->route('calving.index')
                ->with('success', "{$count} calving record(s) deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Error bulk deleting calving records: ' . $e->getMessage());

            return back()->with('error', 'Failed to delete records. Please try again.');
        }
    }

    public function issue(Request $request, CalvingRecord $calvingRecord)
    {
        try {
            $user = auth()->user();

            $calvingRecord->update([
                'issued_by_name' => $user->name,
                'issued_by_unit' => $user->role ?? 'Livestock',
                'issued_by_signature' => true,
                'issued_at' => now(),
                'status' => CalvingRecord::STATUS_ISSUED,
            ]);

            return back()->with('success', 'Calving record issued successfully.');
        } catch (\Exception $e) {
            Log::error('Error issuing calving record: ' . $e->getMessage());

            return back()->with('error', 'Failed to issue calving record. Please try again.');
        }
    }

    public function verify(Request $request, CalvingRecord $calvingRecord)
    {
        try {
            $user = auth()->user();

            $calvingRecord->update([
                'verified_by_name' => $user->name,
                'verified_by_unit' => $user->role ?? 'Security',
                'verified_by_signature' => true,
                'verified_at' => now(),
                'status' => CalvingRecord::STATUS_VERIFIED,
            ]);

            return back()->with('success', 'Calving record verified successfully.');
        } catch (\Exception $e) {
            Log::error('Error verifying calving record: ' . $e->getMessage());

            return back()->with('error', 'Failed to verify calving record. Please try again.');
        }
    }

    public function witness(Request $request, CalvingRecord $calvingRecord)
    {
        try {
            $user = auth()->user();

            $calvingRecord->update([
                'witnessed_by_name' => $user->name,
                'witnessed_by_unit' => $user->role ?? 'Livestock',
                'witnessed_by_signature' => true,
                'witnessed_at' => now(),
                'status' => CalvingRecord::STATUS_WITNESSED,
            ]);

            // Get workflow assignment
            $assignment = CalvingWorkflowAssignment::first();
            
            // Notify the checker(s)
            if ($assignment) {
                $checkerIds = $assignment->checked_by_user_ids ?? [$assignment->checked_by_user_id];
                if (!empty($checkerIds)) {
                    foreach ($checkerIds as $checkerId) {
                        \App\Models\TaskNotification::create([
                            'user_id' => $checkerId,
                            'title' => 'Calving Record Ready for Checking',
                            'message' => "Calving record #{$calvingRecord->id} has been witnessed and is ready for checking.",
                            'type' => 'calving_workflow',
                            'is_read' => false,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            return back()->with('success', 'Calving record witnessed successfully.');
        } catch (\Exception $e) {
            Log::error('Error witnessing calving record: ' . $e->getMessage());

            return back()->with('error', 'Failed to witness calving record. Please try again.');
        }
    }

    public function approve(Request $request, CalvingRecord $calvingRecord)
    {
        try {
            $user = auth()->user();

            $calvingRecord->update([
                'approved_by_name' => $user->name,
                'approved_by_unit' => $user->role ?? 'Livestock',
                'approved_by_signature' => true,
                'approved_at' => now(),
                'status' => CalvingRecord::STATUS_APPROVED,
            ]);

// Notify the creator that their record is approved
            if ($calvingRecord->created_by) {
                App\Models\TaskNotification::create([
                    'user_id' => $calvingRecord->created_by,
                    'title' => 'Calving Record Approved',
                    'message' => "Your calving record #{$calvingRecord->id} has been approved.",
                    'type' => 'calving_workflow',
                    'is_read' => false,
                    'created_by' => auth()->id(),
                ]);
            }

            return back()->with('success', 'Calving record approved successfully.');
        } catch (\Exception $e) {
            Log::error('Error approving calving record: ' . $e->getMessage());

            return back()->with('error', 'Failed to approve calving record. Please try again.');
        }
    }

    public function reject(Request $request, CalvingRecord $calvingRecord)
    {
        try {
            $validated = $request->validate([
                'remarks' => 'nullable|string',
            ]);

            $calvingRecord->update([
                'status' => CalvingRecord::STATUS_REJECTED,
                'remarks' => $validated['remarks'] ?? $calvingRecord->remarks,
            ]);

            return back()->with('success', 'Calving record rejected.');
        } catch (\Exception $e) {
            Log::error('Error rejecting calving record: ' . $e->getMessage());

            return back()->with('error', 'Failed to reject calving record. Please try again.');
        }
    }

    public function export(Request $request)
    {
        $request->validate([
            'month' => 'required|string|max:20',
            'unit' => 'required|string|max:255',
        ]);

        $monthYear = $this->convertMonthToStorageFormat($request->string('month')->toString());
        $operatingUnit = $request->string('unit')->toString();

        $records = CalvingRecord::forMonth($monthYear)
            ->where('operating_unit', $operatingUnit)
            ->orderBy('calving_date', 'asc')
            ->get();

        $pdf = Pdf::loadView('pdfs.calving-monthly-form', [
            'records' => $records,
            'monthYear' => $monthYear,
            'operatingUnit' => $operatingUnit,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = "Monthly_Calving_Record_{$operatingUnit}_{$monthYear}.pdf";
        $filename = str_replace(['/', ' '], '_', $filename);

        return $pdf->download($filename);
    }

    public function lccDocument(CalvingRecord $calvingRecord)
    {
        return Inertia::render('Calving/LCCDocument', [
            'calvingRecord' => $calvingRecord,
        ]);
    }

    public function downloadEndorsement(CalvingRecord $calvingRecord, $stepIndex)
    {
        $stepNames = ['Issued', 'Verified', 'Checked', 'Witnessed', 'Approved'];
        $stepName = $stepNames[$stepIndex] ?? 'Endorsement';

        $stepRoleNames = [
            0 => 'Sr. Assistant Livestock',
            1 => 'Sr. Assistant Security',
            2 => 'Supervisor Livestock',
            3 => 'Estate Management',
            4 => 'Livestock Manager/OIC'
        ];

        $pdf = Pdf::loadView('calving.endorsement', [
            'calvingRecord' => $calvingRecord,
            'stepName' => $stepName,
            'stepIndex' => $stepIndex,
            'stepRoleNames' => $stepRoleNames,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'calving_' . ($calvingRecord->tag_no ?? 'record') . '_' . $stepName . '.pdf';

        return $pdf->download($filename);
    }

    public function uploadEndorsement(Request $request, CalvingRecord $calvingRecord, $stepIndex)
    {
        // DEBUG: Log CSRF token information
        Log::info('CSRF Debug - Token comparison', [
            'session_token' => $request->session()->token(),
            'token_from_header' => $request->header('X-CSRF-TOKEN'),
            'token_from_input' => $request->input('_token'),
            'session_id' => $request->session()->getId(),
        ]);
        
        Log::info('uploadEndorsement called', [
            'calving_record_id' => $calvingRecord->id,
            'stepIndex' => $stepIndex,
            'has_file' => $request->hasFile('signed_document'),
            'all_files' => $request->allFiles(),
            'file_keys' => array_keys($_FILES),
            'php_files' => $_FILES,
            'content_type' => $request->header('Content-Type'),
            'name' => $request->name,
            'date' => $request->date,
            'all_input' => $request->all(),
        ]);

        try {
            $request->validate([
                'signed_document' => 'required|file|mimes:pdf|max:10240',
                'name' => 'required|string|max:255',
                'date' => 'required|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            throw $e;
        }

        $user = auth()->user();
        $stepIndex = (int) $stepIndex;

        Log::info('User info', ['user_id' => $user->id, 'user_role' => $user->role, 'stepIndex' => $stepIndex]);

        if (!$this->userCanUploadWorkflowStep($user, $stepIndex)) {
            Log::warning('Permission denied', [
                'assigned_user_ids' => $this->getAssignedUserIdsForStep($stepIndex),
                'user_id' => $user->id,
                'step_index' => $stepIndex,
            ]);
            return back()->withErrors(['error' => 'You are not assigned to perform this workflow step.']);
        }

        // Get current endorsement documents as array for manipulation
        $rawDocs = $calvingRecord->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $currentStep = $calvingRecord->endorsement_step ?? 0;

        Log::info('Step check', [
            'currentStep' => $currentStep,
            'attemptedStepIndex' => $stepIndex,
            'record_status' => $calvingRecord->status,
            'endorsementDocs' => $endorsementDocs,
        ]);

        // Block uploads if case is already approved or completed
        if ($calvingRecord->status === 'approved' || $calvingRecord->status === 'completed') {
            Log::warning('Record already approved/completed');
            return back()->withErrors(['error' => 'This record has been completed. No further uploads are allowed.']);
        }

        // Check if can upload (current step or re-upload logic)
        $nextStepKey = strval($stepIndex + 1);

        // For steps 0-3: can upload at current step, OR re-upload if next person hasn't uploaded yet
        // For step 4 (manager): can upload/re-upload anytime until record is approved
        if ($stepIndex === 4) {
            // Manager (last step) - can upload/re-upload until record is approved
            $canUpload = ($stepIndex <= $currentStep);
        } else {
            // Steps 0-3 - can upload at current step, OR re-upload if next hasn't uploaded
            $canUpload = ($stepIndex === $currentStep) ||
                         ($stepIndex < $currentStep && !isset($endorsementDocs[$nextStepKey]));
        }

        Log::info('Can upload check', ['canUpload' => $canUpload, 'stepIndex' => $stepIndex, 'currentStep' => $currentStep]);

        if (!$canUpload) {
            Log::warning('Cannot upload at this step', ['stepIndex' => $stepIndex, 'currentStep' => $currentStep]);
            return back()->withErrors(['error' => "Cannot upload at this step. Current step is {$currentStep}, you are trying step {$stepIndex}"]);
        }

        try {
            // Store the file
            $file = $request->file('signed_document');
            $filename = 'calving_endorsement_' . $calvingRecord->id . '_step' . $stepIndex . '_' . time() . '.pdf';
            $path = $file->storeAs('calving_endorsements', $filename, 'public');

            Log::info('File stored', ['path' => $path, 'filename' => $filename]);

            // Update endorsement documents - use string key for consistent JSON object
            $endorsementDocs[strval($stepIndex)] = [
                'name' => $request->name,
                'date' => $request->date,
                'file_path' => $path,
                'uploaded_by' => $user->id,
                'uploaded_at' => now()->toDateTimeString(),
            ];

            // If it's a re-upload for a previous step, clear all subsequent steps and reset workflow to this point
            if ($stepIndex < $currentStep) {
                // Clear subsequent documents
                for ($i = $stepIndex + 1; $i <= 4; $i++) {
                    unset($endorsementDocs[strval($i)]);
                }
                $newStep = $stepIndex + 1;
            } else {
                // Move to next step if uploading at current step
                $newStep = $currentStep;
                if ($stepIndex === $currentStep && $stepIndex < 4) {
                    $newStep = $stepIndex + 1;
                }

                // If manager (step 4) uploads, set step to 5 (all uploaded)
                if ($stepIndex === 4) {
                    $newStep = 5;
                }
            }

            // Update legacy fields for backward compatibility
            $stepFields = [
                0 => ['name' => 'issued_by_name', 'date' => 'issued_by_date', 'signature' => 'issued_by_signature', 'at' => 'issued_at'],
                1 => ['name' => 'verified_by_name', 'date' => 'verified_by_date', 'signature' => 'verified_by_signature', 'at' => 'verified_at'],
                2 => ['name' => 'checked_by_name', 'date' => 'checked_by_date', 'signature' => 'checked_by_signature', 'at' => 'checked_at'],
                3 => ['name' => 'witnessed_by_name', 'date' => 'witnessed_by_date', 'signature' => 'witnessed_by_signature', 'at' => 'witnessed_at'],
                4 => ['name' => 'approved_by_name', 'date' => 'approved_by_date', 'signature' => 'approved_by_signature', 'at' => 'approved_at'],
            ];

            $fields = $stepFields[$stepIndex];
            $statuses = ['pending', 'issued', 'verified', 'checked', 'witnessed', 'approved'];

            $updateData = [
                'endorsement_documents' => $endorsementDocs,
                'endorsement_step' => $newStep,
                $fields['name'] => $request->name,
                $fields['date'] => $request->date,
                $fields['signature'] => true,
            ];

            // Update status based on first-time upload at this step or if we are reverting
            $isFirstUpload = !isset($rawDocs[strval($stepIndex)]);
            if ($isFirstUpload || $stepIndex < $currentStep) {
                $updateData['status'] = $statuses[$stepIndex + 1] ?? 'approved';
            }

            // Clear subsequent legacy fields if re-uploading
            if ($stepIndex < $currentStep) {
                for ($i = $stepIndex + 1; $i <= 4; $i++) {
                    $clearFields = $stepFields[$i];
                    $updateData[$clearFields['name']] = null;
                    $updateData[$clearFields['date']] = null;
                    $updateData[$clearFields['signature']] = false;
                    $updateData[$clearFields['at']] = null;
                }
            }

            Log::info('About to update record', ['updateData' => $updateData]);

            $calvingRecord->update($updateData);

            Log::info('Record updated successfully');

            if ($stepIndex === $currentStep && $stepIndex < 4) {
                $nextStepIndex = $stepIndex + 1;
                $currentStepLabel = $this->getWorkflowStepLabel($stepIndex);
                $nextStepLabel = $this->getWorkflowStepLabel($nextStepIndex);

                foreach ($this->getUsersForWorkflowStep($nextStepIndex) as $notifyUser) {
                    if ((int) $notifyUser->id === (int) $user->id) {
                        continue;
                    }

                    \App\Services\WorkflowNotificationService::createNotification(
                        'calving',
                        'calving',
                        $calvingRecord->id,
                        'workflow_step_completed',
                        'Calving Workflow Step Completed',
                        "The calving workflow step '{$currentStepLabel}' has been completed. Please proceed with '{$nextStepLabel}'.",
                        (int) $notifyUser->id
                    );
                }
            } elseif ($stepIndex === 4 && $newStep === 5) {
                $this->notifyCalvingWorkflowReadyForCompletion($calvingRecord->fresh(), (int) $user->id);
            }

            $message = $isFirstUpload ? 'Document uploaded successfully. Record progressed to next step.' : 'Document re-uploaded successfully.';

            // Inertia requests must receive a redirect/back response, not JSON.
            if ($request->header('X-Inertia')) {
                return back()->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'record' => $calvingRecord->fresh(),
                ]);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error uploading endorsement: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->header('X-Inertia')) {
                return back()->withErrors(['error' => 'Failed to upload document: ' . $e->getMessage()]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload document: ' . $e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to upload document: ' . $e->getMessage()]);
        }
    }

    private function normalizeRole(?string $role): string
    {
        $r = strtolower(trim((string) $role));
        $r = str_replace(['_', '-'], ' ', $r);
        $r = preg_replace('/\s+/', ' ', $r);

        if (in_array($r, ['manager', 'livestock manager', 'act livestock manager', 'act. livestock manager'], true)) {
            return 'livestock manager';
        }

        return $r;
    }

    private function rolesMatch(?string $a, ?string $b): bool
    {
        return $this->normalizeRole($a) === $this->normalizeRole($b);
    }

    private function getDefaultRolesForStep(int $stepIndex): array
    {
        return match ($stepIndex) {
            0 => ['livestock'],
            1 => ['security'],
            2 => ['supervisor'],
            3 => ['assistant_manager'],
            4 => ['livestock manager'],
            default => [],
        };
    }

    private function getAssignedUserIdsForStep(int $stepIndex): array
    {
        if (!Schema::hasTable('calving_workflow_assignments')) {
            return [];
        }

        $assignment = CalvingWorkflowAssignment::first();
        if (!$assignment) {
            return [];
        }

        $ids = match ($stepIndex) {
            0 => $assignment->issued_by_user_ids,
            1 => $assignment->verified_by_user_ids,
            2 => $assignment->checked_by_user_ids,
            3 => $assignment->witnessed_by_user_ids,
            4 => $assignment->approved_by_user_ids,
            default => [],
        };

        if (!is_array($ids)) {
            $ids = [];
        }

        if (empty($ids)) {
            $fallbackId = match ($stepIndex) {
                0 => $assignment->issued_by_user_id,
                1 => $assignment->verified_by_user_id,
                2 => $assignment->checked_by_user_id,
                3 => $assignment->witnessed_by_user_id,
                4 => $assignment->approved_by_user_id,
                default => null,
            };

            if ($fallbackId) {
                $ids = [(int) $fallbackId];
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function userCanUploadWorkflowStep($user, int $stepIndex): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $assignedUserIds = $this->getAssignedUserIdsForStep($stepIndex);

        return !empty($assignedUserIds) && in_array((int) $user->id, $assignedUserIds, true);
    }

    public function pending(Request $request)
    {
        $user = auth()->user();
        $userRole = $user->role ?? '';

        $query = CalvingRecord::query();

        $calvingRecords = $query->orderBy('created_at', 'desc')->get();

        $statuses = ['pending', 'issued', 'verified', 'checked', 'witnessed', 'approved'];
        
        $pendingRecords = $calvingRecords->filter(function ($record) use ($user, $statuses, $userRole) {
            if ($userRole === 'admin') return true;

            $currentStatus = $record->status ?? 'pending';
            $currentIndex = array_search($currentStatus, $statuses);

            if ($currentIndex === false || $currentIndex >= count($statuses) - 1) return false;
            if (!$this->userCanUploadWorkflowStep($user, (int) $currentIndex)) return false;

            // Map status index to signature field
            $signatureFields = [
                0 => 'issued_by_signature',
                1 => 'verified_by_signature',
                2 => 'checked_by_signature',
                3 => 'witnessed_by_signature',
                4 => 'approved_by_signature',
            ];
            
            $signatureField = $signatureFields[$currentIndex] ?? null;

            if ($signatureField) {
                return !$record->{$signatureField};
            }

            return false;
        });

        $stats = [
            'total' => $calvingRecords->count(),
            'pending' => $calvingRecords->where('status', 'pending')->count(),
            'issued' => $calvingRecords->where('status', 'issued')->count(),
            'verified' => $calvingRecords->where('status', 'verified')->count(),
            'checked' => $calvingRecords->where('status', 'checked')->count(),
            'witnessed' => $calvingRecords->where('status', 'witnessed')->count(),
            'approved' => $calvingRecords->where('status', 'approved')->count(),
        ];

        $availableMonths = ['Sept/2024', 'Aug/2024', 'Jul/2024', 'Jan/2026'];
        $operatingUnits = $this->getAvailableOperatingUnits();

        return Inertia::render('Calving/Pending', [
            'calvingRecords' => $calvingRecords,
            'pendingRecords' => $pendingRecords->values(),
            'stats' => $stats,
            'availableMonths' => $availableMonths,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    /**
     * Admin: Mark calving record as completed (locks all uploads)
     * Syncs Cattle profile from Calving after all workflow steps are complete
     */
    public function markAsCompleted(CalvingRecord $calvingRecord)
    {
        $user = Auth::user();
        
        // Only admin can mark as completed
        if ($user->role !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can mark records as completed']);
        }
        
        // Check if all 5 steps are uploaded
        $rawDocs = $calvingRecord->endorsement_documents;
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

        try {
            DB::beginTransaction();

            // Update calving record status
            $calvingRecord->update([
                'status' => 'completed',
                'endorsement_step' => 5,
                'is_reopened' => false,
            ]);

            // Sync to cattle only when workflow is fully completed.
            $this->syncCattleFromCalving($calvingRecord->fresh(), null, true);
            $cattle = Cattle::where('calving_record_id', $calvingRecord->id)->first();
            if (!$cattle) {
                throw new \RuntimeException('Unable to sync cattle profile after completion.');
            }

            Log::info('Cattle record synced from calving record', [
                'calving_record_id' => $calvingRecord->id,
                'cattle_id' => $cattle->id,
                'tag_no' => $cattle->tag_no,
            ]);

            if ($calvingRecord->created_by) {
                \App\Services\WorkflowNotificationService::createNotification(
                    'calving',
                    'calving',
                    $calvingRecord->id,
                    'completed',
                    'Calving Record Completed',
                    "Your calving record #{$calvingRecord->id} has been marked as completed.",
                    (int) $calvingRecord->created_by
                );
            }

            DB::commit();

            return back()->with('success', "Record marked as completed and cattle profile synced for tag #{$calvingRecord->tag_no}.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking calving record as completed: ' . $e->getMessage(), [
                'calving_record_id' => $calvingRecord->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to complete record: ' . $e->getMessage()]);
        }
    }

    /**
     * Admin: Reopen a completed/approved calving record
     */
    public function reopen(CalvingRecord $calvingRecord)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can reopen records']);
        }

        if ($calvingRecord->status !== 'completed') {
            return back()->withErrors(['error' => 'Only records marked as completed can be reopened']);
        }

        $rawDocs = $calvingRecord->endorsement_documents;
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
            $newStatus = 'issued';
        } elseif ($has0 && $has1 && !$has2) {
            $newStep = 2;
            $newStatus = 'verified';
        } elseif ($has0 && $has1 && $has2 && !$has3) {
            $newStep = 3;
            $newStatus = 'checked';
        } elseif ($has0 && $has1 && $has2 && $has3 && !$has4) {
            $newStep = 4;
            $newStatus = 'witnessed';
        } elseif ($has0 && $has1 && $has2 && $has3 && $has4) {
            // Keep step 4 active so manager can re-upload after reopen.
            $newStep = 4;
            $newStatus = 'witnessed';
        }

        $calvingRecord->update([
            'status' => $newStatus,
            'endorsement_step' => $newStep,
            'is_reopened' => true,
        ]);

        $this->removeSyncedCattleForCalving($calvingRecord);

        return back()->with('success', 'Calving record reopened successfully.');
    }

    private function getWorkflowStepLabel(int $stepIndex): string
    {
        return match ($stepIndex) {
            0 => 'Issued',
            1 => 'Verified',
            2 => 'Checked',
            3 => 'Witnessed',
            4 => 'Approved',
            default => 'Next Step',
        };
    }

    private function notifyCalvingWorkflowReadyForCompletion(CalvingRecord $calvingRecord, int $uploaderId): void
    {
        $recordRef = $calvingRecord->tag_no ?: ('Record #' . $calvingRecord->id);

        foreach (User::where('role', 'admin')->get() as $adminUser) {
            if ((int) $adminUser->id === $uploaderId) {
                continue;
            }

            \App\Services\WorkflowNotificationService::createNotification(
                'calving',
                'calving',
                $calvingRecord->id,
                'workflow_ready_for_completion',
                'Calving Workflow Ready for Completion',
                "All workflow steps for calving record {$recordRef} have been uploaded. Please mark the calving record as completed.",
                (int) $adminUser->id
            );
        }
    }

    private function getUsersForWorkflowStep(int $stepIndex)
    {
        $assignedUserIds = $this->getAssignedUserIdsForStep($stepIndex);
        if (empty($assignedUserIds)) {
            return collect();
        }

        return \App\Models\User::whereIn('id', $assignedUserIds)->get();
    }

    private function removeSyncedCattleForCalving(CalvingRecord $calvingRecord): void
    {
        $marker = "Synced from Calving Record #{$calvingRecord->id}";

        $linkedCattle = Cattle::where('calving_record_id', $calvingRecord->id)->get();
        foreach ($linkedCattle as $cattle) {
            $cattle->delete();
        }

        if (!empty($calvingRecord->tag_no)) {
            $fallbackLinked = Cattle::whereNull('calving_record_id')
                ->where('tag_no', $calvingRecord->tag_no)
                ->get();

            foreach ($fallbackLinked as $cattle) {
                $remarks = (string) ($cattle->remarks ?? '');
                if (str_contains($remarks, $marker)) {
                    $cattle->delete();
                }
            }
        }
    }

    /**
     * Download uploaded endorsement document for a specific step
     */
    public function downloadEndorsementDocument(CalvingRecord $calvingRecord, $stepIndex)
    {
        $stepIndex = (int) $stepIndex;
        
        // Get endorsement documents
        $endorsementDocs = $calvingRecord->endorsement_documents;
        if (is_object($endorsementDocs)) {
            $endorsementDocs = json_decode(json_encode($endorsementDocs), true) ?? [];
        } elseif (!is_array($endorsementDocs)) {
            $endorsementDocs = [];
        }

        $stepDoc = $endorsementDocs[strval($stepIndex)] ?? $endorsementDocs[$stepIndex] ?? null;

        if (!$stepDoc || !isset($stepDoc['file_path'])) {
            abort(404, 'Document not found');
        }

        $filePath = storage_path('app/public/' . $stepDoc['file_path']);
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        $stepNames = ['Issued', 'Verified', 'Checked', 'Witnessed', 'Approved'];
        $stepName = $stepNames[$stepIndex] ?? 'Step' . $stepIndex;
        $filename = 'calving_' . ($calvingRecord->tag_no ?? 'record') . '_' . $stepName . '.pdf';

        return response()->download($filePath, $filename);
    }
}
