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
        'week',
        'symptoms',
        'treatment',
        'treatment_code',
        'dosage',
        'remarks',
        'follow_up_required',
        'follow_up_date',
        'follow_up_done',
        'status',
        'current_step',
        'endorsement_documents',
        'endorsement_step',
        'is_reopened',
        'created_by',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'follow_up_date' => 'date',
        'follow_up_required' => 'boolean',
        'follow_up_done' => 'boolean',
        'endorsement_documents' => 'object',
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

    public function scopePendingApprovals($query, $role)
    {
        $roleSteps = [
            'livestock' => 0,   // Prepared by
            'supervisor' => 1,  // Checked by
            'manager' => 2,     // Approved by
        ];

        $requiredStep = $roleSteps[$role] ?? null;

        if ($requiredStep !== null) {
            return $query->where('endorsement_step', $requiredStep)
                        ->whereIn('status', ['pending', 'under_review']);
        }

        return $query->where('status', 'pending');
    }

    public function scopeWithDetails($query)
    {
        return $query->with(['cattle', 'creator']);
    }
}
