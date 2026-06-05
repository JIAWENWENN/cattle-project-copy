<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MortalityApproval extends Model
{
    use HasFactory;

    protected $table = 'mortality_approvals';

    protected $fillable = [
        'mortality_case_id',
        'postmortem_examination_id',
        'approver_id',
        'step',
        'action',
        'comments',
    ];

    protected $casts = [
        'action' => 'string',
        'step' => 'string',
    ];

    public function mortalityCase()
    {
        return $this->belongsTo(MortalityCase::class);
    }

    public function postmortemExamination()
    {
        return $this->belongsTo(PostmortemExamination::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopeByStep($query, $step)
    {
        return $query->where('step', $step);
    }

    public function scopeApproved($query)
    {
        return $query->where('action', 'approved');
    }
}
