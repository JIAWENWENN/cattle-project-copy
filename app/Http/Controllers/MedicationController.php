<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class MedicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Medication::with('history');

        // Search logic
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('generic', 'like', "%{$request->search}%")
                    ->orWhere('batch_number', 'like', "%{$request->search}%");
            });
        }

        // Category filter
        if ($request->category) {
            $query->where('category', $request->category);
        }

        // Sorting logic
        $sortColumn = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $standardColumns = ['name', 'generic', 'category', 'stock', 'expiry_date', 'created_at'];

        if (in_array($sortColumn, $standardColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(custom_fields, '$.\"$sortColumn\"')) $sortDirection");
        }

        return Inertia::render('Inventory/MedicationManager', [
            'medications' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'category', 'sort', 'direction']),
            'dynamicCategories' => Medication::distinct()->pluck('category')->filter()->values(),
            'savedColumns' => Auth::user()->medication_columns
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stock' => 'required|numeric',
        ]);

        $medication = Medication::create($request->all());

        $medication->history()->create([
            'user' => Auth::user()->name,
            'action' => 'Create',
            'detail' => "Initial stock of {$medication->stock} added for {$medication->name}."
        ]);

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $medication = Medication::findOrFail($id);
        $oldStock = $medication->stock;
        $medication->update($request->all());

        if ($oldStock != $medication->stock) {
            $medication->history()->create([
                'user' => Auth::user()->name,
                'action' => 'Update',
                'detail' => "Stock adjusted from $oldStock to {$medication->stock}."
            ]);
        }

        return redirect()->back();
    }

    public function destroy($id)
    {
        Medication::destroy($id);
        return redirect()->back();
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:medications,id'],
        ]);

        Medication::whereIn('id', $validated['ids'])->delete();

        return redirect()->back();
    }

    public function saveColumns(Request $request)
    {
        Auth::user()->update(['medication_columns' => $request->columns]);
        return redirect()->back();
    }
}
