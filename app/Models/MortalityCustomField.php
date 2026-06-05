<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MortalityCustomField extends Model
{
    protected $fillable = [
        'field_type',
        'value',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function getRouteKeyName()
    {
        return 'id';
    }

    public static function getOptions(string $fieldType): array
    {
        return self::where('field_type', $fieldType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('value')
            ->toArray();
    }

    public static function getOptionsWithIds(string $fieldType): array
    {
        return self::where('field_type', $fieldType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'value'])
            ->toArray();
    }
}
