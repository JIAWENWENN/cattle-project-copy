<?php

namespace App\Services;

use App\Models\TaskNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class WorkflowNotificationService
{
    /**
     * Create a workflow notification for the next responsible user
     */
    public static function createNotification(
        string $module,
        string $entityType,
        int $entityId,
        string $action,
        string $title,
        string $message,
        ?int $assignedToId = null
    ): ?TaskNotification {
        // If no specific user assigned, find the next user based on module/role
        if (!$assignedToId) {
            $assignedToId = self::findNextUser($module, $entityType, $entityId, $action);
        }
        
        if (!$assignedToId) {
            return null; // No user found to notify
        }
        
        return TaskNotification::create([
            'type' => 'workflow',
            'title' => $title,
            'message' => $message,
            'user_id' => $assignedToId,
            'is_read' => false,
            'created_by' => Auth::id(),
        ]);
    }
    
    /**
     * Find the next user based on module and workflow step
     */
    protected static function findNextUser($module, $entityType, $entityId, $currentAction): ?int
    {
        // Use assignment models if they exist for the module
        $assignmentClass = self::getAssignmentModel($module);
        
        if ($assignmentClass) {
            $assignment = $assignmentClass::first();
            if ($assignment) {
                return self::getUserIdForAction($assignment, $currentAction);
            }
        }
        
        // Fallback to role-based assignment
        return self::findUserByRole($module, $currentAction);
    }
    
    protected static function getAssignmentModel($module)
    {
        $models = [
            'mortality' => null, // Uses workflow_assignments via MortalityController (explicit user targeting)
            'calving' => \App\Models\CalvingWorkflowAssignment::class,
            'health_treatment' => \App\Models\TreatmentWorkflowAssignment::class,
            'document_endorsement' => \App\Models\DocumentEndorsementWorkflowAssignment::class,
            'transfer' => \App\Models\TransferWorkflowAssignment::class,
            'calving_checklist' => \App\Models\CalvingChecklistWorkflowAssignment::class,
            // Add other modules as needed
        ];
        
        return $models[$module] ?? null;
    }
    
    protected static function getUserIdForAction($assignment, $action)
    {
        // Try user_ids array first
        $userIds = $assignment->user_ids ?? [];
        if (isset($userIds[$action]) && !empty($userIds[$action])) {
            return $userIds[$action];
        }
        
        // Try single user_id field
        $field = $action . '_by_user_id';
        if (isset($assignment->$field) && $assignment->$field) {
            return $assignment->$field;
        }
        
        return null;
    }
    
    protected static function findUserByRole($module, $action)
    {
        $roleMap = [
            'mortality' => [
                'issued' => 'reviewer',
                'verified' => 'checker',
                'checked' => 'witness',
                'witnessed' => 'approver',
            ],
            'health_treatment' => [
                'prepared' => 'livestock',
                'checked' => 'supervisor',
                'approved' => 'livestock manager',
            ],
            'calving' => [
                'issued' => 'issuer',
                'verified' => 'verifier',
                'checked' => 'checker',
                'witnessed' => 'witness',
                'approved' => 'approver',
            ],
            'document_endorsement' => [
                'issued' => 'issuer',
                'verified' => 'verifier',
                'checked' => 'checker',
                'witnessed' => 'witness',
                'approved' => 'approver',
            ],
            'transfer' => [
                'issued' => 'issuer',
                'verified' => 'verifier',
                'checked' => 'checker',
                'witnessed' => 'witness',
                'approved' => 'approver',
            ],
            'calving_checklist' => [
                'issued' => 'issuer',
                'verified' => 'verifier',
                'checked' => 'checker',
                'witnessed' => 'witness',
                'approved' => 'approver',
            ],
        ];
        
        $roleNeeded = $roleMap[$module][$action] ?? null;
        
        if ($roleNeeded) {
            $user = User::whereHas('roles', function($q) use ($roleNeeded) {
                $q->where('name', $roleNeeded);
            })->first();
            
            return $user ? $user->id : null;
        }
        
        return null;
    }
}
