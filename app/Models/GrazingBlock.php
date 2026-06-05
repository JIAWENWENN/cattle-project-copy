<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrazingBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'grazing_data_id',
        'block_id',
        'area',
        'actual',
        'achievement',
        'rate',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'actual' => 'decimal:2',
        'achievement' => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    /**
     * Get the parent grazing data
     */
    public function grazingData()
    {
        return $this->belongsTo(GrazingData::class);
    }

    /**
     * Calculate percentage achieved
     */
    public function getPercentAchievedAttribute()
    {
        return $this->area > 0
            ? round(($this->actual / $this->area) * 100, 1)
            : 0;
    }

    /**
     * Calculate total amount
     */
    public function getTotalAttribute()
    {
        return round($this->achievement * $this->rate, 2);
    }
}
