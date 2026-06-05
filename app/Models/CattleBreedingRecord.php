<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CattleBreedingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'cattle_id',
        'breeding_date',
        'breeding_method',
        'bull_tag',
        'bull_breed',
        'expected_calving_date',
        'actual_calving_date',
        'calving_outcome',
        'calf_tag',
        'calf_gender',
        'calf_birth_weight',
        'remarks',
    ];

    protected $casts = [
        'breeding_date' => 'date',
        'expected_calving_date' => 'date',
        'actual_calving_date' => 'date',
        'calf_birth_weight' => 'decimal:2',
    ];

    public function cattle()
    {
        return $this->belongsTo(Cattle::class);
    }
}
