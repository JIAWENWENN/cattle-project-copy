<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('module'); // e.g., 'Dashboard', 'Cattle', 'Settings'
            $table->enum('permission', ['no-access', 'view', 'create', 'edit', 'full', 'approve']);
            $table->timestamps();

            $table->unique(['user_id', 'module']); // One permission per user per module
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
