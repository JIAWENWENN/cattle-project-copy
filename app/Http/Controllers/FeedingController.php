<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\FeedingRecord;
use App\Models\FeedingOption;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeedingController extends Controller
{
    private const ALLOWED_TRIP_OPTIONS = [
        'FL1B', 'FL1C', 'FL1D', 'FL1F', 'FL1G', 'FL1H',
        'FL2A', 'FL2B', 'FL2C', 'FL2D', 'FL2E', 'FL2F', 'FL2G', 'FL2H',
        'BL1A', 'BL1B', 'BL1C', 'BL1D',
        'BL2A', 'BL2B', 'BL2C', 'BL2D',
        'BL3A', 'BL3B', 'BL3C', 'BL3D',
    ];

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $trip = trim($request->input('trip'));
        $feedType = trim($request->input('feed_type'));
        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }

        $applyFilters = function ($query) use ($startDate, $endDate, $trip, $feedType) {
            if ($startDate) {
                $query->where('date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('date', '<=', $endDate);
            }
            if ($trip && $trip !== '') {
                $query->where('trip_no', $trip);
            }
            if ($feedType && $feedType !== '') {
                $query->where('feed_type', $feedType);
            }

            return $query;
        };

        $recordsPaginator = $applyFilters(FeedingRecord::query()->select([
            'id',
            'date',
            'trip_no',
            'cattle_count',
            'feed_type',
            'planned',
            'actual_usage',
            'receive',
            'carry_forward',
            'balance',
            'remarks',
        ]))
            ->orderBy('date', 'desc')
            ->orderBy('trip_no', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        // DAILY MATRIX
        $dailyRows = $applyFilters(FeedingRecord::query()->selectRaw('
            date,
            feed_type,
            MAX(carry_forward) as carry_forward,
            SUM(receive) as receive,
            SUM(actual_usage) as actual_usage,
            SUM(planned) as planned
        '))
            ->groupBy('date', 'feed_type')
            ->orderBy('date', 'asc')
            ->get();

        $dailyMatrix = $dailyRows->groupBy('date')->map(function ($dayRows) {
            return $dayRows->keyBy('feed_type')->map(function ($row) {
                $carryForward = (float) $row->carry_forward;
                $receive = (float) $row->receive;
                $actualUsage = (float) $row->actual_usage;

                return [
                    'carry_forward' => $carryForward,
                    'receive' => $receive,
                    'actual_usage' => $actualUsage,
                    'balance' => $carryForward + $receive - $actualUsage,
                    'planned' => (float) $row->planned,
                ];
            });
        });

        // TRIP VIEW: each trip aggregated
        $tripRows = $applyFilters(FeedingRecord::query()->selectRaw("
            trip_no,
            MAX(cattle_count) as cattle_count,
            SUM(CASE WHEN feed_type = 'Napier' THEN actual_usage ELSE 0 END) as napier_total,
            SUM(CASE WHEN feed_type = 'Silage' THEN actual_usage ELSE 0 END) as silage_total,
            SUM(CASE WHEN feed_type = 'Baller' THEN actual_usage ELSE 0 END) as baller_total,
            SUM(receive) as receive_total,
            SUM(planned) as planning_total,
            SUM(actual_usage) as actual_total,
            MAX(remarks) as remarks
        "))
            ->groupBy('trip_no')
            ->orderBy('trip_no', 'asc')
            ->get()
            ->map(function ($row) {
                $cattleCount = (int) $row->cattle_count;
                $napierTotal = (float) $row->napier_total;
                $silageTotal = (float) $row->silage_total;
                $ballerTotal = (float) $row->baller_total;

                return [
                'trip_no' => $row->trip_no,
                'cattle_count' => $cattleCount,
                'napier_total' => $napierTotal,
                'napier_kghead' => $cattleCount > 0 ? round($napierTotal / $cattleCount, 1) : 0,
                'silage_total' => $silageTotal,
                'silage_kghead' => $cattleCount > 0 ? round($silageTotal / $cattleCount, 1) : 0,
                'baller_total' => $ballerTotal,
                'baller_kghead' => $cattleCount > 0 ? round($ballerTotal / $cattleCount, 1) : 0,
                'receive_total' => (float) $row->receive_total,
                'planning_total' => (float) $row->planning_total,
                'actual_total' => (float) $row->actual_total,
                'remarks' => $row->remarks ?? '',
                ];
            })
            ->values()
            ->toArray();

        // Group by first 3 chars of trip (e.g. BL2, FL1)
        $tripGroups = [];
        foreach ($tripRows as $row) {
            $prefix = substr($row['trip_no'], 0, 3);
            $tripGroups[$prefix][] = $row;
        }

        $availableTrips = $this->getOptionValues('trip_no', FeedingRecord::distinct()->pluck('trip_no')->toArray());
        $feedTypes = $this->getOptionValues('feed_type', FeedingRecord::distinct()->pluck('feed_type')->toArray());

        $summary = $applyFilters(FeedingRecord::query()->selectRaw('
            feed_type,
            COUNT(*) as record_count,
            SUM(actual_usage) as total_usage,
            SUM(receive) as total_receive,
            AVG(carry_forward) as avg_carry_forward,
            SUM(balance) as total_balance,
            SUM(planned) as total_planned
        '))
            ->groupBy('feed_type')
            ->get()
            ->map(function ($group) {
            return [
                'feed_type' => $group->feed_type,
                'record_count' => (int) $group->record_count,
                'total_usage' => (float) $group->total_usage,
                'total_receive' => (float) $group->total_receive,
                'avg_carry_forward' => (float) $group->avg_carry_forward,
                'total_balance' => (float) $group->total_balance,
                'total_planned' => (float) $group->total_planned,
            ];
            })
            ->values();

        $tripDateDefaults = $applyFilters(FeedingRecord::query()->selectRaw('
            date,
            trip_no,
            MAX(cattle_count) as cattle_count,
            MAX(COALESCE(remarks, "")) as remarks
        '))
            ->groupBy('date', 'trip_no')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    $row->date . '|' . $row->trip_no => [
                        'cattle_count' => (int) $row->cattle_count,
                        'remarks' => $row->remarks,
                    ],
                ];
            });

        $isSingleDate = $startDate && $endDate && $startDate === $endDate;
        $viewMode = $isSingleDate ? 'trip' : 'daily';

        return Inertia::render('Feeding/Index', [
            'records' => $recordsPaginator->items(),
            'pagination' => [
                'current_page' => $recordsPaginator->currentPage(),
                'last_page' => $recordsPaginator->lastPage(),
                'per_page' => $recordsPaginator->perPage(),
                'total' => $recordsPaginator->total(),
                'from' => $recordsPaginator->firstItem(),
                'to' => $recordsPaginator->lastItem(),
            ],
            'dailyMatrix' => $dailyMatrix,
            'tripRows' => $tripRows,
            'tripGroups' => $tripGroups,
            'trips' => $availableTrips,
            'feedTypes' => $feedTypes,
            'feedingOptions' => [
                'trip_no' => FeedingOption::where('field_type', 'trip_no')->orderBy('sort_order')->orderBy('value')->get(['id', 'value']),
                'feed_type' => FeedingOption::where('field_type', 'feed_type')->orderBy('sort_order')->orderBy('value')->get(['id', 'value']),
            ],
            'summary' => $summary,
            'tripDateDefaults' => $tripDateDefaults,
            'viewMode' => $viewMode,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'trip' => $trip,
                'feed_type' => $feedType,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'trip_no' => ['required', 'string', 'max:20', Rule::in(self::ALLOWED_TRIP_OPTIONS)],
            'cattle_count' => 'required|integer|min:0',
            'feed_type' => 'required|string|max:50',
            'planned' => 'nullable|numeric|min:0',
            'actual_usage' => 'nullable|numeric|min:0',
            'receive' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $this->ensureUniqueRecord($validated);

        FeedingRecord::create($validated);

        // Sync cattle_count across all records with same date + trip_no
        FeedingRecord::where('date', $validated['date'])
            ->where('trip_no', $validated['trip_no'])
            ->update(['cattle_count' => $validated['cattle_count']]);

        $this->ensureOption('trip_no', $validated['trip_no']);
        $this->ensureOption('feed_type', $validated['feed_type']);
        $this->recalculateFeedTypeBalances($validated['feed_type']);

        return redirect()->back()->with('success', 'Feeding record added successfully.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'trip_no' => ['required', 'string', 'max:20', Rule::in(self::ALLOWED_TRIP_OPTIONS)],
            'cattle_count' => 'required|integer|min:0',
            'feed_type' => 'required|string|max:50',
            'planned' => 'nullable|numeric|min:0',
            'actual_usage' => 'nullable|numeric|min:0',
            'receive' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $record = FeedingRecord::findOrFail($id);
        $oldFeedType = $record->feed_type;

        $this->ensureUniqueRecord($validated, $record->id);

        $record->update($validated);

        // Sync cattle_count across all records with same date + trip_no
        FeedingRecord::where('date', $validated['date'])
            ->where('trip_no', $validated['trip_no'])
            ->update(['cattle_count' => $validated['cattle_count']]);

        $this->ensureOption('trip_no', $validated['trip_no']);
        $this->ensureOption('feed_type', $validated['feed_type']);
        $this->recalculateFeedTypeBalances($oldFeedType);
        if ($oldFeedType !== $validated['feed_type']) {
            $this->recalculateFeedTypeBalances($validated['feed_type']);
        }

        return redirect()->back()->with('success', 'Feeding record updated successfully.');
    }

    public function destroy($id)
    {
        $record = FeedingRecord::findOrFail($id);
        $feedType = $record->feed_type;
        $record->delete();
        $this->recalculateFeedTypeBalances($feedType);

        return redirect()->back()->with('success', 'Feeding record deleted successfully.');
    }

public function storeOption(Request $request)
    {
        $validated = $request->validate([
            'field_type' => 'required|in:trip_no,feed_type',
            'value' => 'required|string|max:50',
        ]);

        if ($validated['field_type'] === 'trip_no') {
            return redirect()->back()->withErrors([
                'value' => 'Trip options are fixed and cannot be created manually.',
            ]);
        }

        $option = $this->ensureOption($validated['field_type'], $this->normalizeOptionValue($validated['value']));

        return redirect()->back()->with('success', "{$option->value} added.");
    }

    public function updateOption(Request $request, FeedingOption $option)
    {
        $validated = $request->validate([
            'field_type' => 'required|in:trip_no,feed_type',
            'value' => 'required|string|max:50',
        ]);

        $oldFieldType = $option->field_type;
        $oldValue = $option->value;
        $newValue = $this->normalizeOptionValue($validated['value']);

        if ($validated['field_type'] !== $oldFieldType) {
            return redirect()->back()->withErrors(['field_type' => 'Option type cannot be changed.']);
        }

        if ($oldFieldType === 'trip_no') {
            return redirect()->back()->withErrors([
                'value' => 'Trip options are fixed and cannot be edited.',
            ]);
        }

        $duplicateExists = FeedingOption::where('field_type', $oldFieldType)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($newValue)])
            ->where('id', '!=', $option->id)
            ->exists();

        if ($duplicateExists) {
            return redirect()->back()->withErrors(['value' => 'That option already exists.']);
        }

        $option->update([
            'field_type' => $oldFieldType,
            'value' => $newValue,
            'sort_order' => $option->sort_order,
        ]);

        if ($oldFieldType === 'trip_no') {
            FeedingRecord::where('trip_no', $oldValue)->update(['trip_no' => $newValue]);
        } else {
            FeedingRecord::where('feed_type', $oldValue)->update(['feed_type' => $newValue]);
            $this->recalculateFeedTypeBalances($oldValue);
            $this->recalculateFeedTypeBalances($newValue);
        }

        return redirect()->back()->with('success', 'Option updated.');
    }

    public function destroyOption(FeedingOption $option)
    {
        $fieldType = $option->field_type;
        $value = $option->value;

        if ($fieldType === 'trip_no') {
            return redirect()->back()->withErrors([
                'value' => 'Trip options are fixed and cannot be deleted.',
            ]);
        }

        $option->delete();

        if ($fieldType === 'feed_type') {
            $this->recalculateFeedTypeBalances($value);
        }

        return redirect()->back()->with('success', 'Option deleted.');
    }

    private function getOptionValues(string $fieldType, array $recordValues = []): array
    {
        if ($fieldType === 'trip_no') {
            $stored = FeedingOption::where('field_type', 'trip_no')
                ->orderBy('sort_order')
                ->orderBy('value')
                ->pluck('value')
                ->toArray();

            return !empty($stored) ? $stored : self::ALLOWED_TRIP_OPTIONS;
        }

        $stored = FeedingOption::where('field_type', $fieldType)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->pluck('value')
            ->toArray();

        $allValues = array_unique(array_merge($stored, $recordValues));
        usort($allValues, function ($a, $b) {
            return strcasecmp((string) $a, (string) $b);
        });

        return array_values($allValues);
    }

    private function ensureOption(string $fieldType, string $value): FeedingOption
    {
        return FeedingOption::firstOrCreate(
            ['field_type' => $fieldType, 'value' => $this->normalizeOptionValue($value)],
            ['sort_order' => FeedingOption::where('field_type', $fieldType)->max('sort_order') + 1]
        );
    }

    private function normalizeOptionValue(string $value): string
    {
        $value = trim($value);
        return strtoupper($value) === 'OPF' ? 'OPF' : ucwords(strtolower($value));
    }

    private function recalculateFeedTypeBalances(string $feedType): void
    {
        $records = FeedingRecord::where('feed_type', $feedType)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $previousBalance = 0.0;
        $recordsByDate = $records->groupBy('date');

        foreach ($recordsByDate as $dayRecords) {
            $carryForward = $previousBalance;
            $totalReceive = (float) $dayRecords->sum('receive');
            $totalActual = (float) $dayRecords->sum('actual_usage');
            $dayBalance = $carryForward + $totalReceive - $totalActual;

            foreach ($dayRecords as $record) {
                $record->forceFill([
                    'carry_forward' => $carryForward,
                    'balance' => $carryForward + (float) $record->receive - (float) $record->actual_usage,
                ])->save();
            }

            $previousBalance = $dayBalance;
        }
    }

    private function ensureUniqueRecord(array $data, ?int $ignoreId = null): void
    {
        $exists = FeedingRecord::where('date', $data['date'])
            ->where('trip_no', $data['trip_no'])
            ->where('feed_type', $data['feed_type'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'trip_no' => 'A record already exists for this date, trip, and feed type.',
            ]);
        }
    }

    public function exportPdf(Request $request)
    {
        return response()->json(['message' => 'PDF export not yet implemented']);
    }

    /**
     * Feeding Schedule (JADUAL PEMAKANAN BL & FL)
     * Shows trip data split into 40% and 60% allocations.
     */
    public function schedule(Request $request)
    {
        $date = $request->input('date', null);

        $query = FeedingRecord::query()->select([
            'id',
            'date',
            'trip_no',
            'cattle_count',
            'feed_type',
            'planned',
            'actual_usage',
            'receive',
            'carry_forward',
            'balance',
            'remarks',
        ]);
        if ($date) {
            $query->where('date', $date);
        }

        $records = $query->orderBy('date', 'asc')->orderBy('trip_no', 'asc')->get();

        // Get unique dates for dropdown
        $availableDates = FeedingRecord::distinct()->pluck('date')->sort()->values()->toArray();

        // Build trip rows with 40/60 split
        $tripRows = $records->groupBy('trip_no')->map(function ($tripRecords) {
            $mainRecord = $tripRecords->first();
            $cattleCount = $tripRecords->max('cattle_count');

            $napier = $tripRecords->where('feed_type', 'Napier')->sum('actual_usage');
            $opf = $tripRecords->where('feed_type', 'OPF')->sum('actual_usage');
            $conc = $tripRecords->where('feed_type', 'Concentrate')->sum('actual_usage');

            $napierPlan = $tripRecords->where('feed_type', 'Napier')->sum('planned');
            $opfPlan = $tripRecords->where('feed_type', 'OPF')->sum('planned');
            $concPlan = $tripRecords->where('feed_type', 'Concentrate')->sum('planned');

            $actualTotal = $napier + $opf + $conc;
            $planTotal = $napierPlan + $opfPlan + $concPlan;
            $receiveTotal = (float) $tripRecords->sum('receive');

            // 40% allocation
            $napier40 = round($napier * 0.4);
            $opf40 = round($opf * 0.4);
            $conc40 = round($conc * 0.4);
            $bags40 = $conc40 > 0 ? round($conc40 / 25) : 0;
            $plan40 = round($planTotal * 0.4);
            $actual40 = $napier40 + $opf40 + $conc40;

            // 60% allocation
            $napier60 = $napier - $napier40;
            $opf60 = $opf - $opf40;
            $conc60 = $conc - $conc40;
            $bags60 = $conc60 > 0 ? round($conc60 / 25) : 0;
            $plan60 = $planTotal - $plan40;
            $actual60 = $napier60 + $opf60 + $conc60;

            return [
                'trip_no' => $mainRecord->trip_no,
                'cattle_count' => $cattleCount,
                // 60% section
                'napier_60' => $napier60,
                'opf_60' => $opf60,
                'conc_60' => $conc60,
                'bags_60' => $bags60,
                'plan_60' => $plan60,
                'actual_60' => $actual60,
                // 40% section
                'napier_40' => $napier40,
                'opf_40' => $opf40,
                'conc_40' => $conc40,
                'bags_40' => $bags40,
                'plan_40' => $plan40,
                'actual_40' => $actual40,
                // Overall
                'receive_total' => $receiveTotal,
                'planning_total' => $planTotal,
                'actual_total' => $actualTotal,
                'remarks' => $mainRecord->remarks ?? '',
            ];
        })->values()->toArray();

        // Group by prefix (first 3 chars)
        $tripGroups = [];
        foreach ($tripRows as $row) {
            $prefix = substr($row['trip_no'], 0, 3);
            $tripGroups[$prefix][] = $row;
        }

        return Inertia::render('Feeding/Schedule', [
            'tripRows' => $tripRows,
            'tripGroups' => $tripGroups,
            'availableDates' => $availableDates,
            'filters' => [
                'date' => $date,
            ],
        ]);
    }
}
