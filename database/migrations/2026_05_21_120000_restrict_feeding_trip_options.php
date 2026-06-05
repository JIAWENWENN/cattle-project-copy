<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $allowedTrips = [
        'FL1B', 'FL1C', 'FL1D', 'FL1F', 'FL1G', 'FL1H',
        'FL2A', 'FL2B', 'FL2C', 'FL2D', 'FL2E', 'FL2F', 'FL2G', 'FL2H',
        'BL1A', 'BL1B', 'BL1C', 'BL1D',
        'BL2A', 'BL2B', 'BL2C', 'BL2D',
        'BL3A', 'BL3B', 'BL3C', 'BL3D',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            DB::table('feeding_options')
                ->where('field_type', 'trip_no')
                ->whereNotIn('value', $this->allowedTrips)
                ->delete();

            foreach ($this->allowedTrips as $index => $trip) {
                DB::table('feeding_options')->updateOrInsert(
                    ['field_type' => 'trip_no', 'value' => $trip],
                    ['sort_order' => $index, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty because previous trip option values are unknown.
    }
};
