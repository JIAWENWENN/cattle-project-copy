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
        Schema::create('tasks', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('title');
            $blueprint->text('description')->nullable();
            $blueprint->string('status')->default('pending');
            $blueprint->string('priority')->default('Medium');
            $blueprint->string('category')->nullable();
            $blueprint->date('due_date')->nullable();
            
            // Foreign keys for users
            $blueprint->foreignId('assignee_id')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
