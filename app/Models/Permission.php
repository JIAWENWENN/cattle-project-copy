<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['user_id', 'module', 'permission'];

    public static function normalizePermissionList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = array_map('trim', explode(',', $value));
            }
        } else {
            $items = [];
        }

        $allowed = ['no-access', 'view', 'create', 'edit', 'delete', 'full', 'approve'];
        $items = array_values(array_unique(array_filter($items, fn ($item) => in_array($item, $allowed, true))));

        if (in_array('full', $items, true)) {
            return ['full'];
        }

        if (count($items) > 1) {
            $items = array_values(array_filter($items, fn ($item) => $item !== 'no-access'));
        }

        return $items ?: ['no-access'];
    }

    public static function encodePermissionList(array $permissions): string
    {
        return implode(',', static::normalizePermissionList($permissions));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
