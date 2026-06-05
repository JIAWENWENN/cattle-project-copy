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
        Schema::create('postmortem_examinations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mortality_case_id')->constrained('mortality_cases')->onDelete('cascade');

            // Examination Info
            $table->date('examination_date');
            $table->time('examination_time')->nullable();
            $table->foreignId('performed_by')->constrained('users')->onDelete('set null');

            // External Findings
            $table->text('external_skin')->nullable();
            $table->text('external_eyes')->nullable();
            $table->text('external_mouth')->nullable();
            $table->text('external_nostrils')->nullable();
            $table->text('external_ears')->nullable();
            $table->text('external_limbs')->nullable();
            $table->text('external_anus')->nullable();
            $table->text('external_genital')->nullable();
            $table->text('external_general')->nullable();

            // Internal Findings
            $table->text('heart_findings')->nullable();
            $table->text('trachea_findings')->nullable();
            $table->boolean('lung_floating_test')->nullable();
            $table->text('lung_floating_test_details')->nullable();
            $table->boolean('diaphragma_test')->nullable();
            $table->text('diaphragma_test_details')->nullable();
            $table->text('kidney_findings')->nullable();
            $table->text('urinary_bladder_findings')->nullable();

            // Digestive System
            $table->text('rumen_findings')->nullable();
            $table->text('reticulum_findings')->nullable();
            $table->text('omasum_findings')->nullable();
            $table->text('abomasum_findings')->nullable();
            $table->text('small_intestine_findings')->nullable();

            // Final Diagnosis
            $table->text('preliminary_diagnosis')->nullable();
            $table->text('confirmed_cause_of_death')->nullable();
            $table->string('cause_of_death_category')->nullable();
            $table->text('additional_notes')->nullable();
            $table->text('recommendations')->nullable();

            $table->enum('status', ['pending', 'completed', 'under_review', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('postmortem_examinations', function (Blueprint $table) {
                $table->dropForeign(['mortality_case_id']);
                $table->dropForeign(['performed_by']);
            });
        } catch (\Exception $e) {
            // Ignore
        }
        Schema::dropIfExists('postmortem_examinations');
    }
};
