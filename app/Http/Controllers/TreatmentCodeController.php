<?php

namespace App\Http\Controllers;

use App\Models\TreatmentCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TreatmentCodeController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'manager'])) {
            abort(403, 'Access denied');
        }

        $treatmentCodes = TreatmentCode::orderBy('label')->get();

        return Inertia::render('Health/TreatmentCodesManage', [
            'treatmentCodes' => $treatmentCodes,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'manager'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied'], 403);
            }
            abort(403, 'Access denied');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:treatment_codes,code',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $treatmentCode = TreatmentCode::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Treatment code created successfully',
                'data' => $treatmentCode
            ]);
        }

        return back()->with('success', 'Treatment code created successfully');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'manager'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied'], 403);
            }
            abort(403, 'Access denied');
        }

        $treatmentCode = TreatmentCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:treatment_codes,code,' . $id,
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $treatmentCode->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Treatment code updated successfully',
                'data' => $treatmentCode
            ]);
        }

        return back()->with('success', 'Treatment code updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'manager'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied'], 403);
            }
            abort(403, 'Access denied');
        }

        $treatmentCode = TreatmentCode::findOrFail($id);

        // Check if code is being used in treatments
        $treatmentCount = \DB::table('treatments')->where('treatment_code', $treatmentCode->code)->count();

        if ($treatmentCount > 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cannot delete treatment code that is currently in use by ' . $treatmentCount . ' treatment records'], 422);
            }
            return back()->withErrors(['error' => 'Cannot delete treatment code that is currently in use by ' . $treatmentCount . ' treatment records']);
        }

        $treatmentCode->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Treatment code deleted successfully'
            ]);
        }

        return back()->with('success', 'Treatment code deleted successfully');
    }

    public function getActiveCodes()
    {
        $codes = TreatmentCode::active()->orderBy('label')->get();
        return response()->json($codes);
    }
}