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
        Schema::table('postmortem_examinations', function (Blueprint $table) {
            $table->string('lung_floating_test')->nullable()->change();
            $table->string('diaphragma_test')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postmortem_examinations', function (Blueprint $table) {
            $table->boolean('lung_floating_test')->nullable()->change();
            $table->boolean('diaphragma_test')->nullable()->change();
        });
    }
};
