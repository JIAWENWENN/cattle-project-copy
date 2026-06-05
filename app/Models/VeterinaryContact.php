<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VeterinaryContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'position',
        'organization',
        'phone',
        'alt_phone',
        'email',
        'address',
        'availability',
        'emergency',
        'photo_path',
        'notes',
    ];

    protected $casts = [
        'emergency' => 'boolean',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }
}
