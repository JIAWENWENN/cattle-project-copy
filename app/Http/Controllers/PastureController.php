<?php

namespace App\Http\Controllers;

use App\Models\Estate;
use App\Models\PastureBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class PastureController extends Controller
{
    private function normalizeOperatingUnitName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    private function operatingUnitNameExists(string $name, ?int $ignoreEstateId = null): bool
    {
        $normalized = $this->normalizeOperatingUnitName($name);
        if ($normalized === '') {
            return false;
        }

        $query = Estate::query()->whereRaw('LOWER(TRIM(name)) = ?', [$normalized]);

        if ($ignoreEstateId) {
            $query->where('id', '!=', $ignoreEstateId);
        }

        return $query->exists();
    }

    private function validateOperatingUnitName(ValidationValidator $validator, string $name, ?int $ignoreEstateId = null): void
    {
        $validator->after(function (ValidationValidator $v) use ($name, $ignoreEstateId) {
            if ($this->operatingUnitNameExists($name, $ignoreEstateId)) {
                $v->errors()->add('name', 'A record with this operating unit name already exists.');
            }
        });
    }

    private function mapOperatingUnit(Estate $estate): array
    {
        return [
            'id' => $estate->id,
            'name' => $estate->name,
            'area' => (float) $estate->area,
            'latitude' => $estate->latitude ? (float) $estate->latitude : null,
            'longitude' => $estate->longitude ? (float) $estate->longitude : null,
            'place_name' => $estate->place_name,
            'blocks' => $estate->pastureBlocks->map(function ($block) {
                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'area' => (float) $block->area,
                    'phases' => $block->phases->map(function ($phase) {
                        return [
                            'id' => $phase->id,
                            'name' => $phase->name,
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    public function index()
    {
        $operatingUnits = Estate::where('is_active', true)
            ->with(['pastureBlocks.phases'])
            ->orderBy('name')
            ->get()
            ->map(fn ($estate) => $this->mapOperatingUnit($estate))
            ->values();

        return Inertia::render('Pasture/All', [
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function show(Estate $estate)
    {
        $estate->load(['pastureBlocks.phases']);

        $operatingUnits = Estate::where('is_active', true)
            ->with(['pastureBlocks.phases'])
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => $this->mapOperatingUnit($item))
            ->values();

        return Inertia::render('Pasture/Show', [
            'operatingUnit' => $this->mapOperatingUnit($estate),
            'operatingUnits' => $operatingUnits,
            'viewMode' => request()->query('view') === 'all' ? 'all' : 'unit',
        ]);
    }

    public function storeOperatingUnit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'area' => 'required|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'place_name' => 'nullable|string|max:500',
        ]);

        $this->validateOperatingUnitName($validator, (string) $request->input('name'));

        $validated = $validator->validate();

        Estate::create([
            'name' => $validated['name'],
            'area' => $validated['area'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'place_name' => $validated['place_name'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Operating unit created successfully.');
    }

    public function updateOperatingUnit(Request $request, Estate $estate)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'area' => 'required|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'place_name' => 'nullable|string|max:500',
        ]);

        $this->validateOperatingUnitName($validator, (string) $request->input('name'), $estate->id);

        $validated = $validator->validate();

        $estate->update([
            'name' => $validated['name'],
            'area' => $validated['area'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'place_name' => $validated['place_name'] ?? null,
        ]);

        return back()->with('success', 'Operating unit updated successfully.');
    }

    public function destroyOperatingUnit(Estate $estate)
    {
        $estate->delete();
        return back()->with('success', 'Operating unit deleted successfully.');
    }

    public function storeBlock(Request $request, Estate $estate)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'area' => 'nullable|numeric|min:0',
        ]);

        PastureBlock::create([
            'estate_id' => $estate->id,
            'name' => $validated['name'],
            'area' => $validated['area'] ?? 0,
        ]);

        return back()->with('success', 'Block created successfully.');
    }

    public function storePhase(Request $request, \App\Models\PastureBlock $pastureBlock)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pasture_phases', 'name')->where(function ($query) use ($pastureBlock) {
                    return $query->where('pasture_block_id', $pastureBlock->id);
                }),
            ],
        ]);

        \App\Models\PasturePhase::create([
            'pasture_block_id' => $pastureBlock->id,
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Phase created successfully.');
    }
}
