<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('calving_records', function (Blueprint $table) {
            if (!Schema::hasColumn('calving_records', 'issued_by_date')) {
                $table->date('issued_by_date')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'verified_by_date')) {
                $table->date('verified_by_date')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'checked_by_date')) {
                $table->date('checked_by_date')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'witnessed_by_date')) {
                $table->date('witnessed_by_date')->nullable();
            }
            if (!Schema::hasColumn('calving_records', 'approved_by_date')) {
                $table->date('approved_by_date')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('calving_records', function (Blueprint $table) {
            if (Schema::hasColumn('calving_records', 'issued_by_date')) {
                $table->dropColumn('issued_by_date');
            }
            if (Schema::hasColumn('calving_records', 'verified_by_date')) {
                $table->dropColumn('verified_by_date');
            }
            if (Schema::hasColumn('calving_records', 'checked_by_date')) {
                $table->dropColumn('checked_by_date');
            }
            if (Schema::hasColumn('calving_records', 'witnessed_by_date')) {
                $table->dropColumn('witnessed_by_date');
            }
            if (Schema::hasColumn('calving_records', 'approved_by_date')) {
                $table->dropColumn('approved_by_date');
            }
        });
    }
};
