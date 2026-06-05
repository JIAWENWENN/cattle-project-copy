<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MortalityCase extends Model
{
    use HasFactory;

    protected $table = 'mortality_cases';

    protected $fillable = [
        'cattle_id',
        'lmc_no',
        'category',
        'location',
        'herd',
        'block',
        'death_date',
        'initial_notes',
        'reported_by',
        'time_of_death',
        'cause_of_death',
        'treatment',
        'additional_notes',
        'status',
        'current_step',
        'created_by',
        'rejection_reason',
        'endorsement_documents',
        'endorsement_step',
        'is_reopened',
    ];

    protected $casts = [
        'death_date' => 'date',
        'endorsement_documents' => 'object',
        'is_reopened' => 'boolean',
    ];

    public function cattle()
    {
        return $this->belongsTo(Cattle::class);
    }

    public function postmortemExamination()
    {
        return $this->hasOne(PostmortemExamination::class);
    }

    public function approvals()
    {
        return $this->hasMany(MortalityApproval::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getNextStepAttribute()
    {
        $steps = ['issued', 'verified', 'checked', 'witness', 'approved'];
        $currentIndex = array_search($this->current_step, $steps);
        return $steps[$currentIndex + 1] ?? null;
    }

    public function getCurrentStepLabelAttribute()
    {
        $labels = [
            'issued' => 'Issued',
            'verified' => 'Verified',
            'checked' => 'Checked',
            'witness' => 'Witness',
            'approved' => 'Approved',
        ];
        return $labels[$this->current_step] ?? $this->current_step;
    }

    public function scopePendingApprovals($query, $role)
    {
        $roleSteps = [
            'estate' => 'issued',
            'veterinary' => 'verified',
            'supervisor' => 'checked',
            'manager' => 'witness',
            'security' => 'approved',
        ];

        $requiredStep = $roleSteps[$role] ?? null;

        if ($requiredStep) {
            return $query->where('current_step', $requiredStep)
                        ->whereIn('status', ['pm_examination', 'under_review']);
        }

        return $query->where('status', 'pending');
    }

    public function scopeWithDetails($query)
    {
        return $query->with(['cattle', 'postmortemExamination', 'creator']);
    }
}
