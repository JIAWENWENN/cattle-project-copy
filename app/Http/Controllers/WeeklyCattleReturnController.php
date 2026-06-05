<?php

namespace App\Http\Controllers;

use App\Models\CalvingRecord;
use App\Models\Cattle;
use App\Models\Estate;
use App\Models\MortalityCase;
use App\Models\TransferDocument;
use App\Models\TransferLivestock;
use App\Models\WeeklyCattleReturnWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WeeklyCattleReturnController extends Controller
{
    private const CATEGORY_CODES = ['B/B', 'B/C', 'W/B', 'H', 'M/C', 'F/C'];
    private const WORKFLOW_STEPS = [
        ['label' => 'Prepared by', 'roles' => ['livestock'], 'role_name' => 'Sr. Assistant Livestock'],
        ['label' => 'Verified by', 'roles' => ['penyelia', 'security'], 'role_name' => 'Penyelia Security'],
        ['label' => 'Checked by', 'roles' => ['supervisor', 'livestock supervisor'], 'role_name' => 'Livestock Supervisor'],
        ['label' => 'Approved by', 'roles' => ['livestock manager', 'act livestock manager', 'act. livestock manager', 'manager'], 'role_name' => 'ACT. Livestock Manager'],
    ];

    public function index(Request $request)
    {
        [$dateFrom, $dateTo, $filters, $monthLabel, $submissionLabel] = $this->resolveDateRange($request);

        $operatingUnits = $this->getOperatingUnits();
        $selectedUnit = trim((string) $request->get('unit', ''));

        if ($selectedUnit !== '' && !in_array($selectedUnit, $operatingUnits, true)) {
            $selectedUnit = '';
        }

        $unitsToProcess = $selectedUnit !== '' ? [$selectedUnit] : $operatingUnits;
        [$rows, $totals] = $this->buildWeeklyReturnRowsAndTotals($dateFrom, $dateTo, $unitsToProcess, $selectedUnit);

        $workflow = null;
        $workflowCompletedAt = null;
        if ($selectedUnit !== '') {
            $workflow = WeeklyCattleReturnWorkflow::firstOrCreate(
                [
                    'period_from' => $dateFrom->toDateString(),
                    'period_to' => $dateTo->toDateString(),
                    'operating_unit' => $selectedUnit,
                ],
                [
                    'endorsement_step' => 0,
                    'endorsement_documents' => [],
                    'status' => 'pending',
                    'is_completed' => false,
                ]
            );

            $workflowCompletedAt = ((bool) $workflow->is_completed && $workflow->completed_at)
                ? $workflow->completed_at->format('d/m/Y H:i')
                : null;
        }

        return Inertia::render('Cattle/WeeklyReturn', [
            'rows' => $rows,
            'totals' => $totals,
            'categoryCodes' => self::CATEGORY_CODES,
            'operatingUnits' => $operatingUnits,
            'filters' => array_merge($filters, ['unit' => $selectedUnit]),
            'isExport' => $request->boolean('export'),
            'workflow' => $workflow,
            'workflowCompletedAt' => $workflowCompletedAt,
            'workflowSteps' => self::WORKFLOW_STEPS,
            'canMarkCompleted' => strtolower((string) (Auth::user()?->role ?? '')) === 'admin',
            'canReopenWorkflow' => strtolower((string) (Auth::user()?->role ?? '')) === 'admin',
            'meta' => [
                'monthLabel' => $monthLabel,
                'submissionLabel' => $submissionLabel,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ],
            'company' => [
                'name' => 'SAWIT KINABALU FARM PRODUCT SDN. BHD. (465571-P)',
                'subsidiary' => '(Wholly owned by SAWIT KINABALU SDN. BHD. 403109-W)',
                'address' => 'Locked Bag No. 28, Apas Road 91000 Tawau, Sabah',
            ],
        ]);
    }

        public function downloadEndorsementForm(Request $request)
    {
        [$dateFrom, $dateTo, $filters, $monthLabel, $submissionLabel] = $this->resolveDateRange($request);

        $operatingUnits = $this->getOperatingUnits();
        $selectedUnit = trim((string) $request->get('unit', ''));

        if ($selectedUnit !== '' && !in_array($selectedUnit, $operatingUnits, true)) {
            $selectedUnit = '';
        }

        $unitsToProcess = $selectedUnit !== '' ? [$selectedUnit] : $operatingUnits;
        [$rows, $totals] = $this->buildWeeklyReturnRowsAndTotals($dateFrom, $dateTo, $unitsToProcess, $selectedUnit);

        $workflow = null;
        if ($selectedUnit !== '') {
            $workflow = \App\Models\WeeklyCattleReturnWorkflow::where('period_from', $dateFrom->toDateString())
                ->where('period_to', $dateTo->toDateString())
                ->where('operating_unit', $selectedUnit)
                ->first();
        }

        $html = view('pdfs.weekly-return-form', [
            'rows' => $rows,
            'totals' => $totals,
            'categoryCodes' => self::CATEGORY_CODES,
            'workflow' => $workflow,
            'monthLabel' => $monthLabel,
            'submissionLabel' => $submissionLabel,
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'Weekly_Return_Form_' . ($selectedUnit ?: 'All') . '_' . $dateFrom->format('Ymd') . '.pdf';

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function workflow(Request $request)
    {
        [$dateFrom, $dateTo, $filters, $monthLabel, $submissionLabel] = $this->resolveDateRange($request);

        $selectedUnit = trim((string) $request->get('unit', ''));
        if ($selectedUnit === '') {
            return redirect()->route('cattle.weekly-return', [
                'mode' => $filters['mode'] ?? 'week',
                'month' => $filters['month'] ?? null,
                'week' => $filters['week'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ])->withErrors(['error' => 'Please select an operating unit first.']);
        }

        $workflow = WeeklyCattleReturnWorkflow::firstOrCreate(
            [
                'period_from' => $dateFrom->toDateString(),
                'period_to' => $dateTo->toDateString(),
                'operating_unit' => $selectedUnit,
            ],
            [
                'endorsement_step' => 0,
                'endorsement_documents' => [],
                'status' => 'pending',
                'is_completed' => false,
            ]
        );

        $workflowAssignments = \Illuminate\Support\Facades\Schema::hasTable('workflow_assignments')
            ? \App\Models\WorkflowAssignment::where('module', 'Weekly Return')->first()
            : null;
            
        $assignments = $workflowAssignments ? ($workflowAssignments->assignments ?? []) : [];
        $userId = (int) Auth::id();
        $isAdmin = strtolower((string) (Auth::user()?->role ?? '')) === 'admin';

        $steps = array_map(function ($step, $index) use ($assignments, $userId, $isAdmin) {
            $stepKey = match ($index) {
                0 => 'prepared_by_user_ids',
                1 => 'verified_by_user_ids',
                2 => 'checked_by_user_ids',
                3 => 'approved_by_user_ids',
                default => null,
            };
            
            $assignedIds = $stepKey && isset($assignments[$stepKey]) && is_array($assignments[$stepKey]) 
                ? array_map('intval', $assignments[$stepKey]) 
                : [];
                
            $step['can_handle'] = $isAdmin || in_array($userId, $assignedIds, true);
            return $step;
        }, self::WORKFLOW_STEPS, array_keys(self::WORKFLOW_STEPS));

        return Inertia::render('Cattle/WeeklyReturnWorkflow', [
            'workflow' => $workflow,
            'workflowSteps' => $steps,
            'filters' => array_merge($filters, ['unit' => $selectedUnit]),
            'meta' => [
                'monthLabel' => $monthLabel,
                'submissionLabel' => $submissionLabel,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ],
            'canMarkCompleted' => $isAdmin,
            'canReopenWorkflow' => $isAdmin,
            'workflowCompletedAt' => ((bool) $workflow->is_completed && $workflow->completed_at)
                ? $workflow->completed_at->format('d/m/Y H:i')
                : null,
        ]);
    }

    public function uploadEndorsement(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'unit' => 'required|string|max:255',
            'step_index' => 'required|integer|min:0|max:3',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'signed_document' => 'required|file|mimes:pdf|max:10240',
        ]);

        $workflow = WeeklyCattleReturnWorkflow::firstOrCreate(
            [
                'period_from' => $validated['from'],
                'period_to' => $validated['to'],
                'operating_unit' => $validated['unit'],
            ],
            [
                'endorsement_step' => 0,
                'endorsement_documents' => [],
                'status' => 'pending',
                'is_completed' => false,
            ]
        );

        $user = Auth::user();
        $stepIndex = (int) $validated['step_index'];

        if (!$this->canUserHandleStep($stepIndex, $user?->id) && strtolower((string) ($user?->role ?? '')) !== 'admin') {
            return back()->withErrors(['error' => 'You are not allowed to upload this workflow step.']);
        }

        $currentStep = (int) ($workflow->endorsement_step ?? 0);
        if ($stepIndex > $currentStep && strtolower((string) ($user?->role ?? '')) !== 'admin') {
            return back()->withErrors(['error' => 'Please complete previous step first.']);
        }

        $file = $request->file('signed_document');
        $filename = 'weekly_return_' . $workflow->id . '_step' . $stepIndex . '_' . time() . '.pdf';
        $path = $file->storeAs('weekly-return-endorsements', $filename);

        $workflow->setStepDocument($stepIndex, [
            'name' => $validated['name'],
            'date' => $validated['date'],
            'filename' => $filename,
            'path' => $path,
            'uploaded_at' => now()->toISOString(),
            'uploaded_by' => $user?->name,
        ]);

        if ($stepIndex === $currentStep && $currentStep < 3) {
            $workflow->endorsement_step = $currentStep + 1;

            // Notify users responsible for the next step
            $nextStepIndex = $currentStep + 1;
            if (isset(self::WORKFLOW_STEPS[$nextStepIndex])) {
                $usersToNotify = collect();
                if (\Illuminate\Support\Facades\Schema::hasTable('workflow_assignments')) {
                    $assignment = \App\Models\WorkflowAssignment::where('module', 'Weekly Return')->first();
                    $stepKey = match ($nextStepIndex) {
                        0 => 'prepared_by_user_ids',
                        1 => 'verified_by_user_ids',
                        2 => 'checked_by_user_ids',
                        3 => 'approved_by_user_ids',
                        default => null,
                    };
                    $assignedIds = ($stepKey && is_array($assignment?->assignments))
                        ? array_map('intval', (array) ($assignment->assignments[$stepKey] ?? []))
                        : [];
                    if (!empty($assignedIds)) {
                        $usersToNotify = \App\Models\User::whereIn('id', $assignedIds)->get();
                    }
                }

                foreach ($usersToNotify as $notifyUser) {
                    $completedLabel = self::WORKFLOW_STEPS[$stepIndex]['label'] ?? '';
                    $nextLabel = self::WORKFLOW_STEPS[$nextStepIndex]['label'] ?? '';
                    $periodStr = \Carbon\Carbon::parse($workflow->period_from)->format('d/m/Y') . ' to ' . \Carbon\Carbon::parse($workflow->period_to)->format('d/m/Y');

                    \App\Models\TaskNotification::create([
                        'user_id' => $notifyUser->id,
                        'title' => 'Workflow Step Completed',
                        'message' => "The Weekly Return workflow for {$workflow->operating_unit} ({$periodStr}) step '{$completedLabel}' has been completed. Please proceed with '{$nextLabel}'.",
                        'type' => 'workflow_step_completed',
                        'is_read' => false,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }

        $statusMap = [0 => 'prepared', 1 => 'verified', 2 => 'checked', 3 => 'approved'];
        $workflow->status = $statusMap[$stepIndex] ?? $workflow->status;
        $workflow->save();

        return back()->with('success', 'Workflow document uploaded successfully.');
    }

    public function downloadEndorsement(Request $request, int $stepIndex)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'unit' => 'required|string|max:255',
        ]);

        $workflow = WeeklyCattleReturnWorkflow::where('period_from', $validated['from'])
            ->where('period_to', $validated['to'])
            ->where('operating_unit', $validated['unit'])
            ->firstOrFail();

        $doc = $workflow->getStepDocument($stepIndex);
        if (!$doc || !isset($doc['path']) || !Storage::exists($doc['path'])) {
            abort(404, 'Document not found');
        }

        return Storage::download($doc['path'], $doc['filename'] ?? ('weekly_return_step_' . $stepIndex . '.pdf'));
    }

    public function markCompleted(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'unit' => 'required|string|max:255',
        ]);

        $userRole = strtolower((string) (Auth::user()?->role ?? ''));
        if ($userRole !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can mark workflow as completed.']);
        }

        $workflow = WeeklyCattleReturnWorkflow::where('period_from', $validated['from'])
            ->where('period_to', $validated['to'])
            ->where('operating_unit', $validated['unit'])
            ->firstOrFail();

        if (!$workflow->allStepsUploaded()) {
            return back()->withErrors(['error' => 'All endorsement steps must be uploaded first.']);
        }

        $workflow->update([
            'endorsement_step' => 4,
            'is_completed' => true,
            'completed_at' => now(),
            'status' => 'completed',
        ]);

        // Notify administrators that the workflow is completed
        $adminUsers = \App\Models\User::where('role', 'admin')->get();
        $periodStr = \Carbon\Carbon::parse($workflow->period_from)->format('d/m/Y') . ' to ' . \Carbon\Carbon::parse($workflow->period_to)->format('d/m/Y');

        foreach ($adminUsers as $adminUser) {
            \App\Models\TaskNotification::create([
                'user_id' => $adminUser->id,
                'title' => 'Weekly Workflow Completed',
                'message' => "The Weekly Return workflow for {$workflow->operating_unit} ({$periodStr}) has been marked as completed.",
                'type' => 'workflow_completed',
                'is_read' => false,
                'created_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Weekly workflow marked as completed.');
    }

    public function reopenWorkflow(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'unit' => 'required|string|max:255',
        ]);

        $userRole = strtolower((string) (Auth::user()?->role ?? ''));
        if ($userRole !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can reopen workflow.']);
        }

        $workflow = WeeklyCattleReturnWorkflow::where('period_from', $validated['from'])
            ->where('period_to', $validated['to'])
            ->where('operating_unit', $validated['unit'])
            ->firstOrFail();

        $nextStatus = $workflow->allStepsUploaded() ? 'approved' : 'pending';

        $workflow->update([
            'is_completed' => false,
            'completed_at' => null,
            'status' => $nextStatus,
            'endorsement_step' => $workflow->allStepsUploaded()
                ? max((int) ($workflow->endorsement_step ?? 0), 4)
                : (int) ($workflow->endorsement_step ?? 0),
        ]);

        return back()->with('success', 'Weekly workflow reopened successfully.');
    }

    private function resolveDateRange(Request $request): array
    {
        $mode = strtolower(trim((string) $request->get('mode', 'week')));
        $today = now();

        if ($mode === 'date') {
            $fromInput = trim((string) $request->get('from', ''));
            $toInput = trim((string) $request->get('to', ''));

            $dateFrom = $fromInput !== '' ? Carbon::parse($fromInput)->startOfDay() : $today->copy()->startOfWeek(Carbon::MONDAY);
            $dateTo = $toInput !== '' ? Carbon::parse($toInput)->endOfDay() : $today->copy()->endOfWeek(Carbon::SUNDAY);

            if ($dateFrom->greaterThan($dateTo)) {
                [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
            }

            $submissionLabel = $dateFrom->format('d/n/Y') . ' - ' . $dateTo->format('d/n/Y');

            return [
                $dateFrom,
                $dateTo,
                [
                    'mode' => 'date',
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString(),
                    'month' => $dateFrom->format('Y-m'),
                    'week' => 1,
                ],
                $dateFrom->format('M-y'),
                $submissionLabel,
            ];
        }

        $monthInput = trim((string) $request->get('month', $today->format('Y-m')));
        $week = (int) $request->get('week', 1);
        $week = max(1, min(5, $week));

        try {
            $startOfMonth = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable $e) {
            $startOfMonth = $today->copy()->startOfMonth();
            $monthInput = $startOfMonth->format('Y-m');
        }

        $dateFrom = $startOfMonth->copy()->addDays(($week - 1) * 7)->startOfDay();
        if ($dateFrom->month !== $startOfMonth->month) {
            $dateFrom = $startOfMonth->copy()->startOfDay();
            $week = 1;
        }

        $dateTo = $dateFrom->copy()->addDays(6)->endOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();
        if ($dateTo->greaterThan($endOfMonth)) {
            $dateTo = $endOfMonth;
        }

        $submissionLabel = $dateFrom->format('d/n/Y') . ' - ' . $dateTo->format('d/n/Y') . " (WEEK {$week})";

        return [
            $dateFrom,
            $dateTo,
            [
                'mode' => 'week',
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
                'month' => $monthInput,
                'week' => $week,
            ],
            $startOfMonth->format('M-y'),
            $submissionLabel,
        ];
    }

    private function getOperatingUnits(): array
    {
        return Estate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }

    private function emptyBucket(): array
    {
        return array_fill_keys(self::CATEGORY_CODES, 0);
    }

    private function withTotal(array $bucket): array
    {
        $bucket['TOTAL'] = array_sum($bucket);
        return $bucket;
    }

    private function emptyStatsSet(): array
    {
        return [
            'opening' => $this->emptyBucket(),
            'calving' => $this->emptyBucket(),
            'mortality' => $this->emptyBucket(),
            'sale' => $this->emptyBucket(),
            'transfer_in' => $this->emptyBucket(),
            'transfer_out' => $this->emptyBucket(),
        ];
    }

    private function buildWeeklyReturnRowsAndTotals(Carbon $dateFrom, Carbon $dateTo, array $unitsToProcess, string $selectedUnit): array
    {
        $statsByUnit = [];
        foreach ($unitsToProcess as $unitName) {
            $statsByUnit[$unitName] = $this->emptyStatsSet();
            $statsByUnit[$unitName]['opening'] = $this->computeOpeningFromPreviousWeek($unitName, $dateFrom);
        }

        $movementStats = $this->computeMovementStatsByUnit($dateFrom, $dateTo, $unitsToProcess);
        foreach ($movementStats as $unit => $movement) {
            if (!isset($statsByUnit[$unit])) {
                continue;
            }

            foreach (['calving', 'mortality', 'sale', 'transfer_in', 'transfer_out'] as $section) {
                $statsByUnit[$unit][$section] = $movement[$section] ?? $this->emptyBucket();
            }
        }

        $rows = [];
        foreach ($statsByUnit as $unit => $stats) {
            $closing = $this->emptyBucket();
            foreach (self::CATEGORY_CODES as $code) {
                $opening = $stats['opening'][$code] ?? 0;
                $calving = $stats['calving'][$code] ?? 0;
                $mortality = $stats['mortality'][$code] ?? 0;
                $sale = $stats['sale'][$code] ?? 0;
                $transferIn = $stats['transfer_in'][$code] ?? 0;
                $transferOut = $stats['transfer_out'][$code] ?? 0;
                $closing[$code] = $opening + $calving - $mortality - $sale + $transferIn - $transferOut;
            }

            $row = [
                'herd' => $unit,
                'opening' => $this->withTotal($stats['opening']),
                'calving' => $stats['calving'],
                'mortality' => $stats['mortality'],
                'sale' => $stats['sale'],
                'transfer_in' => $stats['transfer_in'],
                'transfer_out' => $stats['transfer_out'],
                'closing' => $this->withTotal($closing),
            ];

            if ($selectedUnit !== '' || $this->rowHasAnyValue($row)) {
                $rows[] = $row;
            }
        }

        if (empty($rows) && $selectedUnit !== '') {
            $rows[] = [
                'herd' => $selectedUnit,
                'opening' => $this->withTotal($this->emptyBucket()),
                'calving' => $this->emptyBucket(),
                'mortality' => $this->emptyBucket(),
                'sale' => $this->emptyBucket(),
                'transfer_in' => $this->emptyBucket(),
                'transfer_out' => $this->emptyBucket(),
                'closing' => $this->withTotal($this->emptyBucket()),
            ];
        }

        return [$rows, $this->buildTotals($rows)];
    }

    private function computeOpeningFromPreviousWeek(string $unitName, Carbon $dateFrom): array
    {
        $previousWeekStart = $dateFrom->copy()->subDays(7)->startOfDay();
        $previousWeekEnd = $dateFrom->copy()->subDay()->endOfDay();

        $previousOpening = $this->computeBookBalanceAtDateForUnit($unitName, $previousWeekStart);
        $previousMovement = $this->computeMovementStatsByUnit($previousWeekStart, $previousWeekEnd, [$unitName])[$unitName] ?? $this->emptyStatsSet();

        $closing = $this->emptyBucket();
        foreach (self::CATEGORY_CODES as $code) {
            $closing[$code] = ($previousOpening[$code] ?? 0)
                + ($previousMovement['calving'][$code] ?? 0)
                - ($previousMovement['mortality'][$code] ?? 0)
                - ($previousMovement['sale'][$code] ?? 0)
                + ($previousMovement['transfer_in'][$code] ?? 0)
                - ($previousMovement['transfer_out'][$code] ?? 0);
        }

        return $closing;
    }

    private function computeBookBalanceAtDateForUnit(string $unitName, Carbon $asOf): array
    {
        $opening = $this->emptyBucket();

        $deceasedBefore = MortalityCase::query()
            ->whereNotNull('cattle_id')
            ->where('status', 'completed')
            ->whereDate('death_date', '<', $asOf->toDateString())
            ->pluck('cattle_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $cattle = Cattle::query()
            ->where(function ($query) use ($unitName) {
                $query->where('location_block', $unitName)
                    ->orWhere('operating_unit', $unitName);
            })
            ->get(['id', 'tag_no', 'category', 'birth_date', 'created_at', 'status']);

        foreach ($cattle as $animal) {
            $eventDate = $animal->birth_date
                ? Carbon::parse($animal->birth_date)->endOfDay()
                : Carbon::parse($animal->created_at)->endOfDay();

            if ($eventDate->greaterThan($asOf->copy()->endOfDay())) {
                continue;
            }

            if (isset($deceasedBefore[(int) $animal->id])) {
                continue;
            }

            $status = strtolower(trim((string) $animal->status));
            if (in_array($status, ['deceased', 'sold'], true)) {
                continue;
            }

            $code = $this->toCategoryCode($animal->category);
            $this->increase($opening, $code);
        }

        return $opening;
    }

    private function computeMovementStatsByUnit(Carbon $dateFrom, Carbon $dateTo, array $unitsToProcess): array
    {
        $statsByUnit = [];
        foreach ($unitsToProcess as $unitName) {
            $statsByUnit[$unitName] = $this->emptyStatsSet();
        }

        if (empty($unitsToProcess)) {
            return $statsByUnit;
        }

        $calvingRecords = CalvingRecord::query()
            ->whereBetween('calving_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('status', 'completed')
            ->whereIn('operating_unit', $unitsToProcess)
            ->get(['operating_unit', 'sex']);

        foreach ($calvingRecords as $record) {
            $unit = trim((string) $record->operating_unit);
            if ($unit === '' || !isset($statsByUnit[$unit])) {
                continue;
            }

            $code = strtoupper(trim((string) $record->sex)) === 'MC' ? 'M/C' : 'F/C';
            $this->increase($statsByUnit[$unit]['calving'], $code);
        }

        $mortalityCases = MortalityCase::query()
            ->with('cattle:id,category,location_block,operating_unit')
            ->where('status', 'completed')
            ->whereBetween('death_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get(['id', 'cattle_id', 'category', 'location', 'death_date']);

        foreach ($mortalityCases as $case) {
            $unit = trim((string) ($case->location ?: $case->cattle?->operating_unit ?: $case->cattle?->location_block));
            if ($unit === '' || !isset($statsByUnit[$unit])) {
                continue;
            }

            $code = $this->toCategoryCode($case->cattle?->category);
            $this->increase($statsByUnit[$unit]['mortality'], $code);
        }

        $saleDocs = TransferDocument::query()
            ->where('type', TransferDocument::TYPE_SIV)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereIn('from_location', $unitsToProcess)
            ->with('livestock:id,transfer_document_id,category,tag_no')
            ->get(['id', 'from_location']);

        $saleTags = $saleDocs->flatMap->livestock
            ->pluck('tag_no')
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $cattleByTag = [];
        if (!empty($saleTags)) {
            $cattleByTag = Cattle::query()
                ->whereIn('tag_no', $saleTags)
                ->get(['id', 'tag_no', 'category'])
                ->mapWithKeys(fn ($cattle) => [trim((string) $cattle->tag_no) => ['id' => (int) $cattle->id, 'category' => (string) $cattle->category]])
                ->all();
        }

        $receivalTags = [];
        if (!empty($saleTags)) {
            $receivalTags = TransferLivestock::query()
                ->whereIn('tag_no', $saleTags)
                ->whereHas('document', function ($query) {
                    $query->where('type', TransferDocument::TYPE_RECEIVAL)
                        ->where('status', TransferDocument::STATUS_COMPLETED);
                })
                ->pluck('tag_no')
                ->map(fn ($tag) => trim((string) $tag))
                ->filter()
                ->unique()
                ->flip()
                ->all();
        }

        foreach ($saleDocs as $doc) {
            $unit = trim((string) $doc->from_location);
            if ($unit === '' || !isset($statsByUnit[$unit])) {
                continue;
            }

            foreach ($doc->livestock as $item) {
                $tag = trim((string) $item->tag_no);
                if ($tag === '' || !isset($receivalTags[$tag])) {
                    continue;
                }

                $cattleCategory = $cattleByTag[$tag]['category'] ?? null;
                $code = $this->toCategoryCode($cattleCategory);
                $this->increase($statsByUnit[$unit]['sale'], $code);
            }
        }

        $transferDocs = TransferDocument::query()
            ->where('type', TransferDocument::TYPE_CTV)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where(function ($query) use ($unitsToProcess) {
                $query->whereIn('from_location', $unitsToProcess)
                    ->orWhereIn('to_location', $unitsToProcess);
            })
            ->with('livestock:id,transfer_document_id,category,tag_no')
            ->get(['id', 'from_location', 'to_location']);

        $transferTags = $transferDocs->flatMap->livestock
            ->pluck('tag_no')
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $transferCategoryByTag = [];
        if (!empty($transferTags)) {
            $transferCategoryByTag = Cattle::query()
                ->whereIn('tag_no', $transferTags)
                ->pluck('category', 'tag_no')
                ->mapWithKeys(fn ($category, $tag) => [trim((string) $tag) => (string) $category])
                ->all();
        }

        foreach ($transferDocs as $doc) {
            $fromUnit = trim((string) $doc->from_location);
            $toUnit = trim((string) $doc->to_location);

            foreach ($doc->livestock as $item) {
                $tag = trim((string) $item->tag_no);
                if ($tag === '' || !isset($transferCategoryByTag[$tag])) {
                    continue;
                }

                $code = $this->toCategoryCode($transferCategoryByTag[$tag]);

                if ($fromUnit !== '' && isset($statsByUnit[$fromUnit])) {
                    $this->increase($statsByUnit[$fromUnit]['transfer_out'], $code);
                }

                if ($toUnit !== '' && isset($statsByUnit[$toUnit])) {
                    $this->increase($statsByUnit[$toUnit]['transfer_in'], $code);
                }
            }
        }

        return $statsByUnit;
    }

    private function increase(array &$bucket, ?string $code, int $amount = 1): void
    {
        if (!$code || !array_key_exists($code, $bucket)) {
            return;
        }

        $bucket[$code] += $amount;
    }

    private function toCategoryCode(?string $category): ?string
    {
        $value = strtoupper(trim((string) $category));
        if ($value === '') {
            return null;
        }

        if (preg_match('/\(([^)]+)\)/', $value, $matches)) {
            $code = strtoupper(trim($matches[1]));
            if (in_array($code, self::CATEGORY_CODES, true)) {
                return $code;
            }
        }

        if (in_array($value, self::CATEGORY_CODES, true)) {
            return $value;
        }

        $compactCodeMap = [
            'BB' => 'B/B',
            'BC' => 'B/C',
            'WB' => 'W/B',
            'MC' => 'M/C',
            'FC' => 'F/C',
        ];

        if (isset($compactCodeMap[$value])) {
            return $compactCodeMap[$value];
        }

        return match (true) {
            str_contains($value, 'BREEDER BULL') => 'B/B',
            str_contains($value, 'BREEDER COW') => 'B/C',
            str_contains($value, 'WEANER BULL') => 'W/B',
            str_contains($value, 'HEIFER') => 'H',
            str_contains($value, 'MALE CALF') => 'M/C',
            str_contains($value, 'FEMALE CALF') => 'F/C',
            default => null,
        };
    }

    private function rowHasAnyValue(array $row): bool
    {
        foreach (['opening', 'calving', 'mortality', 'sale', 'transfer_in', 'transfer_out', 'closing'] as $section) {
            if (array_sum($row[$section]) > 0) {
                return true;
            }
        }

        return false;
    }

    private function buildTotals(array $rows): array
    {
        $totals = [
            'opening' => $this->emptyBucket(),
            'calving' => $this->emptyBucket(),
            'mortality' => $this->emptyBucket(),
            'sale' => $this->emptyBucket(),
            'transfer_in' => $this->emptyBucket(),
            'transfer_out' => $this->emptyBucket(),
            'closing' => $this->emptyBucket(),
        ];

        foreach ($rows as $row) {
            foreach (['opening', 'calving', 'mortality', 'sale', 'transfer_in', 'transfer_out', 'closing'] as $section) {
                foreach (self::CATEGORY_CODES as $code) {
                    $totals[$section][$code] += (int) ($row[$section][$code] ?? 0);
                }
            }
        }

        return [
            'opening' => $this->withTotal($totals['opening']),
            'calving' => $totals['calving'],
            'mortality' => $totals['mortality'],
            'sale' => $totals['sale'],
            'transfer_in' => $totals['transfer_in'],
            'transfer_out' => $totals['transfer_out'],
            'closing' => $this->withTotal($totals['closing']),
        ];
    }

    private function normalizeRole(?string $role): string
    {
        $r = strtolower(trim((string) $role));
        $r = str_replace(['_', '-', '.'], ' ', $r);
        $r = preg_replace('/\s+/', ' ', $r);

        if (in_array($r, ['manager', 'livestock manager', 'act livestock manager', 'act. livestock manager'], true)) {
            return 'livestock manager';
        }

        if (in_array($r, ['sr assistant livestock', 'senior assistant livestock'], true)) {
            return 'livestock';
        }

        if (in_array($r, ['penyelia security', 'sr assistant security', 'senior assistant security'], true)) {
            return 'penyelia';
        }

        return $r;
    }

    private function canUserHandleStep(int $stepIndex, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('workflow_assignments')) {
            return false;
        }

        $assignment = \App\Models\WorkflowAssignment::where('module', 'Weekly Return')->first();
        if (!$assignment || !is_array($assignment->assignments)) {
            return false;
        }

        $stepKey = match ($stepIndex) {
            0 => 'prepared_by_user_ids',
            1 => 'verified_by_user_ids',
            2 => 'checked_by_user_ids',
            3 => 'approved_by_user_ids',
            default => null,
        };

        if (!$stepKey || empty($assignment->assignments[$stepKey])) {
            return false;
        }

        $assignedUserIds = $assignment->assignments[$stepKey];
        if (!is_array($assignedUserIds)) {
            return false;
        }

        return in_array($userId, array_map('intval', $assignedUserIds), true);
    }
}
