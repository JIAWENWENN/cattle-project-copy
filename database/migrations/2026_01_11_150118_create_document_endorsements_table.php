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
        Schema::create('document_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortality_case_id')->constrained()->onDelete('cascade');
            $table->string('lmc_no');
            $table->string('tag_no');
            $table->string('category');
            $table->string('death_date');
            $table->text('clinical_signs')->nullable();
            $table->text('treatment')->nullable();
            $table->text('preliminary_diagnosis')->nullable();
            $table->string('location')->nullable();
            $table->string('herd')->nullable();
            
            // External Findings
            $table->string('external_skin')->nullable();
            $table->string('external_eyes')->nullable();
            $table->string('external_mouth')->nullable();
            $table->string('external_nostrils')->nullable();
            $table->string('external_ears')->nullable();
            $table->string('external_limbs')->nullable();
            $table->string('external_anus')->nullable();
            $table->string('external_genital')->nullable();
            $table->text('external_general')->nullable();
            
            // Internal Organ Findings
            $table->string('heart_findings')->nullable();
            $table->string('trachea_findings')->nullable();
            $table->string('lung_floating_test')->nullable();
            $table->string('lung_floating_test_details')->nullable();
            $table->string('diaphragma_test')->nullable();
            $table->string('diaphragma_test_details')->nullable();
            $table->string('kidney_findings')->nullable();
            $table->string('urinary_bladder_findings')->nullable();
            
            // Digestive System Findings
            $table->string('rumen_findings')->nullable();
            $table->string('reticulum_findings')->nullable();
            $table->string('omasum_findings')->nullable();
            $table->string('abomasum_findings')->nullable();
            $table->string('small_intestine_findings')->nullable();
            
            // Final Diagnosis
            $table->string('confirmed_cause_of_death')->nullable();
            $table->text('additional_notes')->nullable();
            
            // Current workflow step
            $table->integer('current_step')->default(0);
            $table->string('status')->default('pending');
            
            // Endorsement fields for each role
            // Step 0: Issued by - Sr. Assistant Livestock
            $table->string('issued_by_name')->nullable();
            $table->string('issued_by_date')->nullable();
            $table->string('issued_document')->nullable(); // Uploaded signed document
            
            // Step 1: Verified by - Sr. Assistant Security
            $table->string('verified_by_name')->nullable();
            $table->string('verified_by_date')->nullable();
            $table->string('verified_document')->nullable();
            
            // Step 2: Checked by - Supervisor Livestock
            $table->string('checked_by_name')->nullable();
            $table->string('checked_by_date')->nullable();
            $table->string('checked_document')->nullable();
            
            // Step 3: Witnessed by - Estate Management
            $table->string('witnessed_by_name')->nullable();
            $table->string('witnessed_by_date')->nullable();
            $table->string('witnessed_document')->nullable();
            
            // Step 4: Approved by - Livestock Manager/OIC
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_date')->nullable();
            $table->string('approved_document')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_endorsements');
    }
};
