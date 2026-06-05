<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyOperationDutyPerson extends Model
{
    use HasFactory;

    protected $table = 'daily_operation_duty_persons';

    protected $fillable = [
        'estate_name',
        'month',
        'year',
        'week',
        'names',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'names' => 'array',
    ];
}
