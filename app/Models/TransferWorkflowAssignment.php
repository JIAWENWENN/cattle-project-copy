<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferWorkflowAssignment extends Model
{
    protected $fillable = [
        'issued_by_user_id',
        'issued_by_user_ids',
        'approved_by_user_id',
        'approved_by_user_ids',
        'transported_by_user_id',
        'transported_by_user_ids',
        'witnessed_transit_by_user_id',
        'witnessed_transit_by_user_ids',
        'verified_transit_by_user_id',
        'verified_transit_by_user_ids',
        'witnessed_receive_by_user_id',
        'witnessed_receive_by_user_ids',
        'received_by_user_id',
        'received_by_user_ids',
        'completed_by_user_id',
        'completed_by_user_ids',
    ];

    protected $casts = [
        'issued_by_user_ids' => 'array',
        'approved_by_user_ids' => 'array',
        'transported_by_user_ids' => 'array',
        'witnessed_transit_by_user_ids' => 'array',
        'verified_transit_by_user_ids' => 'array',
        'witnessed_receive_by_user_ids' => 'array',
        'received_by_user_ids' => 'array',
        'completed_by_user_ids' => 'array',
    ];
}
