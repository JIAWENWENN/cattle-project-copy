<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyCattleReturnWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_from',
        'period_to',
        'operating_unit',
        'endorsement_step',
        'endorsement_documents',
        'status',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'endorsement_documents' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function getStepDocument(int $stepIndex): ?array
    {
        $docs = $this->endorsement_documents ?? [];
        return $docs[$stepIndex] ?? $docs[(string) $stepIndex] ?? null;
    }

    public function setStepDocument(int $stepIndex, array $documentData): void
    {
        $docs = $this->endorsement_documents ?? [];
        $docs[$stepIndex] = $documentData;
        $this->endorsement_documents = $docs;
    }

    public function allStepsUploaded(): bool
    {
        for ($i = 0; $i < 4; $i++) {
            if (!$this->getStepDocument($i)) {
                return false;
            }
        }

        return true;
    }
}
