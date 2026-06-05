<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PastureBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'estate_id',
        'name',
        'area',
    ];

    protected $casts = [
        'area' => 'decimal:2',
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function phases()
    {
        return $this->hasMany(PasturePhase::class)->orderBy('name');
    }
}
