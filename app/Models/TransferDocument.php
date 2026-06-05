<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_no',
        'form_document_no',
        'revision_no',
        'type',
        'from_location',
        'to_location',
        'date',
        'time',
        'vehicle_no',
        'driver_name',
        'driver_tel',
        'driver_ic',
        'address',
        'siv_no',
        'receipt_no',
        'customer_name',
        'total_cattle',
        'total_value',
        'status',
        'current_step',
        'endorsement_step',
        'endorsement_documents',
        'is_reopened',
        'cattle_location_snapshot',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i',
        'total_cattle' => 'integer',
        'total_value' => 'decimal:2',
        'endorsement_documents' => 'array',
        'cattle_location_snapshot' => 'array',
        'is_reopened' => 'boolean',
        'endorsement_step' => 'integer',
    ];

    const TYPE_CTV = 'CTV';
    const TYPE_RECEIVAL = 'Receival';
    const TYPE_SIV = 'SIV';

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    const STEP_ISSUED = 'issued';
    const STEP_APPROVED = 'approved';
    const STEP_TRANSPORTED = 'transported';
    const STEP_WITNESS_TRANSIT = 'witness_transit';
    const STEP_VERIFIED_TRANSIT = 'verified_transit';
    const STEP_WITNESS_RECEIVE = 'witness_receive';
    const STEP_RECEIVED = 'received';
    const STEP_COMPLETED = 'completed';

    public static function getWorkflowSteps()
    {
        return [
            self::STEP_ISSUED,
            self::STEP_APPROVED,
            self::STEP_TRANSPORTED,
            self::STEP_WITNESS_TRANSIT,
            self::STEP_VERIFIED_TRANSIT,
            self::STEP_WITNESS_RECEIVE,
            self::STEP_RECEIVED,
            self::STEP_COMPLETED,
        ];
    }

    public static function getStepRoles()
    {
        return [
            self::STEP_ISSUED => 'livestock',
            self::STEP_APPROVED => 'livestock manager',
            self::STEP_TRANSPORTED => 'driver',
            self::STEP_WITNESS_TRANSIT => 'assistant_manager',
            self::STEP_VERIFIED_TRANSIT => 'security',
            self::STEP_WITNESS_RECEIVE => 'livestock',
            self::STEP_RECEIVED => 'supervisor',
            self::STEP_COMPLETED => 'security',
        ];
    }

    public function livestock()
    {
        return $this->hasMany(TransferLivestock::class);
    }

    public function approvals()
    {
        return $this->hasMany(TransferApproval::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function generateDocumentNo()
    {
        $prefix = $this->type;
        $year = date('Y');
        $count = self::where('type', $this->type)
            ->whereYear('created_at', $year)
            ->count() + 1;
        $this->document_no = sprintf('%s-%s-%04d', $prefix, $year, $count);
    }

    public function canAdvanceStep()
    {
        $steps = self::getWorkflowSteps();
        $currentIndex = array_search($this->current_step, $steps);
        return $currentIndex !== false && isset($steps[$currentIndex + 1]);
    }

    public function getNextStep()
    {
        $steps = self::getWorkflowSteps();
        $currentIndex = array_search($this->current_step, $steps);
        return $steps[$currentIndex + 1] ?? null;
    }
}
