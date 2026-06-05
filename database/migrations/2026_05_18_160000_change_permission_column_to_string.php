<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('permission')->default('no-access')->change();
            });
        } else {
            DB::statement("ALTER TABLE permissions MODIFY COLUMN permission VARCHAR(255) NOT NULL DEFAULT 'no-access'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('permission')->default('no-access')->change();
            });
        } else {
            DB::statement("ALTER TABLE permissions MODIFY COLUMN permission ENUM('no-access','view','create','edit','delete','full','approve') NOT NULL DEFAULT 'no-access'");
        }
    }
};
