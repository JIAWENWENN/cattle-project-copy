<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasturePhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'pasture_block_id',
        'name',
    ];

    public function block()
    {
        return $this->belongsTo(PastureBlock::class, 'pasture_block_id');
    }
}
