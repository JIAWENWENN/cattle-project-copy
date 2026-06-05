<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_cattle_return_workflows', function (Blueprint $table) {
            $table->id();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('operating_unit');
            $table->integer('endorsement_step')->default(0);
            $table->json('endorsement_documents')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['period_from', 'period_to', 'operating_unit'], 'weekly_return_period_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_cattle_return_workflows');
    }
};
