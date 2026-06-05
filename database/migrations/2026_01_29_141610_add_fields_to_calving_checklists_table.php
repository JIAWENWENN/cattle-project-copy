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
                $table->string('times_of_pregnancy')->nullable()->after('colour');
            }
            
            if (!Schema::hasColumn('calving_checklists', 'location')) {
                $table->string('location')->nullable()->after('times_of_pregnancy');
            }
            
            if (Schema::hasColumn('calving_checklists', 'maminume') && !Schema::hasColumn('calving_checklists', 'mamumune')) {
                $table->renameColumn('maminume', 'mamumune');
            } elseif (!Schema::hasColumn('calving_checklists', 'mamumune')) {
                $table->boolean('mamumune')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('calving_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('calving_checklists', 'times_of_pregnancy')) {
                $table->dropColumn('times_of_pregnancy');
            }
            if (Schema::hasColumn('calving_checklists', 'location')) {
                $table->dropColumn('location');
            }
            if (Schema::hasColumn('calving_checklists', 'mamumune')) {
                $table->renameColumn('mamumune', 'maminume');
            }
        });
    }
};
