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
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic')->nullable();
            $table->string('category')->nullable();
            $table->string('batch_number')->nullable();
            $table->integer('batch_qty')->default(0);
            $table->integer('stock')->default(0);
            $table->date('receival_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('remark')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('medication_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->onDelete('cascade');
            $table->string('user');
            $table->string('action');
            $table->text('detail');
            $table->timestamps();
        });
    }
};
