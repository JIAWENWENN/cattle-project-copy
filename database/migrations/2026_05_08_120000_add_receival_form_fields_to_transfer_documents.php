<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_documents', function (Blueprint $table) {
            $table->string('form_document_no')->nullable()->after('document_no');
            $table->string('revision_no')->nullable()->after('form_document_no');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_documents', function (Blueprint $table) {
            $table->dropColumn(['form_document_no', 'revision_no']);
        });
    }
};
