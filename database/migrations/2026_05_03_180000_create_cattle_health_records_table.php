<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattle_health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cattle_id')->constrained('cattle')->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('reference_no')->nullable();
            $table->string('category')->nullable();
            $table->string('operating_unit')->nullable();
            $table->string('colour')->nullable();
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->string('treatment')->nullable();
            $table->string('dosage')->nullable();
            $table->string('medication')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['cattle_id', 'source_type']);
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->boolean('is_reopened')->default(false)->after('endorsement_step');
        });

        Schema::table('calving_records', function (Blueprint $table) {
            $table->boolean('is_reopened')->default(false)->after('endorsement_documents');
        });

        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->boolean('is_reopened')->default(false)->after('endorsement_step');
        });
    }

    public function down(): void
    {
        Schema::table('mortality_cases', function (Blueprint $table) {
            $table->dropColumn('is_reopened');
        });

        Schema::table('calving_records', function (Blueprint $table) {
            $table->dropColumn('is_reopened');
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('is_reopened');
        });

        Schema::dropIfExists('cattle_health_records');
    }
};
