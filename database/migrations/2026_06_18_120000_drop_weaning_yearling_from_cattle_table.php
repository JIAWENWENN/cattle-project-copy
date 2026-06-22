<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (Schema::hasColumn('cattle', 'weaning_weight')) {
                $table->dropColumn('weaning_weight');
            }
            if (Schema::hasColumn('cattle', 'yearling_weight')) {
                $table->dropColumn('yearling_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            $table->decimal('weaning_weight', 8, 2)->nullable()->after('sire_coat_colour');
            $table->decimal('yearling_weight', 8, 2)->nullable()->after('weaning_weight');
        });
    }
};
