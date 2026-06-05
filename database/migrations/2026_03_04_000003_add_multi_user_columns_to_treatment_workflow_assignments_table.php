<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_workflow_assignments', function (Blueprint $table) {
            $table->json('prepared_by_user_ids')->nullable()->after('prepared_by_user_id');
            $table->json('checked_by_user_ids')->nullable()->after('checked_by_user_id');
            $table->json('approved_by_user_ids')->nullable()->after('approved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_workflow_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'prepared_by_user_ids',
                'checked_by_user_ids',
                'approved_by_user_ids',
            ]);
        });
    }
};

