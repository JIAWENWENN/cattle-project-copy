<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalvingMonthlyWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'month_year',
        'operating_unit',
        'endorsement_step',
        'endorsement_documents',
        'status',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'endorsement_documents' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // Workflow steps matching user request
    const WORKFLOW_STEPS = [
        ['role' => 'livestock', 'label' => 'Issued by', 'field' => 'issued', 'role_name' => 'Sr. Assistant Livestock'],
        ['role' => 'security', 'label' => 'Verified by', 'field' => 'verified', 'role_name' => 'Sr. Assistant Security'],
        ['role' => 'penyelia', 'label' => 'Witnessed by', 'field' => 'witness', 'role_name' => 'Penyelia Security'],
        ['role' => 'manager', 'label' => 'Approved by', 'field' => 'approved', 'role_name' => 'Livestock Manager / OIC'],
    ];

    public function getStepDocument($stepIndex)
    {
        $docs = $this->endorsement_documents ?? [];
        return $docs[$stepIndex] ?? null;
    }

    public function setStepDocument($stepIndex, $documentData)
    {
        $docs = $this->endorsement_documents ?? [];
        $docs[$stepIndex] = $documentData;
        $this->endorsement_documents = $docs;
    }

    public function allStepsUploaded()
    {
        for ($i = 0; $i < count(self::WORKFLOW_STEPS); $i++) {
            if (!$this->getStepDocument($i)) {
                return false;
            }
        }
        return true;
    }
}
