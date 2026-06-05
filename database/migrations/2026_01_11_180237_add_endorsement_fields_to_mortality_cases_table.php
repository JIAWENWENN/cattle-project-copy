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
        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->json('endorsement_documents')->nullable()->after('rejection_reason');
            $table->integer('endorsement_step')->default(0)->after('endorsement_documents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->dropColumn(['endorsement_documents', 'endorsement_step']);
        });
    }
};
