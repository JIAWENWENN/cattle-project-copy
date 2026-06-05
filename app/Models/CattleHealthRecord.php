<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CattleHealthRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'cattle_id',
        'source_type',
        'source_id',
        'reference_no',
        'category',
        'operating_unit',
        'colour',
        'status',
        'description',
        'treatment',
        'dosage',
        'medication',
        'follow_up_required',
        'notes',
        'date',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'follow_up_required' => 'boolean',
        'metadata' => 'array',
    ];

    public function cattle()
    {
        return $this->belongsTo(Cattle::class);
    }
}
