<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_document_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('step');
            $table->boolean('action')->default(false);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('transfer_document_id')->references('id')->on('transfer_documents')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_approvals');
    }
};
