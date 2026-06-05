<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostmortemExamination extends Model
{
    use HasFactory;

    protected $table = 'postmortem_examinations';

    protected $fillable = [
        'mortality_case_id',
        'examination_date',
        'examination_time',
        'performed_by',
        'external_skin',
        'external_eyes',
        'external_mouth',
        'external_nostrils',
        'external_ears',
        'external_limbs',
        'external_anus',
        'external_genital',
        'external_general',
        'heart_findings',
        'trachea_findings',
        'lung_floating_test',
        'lung_floating_test_details',
        'diaphragma_test',
        'diaphragma_test_details',
        'kidney_findings',
        'urinary_bladder_findings',
        'bladder_findings',
        'liver_findings',
        'spleen_findings',
        'joint_findings',
        'subcutaneous_findings',
        'rumen_findings',
        'reticulum_findings',
        'omasum_findings',
        'abomasum_findings',
        'small_intestine_findings',
        'colon_findings',
        'reproductive_organ_findings',
        'preliminary_diagnosis',
        'confirmed_cause_of_death',
        'cause_of_death_category',
        'additional_notes',
        'recommendations',
        'status',
    ];

    protected $casts = [
        'examination_date' => 'date',
        'examination_time' => 'datetime:H:i',
        'lung_floating_test' => 'boolean',
        'diaphragma_test' => 'boolean',
    ];

    public function mortalityCase()
    {
        return $this->belongsTo(MortalityCase::class);
    }

    public function examiner()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approvals()
    {
        return $this->hasMany(MortalityApproval::class, 'postmortem_examination_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
