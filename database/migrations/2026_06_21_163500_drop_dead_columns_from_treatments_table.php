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
        Schema::table('treatments', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('treatments', 'follow_up_done')) $columnsToDrop[] = 'follow_up_done';
            if (Schema::hasColumn('treatments', 'rejection_reason')) $columnsToDrop[] = 'rejection_reason';
            if (Schema::hasColumn('treatments', 'week')) $columnsToDrop[] = 'week';
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->boolean('follow_up_done')->default(false)->after('follow_up_date');
            $table->text('rejection_reason')->nullable()->after('created_by');
            $table->string('week')->nullable()->after('date');
        });
    }
};
