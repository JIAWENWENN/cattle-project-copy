<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('grazing_data', function (Blueprint $table) {
                $table->dropUnique(['estate_id', 'month']);
            });
        } catch (\Exception $e) {
            // Ignore if index doesn't exist
        }

        Schema::table('grazing_data', function (Blueprint $table) {
            $table->string('herd')->default('')->change();
        });

        try {
            Schema::table('grazing_data', function (Blueprint $table) {
                $table->unique(['estate_id', 'month', 'herd']);
            });
        } catch (\Exception $e) {
            // Ignore if index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('grazing_data', function (Blueprint $table) {
                $table->dropUnique(['estate_id', 'month', 'herd']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('grazing_data', function (Blueprint $table) {
                $table->unique(['estate_id', 'month']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        Schema::table('grazing_data', function (Blueprint $table) {
            $table->string('herd')->nullable()->change();
        });
    }
};
