<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryRecordController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isDriver = $user->role === 'driver';
        $driverId = $request->input('driver_id');
        
        // Build the query
        $query = DeliveryRecord::with('driver')
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc');
        
        // If the user is a driver, only show their deliveries
        if ($isDriver) {
            $query->where('user_id', $user->id);
            $selectedDriverId = $user->id;
        } elseif ($driverId) {
            $query->where('user_id', $driverId);
            $selectedDriverId = $driverId;
        } else {
            $selectedDriverId = null;
        }
        
        $deliveries = $query->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->delivery_number,
                    'db_id' => $delivery->id,
                    'date' => $delivery->date,
                    'time' => $delivery->time,
                    'driver' => $delivery->driver->name ?? 'Unknown',
                    'user_id' => $delivery->user_id,
                    'vehicle' => $delivery->vehicle,
                    'origin' => $delivery->origin,
                    'destination' => $delivery->destination,
                    'cargo_type' => $delivery->cargo_type,
                    'cargo_weight' => $delivery->cargo_weight,
                    'status' => $delivery->status,
                    'delivery_notes' => $delivery->delivery_notes,
                    'customer' => $delivery->customer,
                ];
            });

        $drivers = User::where('role', 'driver')->get(['id', 'name']);
        
        // Calculate stats for the selected driver or all if none selected
        $statsQuery = DeliveryRecord::query();
        if ($selectedDriverId) {
            $statsQuery->where('user_id', $selectedDriverId);
        }

        $driverStats = [
            'total' => (clone $statsQuery)->count(),
            'delivered' => (clone $statsQuery)->where('status', 'delivered')->count(),
            'in_transit' => (clone $statsQuery)->where('status', 'in_transit')->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Driver/DeliveryHistory', [
            'deliveries' => $deliveries,
            'drivers' => $drivers,
            'isDriver' => $isDriver,
            'driverStats' => $driverStats,
            'selectedDriverId' => $selectedDriverId ? (int)$selectedDriverId : null,
            'currentDriverId' => $isDriver ? $user->id : null,
            'currentDriverName' => $isDriver ? $user->name : null,
        ]);
    }

    public function shiftSchedule()
    {
        $deliveries = DeliveryRecord::with('driver')
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();

        return Inertia::render('Driver/ShiftSchedule', [
            'deliveries' => $deliveries
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required',
            'user_id' => 'required|exists:users,id',
            'vehicle' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'cargo_type' => 'required|string',
            'cargo_weight' => 'required|string',
            'status' => 'required|in:pending,in_transit,delivered,cancelled',
            'delivery_notes' => 'nullable|string',
            'customer' => 'required|string',
        ]);

        // Generate delivery number
        $latest = DeliveryRecord::orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $validated['delivery_number'] = 'DEL-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

        $validated['time'] = Carbon::parse($validated['time'])->format('H:i');

        DeliveryRecord::create($validated);

        return redirect()->back()->with('success', 'Delivery record created successfully.');
    }

    public function update(Request $request, DeliveryRecord $deliveryRecord)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required',
            'user_id' => 'required|exists:users,id',
            'vehicle' => 'required|string',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'cargo_type' => 'required|string',
            'cargo_weight' => 'required|string',
            'status' => 'required|in:pending,in_transit,delivered,cancelled',
            'delivery_notes' => 'nullable|string',
            'customer' => 'required|string',
        ]);

        $validated['time'] = Carbon::parse($validated['time'])->format('H:i');

        $deliveryRecord->update($validated);

        return redirect()->back()->with('success', 'Delivery record updated successfully.');
    }

    public function destroy(DeliveryRecord $deliveryRecord)
    {
        $deliveryRecord->delete();

        return redirect()->back()->with('success', 'Delivery record deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:delivery_records,id'],
        ]);

        DeliveryRecord::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', count($validated['ids']) . ' delivery record(s) deleted successfully.');
    }
}
