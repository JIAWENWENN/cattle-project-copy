<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentEndorsement extends Model
{
    use HasFactory;

    protected $fillable = [
        'mortality_case_id',
        'lmc_no',
        'tag_no',
        'category',
        'death_date',
        'clinical_signs',
        'treatment',
        'preliminary_diagnosis',
        'location',
        'herd',
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
        'rumen_findings',
        'reticulum_findings',
        'omasum_findings',
        'abomasum_findings',
        'small_intestine_findings',
        'confirmed_cause_of_death',
        'additional_notes',
        'current_step',
        'status',
        'issued_by_name',
        'issued_by_date',
        'issued_document',
        'verified_by_name',
        'verified_by_date',
        'verified_document',
        'checked_by_name',
        'checked_by_date',
        'checked_document',
        'witnessed_by_name',
        'witnessed_by_date',
        'witnessed_document',
        'approved_by_name',
        'approved_by_date',
        'approved_document',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issued_by_date' => 'date',
        'verified_by_date' => 'date',
        'checked_by_date' => 'date',
        'witnessed_by_date' => 'date',
        'approved_by_date' => 'date',
    ];

    public function mortalityCase()
    {
        return $this->belongsTo(MortalityCase::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
