<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('field_level_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('field', 100);
            $table->string('action', 50);
            $table->json('allowed_roles')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module', 'field', 'action'], 'module_field_action_unique');
        });

        // Seed default field level permissions
        $this->seedDefaultPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('field_level_permissions');
    }

    protected function seedDefaultPermissions()
    {
        $permissions = [
            // Mortality Module
            [
                'module' => 'Mortality',
                'field' => 'create_lmc',
                'action' => 'create',
                'allowed_roles' => json_encode(['estate', 'livestock', 'admin']),
                'description' => 'Allow role to create new LMC (Loss of Mortality Case) records',
                'is_active' => true,
            ],
            [
                'module' => 'Mortality',
                'field' => 'pm_examination',
                'action' => 'perform',
                'allowed_roles' => json_encode(['livestock', 'admin']),
                'description' => 'Allow role to perform post-mortem examinations',
                'is_active' => true,
            ],
            [
                'module' => 'Mortality',
                'field' => 'approve_lmc',
                'action' => 'approve',
                'allowed_roles' => json_encode(['security', 'supervisor', 'penyelia', 'manager', 'admin']),
                'description' => 'Allow role to approve LMC at various workflow steps',
                'is_active' => true,
            ],
            [
                'module' => 'Mortality',
                'field' => 'delete_lmc',
                'action' => 'delete',
                'allowed_roles' => json_encode(['admin']),
                'description' => 'Allow role to delete LMC records',
                'is_active' => true,
            ],
            [
                'module' => 'Mortality',
                'field' => 'endorse_document',
                'action' => 'upload',
                'allowed_roles' => json_encode(['livestock', 'security', 'supervisor', 'penyelia', 'manager', 'admin']),
                'description' => 'Allow role to upload endorsement documents',
                'is_active' => true,
            ],
            [
                'module' => 'Mortality',
                'field' => 'complete_lmc',
                'action' => 'complete',
                'allowed_roles' => json_encode(['admin']),
                'description' => 'Allow role to mark LMC as completed',
                'is_active' => true,
            ],

            // Cattle Module
            [
                'module' => 'Cattle',
                'field' => 'view_cattle',
                'action' => 'view',
                'allowed_roles' => json_encode(['estate', 'livestock', 'security', 'supervisor', 'penyelia', 'manager', 'admin']),
                'description' => 'Allow role to view cattle records',
                'is_active' => true,
            ],
            [
                'module' => 'Cattle',
                'field' => 'add_cattle',
                'action' => 'create',
                'allowed_roles' => json_encode(['estate', 'livestock', 'admin']),
                'description' => 'Allow role to add new cattle records',
                'is_active' => true,
            ],
            [
                'module' => 'Cattle',
                'field' => 'edit_cattle',
                'action' => 'edit',
                'allowed_roles' => json_encode(['estate', 'livestock', 'supervisor', 'admin']),
                'description' => 'Allow role to edit cattle records',
                'is_active' => true,
            ],
            [
                'module' => 'Cattle',
                'field' => 'delete_cattle',
                'action' => 'delete',
                'allowed_roles' => json_encode(['admin']),
                'description' => 'Allow role to delete cattle records',
                'is_active' => true,
            ],

            // Health Module
            [
                'module' => 'Health',
                'field' => 'record_treatment',
                'action' => 'create',
                'allowed_roles' => json_encode(['livestock', 'admin']),
                'description' => 'Allow role to record treatments',
                'is_active' => true,
            ],
            [
                'module' => 'Health',
                'field' => 'approve_treatment',
                'action' => 'approve',
                'allowed_roles' => json_encode(['supervisor', 'manager', 'admin']),
                'description' => 'Allow role to approve treatments',
                'is_active' => true,
            ],

            // Inventory Module
            [
                'module' => 'Inventory',
                'field' => 'manage_stock',
                'action' => 'manage',
                'allowed_roles' => json_encode(['storekeeper', 'admin']),
                'description' => 'Allow role to manage inventory stock',
                'is_active' => true,
            ],

            // Feeding Module
            [
                'module' => 'Feeding',
                'field' => 'record_feeding',
                'action' => 'create',
                'allowed_roles' => json_encode(['estate', 'livestock', 'admin']),
                'description' => 'Allow role to record feeding activities',
                'is_active' => true,
            ],
        ];

        foreach ($permissions as $permission) {
            \App\Models\FieldLevelPermission::create($permission);
        }
    }
};
