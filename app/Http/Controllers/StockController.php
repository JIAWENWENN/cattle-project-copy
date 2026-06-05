<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Medication;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $this->syncStockSettings();

        $query = Stock::with(['stockable', 'supplier', 'history']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHasMorph('stockable', [Medication::class], function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('category', 'like', "%{$search}%");
                })
                ->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->category) {
            $query->whereHasMorph('stockable', [Medication::class], function ($sq) use ($request) {
                $sq->where('category', $request->category);
            });
        }

        if ($request->status) {
            $stocks = $query->get();
            if ($request->status === 'critical') {
                $query->whereIn('id', $stocks->filter(fn($s) => $s->current_stock < $s->min_threshold)->pluck('id'));
            } elseif ($request->status === 'ok') {
                $query->whereIn('id', $stocks->filter(fn($s) => $s->current_stock >= $s->min_threshold)->pluck('id'));
            }
        }

        $sortColumn = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortColumn, $sortDirection);

        $stocks = $query->paginate(15)->withQueryString();

        $stocks->getCollection()->transform(function ($stock) {
            return [
                'id' => $stock->id,
                'stockable_type' => $stock->stockable_type,
                'stockable_id' => $stock->stockable_id,
                'name' => $stock->name,
                'sku' => $stock->stockable?->sku ?? $stock->stockable?->batch_number ?? '-',
                'category' => $stock->category,
                'current_stock' => $stock->current_stock,
                'unit' => $stock->unit,
                'expiry_date' => $stock->expiry_date?->format('Y-m-d'),
                'source_type' => $stock->source_type,
                'supplier_id' => $stock->supplier_id,
                'supplier' => $stock->supplier,
                'min_threshold' => $stock->min_threshold,
                'safety_stock' => $stock->safety_stock,
                'oso_avg' => $stock->oso_avg,
                'lead_time' => $stock->lead_time,
                'day_cover' => $stock->day_cover,
                'remark' => $stock->remark,
                'history' => $stock->history,
                'created_at' => $stock->created_at,
            ];
        });

        $categories = Medication::distinct()->pluck('category')->filter()->values();

        $allStocks = Stock::with('stockable')->get();
        $totalItems = $allStocks->count();
        $inStockCount = $allStocks->filter(fn($s) => $s->current_stock >= $s->min_threshold)->count();
        $criticalCount = $allStocks->filter(fn($s) => $s->current_stock < $s->min_threshold)->count();

        return Inertia::render('Inventory/StockManager', [
            'stocks' => $stocks,
            'filters' => $request->only(['search', 'category', 'status', 'sort', 'direction']),
            'suppliers' => Supplier::all(),
            'categories' => $categories,
            'stats' => [
                'total' => $totalItems,
                'in_stock' => $inStockCount,
                'critical' => $criticalCount,
            ],
        ]);
    }

    private function syncStockSettings()
    {
        $medications = Medication::all();
        foreach ($medications as $medication) {
            Stock::firstOrCreate(
                ['stockable_type' => 'App\Models\Medication', 'stockable_id' => $medication->id],
                ['min_threshold' => 50, 'safety_stock' => 20, 'oso_avg' => 0, 'lead_time' => 5]
            );
        }

        Stock::where('stockable_type', 'App\Models\Medication')
            ->whereNotIn('stockable_id', Medication::pluck('id'))
            ->delete();
    }

    public function update(Request $request, $id)
    {
        $stock = Stock::findOrFail($id);
        $oldValues = $stock->only(['supplier_id', 'min_threshold', 'safety_stock', 'oso_avg', 'lead_time']);

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'min_threshold' => 'nullable|numeric|min:0',
            'safety_stock' => 'nullable|numeric|min:0',
            'oso_avg' => 'nullable|numeric|min:0',
            'lead_time' => 'nullable|integer|min:0',
            'remark' => 'nullable|string',
        ]);

        $stock->update($validated);

        $changes = [];
        if ($oldValues['supplier_id'] != $stock->supplier_id) {
            $oldSupplier = $oldValues['supplier_id'] ? Supplier::find($oldValues['supplier_id'])?->name : 'None';
            $newSupplier = $stock->supplier?->name ?? 'None';
            $changes[] = "Supplier: {$oldSupplier} → {$newSupplier}";
        }
        if ($oldValues['min_threshold'] != $stock->min_threshold) {
            $changes[] = "Min Threshold: {$oldValues['min_threshold']} → {$stock->min_threshold}";
        }
        if ($oldValues['oso_avg'] != $stock->oso_avg) {
            $changes[] = "OSO Avg: {$oldValues['oso_avg']} → {$stock->oso_avg}";
        }

        if (count($changes) > 0) {
            $stock->history()->create([
                'user' => Auth::user()->name,
                'action' => 'Settings Updated',
                'detail' => implode(', ', $changes),
            ]);
        }

        return redirect()->back();
    }

    public function destroy($id)
    {
        Stock::destroy($id);
        return redirect()->back();
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:stocks,id',
        ]);

        Stock::whereIn('id', $validated['ids'])->delete();

        return redirect()->back();
    }

    public function bulkUpdateSupplier(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:stocks,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        Stock::whereIn('id', $validated['ids'])->update([
            'supplier_id' => $validated['supplier_id']
        ]);

        return redirect()->back();
    }

    public function export(Request $request)
    {
        $stocks = Stock::with(['stockable', 'supplier'])->get();

        $csv = "Name,Category,Source,Stock,Unit,Supplier,Min Threshold,Safety Stock,OSO Avg,Lead Time,Expiry Date,Status\n";

        foreach ($stocks as $stock) {
            $status = $stock->current_stock >= $stock->min_threshold ? 'In Stock' : 'Low Stock';
            if ($stock->current_stock <= 0) $status = 'Out of Stock';

            $csv .= implode(',', [
                '"' . str_replace('"', '""', $stock->name) . '"',
                '"' . str_replace('"', '""', $stock->category) . '"',
                '"' . $stock->source_type . '"',
                $stock->current_stock,
                '"' . $stock->unit . '"',
                '"' . str_replace('"', '""', $stock->supplier?->name ?? '-') . '"',
                $stock->min_threshold,
                $stock->safety_stock,
                $stock->oso_avg,
                $stock->lead_time,
                $stock->expiry_date?->format('Y-m-d') ?? '-',
                '"' . $status . '"',
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="stock-alert-export-' . date('Y-m-d') . '.csv"');
    }
}
