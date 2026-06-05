<?php

namespace App\Http\Controllers;

use App\Models\Estate;
use App\Models\Herd;
use App\Models\GrazingData;
use App\Models\GrazingBlock;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DataInputController extends Controller
{
    /**
     * Display the data input form
     */
    public function index(Request $request)
    {
        $estatesCollection = Estate::where('is_active', true)->orderBy('name')->get();

        $estates = $estatesCollection->map(function ($estate, $index) {
            return [
                'id' => $index,
                'name' => $estate->name,
                'area' => floatval($estate->area),
                'latitude' => $estate->latitude ? floatval($estate->latitude) : null,
                'longitude' => $estate->longitude ? floatval($estate->longitude) : null,
                'place_name' => $estate->place_name,
                'db_id' => $estate->id,
            ];
        })->values();

        $herds = Herd::all()->map(function ($herd) {
            return [
                'id' => $herd->id,
                'name' => $herd->name,
            ];
        })->toArray();

        // Get the most recent grazing data or default values
        $firstEstate = $estatesCollection->first();
        $savedData = null;
        $blocks = [];

        if ($firstEstate) {
            $savedData = GrazingData::where('estate_id', $firstEstate->id)
                ->latest()
                ->first();

            if ($savedData) {
                $blocks = $savedData->blocks->map(function ($block) {
                    return [
                        'id' => $block->block_id,
                        'area' => floatval($block->area),
                        'actual' => floatval($block->actual),
                        'achievement' => floatval($block->achievement),
                        'rate' => floatval($block->rate),
                    ];
                })->toArray();
            }
        }

        return Inertia::render('DataInput/Index', [
            'estates' => $estates,
            'herds' => $herds,
            'blocks' => $blocks,
            'selectedEstateId' => 0,
            // Don't pass month here - let frontend use current month as default
            // This prevents overwriting user's intended month with old data's month
            'savedData' => $savedData ? [
                'allocated_area' => floatval($savedData->allocated_area),
                'rotation_period' => $savedData->rotation_period,
                'days_in_month' => $savedData->days_in_month,
                'current_month_ha' => floatval($savedData->current_month_ha),
                'rate_per_ha' => floatval($savedData->rate_per_ha),
                'deduction_percent' => floatval($savedData->deduction_percent),
                'deduction_amount' => floatval($savedData->deduction_amount),
                'to_date_ha' => floatval($savedData->to_date_ha),
                'total_budget' => floatval($savedData->total_budget),
                'ytd_claim' => floatval($savedData->ytd_claim),
            ] : null,
        ]);
    }

    /**
     * Store the grazing data
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'estate_id' => 'required|integer',
            'herd' => 'nullable|string',
            'month' => 'required|string',
            'allocated_area' => 'required|numeric',
            'rotation_period' => 'required|numeric',
            'days_in_month' => 'required|integer',
            'current_month_ha' => 'required|numeric',
            'rate_per_ha' => 'required|numeric',
            'deduction_percent' => 'nullable|numeric',
            'deduction_amount' => 'nullable|numeric',
            'to_date_ha' => 'required|numeric',
            'total_budget' => 'required|numeric',
            'ytd_claim' => 'required|numeric',
            'blocks' => 'required|array',
            'blocks.*.id' => 'required|string',
            'blocks.*.area' => 'required|numeric',
            'blocks.*.actual' => 'required|numeric',
            'blocks.*.achievement' => 'required|numeric',
            'blocks.*.rate' => 'required|numeric',
        ]);

        // Get estate from the index (must match the active estates list shown in frontend)
        $estates = Estate::where('is_active', true)->orderBy('name')->get();
        $estate = $estates[$validated['estate_id']] ?? null;

        if (!$estate) {
            return back()->withErrors(['estate_id' => 'Invalid estate selected.']);
        }

        // Check for duplicate block_ids in the submitted blocks
        $blockIds = array_column($validated['blocks'], 'id');
        $duplicates = array_diff_assoc($blockIds, array_unique($blockIds));

        if (!empty($duplicates)) {
            return back()->withErrors([
                'blocks' => 'Duplicate block IDs found: ' . implode(', ', array_unique($duplicates))
            ]);
        }

        // Find existing grazing data for this estate/month/herd
        $existingGrazingData = GrazingData::where('estate_id', $estate->id)
            ->where('month', $validated['month'])
            ->where('herd', $validated['herd'] ?? '')
            ->first();

        // Check if any block_id already exists in a DIFFERENT herd for the same estate and month
        $existingBlocks = GrazingBlock::whereHas('grazingData', function ($query) use ($estate, $validated, $existingGrazingData) {
            $query->where('estate_id', $estate->id)
                ->where('month', $validated['month']);

            // Exclude current grazing data record if it exists
            if ($existingGrazingData) {
                $query->where('id', '!=', $existingGrazingData->id);
            }
        })->whereIn('block_id', $blockIds)->pluck('block_id')->toArray();

        if (!empty($existingBlocks)) {
            return back()->withErrors([
                'blocks' => 'Block(s) already exist in another herd for this estate and month: ' . implode(', ', $existingBlocks)
            ]);
        }

        // Create or update grazing data (unique per estate + month + herd)
        $grazingData = GrazingData::updateOrCreate(
            [
                'estate_id' => $estate->id,
                'month' => $validated['month'],
                'herd' => $validated['herd'] ?? '',
            ],
            [
                'allocated_area' => $validated['allocated_area'],
                'rotation_period' => $validated['rotation_period'],
                'days_in_month' => $validated['days_in_month'],
                'current_month_ha' => $validated['current_month_ha'],
                'rate_per_ha' => $validated['rate_per_ha'],
                'deduction_percent' => $validated['deduction_percent'] ?? 0,
                'deduction_amount' => $validated['deduction_amount'] ?? 0,
                'to_date_ha' => $validated['to_date_ha'],
                'total_budget' => $validated['total_budget'],
                'ytd_claim' => $validated['ytd_claim'],
            ]
        );

        // Delete existing blocks and recreate
        $grazingData->blocks()->delete();

        foreach ($validated['blocks'] as $blockData) {
            $grazingData->blocks()->create([
                'block_id' => $blockData['id'],
                'area' => $blockData['area'],
                'actual' => $blockData['actual'],
                'achievement' => $blockData['achievement'],
                'rate' => $blockData['rate'],
            ]);
        }

        return back()->with('success', 'Grazing data saved successfully.');
    }

    /**
     * Get grazing data for a specific estate, optionally filtered by month and herd
     */
    public function getEstateData(Request $request, $estateIndex)
    {
        $estates = Estate::where('is_active', true)->orderBy('name')->get();
        $estate = $estates[$estateIndex] ?? null;

        if (!$estate) {
            return response()->json(['error' => 'Estate not found'], 404);
        }

        $query = GrazingData::where('estate_id', $estate->id);

        // Filter by month if provided
        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        // Filter by herd if provided
        if ($request->has('herd') && $request->herd) {
            $query->where('herd', $request->herd);
        }

        $grazingData = $query->latest()->first();

        if (!$grazingData) {
            return response()->json([
                'savedData' => null,
                'blocks' => [],
                'estate' => [
                    'name' => $estate->name,
                    'area' => floatval($estate->area),
                ],
            ]);
        }

        return response()->json([
            'savedData' => [
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
            ],
            'blocks' => $grazingData->blocks->map(function ($block) {
                return [
                    'id' => $block->block_id,
                    'area' => floatval($block->area),
                    'actual' => floatval($block->actual),
                    'achievement' => floatval($block->achievement),
                    'rate' => floatval($block->rate),
                ];
            })->toArray(),
            'estate' => [
                'name' => $estate->name,
                'area' => floatval($estate->area),
            ],
        ]);
    }

    /**
     * Store a new estate
     */
    public function storeEstate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'place_name' => 'nullable|string|max:500',
        ]);

        Estate::create([
            'name' => $validated['name'],
            'area' => $validated['area'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'place_name' => $validated['place_name'] ?? null,
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
     * Delete an estate (soft delete - sets is_active to false)
     */
    public function destroyEstate(Estate $estate)
    {
        $estate->update(['is_active' => false]);

        return back()->with('success', 'Estate deleted successfully.');
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
     * Update a herd
     */
    public function updateHerd(Request $request, Herd $herd)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:herds,name,' . $herd->id,
        ]);

        $herd->update($validated);

        return back()->with('success', 'Herd updated successfully.');
    }

    /**
     * Delete a herd
     */
    public function destroyHerd(Herd $herd)
    {
        $herd->delete();

        return back()->with('success', 'Herd deleted successfully.');
    }
}
