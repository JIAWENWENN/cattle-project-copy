<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calving_workflow_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issued_by_user_id')->nullable();
            $table->json('issued_by_user_ids')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->json('verified_by_user_ids')->nullable();
            $table->unsignedBigInteger('checked_by_user_id')->nullable();
            $table->json('checked_by_user_ids')->nullable();
            $table->unsignedBigInteger('witnessed_by_user_id')->nullable();
            $table->json('witnessed_by_user_ids')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->json('approved_by_user_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calving_workflow_assignments');
    }
};
