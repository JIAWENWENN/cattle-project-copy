<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (Schema::hasColumn('cattle', 'herd')) {
                $table->dropColumn('herd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (!Schema::hasColumn('cattle', 'herd')) {
                $table->string('herd')->nullable()->after('ownership');
            }
        });
    }
};
