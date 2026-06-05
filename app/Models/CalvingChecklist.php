<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Permission;

class CalvingChecklist extends Model
{
    use HasFactory;

    protected $table = 'calving_checklists';

    protected $fillable = [
        // Company Information
        'company_name',
        'company_no',
        'ownership',
        'form_no',
        'mcc_no',
        'month_year',
        'operating_unit',

        // Calving Record Details
        'week',
        'calving_date',
        'tag_no',
        'sex',
        'colour',
        'dam_tag_no',
        'location_block',
        'location_phase',
        'general_condition',

        // Treatments and Care
        'treatment_iodine',
        'treatment_woundsarex',
        'colostrum_feeding_24h',
        'mamumune',
        'times_of_pregnancy',
        'location',

        // Tagging and Documentation
        'tagging_checklist_date',
        'lcc_running_number',

        // Status
        'status',
        'workflow_status',
        'remarks',

        // Endorsement workflow
        'endorsement_step',
        'is_completed',
        'completed_at',
        'endorsement_documents',

        // Approval Fields - Issued by (Sr. Assistant Livestock)
        'issued_by_name',
        'issued_by_unit',
        'issued_by_signature',
        'issued_at',

        // Approval Fields - Verified by (Sr. Assistant Security)
        'verified_by_name',
        'verified_by_unit',
        'verified_by_signature',
        'verified_at',

        // Approval Fields - Checked by (Supervisor Livestock)
        'checked_by_name',
        'checked_by_unit',
        'checked_by_signature',
        'checked_at',

        // Approval Fields - Witnessed by (Penyelia Security)
        'witnessed_by_name',
        'witnessed_by_unit',
        'witnessed_by_signature',
        'witnessed_at',

        // Approval Fields - Approved by (Livestock Manager/OIC)
        'approved_by_name',
        'approved_by_unit',
        'approved_by_signature',
        'approved_at',
    ];

