<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentWorkflowAssignment extends Model
{
    protected $fillable = [
        'prepared_by_user_id',
        'prepared_by_user_ids',
        'checked_by_user_id',
        'checked_by_user_ids',
        'approved_by_user_id',
        'approved_by_user_ids',
    ];

    protected $casts = [
        'prepared_by_user_ids' => 'array',
        'checked_by_user_ids' => 'array',
        'approved_by_user_ids' => 'array',
    ];
}
