<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyOperationEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'estate_name',
        'month',
        'year',
        'category_code',
        'daily_values',
        'missing',
        'remark',
    ];

    protected $casts = [
        'daily_values' => 'array',
        'month' => 'integer',
        'year' => 'integer',
        'missing' => 'integer',
    ];
}
