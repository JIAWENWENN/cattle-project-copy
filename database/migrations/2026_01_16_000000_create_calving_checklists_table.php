<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('calving_checklists', function (Blueprint $table) {
            $table->id();

            // Company Information
            $table->string('company_name');
            $table->string('company_no');
            $table->string('ownership')->nullable();
            $table->string('form_no')->default('FORM 1B');
            $table->string('mcc_no')->nullable();
            $table->string('month_year'); // Format: Sept/2024
            $table->string('operating_unit');

            // Calf Identification
            $table->string('tag_no'); // Calf identification tag
            $table->string('lcc_running_number')->nullable(); // LCC No.
            $table->date('calving_date')->nullable(); // Date of birth
            $table->string('sex'); // MC (Male Calf), FC (Female Calf)
            $table->string('colour'); // Coat colour
            $table->string('general_condition')->default('Good'); // Cattle condition

            // Breeder's Details
            $table->string('dam_tag_no')->nullable(); // Dam's identification tag
            $table->string('dam_colour')->nullable(); // Dam coat colour
            $table->string('sire_tag_no')->nullable(); // Sire identification tag
            $table->string('sire_colour')->nullable(); // Sire coat colour

            // Other Details
            $table->string('worker_name')->nullable(); // Worker's name
            $table->string('herd')->nullable(); // Herds

            // Location
            $table->string('location_block')->nullable(); // Block
            $table->string('location_phase')->nullable(); // Phase

            // Remarks
            $table->text('remarks')->nullable();

            // Status
            $table->string('status')->default('pending'); // pending, issued, verified, checked, witnessed, approved, rejected
            $table->string('workflow_status')->default('pending');

            // Workflow tracking
            $table->integer('endorsement_step')->default(0);
            $table->json('endorsement_documents')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();

            // Endorsement Fields - Step 0: Issued by (Sr. Assistant Livestock)
            $table->string('issued_by_name')->nullable();
            $table->date('issued_by_date')->nullable();
            $table->string('issued_document')->nullable();

            // Endorsement Fields - Step 1: Verified by (Sr. Assistant Security)
            $table->string('verified_by_name')->nullable();
            $table->date('verified_by_date')->nullable();
            $table->string('verified_document')->nullable();

            // Endorsement Fields - Step 2: Checked by (Supervisor Livestock)
            $table->string('checked_by_name')->nullable();
            $table->date('checked_by_date')->nullable();
            $table->string('checked_document')->nullable();

            // Endorsement Fields - Step 3: Witnessed by (Penyelia Security)
            $table->string('witnessed_by_name')->nullable();
            $table->date('witnessed_by_date')->nullable();
            $table->string('witnessed_document')->nullable();

            // Endorsement Fields - Step 4: Approved by (Livestock Manager/OIC)
            $table->string('approved_by_name')->nullable();
            $table->date('approved_by_date')->nullable();
            $table->string('approved_document')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('tag_no');
            $table->index('calving_date');
            $table->index('month_year');
            $table->index('status');
            $table->index('workflow_status');
            $table->index('operating_unit');
        });
    }

    public function down()
    {
        Schema::dropIfExists('calving_checklists');
    }
};
