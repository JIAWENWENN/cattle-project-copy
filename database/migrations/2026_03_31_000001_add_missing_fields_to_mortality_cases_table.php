<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            if (!Schema::hasColumn('mortality_cases', 'reported_by')) {
                $table->string('reported_by')->nullable()->after('lmc_no');
            }
            if (!Schema::hasColumn('mortality_cases', 'time_of_death')) {
                $table->time('time_of_death')->nullable()->after('reported_by');
            }
            if (!Schema::hasColumn('mortality_cases', 'cause_of_death')) {
                $table->string('cause_of_death')->nullable()->after('time_of_death');
            }
            if (!Schema::hasColumn('mortality_cases', 'treatment')) {
                $table->text('treatment')->nullable()->after('cause_of_death');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            if (Schema::hasColumn('mortality_cases', 'reported_by')) {
                $table->dropColumn('reported_by');
            }
            if (Schema::hasColumn('mortality_cases', 'time_of_death')) {
                $table->dropColumn('time_of_death');
            }
            if (Schema::hasColumn('mortality_cases', 'cause_of_death')) {
                $table->dropColumn('cause_of_death');
            }
            if (Schema::hasColumn('mortality_cases', 'treatment')) {
                $table->dropColumn('treatment');
            }
        });
    }
};
