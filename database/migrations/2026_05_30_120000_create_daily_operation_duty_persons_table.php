<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_operation_duty_persons', function (Blueprint $table) {
            $table->id();
            $table->string('estate_name')->nullable();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('week', 10)->default('all');
            $table->json('names')->nullable();
            $table->timestamps();

            $table->unique(['estate_name', 'month', 'year', 'week'], 'doml_duty_person_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_operation_duty_persons');
    }
};
