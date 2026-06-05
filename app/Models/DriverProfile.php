<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'license_number',
        'license_expiry',
        'vehicle_assigned',
        'address',
        'emergency_contact',
        'total_deliveries',
        'notes',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'total_deliveries' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
