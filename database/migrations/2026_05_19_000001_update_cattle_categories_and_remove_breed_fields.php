<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $allowedCategories = ['BC', 'WB', 'H', 'BB', 'FC', 'MC'];

    private array $categoryMap = [
        'BREEDER COW (B/C)' => 'BC',
        'BREEDER COW' => 'BC',
        'B/C' => 'BC',
        'BC' => 'BC',
        'WEANER BULL (W/B)' => 'WB',
        'WEANER BULL' => 'WB',
        'W/B' => 'WB',
        'WB' => 'WB',
        'HEIFERS (H)' => 'H',
        'HEIFER (H)' => 'H',
        'HEIFER' => 'H',
        'HEIFERS' => 'H',
        'H' => 'H',
        'BREEDER BULL (B/B)' => 'BB',
        'BREEDER BULL' => 'BB',
        'B/B' => 'BB',
        'BB' => 'BB',
        'FEMALE CALF (F/C)' => 'FC',
        'FEMALE CALF' => 'FC',
        'FEMALECALF' => 'FC',
        'F/C' => 'FC',
        'FC' => 'FC',
        'MALE CALF (M/C)' => 'MC',
        'MALE CALF' => 'MC',
        'MALECALF' => 'MC',
        'M/C' => 'MC',
        'MC' => 'MC',
        'BREEDER' => 'BC',
    ];

    public function up(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (!Schema::hasColumn('cattle', 'dam_category')) {
                $table->string('dam_category')->nullable()->after('dam_tag');
            }

            if (!Schema::hasColumn('cattle', 'sire_category')) {
                $table->string('sire_category')->nullable()->after('sire_tag');
            }
        });

        foreach ($this->categoryMap as $from => $to) {
            DB::table('cattle')
                ->whereRaw('UPPER(TRIM(category)) = ?', [$from])
                ->update(['category' => $to]);
        }

        DB::table('cattle_custom_fields')
            ->whereIn('field_type', ['breed', 'dam_breed', 'sire_breed'])
            ->delete();

        DB::table('cattle_custom_fields')
            ->where('field_type', 'category')
            ->whereNotIn('value', $this->allowedCategories)
            ->delete();

        foreach ($this->allowedCategories as $index => $category) {
            DB::table('cattle_custom_fields')->updateOrInsert(
                ['field_type' => 'category', 'value' => $category],
                [
                    'is_active' => true,
                    'sort_order' => $index,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $dropColumns = array_values(array_filter([
            Schema::hasColumn('cattle', 'breed') ? 'breed' : null,
            Schema::hasColumn('cattle', 'dam_breed') ? 'dam_breed' : null,
            Schema::hasColumn('cattle', 'sire_breed') ? 'sire_breed' : null,
            Schema::hasColumn('cattle', 'weaning_date') ? 'weaning_date' : null,
            Schema::hasColumn('cattle', 'yearling_date') ? 'yearling_date' : null,
        ]));

        if ($dropColumns !== []) {
            Schema::table('cattle', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('cattle', function (Blueprint $table) {
            if (!Schema::hasColumn('cattle', 'breed')) {
                $table->string('breed')->nullable()->after('category');
            }

            if (!Schema::hasColumn('cattle', 'dam_breed')) {
                $table->string('dam_breed')->nullable()->after('dam_tag');
            }

            if (!Schema::hasColumn('cattle', 'sire_breed')) {
                $table->string('sire_breed')->nullable()->after('sire_tag');
            }

            if (!Schema::hasColumn('cattle', 'weaning_date')) {
                $table->date('weaning_date')->nullable()->after('sire_coat_colour');
            }

            if (!Schema::hasColumn('cattle', 'yearling_date')) {
                $table->date('yearling_date')->nullable()->after('weaning_weight');
            }
        });

        Schema::table('cattle', function (Blueprint $table) {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('cattle', 'dam_category') ? 'dam_category' : null,
                Schema::hasColumn('cattle', 'sire_category') ? 'sire_category' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
