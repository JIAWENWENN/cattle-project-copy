<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stocks')) {
            \DB::table('stocks')
                ->where('stockable_type', 'App\Models\Feed')
                ->delete();
        }

        Schema::dropIfExists('feed_histories');
        Schema::dropIfExists('feeds');

        if (Schema::hasColumn('users', 'feed_columns')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('feed_columns');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('feeds')) {
            Schema::create('feeds', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category');
                $table->string('unit');
                $table->integer('qty')->default(0);
                $table->text('remark')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('feed_histories')) {
            Schema::create('feed_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feed_id')->constrained()->onDelete('cascade');
                $table->string('user');
                $table->string('action');
                $table->string('detail');
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('users', 'feed_columns')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('feed_columns')->nullable();
            });
        }
    }
};
