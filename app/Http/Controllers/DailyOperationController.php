<?php

namespace App\Http\Controllers;

use App\Models\Cattle;
use App\Models\Estate;
use App\Models\CalvingRecord;
use App\Models\MortalityCase;
use App\Models\TransferDocument;
use App\Models\DailyOperationEntry;
use App\Models\DailyOperationDutyPerson;
use App\Models\WorkflowAssignment;
use App\Models\TaskNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class DailyOperationController extends Controller
{

    private const CATEGORIES = [
        'B/B' => ['code' => 'B/B', 'name' => 'Breeder Bull', 'name_my' => 'Pejantan'],
        'B/C' => ['code' => 'B/C', 'name' => 'Breeder Cow', 'name_my' => 'Induk'],
        'W/B' => ['code' => 'W/B', 'name' => 'Weaner Bull', 'name_my' => 'Anak Jantan'],
        'H' => ['code' => 'H', 'name' => 'Heifers', 'name_my' => 'Betina Dara'],
        'M/C' => ['code' => 'M/C', 'name' => 'Male Calf', 'name_my' => 'Anak Jantan'],
        'F/C' => ['code' => 'F/C', 'name' => 'Female Calf', 'name_my' => 'Anak Betina'],
    ];

    private const DAY_NAMES = [
        0 => ['en' => 'Sun', 'my' => 'Aha'],
        1 => ['en' => 'Mon', 'my' => 'Isn'],
        2 => ['en' => 'Tue', 'my' => 'Sel'],
        3 => ['en' => 'Wed', 'my' => 'Rab'],
        4 => ['en' => 'Thu', 'my' => 'Kha'],
        5 => ['en' => 'Fri', 'my' => 'Jum'],
        6 => ['en' => 'Sat', 'my' => 'Sab'],
    ];

    public function index(Request $request)
    {
        $selectedLadang = $request->get('ladang', '');
        $selectedMonth = $request->get('month', now()->month);
        $selectedYear = $request->get('year', now()->year);

        $estates = Estate::where('is_active', true)
            ->orWhereRaw("id IN (SELECT DISTINCT location_block FROM cattle WHERE location_block IS NOT NULL AND location_block != '')")
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($selectedLadang === '' && $estates->isNotEmpty()) {
            $selectedLadang = (string) $estates->first()->name;
        }

        $domlNumber = $this->generateDomlNumber($selectedLadang, $selectedMonth, $selectedYear);

        $monthStart = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $daysInMonth = [];
        $currentDay = $monthStart->copy();
        while ($currentDay->lte($monthEnd)) {
            $dayOfWeek = $currentDay->dayOfWeek;
            $daysInMonth[] = [
                'day' => $currentDay->day,
                'day_name' => self::DAY_NAMES[$dayOfWeek],
                'date' => $currentDay->toDateString(),
                'is_weekend' => in_array($dayOfWeek, [0, 6]),
            ];
            $currentDay->addDay();
        }

        $herdsToProcess = [$selectedLadang];

        $dailyData = $this->calculateDailyData($herdsToProcess, $monthStart, $monthEnd);
        $dailyData = $this->applyManualEntries($dailyData, $selectedLadang, (int) $selectedMonth, (int) $selectedYear, $monthStart->daysInMonth);

        return Inertia::render('Cattle/DailyOperation', [
            'estates' => $estates,
            'selectedLadang' => $selectedLadang,
            'selectedMonth' => (int) $selectedMonth,
            'selectedYear' => (int) $selectedYear,
            'selectedWeek' => $request->get('week', '1'),
            'domlNumber' => $domlNumber,
            'daysInMonth' => $daysInMonth,
            'categories' => self::CATEGORIES,
            'dailyData' => $dailyData,
            'workflowDocuments' => $this->getWorkflowDocuments($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', '1')),
            'workflowCurrentStep' => $this->getWorkflowCurrentStep($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', '1')),
            'workflowIsCompleted' => $this->isWorkflowCompleted($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', '1')),
            'workflowCompletedAt' => $this->getWorkflowCompletedAt($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', '1')),
            'domlWorkflowAssignments' => $this->getDomlWorkflowAssignments(),
            'dutyPersonNames' => $this->getDutyPersonNames($selectedLadang, (int) $selectedMonth, (int) $selectedYear, $request->get('week', '1')),
        ]);
    }

    private function generateDomlNumber(?string $ladang, int $month, int $year): string
    {
        if (empty($ladang)) {
            return 'DOML-' . str_pad($month, 2, '0', STR_PAD_LEFT) . $year;
        }

        $estate = Estate::where('name', $ladang)->first();
        $baseNum = $estate ? $estate->id : 1;

        return $baseNum . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . $year;
    }

    private function calculateDailyData(array $herds, Carbon $monthStart, Carbon $monthEnd): array
    {
        $result = [];

        foreach (self::CATEGORIES as $code => $cat) {
            $result[$code] = [
                'previous' => 0,
                'daily' => array_fill(0, $monthStart->daysInMonth, 0),
                'strayed' => 0,
                'notes' => [],
            ];
        }

        $result['total'] = [
            'previous' => 0,
            'daily' => array_fill(0, $monthStart->daysInMonth, 0),
            'strayed' => 0,
            'notes' => [],
        ];

        foreach ($herds as $herdName) {
            $cattleInHerd = Cattle::where('location_block', $herdName)->get();

            foreach ($cattleInHerd as $cattle) {
                $code = $this->getCategoryCode($cattle->category);
                if (!$code) continue;

                $createdMonth = $cattle->created_at
                    ? Carbon::parse($cattle->created_at)->month
                    : 0;

                if ($createdMonth < $monthStart->month ||
                    ($createdMonth == $monthStart->month && Carbon::parse($cattle->created_at)->day < 1)) {
                    $result[$code]['previous']++;
                }
            }

            $calvingInMonth = CalvingRecord::whereBetween('calving_date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString()
            ])->where('operating_unit', $herdName)->get();

            foreach ($calvingInMonth as $calving) {
                $day = Carbon::parse($calving->calving_date)->day - 1;
                $sex = strtoupper(trim($calving->sex));
                $code = in_array($sex, ['MC', 'MALE']) ? 'M/C' : 'F/C';

                if (isset($result[$code])) {
                    $result[$code]['daily'][$day]++;
                    $result[$code]['notes'][] = $day + 1 . '/' . $monthStart->month . ' LAHIR ' . $code . '=1';
                }
            }

            $mortalityInMonth = MortalityCase::whereBetween('death_date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString()
            ])->get();

            foreach ($mortalityInMonth as $mortality) {
                $herd = $mortality->location ?: $mortality->cattle?->location_block;
                if ($herd !== $herdName) continue;

                $day = Carbon::parse($mortality->death_date)->day - 1;
                $code = $this->getCategoryCode($mortality->cattle?->category ?: $mortality->category);

                if ($code && isset($result[$code])) {
                    $result[$code]['daily'][$day]--;
                    $result[$code]['notes'][] = $day + 1 . '/' . $monthStart->month . ' MATI ' . $code . '=1';
                }
            }

            $transfersOut = TransferDocument::whereBetween('date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString()
            ])->where('from_location', $herdName)->get();

            foreach ($transfersOut as $doc) {
                foreach ($doc->livestock as $livestock) {
                    $code = $this->getCategoryCode($livestock->category);
                    if ($code && isset($result[$code])) {
                        $day = Carbon::parse($doc->date)->day - 1;
                        $result[$code]['daily'][$day]--;
                    }
                }
            }

            $transfersIn = TransferDocument::whereBetween('date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString()
            ])->where('to_location', $herdName)->get();

            foreach ($transfersIn as $doc) {
                foreach ($doc->livestock as $livestock) {
                    $code = $this->getCategoryCode($livestock->category);
                    if ($code && isset($result[$code])) {
                        $day = Carbon::parse($doc->date)->day - 1;
                        $result[$code]['daily'][$day]++;
                    }
                }
            }
        }

        foreach (self::CATEGORIES as $code => $cat) {
            $runningTotal = $result[$code]['previous'];
            for ($i = 0; $i < $monthStart->daysInMonth; $i++) {
                $runningTotal += $result[$code]['daily'][$i];
                $result[$code]['daily'][$i] = $runningTotal;
            }
            $result['total']['previous'] += $result[$code]['previous'];
        }

        for ($i = 0; $i < $monthStart->daysInMonth; $i++) {
            $dayTotal = 0;
            foreach (self::CATEGORIES as $code => $cat) {
                $dayTotal += $result[$code]['daily'][$i];
            }
            $result['total']['daily'][$i] = $dayTotal;
        }

        return $result;
    }

    private function getCategoryCode(?string $category): ?string
    {
        if (!$category) return null;

        $value = strtoupper(trim($category));

        $compactCodeMap = [
            'BB' => 'B/B',
            'BC' => 'B/C',
            'WB' => 'W/B',
            'H' => 'H',
            'MC' => 'M/C',
            'FC' => 'F/C',
            'B/B' => 'B/B',
            'B/C' => 'B/C',
            'W/B' => 'W/B',
            'M/C' => 'M/C',
            'F/C' => 'F/C',
        ];

        if (isset($compactCodeMap[$value])) {
            return $compactCodeMap[$value];
        }

        if (str_contains($value, 'BREEDER') && str_contains($value, 'BULL')) return 'B/B';
        if (str_contains($value, 'BREEDER') && str_contains($value, 'COW')) return 'B/C';
        if (str_contains($value, 'WEANER') && str_contains($value, 'BULL')) return 'W/B';
        if (str_contains($value, 'HEIFER')) return 'H';
        if (str_contains($value, 'MALE') && str_contains($value, 'CALF')) return 'M/C';
        if (str_contains($value, 'FEMALE') && str_contains($value, 'CALF')) return 'F/C';
        if (str_contains($value, 'BULL') && !str_contains($value, 'WEA')) return 'B/B';
        if (str_contains($value, 'COW')) return 'B/C';

        return null;
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('daily_operation_entries')) {
            return back()->withErrors([
                'doml' => 'DOML editable data table is not available yet. Please run migrations first.',
            ]);
        }

        $validated = $request->validate([
            'ladang' => ['nullable', 'string', 'max:255'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entries' => ['required', 'array'],
            'entries.*.category_code' => ['required', 'string', 'max:10'],
            'entries.*.daily_values' => ['required', 'array'],
            'entries.*.daily_values.*' => ['nullable', 'integer'],
            'entries.*.missing' => ['nullable', 'integer'],
            'entries.*.remark' => ['nullable', 'string'],
            'week' => ['nullable', 'string'],
            'duty_persons' => ['nullable', 'array'],
            'duty_persons.*' => ['nullable', 'string', 'max:255'],
        ]);

        $week = $this->normalizeWorkflowWeek($validated['week'] ?? 'all');

        if ($week !== null && $this->isWorkflowCompleted($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week)) {
            return back()->withErrors([
                'doml' => 'This DOML workflow is completed. Reopen it before making changes.',
            ]);
        }

        $existingEntries = DailyOperationEntry::where('month', (int) $validated['month'])
            ->where('year', (int) $validated['year'])
            ->where('estate_name', ($validated['ladang'] ?? '') ?: null)
            ->get()
            ->keyBy('category_code');

        $hasChanges = false;
        foreach ($validated['entries'] as $entry) {
            $existing = $existingEntries->get($entry['category_code']);
            $dailyValues = array_values($entry['daily_values']);
            $missing = (int) ($entry['missing'] ?? 0);
            $remark = $entry['remark'] ?? null;

            if (!$existing
                || array_values($existing->daily_values ?? []) !== $dailyValues
                || (int) $existing->missing !== $missing
                || (string) ($existing->remark ?? '') !== (string) ($remark ?? '')
            ) {
                $hasChanges = true;
            }

            DailyOperationEntry::updateOrCreate(
                [
                    'estate_name' => $validated['ladang'] ?: null,
                    'month' => (int) $validated['month'],
                    'year' => (int) $validated['year'],
                    'category_code' => $entry['category_code'],
                ],
                [
                    'daily_values' => $dailyValues,
                    'missing' => $missing,
                    'remark' => $remark,
                ]
            );
        }

        $dutyPersonChanged = $this->saveDutyPersonNames(
            $validated['ladang'] ?? '',
            (int) $validated['month'],
            (int) $validated['year'],
            $validated['week'] ?? 'all',
            $validated['duty_persons'] ?? []
        );

        if (($hasChanges || $dutyPersonChanged) && $week !== null) {
            $this->resetWorkflow($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week);
        }

        return back()->with('success', 'Daily operation data saved successfully.');
    }

    public function export(Request $request)
    {
        $selectedLadang = $request->get('ladang', '');
        $selectedMonth = $request->get('month', now()->month);
        $selectedYear = $request->get('year', now()->year);
        $selectedWeek = $request->get('week', '1');

        $monthStart = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $estates = Estate::where('is_active', true)
            ->orWhereRaw("id IN (SELECT DISTINCT location_block FROM cattle WHERE location_block IS NOT NULL AND location_block != '')")
            ->orderBy('name')
            ->get(['id', 'name']);

        $domlNumber = $this->generateDomlNumber($selectedLadang, $selectedMonth, $selectedYear);

        $allDaysInMonth = [];
        $currentDay = $monthStart->copy();
        while ($currentDay->lte($monthEnd)) {
            $dayOfWeek = $currentDay->dayOfWeek;
            $dayNames = ['Sab', 'Aha', 'Isn', 'Sel', 'Rab', 'Kha', 'Jum'];
            $allDaysInMonth[] = [
                'day' => $currentDay->day,
                'day_name' => $dayNames[$dayOfWeek],
            ];
            $currentDay->addDay();
        }

        $weekStartDay = 0;
        $weekEndDay = $monthStart->daysInMonth - 1;

        if ($selectedWeek !== 'all' && is_numeric($selectedWeek)) {
            $week = (int) $selectedWeek;
            $weekStartDay = ($week - 1) * 7;
            $weekEndDay = min($weekStartDay + 6, $monthStart->daysInMonth - 1);
        }

        $daysInMonth = array_slice($allDaysInMonth, $weekStartDay, $weekEndDay - $weekStartDay + 1);

        $herdsToProcess = $selectedLadang !== ''
            ? [$selectedLadang]
            : $estates->pluck('name')->toArray();

        $dailyData = $this->calculateDailyData($herdsToProcess, $monthStart, $monthEnd);
        $dailyData = $this->applyManualEntries($dailyData, $selectedLadang, (int) $selectedMonth, (int) $selectedYear, $monthStart->daysInMonth);

        $monthName = Carbon::create($selectedYear, $selectedMonth, 1)->format('F');

        $categories = self::CATEGORIES;
        $categoryRows = [];

        foreach ($categories as $code => $cat) {
            $data = $dailyData[$code] ?? ['previous' => 0, 'daily' => [], 'strayed' => 0, 'notes' => []];
            $filteredDaily = [];
            for ($i = $weekStartDay; $i <= $weekEndDay; $i++) {
                $filteredDaily[] = $data['daily'][$i] ?? 0;
            }
            $categoryRows[] = [
                'name' => $cat['name'],
                'name_my' => $cat['name_my'],
                'previous' => $data['previous'] ?? 0,
                'daily' => $filteredDaily,
                'strayed' => $data['strayed'] ?? 0,
                'notes' => implode(', ', $data['notes'] ?? []),
            ];
        }

        if (isset($dailyData['total'])) {
            $total = $dailyData['total'];
            $filteredDaily = [];
            for ($i = $weekStartDay; $i <= $weekEndDay; $i++) {
                $filteredDaily[] = $total['daily'][$i] ?? 0;
            }
            $categoryRows[] = [
                'name' => 'Total',
                'name_my' => 'Jumlah',
                'previous' => $total['previous'] ?? 0,
                'daily' => $filteredDaily,
                'strayed' => $total['strayed'] ?? 0,
                'notes' => '',
                'is_total' => true,
            ];
        }

        $categoryRows[] = [
            'name' => 'Strayed Cattle',
            'name_my' => 'Lembu Terlepas',
            'previous' => 0,
            'daily' => array_fill(0, count($daysInMonth), 0),
            'strayed' => 0,
            'notes' => '',
        ];

        $weekLabel = $selectedWeek === 'all' ? 'All Weeks' : 'Week ' . $selectedWeek;
        $dutyPersonNames = $this->resolveDutyPersonNames(
            $selectedLadang,
            (int) $selectedMonth,
            (int) $selectedYear,
            (string) $selectedWeek,
            $request
        );

        $html = view('cattle.daily-operation-export', [
            'domlNumber' => $domlNumber,
            'ladang' => $selectedLadang,
            'monthName' => $monthName,
            'year' => $selectedYear,
            'weekLabel' => $weekLabel,
            'daysInMonth' => $daysInMonth,
            'categoryRows' => $categoryRows,
            'dutyPersonNames' => $dutyPersonNames,
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $weekSuffix = $selectedWeek === 'all' ? '' : '_W' . $selectedWeek;
        $filename = 'DOML_' . $domlNumber . '_' . $selectedYear . '_' . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . $weekSuffix . '.pdf';

        return $pdf->download($filename);
    }

    public function uploadWorkflowDocument(Request $request)
    {
        $validated = $request->validate([
            'ladang' => ['nullable', 'string', 'max:255'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'step_index' => ['required', 'integer', 'between:0,3'],
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'signed_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'week' => ['required', 'string'],
        ]);

        $week = $this->normalizeWorkflowWeek($validated['week']);
        if ($week === null) {
            return back()->withErrors(['error' => 'Select a specific week (1–4) before uploading workflow documents.']);
        }

        $user = Auth::user();
        $stepIndex = (int) $validated['step_index'];
        if (!$this->canUserActDomlStep($user, $stepIndex)) {
            return back()->withErrors(['error' => 'You do not have permission to upload for this step']);
        }

        $dir = $this->getWorkflowDirectory($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week);
        $stepNumber = $stepIndex + 1;
        $filename = 'step_' . $stepNumber . '.pdf';

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week);
        if (!empty($state['is_completed'])) {
            return back()->withErrors(['error' => 'This DOML workflow is completed. Reopen it before uploading documents.']);
        }

        $docs = $state['documents'];
        $currentStep = (int) ($state['endorsement_step'] ?? 0);

        $nextStepKey = strval($stepIndex + 1);
        if ($stepIndex === 3) {
            $canUpload = ($stepIndex <= $currentStep);
        } else {
            $canUpload = ($stepIndex === $currentStep) || ($stepIndex < $currentStep && !isset($docs[$nextStepKey]));
        }

        if (!$canUpload) {
            return back()->withErrors(['error' => 'Cannot upload at this step']);
        }

        Storage::disk('public')->putFileAs($dir, $validated['signed_document'], $filename);

        $docs[strval($stepIndex)] = [
            'name' => $validated['name'],
            'date' => $validated['date'],
            'file_path' => $dir . '/' . $filename,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $newStep = $currentStep;
        if ($stepIndex === $currentStep && $stepIndex < 3) {
            $newStep = $stepIndex + 1;
        }
        if ($stepIndex === 3) {
            $newStep = 4;
        }

        $this->saveWorkflowState(
            $validated['ladang'] ?? '',
            (int) $validated['month'],
            (int) $validated['year'],
            (string) $week,
            [
                'endorsement_step' => $newStep,
                'documents' => $docs,
            ]
        );

        if ($newStep > $currentStep && $newStep <= 3) {
            $nextStepIndex = $newStep;
            $nextStepUserIds = $this->getDomlAssignedUserIdsForStep($nextStepIndex);

            foreach ($nextStepUserIds as $notifyUserId) {
                if ((int) $notifyUserId === (int) $user->id) {
                    continue;
                }

                TaskNotification::create([
                    'user_id' => (int) $notifyUserId,
                    'title' => 'DOML Workflow Step Ready',
                    'message' => sprintf(
                        'DOML workflow for "%s" is now waiting for %s (%02d/%d).',
                        $this->getDomlStepAssigneeLabel($nextStepIndex),
                        $validated['ladang'] ?: 'All Estates',
                        (int) $validated['month'],
                        (int) $validated['year']
                    ),
                    'type' => 'workflow_step_completed',
                    'is_read' => false,
                    'created_by' => (int) $user->id,
                ]);
            }
        }

        if ($this->allWorkflowStepsUploaded(['documents' => $docs])) {
            $this->notifyAdminsDomlWorkflowReadyForCompletion(
                $validated['ladang'] ?? '',
                (int) $validated['month'],
                (int) $validated['year'],
                (string) $week,
                (int) $user->id
            );
        }

        return back()->with('success', 'Workflow document uploaded successfully.');
    }

    public function downloadWorkflowDocument(Request $request, int $stepIndex)
    {
        if ($stepIndex < 0 || $stepIndex > 3) {
            abort(404);
        }

        $ladang = (string) $request->get('ladang', '');
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);
        $week = (string) $request->get('week', '1');

        $user = Auth::user();
        $userStepIndexes = $this->getUserDomlStepIndexes($user);
        $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin';
        if (empty($userStepIndexes) && !$isAdmin) {
            abort(403, 'You do not have permission to view this document');
        }

        if (!$isAdmin) {
            $canView = false;
            foreach ($userStepIndexes as $userStepIndex) {
                if ($stepIndex === $userStepIndex || $stepIndex === $userStepIndex - 1) {
                    $canView = true;
                    break;
                }
            }
            if (!$canView) {
                abort(403, 'You can only view your own document or the previous person\'s document');
            }
        }

        $state = $this->getWorkflowState($ladang, $month, $year, $week);
        $stepKey = strval($stepIndex);
        $document = $state['documents'][$stepKey] ?? null;

        $dir = $this->getWorkflowDirectory($ladang, $month, $year, $week);
        $filePath = $document['file_path'] ?? ($dir . '/step_' . ($stepIndex + 1) . '.pdf');

        if (!Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        $downloadName = 'doml_workflow_step_' . ($stepIndex + 1) . '_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';
        return Storage::disk('public')->download($filePath, $downloadName);
    }

    public function markWorkflowCompleted(Request $request)
    {
        if (strtolower((string) (Auth::user()->role ?? '')) !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can mark this DOML workflow as completed.']);
        }

        $validated = $request->validate([
            'ladang' => ['nullable', 'string', 'max:255'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'week' => ['required', 'string'],
        ]);

        $week = $this->normalizeWorkflowWeek($validated['week']);
        if ($week === null) {
            return back()->withErrors(['error' => 'Select a specific week (1–4) before completing the workflow.']);
        }

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week);
        if (!$this->allWorkflowStepsUploaded($state)) {
            return back()->withErrors(['error' => 'All workflow documents must be uploaded before marking as completed.']);
        }

        $this->saveWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week, array_merge($state, [
            'endorsement_step' => 4,
            'is_completed' => true,
            'completed_at' => now()->toDateTimeString(),
            'completed_by' => Auth::id(),
        ]));

        return back()->with('success', 'DOML workflow marked as completed successfully.');
    }

    public function reopenWorkflow(Request $request)
    {
        if (strtolower((string) (Auth::user()->role ?? '')) !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can reopen this DOML workflow.']);
        }

        $validated = $request->validate([
            'ladang' => ['nullable', 'string', 'max:255'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'week' => ['required', 'string'],
        ]);

        $week = $this->normalizeWorkflowWeek($validated['week']);
        if ($week === null) {
            return back()->withErrors(['error' => 'Select a specific week (1–4) before reopening the workflow.']);
        }

        $state = $this->getWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week);
        $state['is_completed'] = false;
        $state['completed_at'] = null;
        $state['completed_by'] = null;

        $this->saveWorkflowState($validated['ladang'] ?? '', (int) $validated['month'], (int) $validated['year'], (string) $week, $state);

        return back()->with('success', 'DOML workflow reopened successfully.');
    }

    private function applyManualEntries(array $dailyData, ?string $estateName, int $month, int $year, int $daysInMonth): array
    {
        if (!Schema::hasTable('daily_operation_entries')) {
            return $dailyData;
        }

        try {
            $entries = DailyOperationEntry::where('month', $month)
                ->where('year', $year)
                ->where('estate_name', $estateName ?: null)
                ->get()
                ->keyBy('category_code');
        } catch (QueryException $e) {
            return $dailyData;
        }

        foreach ($entries as $code => $entry) {
            if (!isset($dailyData[$code])) {
                continue;
            }

            $manualValues = is_array($entry->daily_values) ? $entry->daily_values : [];
            $manualValues = array_slice(array_pad($manualValues, $daysInMonth, 0), 0, $daysInMonth);
            $dailyData[$code]['daily'] = array_map('intval', $manualValues);
            $dailyData[$code]['strayed'] = (int) $entry->missing;
            $dailyData[$code]['notes'] = $entry->remark ? [$entry->remark] : [];
        }

        if (isset($dailyData['total'])) {
            $dailyData['total']['previous'] = 0;
            $dailyData['total']['daily'] = array_fill(0, $daysInMonth, 0);
            $dailyData['total']['strayed'] = 0;

            foreach (self::CATEGORIES as $code => $cat) {
                $dailyData['total']['previous'] += (int) ($dailyData[$code]['previous'] ?? 0);
                $dailyData['total']['strayed'] += (int) ($dailyData[$code]['strayed'] ?? 0);
                for ($i = 0; $i < $daysInMonth; $i++) {
                    $dailyData['total']['daily'][$i] += (int) ($dailyData[$code]['daily'][$i] ?? 0);
                }
            }
        }

        return $dailyData;
    }

    private function getWorkflowDocuments(?string $estateName, int $month, int $year, string $week = 'all'): array
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);
        return $state['documents'];
    }

    private function getWorkflowCurrentStep(?string $estateName, int $month, int $year, string $week = 'all'): int
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);
        return (int) ($state['endorsement_step'] ?? 0);
    }

    private function getWorkflowState(?string $estateName, int $month, int $year, string $week = 'all'): array
    {
        if ($this->normalizeWorkflowWeek($week) === null) {
            return $this->emptyWorkflowState();
        }

        $dir = $this->getWorkflowDirectory($estateName, $month, $year, $week);
        $statePath = $dir . '/workflow.json';

        if (Storage::disk('public')->exists($statePath)) {
            $json = Storage::disk('public')->get($statePath);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return [
                    'endorsement_step' => (int) ($decoded['endorsement_step'] ?? 0),
                    'documents' => is_array($decoded['documents'] ?? null) ? $decoded['documents'] : [],
                    'is_completed' => (bool) ($decoded['is_completed'] ?? false),
                    'completed_at' => $decoded['completed_at'] ?? null,
                    'completed_by' => $decoded['completed_by'] ?? null,
                ];
            }
        }

        $docs = [];

        for ($i = 0; $i < 4; $i++) {
            $path = $dir . '/step_' . ($i + 1) . '.pdf';
            if (Storage::disk('public')->exists($path)) {
                $docs[$i] = [
                    'filename' => basename($path),
                    'name' => basename($path),
                    'date' => date('Y-m-d H:i', Storage::disk('public')->lastModified($path)),
                    'file_path' => $path,
                ];
            }
        }

        $currentStep = 0;
        for ($i = 0; $i < 4; $i++) {
            if (!isset($docs[$i])) {
                $currentStep = $i;
                break;
            }
            $currentStep = 4;
        }

        return [
            'endorsement_step' => $currentStep,
            'documents' => $docs,
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    private function emptyWorkflowState(): array
    {
        return [
            'endorsement_step' => 0,
            'documents' => [],
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    private function saveWorkflowState(?string $estateName, int $month, int $year, string $week, array $state): void
    {
        $dir = $this->getWorkflowDirectory($estateName, $month, $year, $week);
        $statePath = $dir . '/workflow.json';

        Storage::disk('public')->put($statePath, json_encode([
            'endorsement_step' => (int) ($state['endorsement_step'] ?? 0),
            'documents' => $state['documents'] ?? [],
            'is_completed' => (bool) ($state['is_completed'] ?? false),
            'completed_at' => $state['completed_at'] ?? null,
            'completed_by' => $state['completed_by'] ?? null,
        ], JSON_PRETTY_PRINT));
    }

    private function isWorkflowCompleted(?string $estateName, int $month, int $year, string $week = 'all'): bool
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);
        return (bool) ($state['is_completed'] ?? false);
    }

    private function getWorkflowCompletedAt(?string $estateName, int $month, int $year, string $week = 'all'): ?string
    {
        $state = $this->getWorkflowState($estateName, $month, $year, $week);
        return $state['completed_at'] ?? null;
    }

    private function allWorkflowStepsUploaded(array $state): bool
    {
        $docs = $state['documents'] ?? [];
        for ($i = 0; $i < 4; $i++) {
            if (!isset($docs[$i]) && !isset($docs[(string) $i])) {
                return false;
            }
        }

        return true;
    }

    private function resetWorkflow(?string $estateName, int $month, int $year, string $week = 'all'): void
    {
        $this->saveWorkflowState($estateName, $month, $year, $week, [
            'endorsement_step' => 0,
            'documents' => [],
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    private function notifyAdminsDomlWorkflowReadyForCompletion(?string $estateName, int $month, int $year, string $week, int $createdBy): void
    {
        $adminUsers = User::whereIn('role', ['admin', 'Admin'])->get(['id']);
        $estateLabel = $estateName ?: 'All Estates';
        $message = "All DOML workflow steps for {$estateLabel} ({$month}/{$year} Week {$week}) have been uploaded. Please review and mark as complete.";

        foreach ($adminUsers as $adminUser) {
            $alreadyNotified = TaskNotification::where('user_id', (int) $adminUser->id)
                ->where('type', 'doml_workflow_ready_for_completion')
                ->where('message', $message)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            TaskNotification::create([
                'user_id' => (int) $adminUser->id,
                'title' => 'DOML Workflow Ready for Completion',
                'message' => $message,
                'type' => 'doml_workflow_ready_for_completion',
                'is_read' => false,
                'created_by' => $createdBy,
            ]);
        }
    }

    private function getWorkflowDirectory(?string $estateName, int $month, int $year, string $week = 'all'): string
    {
        $estate = trim((string) $estateName);
        $estate = $estate === '' ? 'all-estates' : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $estate));
        $estate = trim($estate, '-');
        if ($estate === '') {
            $estate = 'all-estates';
        }

        $base = 'doml-workflow/' . $estate . '/' . $year . '/' . str_pad($month, 2, '0', STR_PAD_LEFT);
        $normalizedWeek = $this->normalizeWorkflowWeek($week);
        if ($normalizedWeek !== null) {
            $base .= '/week-' . $normalizedWeek;
        }

        return $base;
    }

    private function normalizeWorkflowWeek(?string $week): ?string
    {
        $value = trim((string) $week);
        if ($value === '' || $value === 'all') {
            return null;
        }

        if (!in_array($value, ['1', '2', '3', '4'], true)) {
            return null;
        }

        return $value;
    }

    private function resolveDutyPersonNames(?string $estateName, int $month, int $year, string $week, Request $request): array
    {
        $names = $this->getDutyPersonNames($estateName, $month, $year, $week);

        for ($i = 1; $i <= 4; $i++) {
            $provided = trim((string) $request->get("duty_person_{$i}", ''));
            if ($provided !== '') {
                $names[$i - 1] = $provided;
            }
        }

        return $names;
    }

    private function getDutyPersonNames(?string $estateName, int $month, int $year, string $week = 'all'): array
    {
        $saved = $this->getSavedDutyPersonNames($estateName, $month, $year, $week);
        if ($saved !== null) {
            return $saved;
        }

        $names = ['', '', '', ''];
        $normalizedWeek = $this->normalizeWorkflowWeek($week);

        if ($normalizedWeek !== null) {
            $state = $this->getWorkflowState($estateName, $month, $year, $normalizedWeek);
            $docs = $state['documents'] ?? [];

            for ($i = 0; $i < 4; $i++) {
                $doc = $docs[$i] ?? $docs[(string) $i] ?? null;
                if (is_array($doc) && !empty($doc['name'])) {
                    $names[$i] = trim((string) $doc['name']);
                }
            }
        }

        $assignments = $this->getDomlWorkflowAssignments();
        $keys = [
            0 => 'pengembala_user_ids',
            1 => 'pembantu_kanan_ternakan_user_ids',
            2 => 'pembantu_kanan_keselamatan_user_ids',
            3 => 'wakil_ladang_user_ids',
        ];

        for ($i = 0; $i < 4; $i++) {
            if ($names[$i] !== '') {
                continue;
            }

            $ids = is_array($assignments[$keys[$i]] ?? null) ? $assignments[$keys[$i]] : [];
            if (empty($ids)) {
                continue;
            }

            $user = User::query()->find((int) $ids[0]);
            if ($user && trim((string) $user->name) !== '') {
                $names[$i] = trim((string) $user->name);
            }
        }

        return $names;
    }

    private function getSavedDutyPersonNames(?string $estateName, int $month, int $year, string $week = 'all'): ?array
    {
        if (!Schema::hasTable('daily_operation_duty_persons')) {
            return null;
        }

        $record = DailyOperationDutyPerson::query()
            ->where('month', $month)
            ->where('year', $year)
            ->where('estate_name', $estateName ?: null)
            ->where('week', $this->normalizeDutyPersonWeek($week))
            ->first();

        if (!$record || !is_array($record->names)) {
            return null;
        }

        return array_pad(array_map(
            fn ($name) => trim((string) $name),
            $record->names
        ), 4, '');
    }

    private function saveDutyPersonNames(?string $estateName, int $month, int $year, string $week, array $names): bool
    {
        if (!Schema::hasTable('daily_operation_duty_persons')) {
            return false;
        }

        $normalizedNames = array_map(
            fn ($name) => trim((string) $name),
            $names
        );
        $normalizedNames = array_pad($normalizedNames, 4, '');

        $record = DailyOperationDutyPerson::where([
            'estate_name' => $estateName ?: null,
            'month' => $month,
            'year' => $year,
            'week' => $this->normalizeDutyPersonWeek($week),
        ])->first();

        $existingNames = $record ? (is_array($record->names) ? $record->names : []) : [];
        $hasChanges = $existingNames !== $normalizedNames;

        DailyOperationDutyPerson::updateOrCreate(
            [
                'estate_name' => $estateName ?: null,
                'month' => $month,
                'year' => $year,
                'week' => $this->normalizeDutyPersonWeek($week),
            ],
            [
                'names' => $normalizedNames,
            ]
        );

        return $hasChanges;
    }

    private function normalizeDutyPersonWeek(?string $week): string
    {
        $normalized = $this->normalizeWorkflowWeek($week);

        return $normalized ?? 'all';
    }

    private function getDomlWorkflowAssignments(): array
    {
        if (!Schema::hasTable('workflow_assignments')) {
            return [];
        }

        $assignment = WorkflowAssignment::query()->where('module', 'Daily Operation DOML')->first();
        if (!$assignment || !is_array($assignment->assignments)) {
            return [];
        }

        return $assignment->assignments;
    }

    private function getDomlAssignmentKeyByStep(int $stepIndex): ?string
    {
        return [
            0 => 'pengembala_user_ids',
            1 => 'pembantu_kanan_ternakan_user_ids',
            2 => 'pembantu_kanan_keselamatan_user_ids',
            3 => 'wakil_ladang_user_ids',
        ][$stepIndex] ?? null;
    }

    private function getDomlStepAssigneeLabel(int $stepIndex): string
    {
        return [
            0 => 'Pengembala',
            1 => 'Pembantu Kanan Ternakan',
            2 => 'Pembantu Kanan Keselamatan',
            3 => 'Wakil Ladang',
        ][$stepIndex] ?? 'Next Step';
    }

    private function getDomlAssignedUserIdsForStep(int $stepIndex): array
    {
        $assignments = $this->getDomlWorkflowAssignments();
        $key = $this->getDomlAssignmentKeyByStep($stepIndex);
        if (!$key) {
            return [];
        }

        $ids = is_array($assignments[$key] ?? null) ? $assignments[$key] : [];
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function canUserActDomlStep($user, int $stepIndex): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $ids = $this->getDomlAssignedUserIdsForStep($stepIndex);

        return in_array((int) $user->id, $ids, true);
    }

    private function getUserDomlStepIndexes($user): array
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return [0, 1, 2, 3];
        }

        $indexes = [];
        for ($i = 0; $i <= 3; $i++) {
            if ($this->canUserActDomlStep($user, $i)) {
                $indexes[] = $i;
            }
        }

        return $indexes;
    }
}
