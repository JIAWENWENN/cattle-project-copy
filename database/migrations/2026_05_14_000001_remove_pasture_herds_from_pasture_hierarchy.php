<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pasture_blocks')) {
            return;
        }

        if (!Schema::hasColumn('pasture_blocks', 'estate_id')) {
            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->foreignId('estate_id')->nullable()->after('id')->constrained('estates')->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('pasture_blocks', 'pasture_herd_id')) {
            Schema::dropIfExists('pasture_herds');
            return;
        }

        if (Schema::hasTable('pasture_herds') && Schema::hasColumn('pasture_blocks', 'pasture_herd_id')) {
            DB::table('pasture_blocks')
                ->join('pasture_herds', 'pasture_blocks.pasture_herd_id', '=', 'pasture_herds.id')
                ->update(['pasture_blocks.estate_id' => DB::raw('pasture_herds.estate_id')]);
        }

        if (Schema::hasColumn('pasture_blocks', 'pasture_herd_id')) {
            $this->dropForeignKeysForColumn('pasture_blocks', 'pasture_herd_id');
            $this->dropIndexesForColumns('pasture_blocks', ['pasture_herd_id', 'name']);

            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->dropColumn('pasture_herd_id');
            });
        }

        DB::table('pasture_blocks')->whereNull('estate_id')->delete();

        Schema::dropIfExists('pasture_herds');
    }

    public function down(): void
    {
        Schema::dropIfExists('pasture_herds');
        Schema::create('pasture_herds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained('estates')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['estate_id', 'name']);
        });

        if (!Schema::hasColumn('pasture_blocks', 'pasture_herd_id')) {
            try {
                Schema::table('pasture_blocks', function (Blueprint $table) {
                    $table->foreignId('pasture_herd_id')->nullable()->after('estate_id')->constrained('pasture_herds')->cascadeOnDelete();
                });
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            DB::table('estates')->select('id', 'name')->orderBy('id')->chunk(100, function ($estates) {
                foreach ($estates as $estate) {
                    $herdId = DB::table('pasture_herds')->insertGetId([
                        'estate_id' => $estate->id,
                        'name' => $estate->name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('pasture_blocks')
                        ->where('estate_id', $estate->id)
                        ->update(['pasture_herd_id' => $herdId]);
                }
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->dropForeign(['estate_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->dropUnique(['estate_id', 'name']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->dropColumn('estate_id');
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->foreignId('pasture_herd_id')->nullable(false)->change();
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('pasture_blocks', function (Blueprint $table) {
                $table->unique(['pasture_herd_id', 'name']);
            });
        } catch (\Exception $e) {
            // Ignore
        }
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $database = DB::getDatabaseName();

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    private function dropIndexesForColumns(string $table, array $columns): void
    {
        $database = DB::getDatabaseName();

        $indexes = DB::table('information_schema.STATISTICS')
            ->select('INDEX_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('NON_UNIQUE', 0)
            ->where('INDEX_NAME', '!=', 'PRIMARY')
            ->groupBy('INDEX_NAME')
            ->get()
            ->filter(function ($index) use ($database, $table, $columns) {
                $indexColumns = DB::table('information_schema.STATISTICS')
                    ->where('TABLE_SCHEMA', $database)
                    ->where('TABLE_NAME', $table)
                    ->where('INDEX_NAME', $index->INDEX_NAME)
                    ->orderBy('SEQ_IN_INDEX')
                    ->pluck('COLUMN_NAME')
                    ->all();

                return $indexColumns === $columns;
            })
            ->pluck('INDEX_NAME');

        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
