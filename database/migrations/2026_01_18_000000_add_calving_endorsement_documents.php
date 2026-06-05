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
            $table->integer('endorsement_step')->default(0);
            $table->json('endorsement_documents')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calving_records', function (Blueprint $table) {
            $table->dropColumn(['endorsement_step', 'endorsement_documents']);
        });
    }
};
