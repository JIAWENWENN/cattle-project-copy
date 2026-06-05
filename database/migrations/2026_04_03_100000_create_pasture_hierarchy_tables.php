<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasture_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained('estates')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('area', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pasture_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasture_block_id')->constrained('pasture_blocks')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['pasture_block_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasture_phases');
        Schema::dropIfExists('pasture_blocks');
    }
};
