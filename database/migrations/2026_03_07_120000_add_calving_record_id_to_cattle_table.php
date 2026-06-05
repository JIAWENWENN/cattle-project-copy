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
        Schema::table('cattle', function (Blueprint $table) {
            if (!Schema::hasColumn('cattle', 'calving_record_id')) {
                $table->unsignedBigInteger('calving_record_id')->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (Schema::hasColumn('cattle', 'calving_record_id')) {
                $table->dropIndex(['calving_record_id']);
                $table->dropColumn('calving_record_id');
            }
        });
    }
};
