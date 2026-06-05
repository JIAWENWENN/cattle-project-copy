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
        Schema::table('postmortem_examinations', function (Blueprint $table) {
            // Additional organ fields based on LMC form
            $table->text('bladder_findings')->nullable()->after('urinary_bladder_findings');
            $table->text('liver_findings')->nullable()->after('bladder_findings');
            $table->text('spleen_findings')->nullable()->after('liver_findings');
            $table->text('joint_findings')->nullable()->after('spleen_findings');
            $table->text('subcutaneous_findings')->nullable()->after('joint_findings');
            $table->text('colon_findings')->nullable()->after('small_intestine_findings');
            $table->text('reproductive_organ_findings')->nullable()->after('colon_findings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postmortem_examinations', function (Blueprint $table) {
            $table->dropColumn([
                'bladder_findings',
                'liver_findings',
                'spleen_findings',
                'joint_findings',
                'subcutaneous_findings',
                'colon_findings',
                'reproductive_organ_findings'
            ]);
        });
    }
};
