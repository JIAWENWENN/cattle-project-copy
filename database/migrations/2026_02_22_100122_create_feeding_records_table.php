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
        Schema::create('feeding_records', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('trip_no');
            $table->integer('cattle_count');
            $table->string('feed_type');
            $table->decimal('planned', 10, 2)->default(0);
            $table->decimal('actual_usage', 10, 2)->default(0);
            $table->decimal('receive', 10, 2)->default(0);
            $table->decimal('carry_forward', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['date', 'trip_no']);
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeding_records');
    }
};
