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
        Schema::table('calving_records', function (Blueprint $table) {
            // Issued by (Sr. Assistant Livestock)
            if (!Schema::hasColumn('calving_records', 'issued_by_name')) {
                $table->string('issued_by_name')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'issued_by_unit')) {
                $table->string('issued_by_unit')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'issued_by_signature')) {
                $table->boolean('issued_by_signature')->default(false);
            }

            // Verified by (Sr. Assistant Security)
            if (!Schema::hasColumn('calving_records', 'verified_by_name')) {
                $table->string('verified_by_name')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'verified_by_unit')) {
                $table->string('verified_by_unit')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'verified_by_signature')) {
                $table->boolean('verified_by_signature')->default(false);
            }

            // Checked by (Supervisor Livestock)
            if (!Schema::hasColumn('calving_records', 'checked_by_name')) {
                $table->string('checked_by_name')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'checked_by_unit')) {
                $table->string('checked_by_unit')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'checked_by_signature')) {
                $table->boolean('checked_by_signature')->default(false);
            }

            // Witnessed by (Estate Management)
            if (!Schema::hasColumn('calving_records', 'witnessed_by_name')) {
                $table->string('witnessed_by_name')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'witnessed_by_unit')) {
                $table->string('witnessed_by_unit')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'witnessed_by_signature')) {
                $table->boolean('witnessed_by_signature')->default(false);
            }

            // Approved by (Livestock Manager/OIC)
            if (!Schema::hasColumn('calving_records', 'approved_by_name')) {
                $table->string('approved_by_name')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'approved_by_unit')) {
                $table->string('approved_by_unit')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'approved_by_signature')) {
                $table->boolean('approved_by_signature')->default(false);
            }

            // Timestamps for each approval
            if (!Schema::hasColumn('calving_records', 'issued_at')) {
                $table->timestamp('issued_at')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'checked_at')) {
                $table->timestamp('checked_at')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'witnessed_at')) {
                $table->timestamp('witnessed_at')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to drop columns as they may have been added by the original migration
    }
};
