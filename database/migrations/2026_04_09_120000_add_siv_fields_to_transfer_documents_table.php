<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_documents', function (Blueprint $table) {
            $table->string('address')->nullable()->after('driver_ic');
            $table->string('siv_no')->nullable()->after('address');
            $table->string('receipt_no')->nullable()->after('siv_no');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_documents', function (Blueprint $table) {
            $table->dropColumn(['address', 'siv_no', 'receipt_no']);
        });
    }
};
