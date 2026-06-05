<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->string('lmc_no')->nullable()->after('cattle_id');
        });

        Schema::create('mortality_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_type');
            $table->string('value');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['field_type', 'value']);
        });

        $this->seedDefaultValues();
    }

    private function seedDefaultValues(): void
    {
        $defaults = [
            'category' => ['Disease', 'Accident', 'Predation', 'Nutritional', 'Unknown', 'Other'],
            'preliminary_diagnosis' => ['Bloat', 'Acidosis', 'Pneumonia', 'Enteritis', 'Trauma', 'Unknown', 'Other'],
        ];

        foreach ($defaults as $type => $values) {
            foreach ($values as $index => $value) {
                DB::table('mortality_custom_fields')->insert([
                    'field_type' => $type,
                    'value' => $value,
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mortality_custom_fields');

        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->dropColumn('lmc_no');
        });
    }
};
