<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_no',
        'cattle_id',
        'tag_no',
        'category',
        'operating_unit',
        'colour',
        'date',
        'symptoms',
        'treatment',
        'treatment_code',
        'dosage',
        'remarks',
        'follow_up_required',
        'follow_up_date',
        'status',
        'current_step',
        'is_reopened',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'follow_up_date' => 'date',
        'follow_up_required' => 'boolean',
        'is_reopened' => 'boolean',
    ];

    public function cattle()
    {
        return $this->belongsTo(Cattle::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getNextStepAttribute()
    {
        $steps = ['prepared', 'checked', 'approved'];
        $currentIndex = array_search($this->current_step, $steps);
        return $steps[$currentIndex + 1] ?? null;
    }

    public function getCurrentStepLabelAttribute()
    {
        $labels = [
            'prepared' => 'Prepared',
            'checked' => 'Checked',
            'approved' => 'Approved',
        ];
        return $labels[$this->current_step] ?? $this->current_step;
    }

    public function scopeWithDetails($query)
    {
        return $query->with(['cattle', 'creator']);
    }
}
