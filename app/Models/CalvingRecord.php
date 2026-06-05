<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalvingRecord extends Model
{
    use HasFactory;

    protected $table = 'calving_records';

    protected $fillable = [
        // Record Creator
        'created_by',

        // Company Information
        'company_name',
        'company_no',
        'ownership',
        'form_no',
        'mcc_no',
        'month_year',
        'operating_unit',

        // Calf Identification
        'tag_no',
        'lcc_running_number',
        'cattle_no_request_form',
        'calving_date',
        'sex',
        'colour',
        'general_condition',

        // Breeder's Details
        'dam_tag_no',
        'dam_colour',
        'sire_tag_no',
        'sire_colour',

        // Other Details
        'worker_name',
        'herd',

        // Location
        'location_block',
        'location_phase',

        // Status
        'status',
        'remarks',

        // Approval Fields - Issued by (Sr. Assistant Livestock)
        'issued_by_name',
        'issued_by_unit',
        'issued_by_signature',
        'issued_by_date',
        'issued_at',

        // Approval Fields - Verified by (Sr. Assistant Security)
        'verified_by_name',
        'verified_by_unit',
        'verified_by_signature',
        'verified_by_date',
        'verified_at',

        // Approval Fields - Checked by (Supervisor Livestock)
        'checked_by_name',
        'checked_by_unit',
        'checked_by_signature',
        'checked_by_date',
        'checked_at',

        // Approval Fields - Witnessed by (Estate Management)
        'witnessed_by_name',
        'witnessed_by_unit',
        'witnessed_by_signature',
        'witnessed_by_date',
        'witnessed_at',

        // Approval Fields - Approved by (Livestock Manager/OIC)
        'approved_by_name',
        'approved_by_unit',
        'approved_by_signature',
        'approved_by_date',
        'approved_at',

        // Endorsement document workflow
        'endorsement_step',
        'endorsement_documents',
        'is_reopened',
    ];

    protected $casts = [
        'calving_date' => 'date',
        'issued_by_signature' => 'boolean',
        'verified_by_signature' => 'boolean',
        'checked_by_signature' => 'boolean',
        'witnessed_by_signature' => 'boolean',
        'approved_by_signature' => 'boolean',
        'issued_by_date' => 'date',
        'verified_by_date' => 'date',
        'checked_by_date' => 'date',
        'witnessed_by_date' => 'date',
        'approved_by_date' => 'date',
        'issued_at' => 'datetime',
        'verified_at' => 'datetime',
        'checked_at' => 'datetime',
        'witnessed_at' => 'datetime',
        'approved_at' => 'datetime',
        'endorsement_step' => 'integer',
        'endorsement_documents' => 'array',
        'is_reopened' => 'boolean',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ISSUED = 'issued';
    const STATUS_VERIFIED = 'verified';
    const STATUS_CHECKED = 'checked';
    const STATUS_WITNESSED = 'witnessed';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the user who created this record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all calving records for a specific month/year
     */
    public static function getByMonthYear($monthYear)
    {
        return static::where('month_year', $monthYear)->orderBy('calving_date')->get();
    }

    /**
     * Get all calving records for a specific operating unit
     */
    public static function getByOperatingUnit($operatingUnit)
    {
        return static::where('operating_unit', $operatingUnit)->orderBy('calving_date')->get();
    }

    /**
     * Check if record is fully approved
     */
    public function isFullyApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if record can be approved by given role
     */
    public function canBeApprovedBy($role)
    {
        switch ($role) {
            case 'supervisor':
                return in_array($this->status, [self::STATUS_ISSUED]);
            case 'manager':
                return in_array($this->status, [self::STATUS_VERIFIED, self::STATUS_WITNESSED]);
            default:
                return false;
        }
    }

    /**
     * Get sex display label
     */
    public function getSexLabelAttribute()
    {
        return match($this->sex) {
            'MC' => 'Male Calf',
            'FC' => 'Female Calf',
            default => $this->sex,
        };
    }

    /**
     * Get treatment status label
     */
    public function getTreatmentStatusAttribute()
    {
        $treatments = [];
        if ($this->treatment_iodine) $treatments[] = 'Iodine';
        if ($this->treatment_woundsarex) $treatments[] = 'Woundsarex';
        
        return empty($treatments) ? 'None' : implode(', ', $treatments);
    }

    /**
     * Scope for pending records
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved records
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for specific month
     */
    public function scopeForMonth($query, $monthYear)
    {
        return $query->where('month_year', $monthYear);
    }
}
