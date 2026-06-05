<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_no')->unique();
            $table->string('type'); // CTV, Receival, SIV
            $table->string('from_location');
            $table->string('to_location')->nullable();
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_tel')->nullable();
            $table->string('driver_ic')->nullable();
            $table->string('customer_name')->nullable();
            $table->integer('total_cattle')->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->string('status')->default('pending'); // pending, in_progress, completed, rejected
            $table->string('current_step')->default('issued');
            $table->integer('endorsement_step')->default(0);
            $table->json('endorsement_documents')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_documents');
    }
};
