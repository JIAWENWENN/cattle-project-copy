<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    protected $fillable = [
        'name', 'generic', 'category', 'batch_number',
        'batch_qty', 'stock', 'receival_date', 'expiry_date',
        'remark', 'custom_fields', 'status'
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'receival_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(MedicationHistory::class)->latest();
    }
}