    protected $casts = [
        'calving_date' => 'date',
        'tagging_checklist_date' => 'date',
        'treatment_iodine' => 'boolean',
        'treatment_woundsarex' => 'boolean',
        'colostrum_feeding_24h' => 'boolean',
        'mamumune' => 'boolean',
        'issued_by_signature' => 'boolean',
        'verified_by_signature' => 'boolean',
        'checked_by_signature' => 'boolean',
        'witnessed_by_signature' => 'boolean',
        'approved_by_signature' => 'boolean',
        'issued_at' => 'datetime',
        'verified_at' => 'datetime',
        'checked_at' => 'datetime',
        'witnessed_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'endorsement_documents' => 'array',
        'is_completed' => 'boolean',
        'endorsement_step' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ISSUED = 'issued';
    const STATUS_VERIFIED = 'verified';
    const STATUS_CHECKED = 'checked';
    const STATUS_WITNESSED = 'witnessed';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';

    // Document endorsement workflow steps (matches mortality module)
    const DOC_WORKFLOW_STEPS = [
        ['role' => 'livestock', 'label' => 'Issued by', 'field' => 'issued', 'role_name' => 'Sr. Assistant Livestock'],
        ['role' => 'security', 'label' => 'Verified by', 'field' => 'verified', 'role_name' => 'Sr. Assistant Security'],
        ['role' => 'penyelia', 'label' => 'Witnessed by', 'field' => 'witnessed', 'role_name' => 'Penyelia Security'],
        ['role' => 'manager', 'label' => 'Approved by', 'field' => 'approved', 'role_name' => 'Livestock Manager / OIC'],
    ];

    /**
     * Resolve equivalent month formats used in legacy/current data.
     */
    public static function monthYearVariants($monthYear): array
    {
        $value = trim((string) $monthYear);
        if ($value === '') {
            return [];
        }

        $variants = [$value];
        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            $year = $matches[1];
            $monthNum = (int) $matches[2];
            if ($monthNum >= 1 && $monthNum <= 12) {
                $abbr = $monthNames[$monthNum - 1];
                $variants[] = $abbr . '/' . $year;
                if ($monthNum === 9) {
                    $variants[] = 'Sept/' . $year;
                }
            }
        }

        if (preg_match('/^([A-Za-z]{3,4})\/(\d{4})$/', $value, $matches)) {
            $monthToken = strtolower($matches[1]);
            $year = $matches[2];
            $monthNum = $monthMap[$monthToken] ?? null;
            if ($monthNum) {
                $variants[] = sprintf('%s-%02d', $year, $monthNum);
                if ($monthNum === 9) {
                    $variants[] = 'Sep/' . $year;
                    $variants[] = 'Sept/' . $year;
                }
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Parse month/year input to numeric parts.
     */
    public static function parseMonthYearParts($monthYear): ?array
    {
        $value = trim((string) $monthYear);
        if ($value === '') {
            return null;
        }

        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            if ($month >= 1 && $month <= 12) {
                return ['year' => $year, 'month' => $month];
            }
        }

        if (preg_match('/^([A-Za-z]{3,4})\/(\d{4})$/', $value, $matches)) {
            $token = strtolower($matches[1]);
            $year = (int) $matches[2];
            $month = $monthMap[$token] ?? null;
            if ($month) {
                return ['year' => $year, 'month' => $month];
            }
        }

        return null;
    }

    /**
     * Get all records for a specific month/year
     */
    public static function getByMonthYear($monthYear)
    {
        $variants = static::monthYearVariants($monthYear);
        $parts = static::parseMonthYearParts($monthYear);

        if (count($variants) === 0 && !$parts) {
            return collect();
        }

        return static::query()
            ->where(function ($query) use ($variants, $parts) {
                if (count($variants) === 1) {
                    $query->where('month_year', $variants[0]);
                } elseif (count($variants) > 1) {
                    $query->whereIn('month_year', $variants);
                }

                if ($parts) {
                    $query->orWhere(function ($dateQuery) use ($parts) {
                        $dateQuery->whereYear('calving_date', $parts['year'])
                            ->whereMonth('calving_date', $parts['month']);
                    });
                }
            })
            ->orderBy('calving_date')
            ->get();
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
        $variants = static::monthYearVariants($monthYear);
        $parts = static::parseMonthYearParts($monthYear);

        if (count($variants) === 0 && !$parts) {
            return $query;
        }

        return $query->where(function ($monthQuery) use ($variants, $parts) {
            if (count($variants) === 1) {
                $monthQuery->where('month_year', $variants[0]);
            } elseif (count($variants) > 1) {
                $monthQuery->whereIn('month_year', $variants);
            }

            if ($parts) {
                $monthQuery->orWhere(function ($dateQuery) use ($parts) {
                    $dateQuery->whereYear('calving_date', $parts['year'])
                        ->whereMonth('calving_date', $parts['month']);
                });
            }
        });
    }

    /**
     * Scope for non-completed records
     */
    public function scopeNotCompleted($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Check if record is fully approved
     */
    public function isFullyApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if record is completed
     */
    public function isCompleted()
    {
        return $this->is_completed === true;
    }

    /**
     * Get the current endorsement step
     */
    public function getCurrentDocStep()
    {
        $step = $this->endorsement_step ?? 0;
        if ($step >= count(self::DOC_WORKFLOW_STEPS)) {
            return null;
        }
        return self::DOC_WORKFLOW_STEPS[$step];
    }

    /**
     * Get document for a specific step
     */
    public function getStepDocument($stepIndex)
    {
        $docs = $this->endorsement_documents ?? [];
        return $docs[$stepIndex] ?? $docs[String($stepIndex)] ?? null;
    }

    /**
     * Set document for a specific step
     */
    public function setStepDocument($stepIndex, $documentData)
    {
        $docs = $this->endorsement_documents ?? [];
        $docs[$stepIndex] = $documentData;
        $this->endorsement_documents = $docs;
    }

    /**
     * Check if all endorsement steps are uploaded
     */
    public function allStepsUploaded()
    {
        for ($i = 0; $i < 5; $i++) {
            if (!$this->getStepDocument($i)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a user can upload document for current step
     */
    public function canUploadForStep($user = null)
    {
        if ($this->isCompleted()) {
            return false;
        }

        $currentStep = $this->endorsement_step ?? 0;
        if ($currentStep >= count(self::DOC_WORKFLOW_STEPS)) {
            return false;
        }

        if (!$user instanceof \App\Models\User) {
            $user = auth()->user();
        }

        if (!$user) {
            return false;
        }

        if (strtolower((string) $user->role) === 'admin') {
            return true;
        }

        return in_array($user->id, $this->getAssignedUserIdsForStep($currentStep), true);
    }

    /**
     * Check if a user can view their OWN uploaded document
     */
    public function canViewOwnDocument($user = null)
    {
        if (!$user instanceof \App\Models\User) {
            $user = auth()->user();
        }

        if (!$user) {
            return false;
        }

        if (strtolower((string) $user->role) === 'admin') {
            return true;
        }

        $userStepIndices = $this->getUserStepIndices($user->id);

        foreach ($userStepIndices as $stepIndex) {
            if ($this->getStepDocument($stepIndex) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user can download previous step's document
     */
    public function canDownloadPrevious($user = null)
    {
        if (!$user instanceof \App\Models\User) {
            $user = auth()->user();
        }

        if (!$user) {
            return false;
        }

        if (strtolower((string) $user->role) === 'admin') {
            return true;
        }

        $userStepIndices = $this->getUserStepIndices($user->id);

        foreach ($userStepIndices as $stepIndex) {
            // Check if previous step has document
            if ($stepIndex > 0 && $this->getStepDocument($stepIndex - 1) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if admin can mark as completed
     */
    public function canMarkAsCompleted($userRole)
    {
        if ($userRole !== 'admin') {
            return false;
        }
        if ($this->isCompleted()) {
            return false;
        }
        return $this->allStepsUploaded();
    }

    /**
     * Check if user can perform edit (module permission or workflow assignment).
     */
    public function canEdit($user = null): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        if ($this->status === self::STATUS_REJECTED) {
            return false;
        }

        if (!$user instanceof \App\Models\User) {
            $user = auth()->user();
        }

        if (!$user) {
            return false;
        }

        if (strtolower((string) $user->role) === 'admin') {
            return true;
        }

        $perms = Permission::normalizePermissionList($user->hasPermission('Calving Checklist'));
        if (in_array('full', $perms, true) || in_array('edit', $perms, true)) {
            return true;
        }

        $userStepIndices = $this->getUserStepIndices($user->id);

        foreach ($userStepIndices as $stepIndex) {
            $currentStep = $this->endorsement_step ?? 0;
            if ($stepIndex >= $currentStep) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the user IDs assigned to a specific step.
     */
    protected function getAssignedUserIdsForStep($stepIndex)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('calving_workflow_assignments')) {
            return [];
        }
        $assignment = \App\Models\CalvingWorkflowAssignment::first();
        if (!$assignment) {
            return [];
        }

        $ids = [];
        if ($stepIndex === 0) {
            $ids = is_array($assignment->issued_by_user_ids) ? $assignment->issued_by_user_ids : [];
            if (empty($ids) && $assignment->issued_by_user_id) $ids = [$assignment->issued_by_user_id];
        } elseif ($stepIndex === 1) {
            $ids = is_array($assignment->verified_by_user_ids) ? $assignment->verified_by_user_ids : [];
            if (empty($ids) && $assignment->verified_by_user_id) $ids = [$assignment->verified_by_user_id];
        } elseif ($stepIndex === 2) {
            $ids = is_array($assignment->checked_by_user_ids) ? $assignment->checked_by_user_ids : [];
            if (empty($ids) && $assignment->checked_by_user_id) $ids = [$assignment->checked_by_user_id];
        } elseif ($stepIndex === 3) {
            $ids = is_array($assignment->witnessed_by_user_ids) ? $assignment->witnessed_by_user_ids : [];
            if (empty($ids) && $assignment->witnessed_by_user_id) $ids = [$assignment->witnessed_by_user_id];
        } elseif ($stepIndex === 4) {
            $ids = is_array($assignment->approved_by_user_ids) ? $assignment->approved_by_user_ids : [];
            if (empty($ids) && $assignment->approved_by_user_id) $ids = [$assignment->approved_by_user_id];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * Get all step indices that the user is assigned to.
     */
    protected function getUserStepIndices($userId)
    {
        $indices = [];
        for ($i = 0; $i < 5; $i++) {
            if (in_array($userId, $this->getAssignedUserIdsForStep($i))) {
                $indices[] = $i;
            }
        }
        return $indices;
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
     * Get the current action label for the workflow status
     */
    public function getCurrentActionLabel()
    {
        $currentStep = $this->getCurrentDocStep();
        if (!$currentStep) {
            return $this->isCompleted() ? 'Completed' : 'Unknown';
        }

        // Check if PM exam equivalent is needed (not applicable for calving)
        // Return current step label
        return $currentStep['label'] ?? 'Pending';
    }
}
