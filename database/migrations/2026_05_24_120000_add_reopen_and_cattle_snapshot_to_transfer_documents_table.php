<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_documents', function (Blueprint $table) {
            $table->boolean('is_reopened')->default(false)->after('endorsement_documents');
            $table->json('cattle_location_snapshot')->nullable()->after('is_reopened');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_documents', function (Blueprint $table) {
            $table->dropColumn(['is_reopened', 'cattle_location_snapshot']);
        });
    }
};
