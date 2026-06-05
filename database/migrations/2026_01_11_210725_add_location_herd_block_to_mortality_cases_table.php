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
            $table->string('location')->nullable()->after('category');
            $table->string('herd')->nullable()->after('location');
            $table->string('block')->nullable()->after('herd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->dropColumn(['location', 'herd', 'block']);
        });
    }
};
