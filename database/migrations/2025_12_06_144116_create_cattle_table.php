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
        Schema::create('cattle', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('tag_no')->unique();
            $table->string('category');
            $table->string('color');
            $table->date('birth_date');
            $table->enum('gender', ['Male', 'Female']);

            // Receival & Condition
            // NOTE: Only one instance of receival_weight is allowed here.
            $table->decimal('receival_weight', 8, 2)->nullable();
            $table->string('general_condition')->nullable();
            $table->string('ownership')->nullable();
            $table->string('placement_yard')->nullable();
            $table->string('location_block')->nullable();
            $table->string('location_phase')->nullable();

            // Genealogy (Optional)
            $table->string('dam_tag')->nullable();
            $table->string('dam_category')->nullable();
            $table->string('dam_colour')->nullable();

            // Milestones (Optional)
            $table->decimal('weaning_weight', 8, 2)->nullable();
            $table->decimal('yearling_weight', 8, 2)->nullable();

            // Additional Fields
            $table->string('status')->default('Active');
            $table->text('remarks')->nullable();

            // Current Weight (Updated via activities)
            $table->decimal('weight', 8, 2)->nullable();

            $table->timestamps();

            // Indexes for faster searching
            $table->index('tag_no');
            $table->index('category');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cattle');
    }
};
