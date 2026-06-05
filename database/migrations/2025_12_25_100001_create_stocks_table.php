<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            // Polymorphic relationship to Feed or Medication
            $table->string('stockable_type'); // 'App\Models\Feed' or 'App\Models\Medication'
            $table->unsignedBigInteger('stockable_id');
            // Supplier link
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            // Stock threshold settings (user configurable)
            $table->integer('min_threshold')->default(50);
            $table->integer('safety_stock')->default(20);
            $table->decimal('oso_avg', 10, 2)->default(0)->comment('Order Point Average / Daily Usage Rate');
            $table->integer('lead_time')->default(5)->comment('Lead time in days');
            $table->text('remark')->nullable();
            $table->timestamps();

            // Ensure one stock setting per item
            $table->unique(['stockable_type', 'stockable_id']);
            $table->index(['stockable_type', 'stockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
