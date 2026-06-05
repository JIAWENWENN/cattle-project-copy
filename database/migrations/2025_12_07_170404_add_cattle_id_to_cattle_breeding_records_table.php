<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cattle_breeding_records', function (Blueprint $table) {
            // Foreign key
            $table->unsignedBigInteger('cattle_id')->after('id');

            // Breeding information
            $table->date('breeding_date');
            $table->string('breeding_method')->nullable(); // Natural, AI, ET
            $table->string('bull_tag')->nullable();
            $table->string('bull_breed')->nullable();
            $table->date('expected_calving_date')->nullable();
            $table->date('actual_calving_date')->nullable();
            $table->string('calving_outcome')->nullable(); // Live birth, stillborn, etc.
            $table->string('calf_tag')->nullable();
            $table->string('calf_gender')->nullable();
            $table->decimal('calf_birth_weight', 8, 2)->nullable();
            $table->text('remarks')->nullable();

            // Foreign key constraint
            $table->foreign('cattle_id')
                ->references('id')
                ->on('cattle')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        try {
            Schema::table('cattle_breeding_records', function (Blueprint $table) {
                $table->dropForeign(['cattle_id']);
            });
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            Schema::table('cattle_breeding_records', function (Blueprint $table) {
                $table->dropColumn([
                    'cattle_id',
                    'breeding_date',
                    'breeding_method',
                    'bull_tag',
                    'bull_breed',
                    'expected_calving_date',
                    'actual_calving_date',
                    'calving_outcome',
                    'calf_tag',
                    'calf_gender',
                    'calf_birth_weight',
                    'remarks',
                ]);
            });
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
