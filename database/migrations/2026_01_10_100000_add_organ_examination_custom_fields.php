<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $organFields = [
            // Heart options
            ['field_type' => 'heart_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'heart_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'heart_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Trachea options
            ['field_type' => 'trachea_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'trachea_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'trachea_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Lung floating test options
            ['field_type' => 'lung_floating_options', 'value' => 'Positive (Float)', 'sort_order' => 1],
            ['field_type' => 'lung_floating_options', 'value' => 'Negative (Sink)', 'sort_order' => 2],
            ['field_type' => 'lung_floating_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Diaphragma test options
            ['field_type' => 'diaphragma_options', 'value' => 'Positive (+)', 'sort_order' => 1],
            ['field_type' => 'diaphragma_options', 'value' => 'Negative (-)', 'sort_order' => 2],
            ['field_type' => 'diaphragma_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Kidney options
            ['field_type' => 'kidney_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'kidney_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'kidney_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Urinary bladder options
            ['field_type' => 'urinary_bladder_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'urinary_bladder_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'urinary_bladder_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Rumen options
            ['field_type' => 'rumen_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'rumen_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'rumen_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Reticulum options
            ['field_type' => 'reticulum_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'reticulum_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'reticulum_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Omasum options
            ['field_type' => 'omasum_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'omasum_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'omasum_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Abomasum options
            ['field_type' => 'abomasum_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'abomasum_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'abomasum_options', 'value' => 'NE', 'sort_order' => 3],
            
            // Small intestine options
            ['field_type' => 'small_intestine_options', 'value' => 'Normal', 'sort_order' => 1],
            ['field_type' => 'small_intestine_options', 'value' => 'Abnormal', 'sort_order' => 2],
            ['field_type' => 'small_intestine_options', 'value' => 'NE', 'sort_order' => 3],
        ];

        foreach ($organFields as $field) {
            DB::table('mortality_custom_fields')->insertOrIgnore([
                'field_type' => $field['field_type'],
                'value' => $field['value'],
                'sort_order' => $field['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fieldTypes = [
            'heart_options',
            'trachea_options',
            'lung_floating_options',
            'diaphragma_options',
            'kidney_options',
            'urinary_bladder_options',
            'rumen_options',
            'reticulum_options',
            'omasum_options',
            'abomasum_options',
            'small_intestine_options',
        ];

        DB::table('mortality_custom_fields')
            ->whereIn('field_type', $fieldTypes)
            ->delete();
    }
};
