<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'trip_no',
        'cattle_count',
        'feed_type',
        'planned',
        'actual_usage',
        'receive',
        'carry_forward',
        'balance',
        'remarks',
    ];
}
