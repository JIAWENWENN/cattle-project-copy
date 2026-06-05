<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRecord extends Model
{
    protected $fillable = [
        'delivery_number',
        'date',
        'time',
        'user_id',
        'vehicle',
        'origin',
        'destination',
        'cargo_type',
        'cargo_weight',
        'status',
        'delivery_notes',
        'customer',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
