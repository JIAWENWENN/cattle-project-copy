<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalvingWorkflowAssignment extends Model
{
    protected $fillable = [
        'issued_by_user_id',
        'issued_by_user_ids',
        'verified_by_user_id',
        'verified_by_user_ids',
        'checked_by_user_id',
        'checked_by_user_ids',
        'witnessed_by_user_id',
        'witnessed_by_user_ids',
        'approved_by_user_id',
        'approved_by_user_ids',
    ];

    protected $casts = [
        'issued_by_user_ids' => 'array',
        'verified_by_user_ids' => 'array',
        'checked_by_user_ids' => 'array',
        'witnessed_by_user_ids' => 'array',
        'approved_by_user_ids' => 'array',
    ];
}
