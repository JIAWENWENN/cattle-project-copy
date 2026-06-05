<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_livestocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_document_id');
            $table->string('tag_no');
            $table->string('category')->nullable();
            $table->string('colour')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->boolean('condition_good')->default(false);
            $table->boolean('condition_not_good')->default(false);
            $table->text('remarks')->nullable();
            $table->string('purpose')->nullable();
            $table->string('yard')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('transfer_document_id')->references('id')->on('transfer_documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_livestocks');
    }
};
