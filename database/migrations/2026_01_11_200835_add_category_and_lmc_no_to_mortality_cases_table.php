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
            if (!Schema::hasColumn('mortality_cases', 'category')) {
                $table->string('category')->nullable()->after('lmc_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            if (Schema::hasColumn('mortality_cases', 'category')) {
                $table->dropColumn(['category']);
            }
        });
    }
};
