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
        Schema::table('calving_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('calving_checklists', 'week')) {
                $table->string('week')->nullable()->after('operating_unit');
            }
            if (!Schema::hasColumn('calving_checklists', 'times_of_pregnancy')) {
                $table->string('times_of_pregnancy')->nullable()->after('general_condition');
            }
            if (!Schema::hasColumn('calving_checklists', 'location')) {
                $table->string('location')->nullable()->after('times_of_pregnancy');
            }
            if (!Schema::hasColumn('calving_checklists', 'treatment_iodine')) {
                $table->boolean('treatment_iodine')->default(false)->after('location');
            }
            if (!Schema::hasColumn('calving_checklists', 'treatment_woundsarex')) {
                $table->boolean('treatment_woundsarex')->default(false)->after('treatment_iodine');
            }
            if (!Schema::hasColumn('calving_checklists', 'colostrum_feeding_24h')) {
                $table->boolean('colostrum_feeding_24h')->default(false)->after('treatment_woundsarex');
            }
            if (!Schema::hasColumn('calving_checklists', 'mamumune')) {
                $table->boolean('mamumune')->default(false)->after('colostrum_feeding_24h');
            }
            if (!Schema::hasColumn('calving_checklists', 'tagging_checklist_date')) {
                $table->date('tagging_checklist_date')->nullable()->after('mamumune');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calving_checklists', function (Blueprint $table) {
            $table->dropColumn([
                'week',
                'times_of_pregnancy',
                'location',
                'treatment_iodine',
                'treatment_woundsarex',
                'colostrum_feeding_24h',
                'mamumune',
                'tagging_checklist_date'
            ]);
        });
    }
};
