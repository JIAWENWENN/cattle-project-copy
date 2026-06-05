<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_workflow_assignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('issued_by_user_id')->nullable();
            $table->json('issued_by_user_ids')->nullable();

            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->json('approved_by_user_ids')->nullable();

            $table->unsignedBigInteger('transported_by_user_id')->nullable();
            $table->json('transported_by_user_ids')->nullable();

            $table->unsignedBigInteger('witnessed_transit_by_user_id')->nullable();
            $table->json('witnessed_transit_by_user_ids')->nullable();

            $table->unsignedBigInteger('verified_transit_by_user_id')->nullable();
            $table->json('verified_transit_by_user_ids')->nullable();

            $table->unsignedBigInteger('witnessed_receive_by_user_id')->nullable();
            $table->json('witnessed_receive_by_user_ids')->nullable();

            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->json('received_by_user_ids')->nullable();

            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->json('completed_by_user_ids')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_workflow_assignments');
    }
};
