<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area',
        'latitude',
        'longitude',
        'place_name',
        'is_active',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    /**
     * Get all grazing data records for this estate
     */
    public function grazingData()
    {
        return $this->hasMany(GrazingData::class);
    }

    /**
     * Get the latest grazing data record
     */
    public function latestGrazingData()
    {
        return $this->hasOne(GrazingData::class)->latest();
    }

    public function pastureBlocks()
    {
        return $this->hasMany(PastureBlock::class)->orderBy('name');
    }
}
