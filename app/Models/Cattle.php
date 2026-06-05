<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cattle extends Model
{
    use HasFactory;

    protected $table = 'cattle';

    protected $fillable = [
        // Basic Information
        'tag_no',
        'lcc_running_number',
        'calving_record_id',
        'category',
        'coat_colour',
        'birth_date',
        'gender',

        // Receival & Condition
        'receival_weight',
        'general_condition',
        'operating_unit',
        'location_block',
        'location_phase',

        // Dam (Mother) Genealogy
        'dam_tag',
        'dam_category',
        'dam_colour',

        // Sire (Father) Genealogy
        'sire_tag',
        'sire_category',
        'sire_coat_colour',

        // Milestones
        'weaning_weight',
        'yearling_weight',

        // Additional
        'status',
        'remarks',
        'profile_picture',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'receival_weight' => 'decimal:2',
        'weight' => 'decimal:2',
        'weaning_weight' => 'decimal:2',
        'yearling_weight' => 'decimal:2'
    ];

    public function setCoatColourAttribute($value): void
    {
        $this->attributes['coat_colour'] = is_null($value) ? '' : trim((string) $value);
    }

    // Relationships
    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }

    public function movements()
    {
        return $this->hasMany(TransferLivestock::class, 'tag_no', 'tag_no');
    }

    public function breedingRecords()
    {
        return $this->hasMany(CattleBreedingRecord::class);
    }
}
