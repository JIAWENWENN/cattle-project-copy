<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentMonthlyWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'operating_unit',
        'endorsement_step',
        'endorsement_documents',
        'status',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'endorsement_documents' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];
}

