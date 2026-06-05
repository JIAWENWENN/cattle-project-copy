<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CattleMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'cattle_id',
        'from_location',
        'to_location',
        'date',
        'reason',
        'transported_by'
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function cattle()
    {
        return $this->belongsTo(Cattle::class);
    }
}
