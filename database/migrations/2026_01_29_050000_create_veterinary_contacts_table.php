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
        Schema::create('veterinary_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // veterinarian, clinic, supplier
            $table->string('position')->nullable();
            $table->string('organization')->nullable();
            $table->string('phone');
            $table->string('alt_phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('availability')->nullable();
            $table->boolean('emergency')->default(false);
            $table->integer('rating')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veterinary_contacts');
    }
};
