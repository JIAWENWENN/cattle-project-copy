<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_type'); // category, coat_colour, etc.
            $table->string('value');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['field_type', 'value']);
        });

        // Seed default values
        $this->seedDefaultValues();
    }

    private function seedDefaultValues(): void
    {
        $defaults = [
            'category' => ['BC', 'WB', 'H', 'BB', 'FC', 'MC'],
            'coat_colour' => ['Grey', 'Honey', 'Red', 'Black', 'Stripe', 'White', 'Brown'],
            'general_condition' => ['Excellent', 'Good', 'Fair', 'Poor'],
            'ownership' => ['Owned', 'Leased', 'Contract'],
        ];

        foreach ($defaults as $type => $values) {
            foreach ($values as $index => $value) {
                DB::table('cattle_custom_fields')->insert([
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
        Schema::dropIfExists('cattle_custom_fields');
    }
};
