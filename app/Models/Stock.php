<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'supplier_id',
        'min_threshold',
        'safety_stock',
        'oso_avg',
        'lead_time',
        'remark',
    ];

    protected $casts = [
        'min_threshold' => 'integer',
        'safety_stock' => 'integer',
        'oso_avg' => 'float',
        'lead_time' => 'integer',
    ];

    /**
     * Get the parent stockable model (Medication)
     */
    public function stockable()
    {
        return $this->morphTo();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function history()
    {
        return $this->hasMany(StockHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get item name from the linked medication
     */
    public function getNameAttribute()
    {
        return $this->stockable?->name ?? 'Unknown';
    }

    /**
     * Get category from the linked medication
     */
    public function getCategoryAttribute()
    {
        return $this->stockable?->category ?? '-';
    }

    /**
     * Get current stock quantity from the linked medication
     */
    public function getCurrentStockAttribute()
    {
        return $this->stockable?->stock ?? 0;
    }

    /**
     * Get unit from the linked medication
     */
    public function getUnitAttribute()
    {
        return $this->stockable?->unit ?? 'Units';
    }

    /**
     * Get expiry date from the linked medication
     */
    public function getExpiryDateAttribute()
    {
        return $this->stockable?->expiry_date ?? null;
    }

    /**
     * Get source type label
     */
    public function getSourceTypeAttribute()
    {
        return 'Medication';
    }

    /**
     * Calculate day cover
     */
    public function getDayCoverAttribute()
    {
        if (!$this->oso_avg || $this->oso_avg <= 0) return 0;
        return round($this->current_stock / $this->oso_avg, 1);
    }
}
