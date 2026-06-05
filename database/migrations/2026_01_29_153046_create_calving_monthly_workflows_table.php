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
        Schema::create('calving_monthly_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('month_year');
            $table->string('operating_unit');
            $table->integer('endorsement_step')->default(0);
            $table->json('endorsement_documents')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['month_year', 'operating_unit'], 'month_unit_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calving_monthly_workflows');
    }
};
