<?php

namespace App\Http\Controllers;

use App\Models\Estate;
use App\Models\GrazingData;
use App\Models\GrazingBlock;
use App\Models\Herd;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class HerdCardController extends Controller
{
    public function grazingDetailsIndex(Request $request)
    {
        $search = $request->get('search', '');
        $operatingUnit = $request->get('operating_unit', '');
        $month = $request->get('month', '');
        $perPage = 10;

        $query = GrazingData::with('estate')
            ->when($operatingUnit, function ($q, $value) {
                $q->where('estate_id', $value);
            })
            ->when($month, function ($q, $value) {
                $q->where('month', $value);
            })
            ->when($search, function ($q, $value) {
                $q->where(function ($sub) use ($value) {
                    $sub->whereHas('estate', function ($estateQuery) use ($value) {
                            $estateQuery->where('name', 'like', "%{$value}%");
                        });
                });
            })
            ->orderBy('month', 'desc')
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage)->withQueryString();

        $records = collect($paginator->items())->map(function ($item) {
            return [
                'id' => $item->id,
                'month' => $item->month,
                'operating_unit' => $item->estate?->name,
                'operating_unit_id' => $item->estate_id,
                'allocated_area' => (float) $item->allocated_area,
                'rotation_period' => (int) $item->rotation_period,
                'days_in_month' => (int) $item->days_in_month,
                'current_month_ha' => (float) $item->current_month_ha,
                'rate_per_ha' => (float) $item->rate_per_ha,
                'deduction_percent' => (float) $item->deduction_percent,
                'deduction_amount' => (float) $item->deduction_amount,
                'to_date_ha' => (float) $item->to_date_ha,
                'total_budget' => (float) $item->total_budget,
                'ytd_claim' => (float) $item->ytd_claim,
                'budget_remaining' => (float) $item->budget_remaining,
            ];
        })->values();

        $operatingUnits = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($estate) => [
                'id' => $estate->id,
                'name' => $estate->name,
            ])
            ->values();

        return Inertia::render('HerdCards/GrazingDetailsIndex', [
            'records' => $records,
            'operatingUnits' => $operatingUnits,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'operating_unit' => $operatingUnit,
                'month' => $month,
            ],
        ]);
    }

    public function grazingDetailsCreate()
    {
        $operatingUnits = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'area'])
            ->map(fn ($estate) => [
                'id' => $estate->id,
                'name' => $estate->name,
                'area' => (float) $estate->area,
            ])
            ->values();

        return Inertia::render('HerdCards/GrazingDetailsCreate', [
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function grazingDetailsStore(Request $request)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'month' => 'required|date_format:Y-m',
            'allocated_area' => 'required|numeric|min:0',
            'rotation_period' => 'required|integer|min:1',
            'days_in_month' => 'required|integer|min:1|max:31',
            'current_month_ha' => 'required|numeric|min:0',
            'rate_per_ha' => 'required|numeric|min:0',
            'deduction_percent' => 'required|numeric|min:0',
            'deduction_amount' => 'required|numeric|min:0',
            'to_date_ha' => 'required|numeric|min:0',
            'total_budget' => 'required|numeric|min:0',
            'ytd_claim' => 'required|numeric|min:0',
        ]);

        GrazingData::create($validated);

        return redirect()->route('herd-cards.grazing-details.index')->with('success', 'Grazing detail record added successfully.');
    }

    public function grazingDetailsUpdate(Request $request, GrazingData $grazingData)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'month' => 'required|date_format:Y-m',
            'allocated_area' => 'required|numeric|min:0',
            'rotation_period' => 'required|integer|min:1',
            'days_in_month' => 'required|integer|min:1|max:31',
            'current_month_ha' => 'required|numeric|min:0',
            'rate_per_ha' => 'required|numeric|min:0',
            'deduction_percent' => 'required|numeric|min:0',
            'deduction_amount' => 'required|numeric|min:0',
            'to_date_ha' => 'required|numeric|min:0',
            'total_budget' => 'required|numeric|min:0',
            'ytd_claim' => 'required|numeric|min:0',
        ]);

        $grazingData->update($validated);

        return back()->with('success', 'Grazing detail record updated successfully.');
    }

    public function grazingDetailsDestroy(GrazingData $grazingData)
    {
        $grazingData->blocks()->delete();
        $grazingData->delete();

        return back()->with('success', 'Grazing detail record deleted successfully.');
    }

    /**
     * Display all herd cards (estates with their grazing data summary)
     */
    public function index(Request $request)
    {
        // Get the most recent month with data, or current month as fallback
        $latestMonth = GrazingData::orderBy('month', 'desc')->value('month');
        $defaultMonth = $latestMonth ?? date('Y-m');
        
        $month = $request->get('month', $defaultMonth);
        $status = $request->get('status', '');
        $search = $request->get('search', '');

        // Get all active estates with their latest grazing data for the selected month
        $estates = Estate::where('is_active', true)
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        $herdCards = $estates->map(function ($estate) use ($month) {
            // Get grazing data for this estate and month (may have multiple herds)
            $grazingDataRecords = GrazingData::where('estate_id', $estate->id)
                ->where('month', $month)
                ->with('blocks')
                ->get();

            $totalArea = floatval($estate->area);
            $activeBlocks = 0;
            $achievement = 0;
            $ratePerHa = 0;
            $monthlyClaim = 0;
            $totalActual = 0;
            $totalBlockArea = 0;
            $herdName = '';

            foreach ($grazingDataRecords as $grazingData) {
                if ($grazingData->blocks->count() > 0) {
                    $activeBlocks += $grazingData->blocks->count();
                    $totalActual += $grazingData->blocks->sum('actual');
                    $totalBlockArea += $grazingData->blocks->sum('area');
                    $ratePerHa = floatval($grazingData->rate_per_ha); // Use last rate
                    $monthlyClaim += $grazingData->blocks->sum(function ($block) {
                        return floatval($block->achievement) * floatval($block->rate);
                    });
                    $herdName = $grazingData->herd ?: $herdName;
                }
            }

            $achievement = $totalBlockArea > 0 ? round(($totalActual / $totalBlockArea) * 100, 1) : 0;

            // Determine status based on achievement
            $statusVal = 'below';
            $statusLabel = 'Below Target';
            if ($achievement >= 98) {
                $statusVal = 'above';
                $statusLabel = 'Above Target';
            } elseif ($achievement >= 95) {
                $statusVal = 'at';
                $statusLabel = 'At Target';
            }

            // Get first grazing data for detailed fields
            $firstGrazingData = $grazingDataRecords->first();

            // Collect all blocks from all grazing records
            $allBlocks = [];
            foreach ($grazingDataRecords as $gd) {
                foreach ($gd->blocks as $block) {
                    $allBlocks[] = [
                        'id' => $block->id,
                        'block_id' => $block->block_id,
                        'area' => floatval($block->area),
                        'actual' => floatval($block->actual),
                        'achievement' => floatval($block->achievement),
                        'rate' => floatval($block->rate),
                    ];
                }
            }

            return [
                'id' => $estate->id,
                'slug' => Str::slug($estate->name),
                'name' => $estate->name,
                'herd' => $herdName,
                'total_area' => $totalArea,
                'area' => floatval($estate->area),
                'latitude' => $estate->latitude ? floatval($estate->latitude) : null,
                'longitude' => $estate->longitude ? floatval($estate->longitude) : null,
                'place_name' => $estate->place_name,
                'active_blocks' => $activeBlocks,
                'achievement' => $achievement,
                'rate_per_ha' => $ratePerHa,
                'monthly_claim' => round($monthlyClaim, 2),
                'status' => $statusVal,
                'status_label' => $statusLabel,
                'updated_at' => $grazingDataRecords->count() > 0 ? $firstGrazingData->updated_at->format('M Y') : null,
                'has_data' => $grazingDataRecords->count() > 0,
                // For editing - full grazing data
                'grazing_data_id' => $firstGrazingData ? $firstGrazingData->id : null,
                'allocated_area' => $firstGrazingData ? floatval($firstGrazingData->allocated_area) : $totalArea,
                'rotation_period' => $firstGrazingData ? $firstGrazingData->rotation_period : 62,
                'days_in_month' => $firstGrazingData ? $firstGrazingData->days_in_month : 30,
                'current_month_ha' => $firstGrazingData ? floatval($firstGrazingData->current_month_ha) : 0,
                'deduction_percent' => $firstGrazingData ? floatval($firstGrazingData->deduction_percent) : 0,
                'deduction_amount' => $firstGrazingData ? floatval($firstGrazingData->deduction_amount) : 0,
                'to_date_ha' => $firstGrazingData ? floatval($firstGrazingData->to_date_ha) : 0,
                'total_budget' => $firstGrazingData ? floatval($firstGrazingData->total_budget) : 0,
                'ytd_claim' => $firstGrazingData ? floatval($firstGrazingData->ytd_claim) : 0,
                'blocks' => $allBlocks,
            ];
        });

        // Filter by status if provided
        if ($status) {
            $herdCards = $herdCards->filter(fn($card) => $card['status'] === $status);
        }

        // Calculate summary stats
        $totalHerds = $herdCards->count();
        $aboveTarget = $herdCards->where('status', 'above')->count();
        $atTarget = $herdCards->where('status', 'at')->count();
        $belowTarget = $herdCards->where('status', 'below')->count();
        $totalClaim = $herdCards->sum('monthly_claim');

        // Calculate overall summary
        $totalArea = $herdCards->sum('total_area');
        $totalActiveBlocks = $herdCards->sum('active_blocks');
        $avgAchievement = $herdCards->count() > 0 ? round($herdCards->avg('achievement'), 2) : 0;

        // Get all herds for the dropdown
        $herds = Herd::orderBy('name')->get();

        return Inertia::render('HerdCards/Index', [
            'herdCards' => $herdCards->values(),
            'herds' => $herds,
            'summary' => [
                'total_herds' => $totalHerds,
                'above_target' => $aboveTarget,
                'at_target' => $atTarget,
                'below_target' => $belowTarget,
                'total_claim' => round($totalClaim, 2),
                'total_area' => $totalArea,
                'total_active_blocks' => $totalActiveBlocks,
                'avg_achievement' => $avgAchievement,
            ],
            'filters' => [
                'month' => $month,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display herd details for a specific estate
     */
    public function show(Request $request, Estate $estate)
    {
        $month = $request->get('month', date('Y-m'));

        // Get ALL grazing data for this estate and month (may have multiple herds)
        $grazingDataRecords = GrazingData::where('estate_id', $estate->id)
            ->where('month', $month)
            ->with('blocks')
            ->get();

        $blocks = [];
        $totalClaim = 0;
        $avgAchievement = 0;
        $totalAchievement = 0;

        // Aggregate blocks from all grazing data records
        foreach ($grazingDataRecords as $grazingData) {
            if ($grazingData->blocks->count() > 0) {
                foreach ($grazingData->blocks as $block) {
                    $areaActualPercent = floatval($block->area) > 0
                        ? round((floatval($block->actual) / floatval($block->area)) * 100, 0)
                        : 0;
                    $total = floatval($block->achievement) * floatval($block->rate);

                    // Determine status based on area actual %
                    $status = 'below';
                    $statusLabel = 'Below Target';
                    if ($areaActualPercent >= 98) {
                        $status = 'excellent';
                        $statusLabel = 'Excellent';
                    } elseif ($areaActualPercent >= 95) {
                        $status = 'good';
                        $statusLabel = 'Good';
                    } elseif ($areaActualPercent >= 90) {
                        $status = 'average';
                        $statusLabel = 'Average';
                    }

                    $blocks[] = [
                        'id' => $block->id,
                        'block_id' => $block->block_id,
                        'area' => floatval($block->area),
                        'rate' => floatval($block->actual),
                        'area_actual_percent' => $areaActualPercent,
                        'achievement' => floatval($block->achievement),
                        'rm_per_ha' => floatval($block->rate),
                        'total' => round($total, 2),
                        'status' => $status,
                        'status_label' => $statusLabel,
                        'herd' => $grazingData->herd,
                    ];
                }
            }
        }

        if (count($blocks) > 0) {
            $totalClaim = collect($blocks)->sum('total');
            $totalAchievement = collect($blocks)->sum('achievement');
            $avgAchievement = round(collect($blocks)->avg('area_actual_percent'), 2);
        }

        // Use first grazing data for metadata
        $grazingData = $grazingDataRecords->first();

        // Find best performing block
        $bestBlock = collect($blocks)->sortByDesc('achievement')->first();

        return Inertia::render('HerdCards/Show', [
            'estate' => [
                'id' => $estate->id,
                'name' => $estate->name,
                'slug' => Str::slug($estate->name),
                'area' => floatval($estate->area),
                'latitude' => $estate->latitude ? floatval($estate->latitude) : null,
                'longitude' => $estate->longitude ? floatval($estate->longitude) : null,
                'place_name' => $estate->place_name,
            ],
            'blocks' => $blocks,
            'summary' => [
                'total_area' => floatval($estate->area),
                'active_blocks' => count($blocks),
                'avg_achievement' => $avgAchievement,
                'total_claim' => round($totalClaim, 2),
                'total_achievement' => round($totalAchievement, 2),
                'best_block' => $bestBlock ? $bestBlock['block_id'] : 'N/A',
                'avg_rm_per_ha' => $grazingData ? floatval($grazingData->rate_per_ha) : 0,
            ],
            'month' => $month,
            'grazingData' => $grazingData ? [
                'id' => $grazingData->id,
                'herd' => $grazingData->herd,
                'month' => $grazingData->month,
                'allocated_area' => floatval($grazingData->allocated_area),
                'rotation_period' => $grazingData->rotation_period,
                'days_in_month' => $grazingData->days_in_month,
                'current_month_ha' => floatval($grazingData->current_month_ha),
                'rate_per_ha' => floatval($grazingData->rate_per_ha),
                'deduction_percent' => floatval($grazingData->deduction_percent),
                'deduction_amount' => floatval($grazingData->deduction_amount),
                'to_date_ha' => floatval($grazingData->to_date_ha),
                'total_budget' => floatval($grazingData->total_budget),
                'ytd_claim' => floatval($grazingData->ytd_claim),
            ] : null,
        ]);
    }

    /**
     * Update herd card data
     */
    public function update(Request $request, Estate $estate)
    {
        \Log::info('HerdCardController@update called', [
            'estate_id' => $estate->id,
            'request_data' => $request->all()
        ]);
        
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'area' => 'nullable|numeric|min:0',
                'herd' => 'nullable|string',
                'month' => 'required|string',
                'rate_per_ha' => 'nullable|numeric',
                'grazing_data_id' => 'nullable|integer|exists:grazing_data,id',
                'allocated_area' => 'nullable|numeric',
                'rotation_period' => 'nullable|integer',
                'days_in_month' => 'nullable|integer',
                'current_month_ha' => 'nullable|numeric',
                'deduction_percent' => 'nullable|numeric',
                'deduction_amount' => 'nullable|numeric',
                'to_date_ha' => 'nullable|numeric',
                'total_budget' => 'nullable|numeric',
                'ytd_claim' => 'nullable|numeric',
                'blocks' => 'nullable|array',
                'blocks.*.id' => 'nullable|integer',
                'blocks.*.block_id' => 'required_with:blocks|string',
                'blocks.*.area' => 'nullable|numeric',
                'blocks.*.actual' => 'nullable|numeric',
                'blocks.*.achievement' => 'nullable|numeric',
                'blocks.*.rate' => 'nullable|numeric',
            ]);
            
            // Use estate's current area if not provided
            $validated['area'] = $validated['area'] ?? $estate->area;
            
            \Log::info('Validation passed', ['validated' => $validated]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', ['errors' => $e->errors()]);
            throw $e;
        }
        
        // Set default rate_per_ha if not provided
        $ratePerHa = $validated['rate_per_ha'] ?? 11.09;
        if (empty($ratePerHa) || $ratePerHa == 0) {
            $ratePerHa = 11.09;
        }

        // Update estate
        $estate->update([
            'name' => $validated['name'],
            'area' => $validated['area'],
        ]);

        // Find existing grazing data by ID if provided, BUT only if month matches
        // This prevents updating old month data when user changes the month in the form
        $grazingData = null;
        if (!empty($validated['grazing_data_id'])) {
            $existingData = GrazingData::find($validated['grazing_data_id']);
            // Only use the existing record if the month matches the requested month
            if ($existingData && $existingData->month === $validated['month']) {
                $grazingData = $existingData;
            }
        }

        if (!$grazingData) {
            // Try to find by estate_id and month (ignore herd for lookup)
            $grazingData = GrazingData::where('estate_id', $estate->id)
                ->where('month', $validated['month'])
                ->first();
        }

        if ($grazingData) {
            // Update existing record
            $grazingData->update([
                'herd' => $validated['herd'] ?? '',
                'allocated_area' => $validated['allocated_area'] ?? $validated['area'],
                'rotation_period' => $validated['rotation_period'] ?? 62,
                'days_in_month' => $validated['days_in_month'] ?? 30,
                'current_month_ha' => $validated['current_month_ha'] ?? 0,
                'rate_per_ha' => $ratePerHa,
                'deduction_percent' => $validated['deduction_percent'] ?? 0,
                'deduction_amount' => $validated['deduction_amount'] ?? 0,
                'to_date_ha' => $validated['to_date_ha'] ?? 0,
                'total_budget' => $validated['total_budget'] ?? 0,
                'ytd_claim' => $validated['ytd_claim'] ?? 0,
            ]);
        } else {
            // Create new record
            $grazingData = GrazingData::create([
                'estate_id' => $estate->id,
                'month' => $validated['month'],
                'herd' => $validated['herd'] ?? '',
                'allocated_area' => $validated['allocated_area'] ?? $validated['area'],
                'rotation_period' => $validated['rotation_period'] ?? 62,
                'days_in_month' => $validated['days_in_month'] ?? 30,
                'current_month_ha' => $validated['current_month_ha'] ?? 0,
                'rate_per_ha' => $ratePerHa,
                'deduction_percent' => $validated['deduction_percent'] ?? 0,
                'deduction_amount' => $validated['deduction_amount'] ?? 0,
                'to_date_ha' => $validated['to_date_ha'] ?? 0,
                'total_budget' => $validated['total_budget'] ?? 0,
                'ytd_claim' => $validated['ytd_claim'] ?? 0,
            ]);
        }

        // Update blocks if blocks key is present in request (even if empty)
        if ($request->has('blocks')) {
            // Check for duplicate block_ids in the submitted blocks
            if (!empty($validated['blocks'])) {
                $blockIds = array_column($validated['blocks'], 'block_id');
                $duplicates = array_diff_assoc($blockIds, array_unique($blockIds));

                if (!empty($duplicates)) {
                    return back()->withErrors([
                        'blocks' => 'Duplicate block IDs found: ' . implode(', ', array_unique($duplicates))
                    ]);
                }

                // Check if any block_id already exists in a DIFFERENT herd for the same estate and month
                $existingBlocks = GrazingBlock::whereHas('grazingData', function ($query) use ($estate, $validated, $grazingData) {
                    $query->where('estate_id', $estate->id)
                        ->where('month', $validated['month']);

                    // Exclude current grazing data record
                    if ($grazingData) {
                        $query->where('id', '!=', $grazingData->id);
                    }
                })->whereIn('block_id', $blockIds)->pluck('block_id')->toArray();

                if (!empty($existingBlocks)) {
                    return back()->withErrors([
                        'blocks' => 'Block(s) already exist in another herd for this estate and month: ' . implode(', ', $existingBlocks)
                    ]);
                }
            }

            // Delete existing blocks and recreate
            $grazingData->blocks()->delete();

            // Only create new blocks if array is not empty
            if (!empty($validated['blocks'])) {
                foreach ($validated['blocks'] as $blockData) {
                    $grazingData->blocks()->create([
                        'block_id' => $blockData['block_id'],
                        'area' => $blockData['area'] ?? 0,
                        'actual' => $blockData['actual'] ?? 0,
                        'achievement' => $blockData['achievement'] ?? 0,
                        'rate' => $blockData['rate'] ?? $ratePerHa,
                    ]);
                }
            }
        }

        \Log::info('HerdCardController@update completed successfully');
        
        return redirect()->back()->with('success', 'Herd card updated successfully.');
    }

    /**
     * Quick update for inline editing from the table
     */
    public function quickUpdate(Request $request, Estate $estate)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'area' => 'sometimes|numeric|min:0',
            'herd' => 'sometimes|string',
            'rate_per_ha' => 'sometimes|numeric',
            'month' => 'required|string',
        ]);

        // Update estate if name or area provided
        if (isset($validated['name']) || isset($validated['area'])) {
            $estate->update(array_filter([
                'name' => $validated['name'] ?? null,
                'area' => $validated['area'] ?? null,
            ]));
        }

        // Update grazing data if herd or rate_per_ha provided
        if (isset($validated['herd']) || isset($validated['rate_per_ha'])) {
            $grazingData = GrazingData::where('estate_id', $estate->id)
                ->where('month', $validated['month'])
                ->first();

            if ($grazingData) {
                $grazingData->update(array_filter([
                    'herd' => $validated['herd'] ?? null,
                    'rate_per_ha' => $validated['rate_per_ha'] ?? null,
                ]));
            }
        }

        return back()->with('success', 'Updated successfully.');
    }

    /**
     * Store a new herd
     */
    public function storeHerd(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:herds,name',
        ]);

        Herd::create($validated);

        return back()->with('success', 'Herd created successfully.');
    }

    /**
     * Update an existing herd
     */
    public function updateHerd(Request $request, Herd $herd)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:herds,name,' . $herd->id,
        ]);

        // Update the herd name in the herds table
        $oldName = $herd->name;
        $herd->update($validated);

        // Also update all grazing_data records that reference this herd
        GrazingData::where('herd', $oldName)->update(['herd' => $validated['name']]);

        return back()->with('success', 'Herd updated successfully.');
    }

    /**
     * Delete a herd
     */
    public function destroyHerd(Herd $herd)
    {
        // Clear herd reference in grazing_data
        GrazingData::where('herd', $herd->name)->update(['herd' => '']);

        $herd->delete();

        return back()->with('success', 'Herd deleted successfully.');
    }

    /**
     * Store a new estate
     */
    public function storeEstate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|numeric|min:0',
        ]);

        Estate::create([
            'name' => $validated['name'],
            'area' => $validated['area'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Estate created successfully.');
    }

    /**
     * Update an existing estate
     */
    public function updateEstate(Request $request, Estate $estate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'place_name' => 'nullable|string|max:500',
        ]);

        $estate->update([
            'name' => $validated['name'],
            'area' => $validated['area'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'place_name' => $validated['place_name'] ?? null,
        ]);

        return back()->with('success', 'Estate updated successfully.');
    }

    /**
     * Delete an estate
     */
    public function destroyEstate(Estate $estate)
    {
        // Delete related grazing data and blocks
        $grazingDataIds = GrazingData::where('estate_id', $estate->id)->pluck('id');
        GrazingBlock::whereIn('grazing_data_id', $grazingDataIds)->delete();
        GrazingData::where('estate_id', $estate->id)->delete();

        $estate->delete();

        return back()->with('success', 'Estate deleted successfully.');
    }

    /**
     * Bulk delete estates
     */
    public function bulkDestroyEstates(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:estates,id',
        ]);

        foreach ($validated['ids'] as $id) {
            $estate = Estate::find($id);
            if ($estate) {
                // Delete related grazing data and blocks
                $grazingDataIds = GrazingData::where('estate_id', $id)->pluck('id');
                GrazingBlock::whereIn('grazing_data_id', $grazingDataIds)->delete();
                GrazingData::where('estate_id', $id)->delete();
                $estate->delete();
            }
        }

        return back()->with('success', count($validated['ids']) . ' estates deleted successfully.');
    }

    /**
     * Delete a single block
     */
    public function destroyBlock(GrazingBlock $block)
    {
        $block->delete();

        return back()->with('success', 'Block deleted successfully.');
    }

    /**
     * Bulk delete blocks
     */
    public function bulkDestroyBlocks(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:grazing_blocks,id',
        ]);

        GrazingBlock::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', count($validated['ids']) . ' blocks deleted successfully.');
    }
}
