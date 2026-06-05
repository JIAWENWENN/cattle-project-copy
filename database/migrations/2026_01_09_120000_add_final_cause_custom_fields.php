<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $finalCauseOptions = [
            'Respiratory Disorder',
            'Digestive Disorder',
            'Bloat (Tympany)',
            'Acidosis',
            'Trauma',
            'Metabolic Disease',
            'Infectious Disease',
            'Parasitic Infestation',
            'Toxicity',
            'Unknown - Further Tests Required',
            'Other'
        ];

        foreach ($finalCauseOptions as $index => $value) {
            DB::table('mortality_custom_fields')->insertOrIgnore([
                'field_type' => 'final_cause',
                'value' => $value,
                'is_active' => true,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('mortality_custom_fields')
            ->where('field_type', 'final_cause')
            ->delete();
    }
};
