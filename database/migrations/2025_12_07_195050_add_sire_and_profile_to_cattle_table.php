<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            // Sire information
            $table->string('sire_tag')->nullable()->after('dam_colour');
            $table->string('sire_category')->nullable()->after('sire_tag');
            $table->string('sire_coat_colour')->nullable()->after('sire_category');

            // Profile picture
            $table->string('profile_picture')->nullable()->after('remarks');

            // Rename color to coat_colour if it exists
            if (Schema::hasColumn('cattle', 'color')) {
                $table->renameColumn('color', 'coat_colour');
            } else {
                $table->string('coat_colour')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (Schema::hasColumn('cattle', 'sire_tag')) {
                $table->dropColumn('sire_tag');
            }
            if (Schema::hasColumn('cattle', 'sire_category')) {
                $table->dropColumn('sire_category');
            }
            if (Schema::hasColumn('cattle', 'sire_coat_colour')) {
                $table->dropColumn('sire_coat_colour');
            }
            if (Schema::hasColumn('cattle', 'profile_picture')) {
                $table->dropColumn('profile_picture');
            }

            if (Schema::hasColumn('cattle', 'coat_colour')) {
                $table->renameColumn('coat_colour', 'color');
            }
        });
    }
};
