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
        Schema::create('mortality_cases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cattle_id')->constrained()->onDelete('cascade');
            $table->date('death_date');
            $table->text('initial_notes')->nullable();
            $table->enum('status', ['pending', 'pm_examination', 'under_review', 'approved', 'rejected', 'completed'])->default('pending');
            $table->enum('current_step', ['issued', 'verified', 'checked', 'witness', 'approved'])->default('issued');

            $table->foreignId('created_by')->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('mortality_cases', function (Blueprint $table) {
                $table->dropForeign(['cattle_id']);
                $table->dropForeign(['created_by']);
            });
        } catch (\Exception $e) {
            // Ignore
        }
        Schema::dropIfExists('mortality_cases');
    }
};
