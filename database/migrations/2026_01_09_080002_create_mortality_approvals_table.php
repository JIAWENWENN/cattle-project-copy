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
        Schema::create('mortality_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mortality_case_id')->constrained('mortality_cases')->onDelete('cascade');
            $table->foreignId('postmortem_examination_id')->nullable()->constrained('postmortem_examinations')->onDelete('set null');
            $table->foreignId('approver_id')->constrained('users')->onDelete('set null');

            $table->enum('step', ['issued', 'verified', 'checked', 'witness', 'approved']);
            $table->enum('action', ['approved', 'rejected', 'returned'])->default('approved');
            $table->text('comments')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('mortality_approvals', function (Blueprint $table) {
                $table->dropForeign(['mortality_case_id']);
                $table->dropForeign(['postmortem_examination_id']);
                $table->dropForeign(['approver_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }
        Schema::dropIfExists('mortality_approvals');
    }
};
