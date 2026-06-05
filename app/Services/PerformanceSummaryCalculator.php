<?php

namespace App\Services;

use App\Models\CalvingRecord;
use App\Models\Cattle;
use App\Models\DailyOperationEntry;
use App\Models\MortalityCase;
use App\Models\TransferDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class PerformanceSummaryCalculator
{
    public const CATEGORY_CODES = ['B/B', 'B/C', 'W/B', 'H', 'M/C', 'F/C'];

    public function emptyBucket(): array
    {
        return array_fill_keys(self::CATEGORY_CODES, 0);
    }

    public function withTotal(array $bucket): array
    {
        $bucket['TOTAL'] = array_sum($bucket);

        return $bucket;
    }

    public function sumBuckets(array ...$buckets): array
    {
        $result = $this->emptyBucket();

        foreach ($buckets as $bucket) {
            foreach (self::CATEGORY_CODES as $code) {
                $result[$code] += (int) ($bucket[$code] ?? 0);
            }
        }

        return $result;
    }

    public function sumStatsSets(array ...$statsSets): array
    {
        $result = $this->emptyStatsSet();

        foreach ($statsSets as $stats) {
            foreach (array_keys($result) as $section) {
                $result[$section] = $this->sumBuckets($result[$section], $stats[$section] ?? []);
            }
        }

        return $result;
    }

    public function emptyStatsSet(): array
    {
        return [
            'opening' => $this->emptyBucket(),
            'calving' => $this->emptyBucket(),
            'mortality' => $this->emptyBucket(),
            'sale' => $this->emptyBucket(),
            'transfer_in' => $this->emptyBucket(),
            'transfer_out' => $this->emptyBucket(),
            'purchased' => $this->emptyBucket(),
            'missing' => $this->emptyBucket(),
            'recovered' => $this->emptyBucket(),
        ];
    }

    public function buildMovementsForHerd(string $herd, Carbon $dateFrom, Carbon $dateTo, ?string $operatingUnit = null): array
    {
        $stats = $this->emptyStatsSet();

        $this->applyCalving($stats, $herd, $dateFrom, $dateTo, $operatingUnit);
        $this->applyMortality($stats, $herd, $dateFrom, $dateTo);
        $this->applySales($stats, $herd, $dateFrom, $dateTo);
        $this->applyTransfers($stats, $herd, $dateFrom, $dateTo);
        $this->applyPurchased($stats, $herd, $dateFrom, $dateTo);
        $this->applyMissingAndRecovered($stats, $herd, $dateFrom, $dateTo);

        return $stats;
    }

    /**
     * Opening book balance at the start of the selected month.
     * When records are complete this matches the prior month's closing balance.
     */
    public function computeOpeningBalance(string $herd, Carbon $dateFrom): array
    {
        $previousMonthEnd = $dateFrom->copy()->subDay()->endOfDay();
        if ($previousMonthEnd->year < 2020) {
            return $this->computeBookBalanceAtDate($herd, $dateFrom);
        }

        $previousMonthStart = $previousMonthEnd->copy()->startOfMonth()->startOfDay();
        $previousOpening = $this->computeBookBalanceAtDate($herd, $previousMonthStart);
        $previousMovements = $this->buildMovementsForHerd($herd, $previousMonthStart, $previousMonthEnd);

        return $this->computeClosing($previousOpening, $previousMovements);
    }

    public function computeClosing(array $opening, array $movements): array
    {
        $closing = $this->emptyBucket();

        foreach (self::CATEGORY_CODES as $code) {
            $closing[$code] = max(0,
                ($opening[$code] ?? 0)
                + ($movements['calving'][$code] ?? 0)
                - ($movements['mortality'][$code] ?? 0)
                - ($movements['sale'][$code] ?? 0)
                + ($movements['transfer_in'][$code] ?? 0)
                - ($movements['transfer_out'][$code] ?? 0)
                + ($movements['purchased'][$code] ?? 0)
                - ($movements['missing'][$code] ?? 0)
                + ($movements['recovered'][$code] ?? 0)
            );
        }

        return $closing;
    }

    public function physicalCountForHerd(string $herd, int $month, int $year, ?string $operatingUnit = null): array
    {
        $physical = $this->emptyBucket();
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        if (!Schema::hasTable('daily_operation_entries')) {
            return $physical;
        }

        $estateName = $operatingUnit ?: $this->resolveOperatingUnitName($herd) ?: $herd;
        $completedWeeks = $this->getCompletedDomlWeeks($estateName, $month, $year);

        if (empty($completedWeeks)) {
            return $physical;
        }

        $entries = DailyOperationEntry::query()
            ->where('month', $month)
            ->where('year', $year)
            ->where('estate_name', $estateName)
            ->get()
            ->keyBy('category_code');

        foreach (self::CATEGORY_CODES as $code) {
            $entry = $entries->get($code);
            if (!$entry || !is_array($entry->daily_values)) {
                continue;
            }

            $values = array_slice(array_pad($entry->daily_values, $daysInMonth, 0), 0, $daysInMonth);

            foreach ($completedWeeks as $week) {
                foreach ($this->getWeekDayIndices($week, $daysInMonth) as $dayIndex) {
                    $physical[$code] += (int) ($values[$dayIndex] ?? 0);
                }
            }
        }

        return $physical;
    }

    public function toCategoryCode(?string $category): ?string
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
            'CALF' => 'M/C',
        ];

        if (isset($compactCodeMap[$value])) {
            return $compactCodeMap[$value];
        }

        return match (true) {
            str_contains($value, 'BREEDER') && str_contains($value, 'BULL') => 'B/B',
            str_contains($value, 'BREEDER') && str_contains($value, 'COW') => 'B/C',
            str_contains($value, 'WEANER') && str_contains($value, 'BULL') => 'W/B',
            str_contains($value, 'HEIFER') => 'H',
            str_contains($value, 'MALE') && str_contains($value, 'CALF') => 'M/C',
            str_contains($value, 'FEMALE') && str_contains($value, 'CALF') => 'F/C',
            default => null,
        };
    }

    private function computeBookBalanceAtDate(string $herd, Carbon $asOfDate): array
    {
        $opening = $this->emptyBucket();
        $asOf = $asOfDate->copy()->startOfDay();

        $deceasedBefore = MortalityCase::query()
            ->whereNotNull('cattle_id')
            ->whereDate('death_date', '<', $asOf->toDateString())
            ->pluck('cattle_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $soldBeforeTags = TransferDocument::query()
            ->where('type', TransferDocument::TYPE_SIV)
            ->whereDate('date', '<', $asOf->toDateString())
            ->where('from_location', $herd)
            ->with(['livestock:id,transfer_document_id,tag_no'])
            ->get()
            ->flatMap(fn ($doc) => $doc->livestock->pluck('tag_no'))
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->flip()
            ->all();

        $cattle = Cattle::query()
            ->where('location_block', $herd)
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

            $tag = trim((string) $animal->tag_no);
            if ($tag !== '' && isset($soldBeforeTags[$tag])) {
                continue;
            }

            $code = $this->toCategoryCode($animal->category);
            if ($code) {
                $opening[$code]++;
            }
        }

        return $opening;
    }

    private function applyCalving(array &$stats, string $herd, Carbon $dateFrom, Carbon $dateTo, ?string $operatingUnit = null): void
    {
        $normalizedHerd = $this->normalizeHerdName($herd);
        $normalizedOperatingUnit = $this->normalizeHerdName($operatingUnit);

        $records = CalvingRecord::query()
            ->whereBetween('calving_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('status', 'completed')
            ->get(['id', 'tag_no', 'calving_date', 'sex', 'location_block', 'herd', 'operating_unit']);

        $seen = [];

        foreach ($records as $record) {
            if (
                $normalizedOperatingUnit !== ''
                && $this->normalizeHerdName((string) ($record->operating_unit ?? '')) !== $normalizedOperatingUnit
            ) {
                continue;
            }

            if (! $this->matchesCalvingHerd(
                $normalizedHerd,
                (string) ($record->location_block ?? ''),
                (string) ($record->herd ?? '')
            )) {
                continue;
            }

            $tag = strtoupper(trim((string) ($record->tag_no ?? '')));
            $dateKey = $record->calving_date ? Carbon::parse($record->calving_date)->toDateString() : '';
            $dedupeKey = $tag !== '' ? ($tag . '|' . $dateKey) : ('record|' . $record->id);
            $seen[$dedupeKey] = true;

            $code = $this->calvingCategoryFromSex($record->sex);
            if ($code) {
                $stats['calving'][$code]++;
            }
        }

    }

    private function applyMortality(array &$stats, string $herd, Carbon $dateFrom, Carbon $dateTo): void
    {
        $cases = MortalityCase::query()
            ->with('cattle:id,category,location_block')
            ->whereBetween('death_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('status', 'completed')
            ->get(['cattle_id', 'category', 'location']);

        foreach ($cases as $case) {
            $caseHerd = trim((string) ($case->location ?: $case->cattle?->location_block));
            if ($caseHerd !== $herd) {
                continue;
            }

            $code = $this->toCategoryCode($case->category ?: $case->cattle?->category);
            if ($code) {
                $stats['mortality'][$code]++;
            }
        }
    }

    private function applySales(array &$stats, string $herd, Carbon $dateFrom, Carbon $dateTo): void
    {
        $docs = TransferDocument::query()
            ->where('type', TransferDocument::TYPE_SIV)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('from_location', $herd)
            ->with('livestock:id,transfer_document_id,category,tag_no')
            ->get();

        if ($docs->isEmpty()) {
            return;
        }

        $tagNos = $docs->flatMap->livestock->pluck('tag_no')->filter()->map(fn($t) => trim((string)$t))->unique()->toArray();
        $cattleStatuses = [];
        $tagsWithCompletedReceival = [];

        if (!empty($tagNos)) {
            $cattleStatuses = Cattle::whereIn('tag_no', $tagNos)->pluck('status', 'tag_no')->map(fn($s) => strtolower(trim((string)$s)))->toArray();

            $tagsWithCompletedReceival = \App\Models\TransferLivestock::whereIn('tag_no', $tagNos)
                ->whereHas('document', function ($q) {
                    $q->where('type', TransferDocument::TYPE_RECEIVAL)
                      ->where('status', TransferDocument::STATUS_COMPLETED);
                })->pluck('tag_no')->map(fn($t) => trim((string)$t))->unique()->flip()->toArray();
        }

        foreach ($docs as $doc) {
            foreach ($doc->livestock as $item) {
                $tagNo = trim((string) $item->tag_no);
                if ($tagNo === '') continue;

                $cattleStatus = $cattleStatuses[$tagNo] ?? null;
                if ($cattleStatus !== 'sold') {
                    continue;
                }

                if (!isset($tagsWithCompletedReceival[$tagNo])) {
                    continue;
                }

                $code = $this->toCategoryCode($item->category);
                if ($code) {
                    $stats['sale'][$code]++;
                }
            }
        }
    }

    private function applyTransfers(array &$stats, string $herd, Carbon $dateFrom, Carbon $dateTo): void
    {
        $normalizedHerd = $this->normalizeHerdName($herd);
        if ($normalizedHerd === '') {
            return;
        }

        $docs = TransferDocument::query()
            ->where('type', TransferDocument::TYPE_CTV)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where(function ($query) use ($normalizedHerd) {
                $query->whereRaw('UPPER(TRIM(from_location)) = ?', [$normalizedHerd])
                    ->orWhereRaw('UPPER(TRIM(to_location)) = ?', [$normalizedHerd]);
            })
            ->with('livestock:id,transfer_document_id,category')
            ->get(['id', 'type', 'from_location', 'to_location']);

        foreach ($docs as $doc) {
            $fromLocation = $this->normalizeHerdName($doc->from_location);
            $toLocation = $this->normalizeHerdName($doc->to_location);

            foreach ($doc->livestock as $item) {
                $code = $this->toCategoryCode($item->category);
                if (!$code) {
                    continue;
                }

                // Transfer OUT: cattle leaving this location (from side), CTV only.
                if ($doc->type === TransferDocument::TYPE_CTV && $fromLocation === $normalizedHerd) {
                    $stats['transfer_out'][$code]++;
                }

                // Transfer IN: cattle arriving at this location (to side).
                if ($toLocation === $normalizedHerd) {
                    $stats['transfer_in'][$code]++;
                }
            }
        }
    }

    private function applyPurchased(array &$stats, string $herd, Carbon $dateFrom, Carbon $dateTo): void
    {
        $receivalDocs = TransferDocument::query()
            ->where('type', TransferDocument::TYPE_RECEIVAL)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('to_location', $herd)
            ->with('livestock:id,transfer_document_id,category,tag_no')
            ->get();

        $receivalTags = [];

        if ($receivalDocs->isNotEmpty()) {
            $tagNos = $receivalDocs->flatMap->livestock->pluck('tag_no')->filter()->map(fn($t) => trim((string)$t))->unique()->toArray();
            $cattleStatuses = [];
            $tagsWithCompletedSiv = [];

            if (!empty($tagNos)) {
                $cattleStatuses = Cattle::whereIn('tag_no', $tagNos)->pluck('status', 'tag_no')->map(fn($s) => strtolower(trim((string)$s)))->toArray();

                $tagsWithCompletedSiv = \App\Models\TransferLivestock::whereIn('tag_no', $tagNos)
                    ->whereHas('document', function ($q) {
                        $q->where('type', TransferDocument::TYPE_SIV)
                          ->where('status', TransferDocument::STATUS_COMPLETED);
                    })->pluck('tag_no')->map(fn($t) => trim((string)$t))->unique()->flip()->toArray();
            }

            foreach ($receivalDocs as $doc) {
                foreach ($doc->livestock as $item) {
                    $tag = trim((string) $item->tag_no);
                    if ($tag !== '') {
                        $receivalTags[$tag] = true;
                    }

                    if ($tag === '') continue;

                    $cattleStatus = $cattleStatuses[$tag] ?? null;
                    if ($cattleStatus !== 'sold') {
                        continue;
                    }

                    if (!isset($tagsWithCompletedSiv[$tag])) {
                        continue;
                    }

                    $code = $this->toCategoryCode($item->category);
                    if ($code) {
                        $stats['purchased'][$code]++;
                    }
                }
            }
        }

        $newCattle = Cattle::query()
            ->where('location_block', $herd)
            ->whereBetween('created_at', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()])
            ->whereNull('calving_record_id')
            ->where(function ($query) {
                $query->whereNull('remarks')
                    ->orWhere('remarks', 'not like', 'Auto-created from Calving Record%');
            })
            ->get(['tag_no', 'category', 'status']);

        foreach ($newCattle as $animal) {
            $tag = trim((string) $animal->tag_no);
            if ($tag !== '' && isset($receivalTags[$tag])) {
                continue;
            }

            $status = strtolower(trim((string) $animal->status));
            if (in_array($status, ['deceased', 'sold'], true)) {
                continue;
            }

            $code = $this->toCategoryCode($animal->category);
            if ($code) {
                $stats['purchased'][$code]++;
            }
        }
    }

    private function applyMissingAndRecovered(array &$stats, string $herd, Carbon $dateFrom, Carbon $dateTo): void
    {
        $cattle = Cattle::query()
            ->where('location_block', $herd)
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('updated_at', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()])
                    ->orWhereBetween('created_at', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()]);
            })
            ->get(['category', 'status', 'remarks', 'created_at', 'updated_at']);

        foreach ($cattle as $animal) {
            $code = $this->toCategoryCode($animal->category);
            if (!$code) {
                continue;
            }

            $status = trim((string) $animal->status);
            $updatedInPeriod = Carbon::parse($animal->updated_at)->between($dateFrom, $dateTo);

            if ($status === 'Missing' && $updatedInPeriod) {
                $stats['missing'][$code]++;
            }

            if ($status === 'Active' && $updatedInPeriod && Carbon::parse($animal->created_at)->lt($dateFrom)) {
                $remarks = strtolower((string) ($animal->remarks ?? ''));
                if (str_contains($remarks, 'recover')) {
                    $stats['recovered'][$code]++;
                }
            }
        }
    }

    private function resolveOperatingUnitName(string $herd): ?string
    {
        $estate = \App\Models\Estate::where('is_active', true)
            ->where(function ($query) use ($herd) {
                $query->where('name', $herd)
                    ->orWhereHas('pastureBlocks', fn ($q) => $q->where('name', $herd));
            })
            ->value('name');

        if ($estate) {
            return $estate;
        }

        $fromCattle = Cattle::query()
            ->where('location_block', $herd)
            ->whereNotNull('operating_unit')
            ->where('operating_unit', '!=', '')
            ->value('operating_unit');

        return $fromCattle ? trim((string) $fromCattle) : null;
    }

    private function calvingCategoryFromSex(?string $sex): ?string
    {
        $value = strtoupper(trim((string) $sex));

        return match (true) {
            in_array($value, ['MC', 'MALE', 'M', 'BULL'], true) => 'M/C',
            in_array($value, ['FC', 'FEMALE', 'F', 'COW'], true) => 'F/C',
            default => null,
        };
    }

    private function matchesCalvingHerd(string $normalizedHerd, string $locationBlock, string $fallbackField): bool
    {
        $normalizedBlock = $this->normalizeHerdName($locationBlock);
        if ($normalizedBlock !== '') {
            return $normalizedBlock === $normalizedHerd;
        }

        $normalizedFallback = $this->normalizeHerdName($fallbackField);

        return $normalizedFallback !== '' && $normalizedFallback === $normalizedHerd;
    }

    private function getCompletedDomlWeeks(string $estateName, int $month, int $year): array
    {
        $completedWeeks = [];

        for ($week = 1; $week <= 4; $week++) {
            if ($this->isDomlWeekWorkflowCompleted($estateName, $month, $year, $week)) {
                $completedWeeks[] = $week;
            }
        }

        return $completedWeeks;
    }

    private function getWeekDayIndices(int $week, int $daysInMonth): array
    {
        $start = ($week - 1) * 7;
        $end = min($start + 6, $daysInMonth - 1);

        if ($start > $daysInMonth - 1) {
            return [];
        }

        return range($start, $end);
    }

    private function isDomlWeekWorkflowCompleted(string $estateName, int $month, int $year, int $week): bool
    {
        $estate = trim($estateName);
        $estate = $estate === '' ? 'all-estates' : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $estate));
        $estate = trim($estate, '-');
        if ($estate === '') {
            $estate = 'all-estates';
        }

        $statePath = 'doml-workflow/' . $estate . '/' . $year . '/'
            . str_pad($month, 2, '0', STR_PAD_LEFT) . '/week-' . $week . '/workflow.json';

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($statePath)) {
            return false;
        }

        $json = \Illuminate\Support\Facades\Storage::disk('public')->get($statePath);
        $decoded = json_decode($json, true);

        return is_array($decoded) && !empty($decoded['is_completed']);
    }

    private function isDomlWorkflowCompleted(?string $estateName, int $month, int $year): bool
    {
        $completedWeeks = $this->getCompletedDomlWeeks(trim((string) $estateName), $month, $year);

        return count($completedWeeks) === 4;
    }

    private function normalizeHerdName(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
