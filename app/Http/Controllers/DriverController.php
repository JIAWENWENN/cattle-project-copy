<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\DriverProfile;
use Inertia\Inertia;

class DriverController extends Controller
{
    public function index()
    {
        $drivers = User::where('role', 'driver')
            ->with(['driverProfile'])
            ->get()
            ->map(function ($user) {
                $profile = $user->driverProfile;
                $deliveryCount = \App\Models\DeliveryRecord::where('user_id', $user->id)->count();
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'phone' => $profile->phone ?? '',
                    'license_number' => $profile->license_number ?? '',
                    'license_expiry' => ($profile && $profile->license_expiry) ? $profile->license_expiry->format('Y-m-d') : '',
                    'vehicle_assigned' => $profile->vehicle_assigned ?? '-',
                    'total_deliveries' => $deliveryCount,
                    'address' => $profile->address ?? '',
                    'emergency_contact' => $profile->emergency_contact ?? '',
                    'notes' => $profile->notes ?? '',
                ];
            });

        return Inertia::render('Driver/Index', [
            'drivers' => $drivers
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->role !== 'driver') {
            abort(403);
        }

        $validated = $request->validate([
            'phone' => 'nullable|string',
            'license_number' => 'nullable|string',
            'license_expiry' => 'nullable|date',
            'vehicle_assigned' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $user->driverProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return redirect()->back()->with('success', 'Driver profile updated successfully.');
    }
}
