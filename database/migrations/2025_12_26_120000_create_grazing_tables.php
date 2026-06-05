<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Estates Table
        Schema::create('estates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('area', 10, 2); // Area in hectares
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Herds Table
        Schema::create('herds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Grazing Data Table (Monthly records per estate per herd)
        Schema::create('grazing_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->onDelete('cascade');
            $table->string('herd')->default('');
            $table->string('month'); // Format: YYYY-MM
            $table->decimal('allocated_area', 10, 2);
            $table->integer('rotation_period'); // Days
            $table->integer('days_in_month');
            $table->decimal('current_month_ha', 10, 2);
            $table->decimal('rate_per_ha', 8, 2);
            $table->decimal('deduction_percent', 5, 2)->default(0);
            $table->decimal('deduction_amount', 10, 2)->default(0);
            $table->decimal('to_date_ha', 10, 2);
            $table->decimal('total_budget', 15, 2);
            $table->decimal('ytd_claim', 15, 2);
            $table->timestamps();

            // Unique constraint: one record per estate per month per herd
            $table->unique(['estate_id', 'month', 'herd']);
        });

        // Grazing Blocks Table (Block-level data linked to grazing_data)
        Schema::create('grazing_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grazing_data_id')->constrained()->onDelete('cascade');
            $table->string('block_id'); // e.g., "Block 11"
            $table->decimal('area', 10, 2);
            $table->decimal('actual', 10, 2);
            $table->decimal('achievement', 10, 2);
            $table->decimal('rate', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grazing_blocks');
        Schema::dropIfExists('grazing_data');
        Schema::dropIfExists('herds');
        Schema::dropIfExists('estates');
    }
};
