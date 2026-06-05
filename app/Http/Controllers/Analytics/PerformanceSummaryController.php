<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\CalvingRecord;
use App\Models\Estate;
use App\Services\PerformanceSummaryCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PerformanceSummaryController extends Controller
{
    public function __construct(
        private readonly PerformanceSummaryCalculator $calculator
    ) {}

    public function index(Request $request)
    {
        [$dateFrom, $dateTo, $filters, $monthLabel, $yearLabel] = $this->resolveDateRange($request);

        $selectedOperatingUnit = trim((string) $request->get('operating_unit', $request->get('herd', '')));
        $month = (int) $filters['month'];
        $year = (int) $filters['year'];

        $operatingUnitsList = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = [];

        if ($selectedOperatingUnit === '') {
            foreach ($operatingUnitsList as $estate) {
                $row = $this->buildOperatingUnitRow($estate->name, $dateFrom, $dateTo, $month, $year);
                if ($this->rowHasAnyValue($row)) {
                    $rows[] = $row;
                }
            }
        } else {
            $herdsToProcess = $this->resolveHerdsForOperatingUnit($selectedOperatingUnit);

            foreach ($herdsToProcess as $herdName) {
                $herdName = trim((string) $herdName);
                $row = $this->buildHerdRow($herdName, $selectedOperatingUnit, $dateFrom, $dateTo, $month, $year);
                if ($this->rowHasAnyValue($row)) {
                    $rows[] = $row;
                }
            }
        }

        return Inertia::render('Analytics/PerformanceSummary', [
            'rows' => $rows,
            'totals' => $this->buildTotals($rows),
            'categoryCodes' => PerformanceSummaryCalculator::CATEGORY_CODES,
            'operatingUnits' => $operatingUnitsList,
            'filters' => array_merge($filters, [
                'operating_unit' => $selectedOperatingUnit,
                'herd' => $selectedOperatingUnit,
            ]),
            'meta' => [
                'month' => $month,
                'year' => $year,
                'monthLabel' => $monthLabel,
                'yearLabel' => $yearLabel,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ],
        ]);
    }

    private function buildOperatingUnitRow(string $operatingUnitName, Carbon $dateFrom, Carbon $dateTo, int $month, int $year): array
    {
        $herds = $this->resolveHerdsForOperatingUnit($operatingUnitName);

        $openingBuckets = [];
        $movementSets = [];

        foreach ($herds as $herdName) {
            $openingBuckets[] = $this->calculator->computeOpeningBalance($herdName, $dateFrom);
            $movementSets[] = $this->calculator->buildMovementsForHerd($herdName, $dateFrom, $dateTo, $operatingUnitName);
        }

        $opening = $this->calculator->sumBuckets(...$openingBuckets);
        $movements = $this->calculator->sumStatsSets(...$movementSets);
        $closing = $this->calculator->computeClosing($opening, $movements);
        $physicalCount = $this->calculator->physicalCountForHerd($operatingUnitName, $month, $year, $operatingUnitName);

        return $this->formatRow($operatingUnitName, $operatingUnitName, $opening, $movements, $closing, $physicalCount);
    }

    private function buildHerdRow(string $herdName, string $operatingUnitName, Carbon $dateFrom, Carbon $dateTo, int $month, int $year): array
    {
        $opening = $this->calculator->computeOpeningBalance($herdName, $dateFrom);
        $movements = $this->calculator->buildMovementsForHerd($herdName, $dateFrom, $dateTo, $operatingUnitName);
        $closing = $this->calculator->computeClosing($opening, $movements);
        $ouName = $this->resolveOperatingUnitForHerd($herdName) ?? $operatingUnitName;
        $physicalCount = $this->calculator->physicalCountForHerd($herdName, $month, $year, $ouName);

        return $this->formatRow($ouName, $herdName, $opening, $movements, $closing, $physicalCount);
    }

    private function formatRow(
        string $operatingUnitName,
        string $herdLabel,
        array $opening,
        array $movements,
        array $closing,
        array $physicalCount
    ): array {
        $difference = $this->calculator->emptyBucket();
        foreach (PerformanceSummaryCalculator::CATEGORY_CODES as $code) {
            $difference[$code] = ($physicalCount[$code] ?? 0) - ($closing[$code] ?? 0);
        }

        return [
            'operating_unit' => $operatingUnitName,
            'herd' => $herdLabel,
            'opening' => $this->calculator->withTotal($opening),
            'calving' => $this->calculator->withTotal($movements['calving']),
            'mortality' => $this->calculator->withTotal($movements['mortality']),
            'transfer_in' => $this->calculator->withTotal($movements['transfer_in']),
            'transfer_out' => $this->calculator->withTotal($movements['transfer_out']),
            'sale' => $this->calculator->withTotal($movements['sale']),
            'purchased' => $this->calculator->withTotal($movements['purchased']),
            'missing' => $this->calculator->withTotal($movements['missing']),
            'recovered' => $this->calculator->withTotal($movements['recovered']),
            'closing' => $this->calculator->withTotal($closing),
            'physical_count' => $this->calculator->withTotal($physicalCount),
            'difference' => $this->calculator->withTotal($difference),
        ];
    }

    /**
     * Pasture blocks (herds) under a single operating unit.
     */
    private function resolveHerdsForOperatingUnit(string $operatingUnitName): array
    {
        $estate = Estate::where('is_active', true)
            ->where('name', $operatingUnitName)
            ->with('pastureBlocks:id,estate_id,name')
            ->first();

        $herds = $estate
            ? $estate->pastureBlocks->pluck('name')->filter()->all()
            : [];

        $cattleBlocks = \App\Models\Cattle::query()
            ->where('operating_unit', $operatingUnitName)
            ->whereNotNull('location_block')
            ->where('location_block', '!=', '')
            ->distinct()
            ->pluck('location_block')
            ->all();

        $calvingBlocks = CalvingRecord::query()
            ->where('operating_unit', $operatingUnitName)
            ->whereNotNull('location_block')
            ->where('location_block', '!=', '')
            ->distinct()
            ->pluck('location_block')
            ->all();

        $herds = array_values(array_unique(array_filter(array_map(
            'trim',
            array_merge($herds, $cattleBlocks, $calvingBlocks)
        ))));
        sort($herds, SORT_NATURAL | SORT_FLAG_CASE);

        $hasBlankHerdData = CalvingRecord::query()
            ->where('operating_unit', $operatingUnitName)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('location_block')
                    ->orWhere('location_block', '');
            })
            ->exists();

        if ($hasBlankHerdData) {
            array_unshift($herds, '');
            $herds = array_values(array_unique($herds, SORT_REGULAR));
        }

        // Keep OU-level fallback to capture modules that store location at OU level.
        if (!in_array($operatingUnitName, $herds, true)) {
            $herds[] = $operatingUnitName;
        }

        return $herds;
    }

    private function resolveOperatingUnitForHerd(string $herdName): ?string
    {
        $estate = Estate::where('is_active', true)
            ->where(function ($query) use ($herdName) {
                $query->where('name', $herdName)
                    ->orWhereHas('pastureBlocks', fn ($q) => $q->where('name', $herdName));
            })
            ->first(['id', 'name']);

        if ($estate) {
            return $estate->name;
        }

        $fromCattle = \App\Models\Cattle::query()
            ->where('location_block', $herdName)
            ->whereNotNull('operating_unit')
            ->where('operating_unit', '!=', '')
            ->value('operating_unit');

        return $fromCattle ? trim((string) $fromCattle) : null;
    }

    private function resolveDateRange(Request $request): array
    {
        $monthInput = $request->get('month', now()->month);
        $yearInput = $request->get('year', now()->year);

        $month = max(1, min(12, (int) $monthInput));
        $year = max(2020, (int) $yearInput);

        $dateFrom = Carbon::create($year, $month, 1)->startOfDay();
        $dateTo = $dateFrom->copy()->endOfMonth()->endOfDay();

        return [
            $dateFrom,
            $dateTo,
            [
                'month' => $month,
                'year' => $year,
            ],
            $dateFrom->format('F'),
            (string) $year,
        ];
    }

    private function rowHasAnyValue(array $row): bool
    {
        foreach (['opening', 'calving', 'mortality', 'sale', 'transfer_in', 'transfer_out', 'purchased', 'missing', 'recovered', 'closing', 'physical_count'] as $section) {
            if (($row[$section]['TOTAL'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function buildTotals(array $rows): array
    {
        $totals = [
            'opening' => $this->calculator->emptyBucket(),
            'calving' => $this->calculator->emptyBucket(),
            'mortality' => $this->calculator->emptyBucket(),
            'sale' => $this->calculator->emptyBucket(),
            'transfer_in' => $this->calculator->emptyBucket(),
            'transfer_out' => $this->calculator->emptyBucket(),
            'purchased' => $this->calculator->emptyBucket(),
            'missing' => $this->calculator->emptyBucket(),
            'recovered' => $this->calculator->emptyBucket(),
            'closing' => $this->calculator->emptyBucket(),
            'physical_count' => $this->calculator->emptyBucket(),
            'difference' => $this->calculator->emptyBucket(),
        ];

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $section) {
                foreach (PerformanceSummaryCalculator::CATEGORY_CODES as $code) {
                    $totals[$section][$code] += (int) ($row[$section][$code] ?? 0);
                }
            }
        }

        $result = [];
        foreach ($totals as $section => $bucket) {
            $result[$section] = $this->calculator->withTotal($bucket);
        }

        return $result;
    }
}
