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
        Schema::table('veterinary_contacts', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('emergency');
            $table->dropColumn('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veterinary_contacts', function (Blueprint $table) {
            $table->integer('rating')->default(0)->after('emergency');
            $table->dropColumn('photo_path');
        });
    }
};
