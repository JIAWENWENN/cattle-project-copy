<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_operation_entries', function (Blueprint $table) {
            $table->id();
            $table->string('estate_name')->nullable();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('category_code', 10);
            $table->json('daily_values')->nullable();
            $table->integer('missing')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['estate_name', 'month', 'year', 'category_code'], 'doml_unique_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_operation_entries');
    }
};
