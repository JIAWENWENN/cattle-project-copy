<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('calving_records', function (Blueprint $table) {
            $table->string('cattle_no_request_form')->nullable()->after('lcc_running_number');
            $table->string('endorsement_name')->nullable()->after('remarks');
            $table->date('endorsement_date')->nullable()->after('endorsement_name');
        });
    }

    public function down()
    {
        Schema::table('calving_records', function (Blueprint $table) {
            $table->dropColumn('cattle_no_request_form');
            $table->dropColumn('endorsement_name');
            $table->dropColumn('endorsement_date');
        });
    }
};
