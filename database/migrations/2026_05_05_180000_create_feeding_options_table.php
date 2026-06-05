<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeding_options', function (Blueprint $table) {
            $table->id();
            $table->string('field_type');
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['field_type', 'value']);
        });

        foreach (['BL2B', 'FL1F', 'BL2D', 'KL3A', 'TL1C'] as $idx => $trip) {
            DB::table('feeding_options')->insertOrIgnore([
                'field_type' => 'trip_no',
                'value' => $trip,
                'sort_order' => $idx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Napier', 'OPF', 'Silage', 'Baller'] as $idx => $type) {
            DB::table('feeding_options')->insertOrIgnore([
                'field_type' => 'feed_type',
                'value' => $type,
                'sort_order' => $idx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_options');
    }
};
