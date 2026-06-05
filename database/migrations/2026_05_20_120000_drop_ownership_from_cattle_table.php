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
            $table->dropColumn('ownership');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            $table->string('ownership')->nullable()->after('general_condition');
        });
    }
};
