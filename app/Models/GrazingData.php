<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrazingData extends Model
{
    use HasFactory;

    protected $fillable = [
        'estate_id',
        'herd',
        'month',
        'allocated_area',
        'rotation_period',
        'days_in_month',
        'current_month_ha',
        'rate_per_ha',
        'deduction_percent',
        'deduction_amount',
        'to_date_ha',
        'total_budget',
        'ytd_claim',
    ];

    protected $casts = [
        'allocated_area' => 'decimal:2',
        'rotation_period' => 'integer',
        'days_in_month' => 'integer',
        'current_month_ha' => 'decimal:2',
        'rate_per_ha' => 'decimal:2',
        'deduction_percent' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'to_date_ha' => 'decimal:2',
        'total_budget' => 'decimal:2',
        'ytd_claim' => 'decimal:2',
    ];

    /**
     * Get the estate for this grazing data
     */
    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Get all blocks for this grazing data
     */
    public function blocks()
    {
        return $this->hasMany(GrazingBlock::class);
    }

    /**
     * Calculate daily capacity
     */
    public function getDailyCapacityAttribute()
    {
        return $this->rotation_period > 0
            ? round($this->allocated_area / $this->rotation_period, 2)
            : 0;
    }

    /**
     * Calculate current month percentage
     */
    public function getCurrentMonthPercentAttribute()
    {
        return $this->allocated_area > 0
            ? round(($this->current_month_ha / $this->allocated_area) * 100, 1)
            : 0;
    }

    /**
     * Calculate gross claim
     */
    public function getGrossClaimAttribute()
    {
        return round($this->current_month_ha * $this->rate_per_ha, 2);
    }

    /**
     * Calculate net claim
     */
    public function getNetClaimAttribute()
    {
        return round($this->gross_claim - $this->deduction_amount, 2);
    }

    /**
     * Calculate budget remaining
     */
    public function getBudgetRemainingAttribute()
    {
        return round($this->total_budget - $this->ytd_claim, 2);
    }
}
