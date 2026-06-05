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
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->string('treatment_no')->nullable(); // Treatment reference number
            $table->foreignId('cattle_id')->nullable()->constrained('cattle')->nullOnDelete();
            $table->string('tag_no')->nullable();
            $table->string('category')->nullable();
            $table->string('colour')->nullable();
            $table->date('date')->nullable();
            $table->string('week')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('treatment')->nullable();
            $table->string('treatment_code')->nullable();
            $table->string('dosage')->nullable();
            $table->string('medication')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->boolean('follow_up_done')->default(false);

            // Status for workflow
            $table->enum('status', ['pending', 'under_review', 'completed', 'rejected'])->default('pending');
            $table->enum('current_step', ['prepared', 'checked', 'approved'])->default('prepared');

            // Endorsement workflow fields (same pattern as mortality)
            $table->json('endorsement_documents')->nullable();
            $table->integer('endorsement_step')->default(0); // 0-3 (0=waiting step0, 3=all done awaiting admin)

            // Creator/Admin tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
