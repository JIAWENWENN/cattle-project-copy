<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationHistory extends Model
{
    // Add this property to allow mass assignment
    protected $fillable = [
        'medication_id',
        'user',
        'action',
        'detail'
    ];

    /**
     * Get the medication that owns the history.
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
