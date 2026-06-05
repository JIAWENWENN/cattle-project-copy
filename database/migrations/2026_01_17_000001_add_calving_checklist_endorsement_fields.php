<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add endorsement workflow fields to existing table if they do not exist
        Schema::table('calving_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('calving_checklists', 'endorsement_step')) {
                $table->integer('endorsement_step')->default(0);
            }
            
            if (!Schema::hasColumn('calving_checklists', 'workflow_status')) {
                $table->string('workflow_status')->default('pending');
            }
            
            if (!Schema::hasColumn('calving_checklists', 'endorsement_documents')) {
                $table->json('endorsement_documents')->nullable();
            }
            
            if (!Schema::hasColumn('calving_checklists', 'is_completed')) {
                $table->boolean('is_completed')->default(false);
            }
            
            if (!Schema::hasColumn('calving_checklists', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            if (!Schema::hasColumn('calving_checklists', 'checked_by_name')) {
                $table->string('checked_by_name')->nullable();
            }
            
            if (!Schema::hasColumn('calving_checklists', 'checked_by_unit')) {
                $table->string('checked_by_unit')->nullable();
            }
            
            if (!Schema::hasColumn('calving_checklists', 'checked_by_signature')) {
                $table->boolean('checked_by_signature')->default(false);
            }
            
            if (!Schema::hasColumn('calving_checklists', 'checked_at')) {
                $table->timestamp('checked_at')->nullable();
            }
        });
        
        // Update existing records to set proper endorsement_step based on status
        DB::statement("
            UPDATE calving_checklists SET 
                endorsement_step = CASE 
                    WHEN status = 'pending' THEN 0
                    WHEN status = 'issued' THEN 1
                    WHEN status = 'verified' THEN 2
                    WHEN status = 'checked' THEN 3
                    WHEN status = 'witnessed' THEN 4
                    WHEN status = 'approved' THEN 5
                    ELSE 0
                END,
                workflow_status = CASE 
                    WHEN status = 'approved' THEN 'completed'
                    ELSE status
                END
        ");
    }

    public function down()
    {
        Schema::table('calving_checklists', function (Blueprint $table) {
            $table->dropColumn([
                'endorsement_step',
                'workflow_status',
                'endorsement_documents',
                'is_completed',
                'completed_at',
                'checked_by_name',
                'checked_by_unit',
                'checked_by_signature',
                'checked_at',
            ]);
        });
    }
};
