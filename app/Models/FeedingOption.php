<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedingOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_type',
        'value',
        'sort_order',
    ];
}
