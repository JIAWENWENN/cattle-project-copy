<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'medication_columns')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('medication_columns');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'medication_columns')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('medication_columns')->nullable()->after('profile_photo');
            });
        }
    }
};
