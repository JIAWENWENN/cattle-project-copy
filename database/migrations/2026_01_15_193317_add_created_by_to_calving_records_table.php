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
            $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        try {
            Schema::table('calving_records', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('calving_records', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
