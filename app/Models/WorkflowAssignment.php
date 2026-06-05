<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAssignment extends Model
{
    protected $fillable = [
        'module',
        'assignments',
    ];

    protected $casts = [
        'assignments' => 'array',
    ];
}
