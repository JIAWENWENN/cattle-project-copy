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
        Schema::create('delivery_records', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->date('date');
            $table->time('time');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Driver
            $table->string('vehicle');
            $table->string('origin');
            $table->string('destination');
            $table->string('cargo_type');
            $table->string('cargo_weight');
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->text('delivery_notes')->nullable();
            $table->string('customer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_records');
    }
};
