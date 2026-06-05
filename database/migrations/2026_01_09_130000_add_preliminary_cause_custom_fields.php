<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $preliminaryCauses = [
            'Respiratory',
            'Digestive',
            'Trauma',
            'Bunting',
            'Metabolic',
            'Parasitic',
            'Infectious',
            'Unknown',
            'Other'
        ];

        foreach ($preliminaryCauses as $index => $value) {
            DB::table('mortality_custom_fields')->insertOrIgnore([
                'field_type' => 'preliminary_cause',
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
            ->where('field_type', 'preliminary_cause')
            ->delete();
    }
};
