<?php

namespace App\Http\Controllers;

use App\Models\TransferDocument;
use App\Models\TransferLivestock;
use App\Models\TransferApproval;
use App\Models\Cattle;
use App\Models\Estate;
use App\Models\TransferWorkflowAssignment;
use App\Models\User;
use App\Models\Permission;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;

class TransferController extends Controller
{
    private function getPermissionModuleForType(?string $type): string
    {
        return match ($type) {
            TransferDocument::TYPE_RECEIVAL => 'Transfer Receival',
            TransferDocument::TYPE_SIV => 'Transfer SIV',
            default => 'Transfer CTV',
        };
    }

    private function userHasModulePermission($user, string $module, string $action): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $permissions = Permission::normalizePermissionList($user->hasPermission($module));

        return in_array('full', $permissions, true) || in_array($action, $permissions, true);
    }

    private function userIsAssignedToAnyTransferStep($user, ?string $documentType): bool
    {
        $stepCount = $this->getWorkflowStepCount($documentType);
        for ($i = 0; $i < $stepCount; $i++) {
            if ($this->userCanUploadWorkflowStep($user, $i, $documentType)) {
                return true;
            }
        }

        return false;
    }

    private function userCanAccessTransferWorkflow($user, ?string $documentType): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $module = $this->getPermissionModuleForType($documentType);

        return $this->userHasModulePermission($user, $module, 'view')
            || $this->userHasModulePermission($user, $module, 'full')
            || $this->userIsAssignedToAnyTransferStep($user, $documentType);
    }

    private function denyUnlessModulePermission(string $module, string $action): ?\Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return null;
        }

        $allowed = match ($action) {
            'view' => $this->userHasModulePermission($user, $module, 'view')
                || $this->userHasModulePermission($user, $module, 'full'),
            'create' => $this->userHasModulePermission($user, $module, 'create')
                || $this->userHasModulePermission($user, $module, 'full'),
            'edit' => $this->userHasModulePermission($user, $module, 'edit')
                || $this->userHasModulePermission($user, $module, 'full'),
            'delete' => $this->userHasModulePermission($user, $module, 'delete')
                || $this->userHasModulePermission($user, $module, 'full'),
            default => false,
        };

        if (!$allowed) {
            return redirect()->route('access-denied');
        }

        return null;
    }

    private function denyUnlessTypePermission(?string $documentType, string $action): ?\Illuminate\Http\RedirectResponse
    {
        return $this->denyUnlessModulePermission($this->getPermissionModuleForType($documentType), $action);
    }

    private function getAssignedUserIdsForStep(int $stepIndex, ?string $documentType = null): array
    {
        if (!Schema::hasTable('transfer_workflow_assignments')) {
            return [];
        }

        $assignment = TransferWorkflowAssignment::first();
        if (!$assignment) {
            return [];
        }

        $legacyId = null;

        if ($documentType === TransferDocument::TYPE_RECEIVAL) {
            $ids = match ($stepIndex) {
                0 => $assignment->issued_by_user_ids,
                1 => $assignment->witnessed_transit_by_user_ids,
                2 => $assignment->verified_transit_by_user_ids,
                default => [],
            };
            $legacyId = match ($stepIndex) {
                0 => $assignment->issued_by_user_id,
                1 => $assignment->witnessed_transit_by_user_id,
                2 => $assignment->verified_transit_by_user_id,
                default => null,
            };
        } elseif ($documentType === TransferDocument::TYPE_SIV) {
            $ids = match ($stepIndex) {
                0 => $assignment->issued_by_user_ids,
                1 => $assignment->approved_by_user_ids,
                2 => $assignment->received_by_user_ids,
                3 => $assignment->completed_by_user_ids,
                default => [],
            };
            $legacyId = match ($stepIndex) {
                0 => $assignment->issued_by_user_id,
                1 => $assignment->approved_by_user_id,
                2 => $assignment->received_by_user_id,
                3 => $assignment->completed_by_user_id,
                default => null,
            };
        } else {
            $ids = match ($stepIndex) {
                0 => $assignment->issued_by_user_ids,
                1 => $assignment->approved_by_user_ids,
                2 => $assignment->transported_by_user_ids,
                3 => $assignment->witnessed_transit_by_user_ids,
                4 => $assignment->verified_transit_by_user_ids,
                5 => $assignment->witnessed_receive_by_user_ids,
                6 => $assignment->received_by_user_ids,
                7 => $assignment->completed_by_user_ids,
                default => [],
            };
            $legacyId = match ($stepIndex) {
                0 => $assignment->issued_by_user_id,
                1 => $assignment->approved_by_user_id,
                2 => $assignment->transported_by_user_id,
                3 => $assignment->witnessed_transit_by_user_id,
                4 => $assignment->verified_transit_by_user_id,
                5 => $assignment->witnessed_receive_by_user_id,
                6 => $assignment->received_by_user_id,
                7 => $assignment->completed_by_user_id,
                default => null,
            };
        }

        if (!is_array($ids)) {
            $ids = [];
        }

        if (empty($ids) && !empty($legacyId)) {
            $ids = [(int) $legacyId];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function getWorkflowStepCount(?string $documentType = null): int
    {
        if ($documentType === TransferDocument::TYPE_RECEIVAL) {
            return 3;
        }

        return $documentType === TransferDocument::TYPE_SIV ? 4 : 8;
    }

    private function normalizeEndorsementDocuments(mixed $rawDocs): array
    {
        if (is_object($rawDocs)) {
            return json_decode(json_encode($rawDocs), true) ?? [];
        }

        if (is_array($rawDocs)) {
            return $rawDocs;
        }

        return [];
    }

    private function resolveEndorsementStepFromDocuments(array $endorsementDocs, int $stepCount): int
    {
        for ($i = 0; $i < $stepCount; $i++) {
            if (!isset($endorsementDocs[(string) $i]) && !isset($endorsementDocs[$i])) {
                return $i;
            }
        }

        return $stepCount;
    }

    private function syncEndorsementStepFromDocuments(TransferDocument $document): void
    {
        if (!$document->is_reopened || $this->isTransferDocumentCompleted($document)) {
            return;
        }

        $stepCount = $this->getWorkflowStepCount($document->type);
        $endorsementDocs = $this->normalizeEndorsementDocuments($document->endorsement_documents);
        $resolvedStep = $this->resolveEndorsementStepFromDocuments($endorsementDocs, $stepCount);

        if ((int) ($document->endorsement_step ?? 0) !== $resolvedStep) {
            $document->update(['endorsement_step' => $resolvedStep]);
            $document->refresh();
        }
    }

    private function isTransferDocumentCompleted(TransferDocument $document): bool
    {
        return $document->status === TransferDocument::STATUS_COMPLETED;
    }

    private function ensureTransferWorkflowAccessible(TransferDocument $document): ?\Illuminate\Http\RedirectResponse
    {
        if (!$this->isTransferDocumentCompleted($document)) {
            return null;
        }

        if ((Auth::user()->role ?? null) === 'admin') {
            return null;
        }

        $route = match ($document->type) {
            TransferDocument::TYPE_CTV => 'transfer.ctv.index',
            TransferDocument::TYPE_SIV => 'transfer.siv.index',
            TransferDocument::TYPE_RECEIVAL => 'transfer.receival.index',
            default => 'transfer.pending',
        };

        $label = $this->getTransferTypeLabel($document->type);

        return redirect()->route($route)->withErrors([
            'error' => "Completed {$label} records cannot be accessed until the workflow is reopened.",
        ]);
    }

    private function ensureCtvCanBeModified(TransferDocument $document): ?\Illuminate\Http\RedirectResponse
    {
        if ($document->type !== TransferDocument::TYPE_CTV) {
            return redirect()->route('transfer.ctv.index')->withErrors(['error' => 'Invalid CTV record.']);
        }

        return $this->ensureTransferCanBeModified($document);
    }

    private function ensureTransferCanBeModified(TransferDocument $document): ?\Illuminate\Http\RedirectResponse
    {
        if (!$this->isTransferDocumentCompleted($document)) {
            return null;
        }

        $route = match ($document->type) {
            TransferDocument::TYPE_CTV => 'transfer.ctv.index',
            TransferDocument::TYPE_SIV => 'transfer.siv.index',
            TransferDocument::TYPE_RECEIVAL => 'transfer.receival.index',
            default => 'transfer.pending',
        };

        $label = $this->getTransferTypeLabel($document->type);

        return redirect()->route($route)->withErrors([
            'error' => "Completed {$label} records cannot be edited or deleted until the workflow is reopened.",
        ]);
    }

    private function ensureTransferViewPermission(TransferDocument $document): ?\Illuminate\Http\RedirectResponse
    {
        if (!$this->userCanAccessTransferWorkflow(Auth::user(), $document->type)) {
            return redirect()->route('access-denied');
        }

        return null;
    }

    private function syncCattleFromTransfer(TransferDocument $document): void
    {
        if ($document->type !== TransferDocument::TYPE_CTV || empty($document->to_location)) {
            return;
        }

        $document->loadMissing('livestock');
        $snapshot = [];

        foreach ($document->livestock as $item) {
            if (empty($item->tag_no)) {
                continue;
            }

            $cattle = Cattle::where('tag_no', $item->tag_no)->first();
            if (!$cattle) {
                continue;
            }

            $snapshot[$item->tag_no] = [
                'operating_unit' => $cattle->operating_unit,
                'location_block' => $cattle->location_block,
                'location_phase' => $cattle->location_phase,
            ];

            $cattle->update([
                'operating_unit' => $document->to_location,
                'location_block' => null,
                'location_phase' => null,
            ]);
        }

        if (!empty($snapshot)) {
            $document->update(['cattle_location_snapshot' => $snapshot]);
        }
    }

    private function revertCattleFromTransfer(TransferDocument $document): void
    {
        if ($document->type !== TransferDocument::TYPE_CTV) {
            return;
        }

        $snapshot = $document->cattle_location_snapshot;
        if (is_object($snapshot)) {
            $snapshot = json_decode(json_encode($snapshot), true) ?? [];
        }

        if (!is_array($snapshot) || empty($snapshot)) {
            if (empty($document->from_location)) {
                return;
            }

            $document->loadMissing('livestock');
            foreach ($document->livestock as $item) {
                if (empty($item->tag_no)) {
                    continue;
                }

                Cattle::where('tag_no', $item->tag_no)->update([
                    'operating_unit' => $document->from_location,
                ]);
            }

            return;
        }

        foreach ($snapshot as $tagNo => $location) {
            if (empty($tagNo) || !is_array($location)) {
                continue;
            }

            Cattle::where('tag_no', $tagNo)->update([
                'operating_unit' => $location['operating_unit'] ?? null,
                'location_block' => $location['location_block'] ?? null,
                'location_phase' => $location['location_phase'] ?? null,
            ]);
        }

        $document->update(['cattle_location_snapshot' => null]);
    }

    /**
     * Cattle are marked Sold only when the same tag appears on a completed SIV
     * and a completed Receival (sales issue + receival workflows both done).
     */
    private function getTransferDocumentTagNos(TransferDocument $document): array
    {
        $document->loadMissing('livestock');

        return $document->livestock
            ->pluck('tag_no')
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function tagHasCompletedTransferDocument(string $tagNo, string $type): bool
    {
        return TransferDocument::query()
            ->where('type', $type)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->whereHas('livestock', fn ($query) => $query->where('tag_no', $tagNo))
            ->exists();
    }

    private function tagIsInSaleTransferPipeline(string $tagNo): bool
    {
        return TransferLivestock::query()
            ->where('tag_no', $tagNo)
            ->whereHas('document', fn ($query) => $query->whereIn('type', [
                TransferDocument::TYPE_SIV,
                TransferDocument::TYPE_RECEIVAL,
            ]))
            ->exists();
    }

    private function shouldMarkCattleAsSold(string $tagNo): bool
    {
        return $this->tagHasCompletedTransferDocument($tagNo, TransferDocument::TYPE_SIV)
            && $this->tagHasCompletedTransferDocument($tagNo, TransferDocument::TYPE_RECEIVAL);
    }

    private function syncSoldStatusForTags(array $tagNos): void
    {
        foreach (array_unique(array_filter(array_map(fn ($tag) => trim((string) $tag), $tagNos))) as $tagNo) {
            if ($tagNo === '') {
                continue;
            }

            $cattle = Cattle::where('tag_no', $tagNo)->first();
            if (!$cattle) {
                continue;
            }

            if ($this->shouldMarkCattleAsSold($tagNo)) {
                if ($cattle->status !== 'Sold') {
                    $cattle->update(['status' => 'Sold']);
                }

                continue;
            }

            if ($cattle->status === 'Sold' && $this->tagIsInSaleTransferPipeline($tagNo)) {
                $cattle->update(['status' => 'Active']);
            }
        }
    }

    private function syncSoldStatusFromSaleDocument(TransferDocument $document): void
    {
        if (!in_array($document->type, [TransferDocument::TYPE_SIV, TransferDocument::TYPE_RECEIVAL], true)) {
            return;
        }

        $this->syncSoldStatusForTags($this->getTransferDocumentTagNos($document));
    }

    private function getCtvStepNames(): array
    {
        return [
            0 => 'Issued By (Transferor Estate)',
            1 => 'Approved By (Transferor Estate)',
            2 => 'Transported By (Transferor Estate)',
            3 => 'Witness By (Transferor Estate)',
            4 => 'Verified By (Transferor Estate)',
            5 => 'Witnessed By (Receiving Estate)',
            6 => 'Received By (Receiving Estate)',
            7 => 'Verified Completion By (Receiving Estate)',
        ];
    }

    private function getCtvStepRoleNames(): array
    {
        return [
            0 => 'Position',
            1 => 'Livestock Manager/OIC',
            2 => 'Driver',
            3 => 'Estate Personal',
            4 => 'Gate House Security',
            5 => 'Estate Personal',
            6 => 'Livestock Supervisor/Assistant',
            7 => 'Gate House Security',
        ];
    }

    private function getCtvWorkflowRoles(): array
    {
        $roles = [];
        foreach ($this->getCtvStepNames() as $idx => $title) {
            $roles[] = [
                'title' => $title,
                'role' => $this->getCtvStepRoleNames()[$idx] ?? '',
                'idx' => $idx,
            ];
        }

        return $roles;
    }

    private function getTransferTypeLabel(?string $type): string
    {
        return match ($type) {
            TransferDocument::TYPE_CTV => 'CTV',
            TransferDocument::TYPE_RECEIVAL => 'Receival',
            TransferDocument::TYPE_SIV => 'SIV',
            default => 'Transfer',
        };
    }

    private function getTransferStepNames(?string $documentType = null): array
    {
        if ($documentType === TransferDocument::TYPE_RECEIVAL) {
            return [
                0 => 'Prepared By',
                1 => 'Witness By',
                2 => 'Verified By',
            ];
        }

        if ($documentType === TransferDocument::TYPE_SIV) {
            return [
                0 => 'Requested By',
                1 => 'Approved By',
                2 => 'Verified By',
                3 => 'Received By',
            ];
        }

        return $this->getCtvStepNames();
    }

    private function getUsersForWorkflowStep(int $stepIndex, ?string $documentType = null)
    {
        $assignedUserIds = $this->getAssignedUserIdsForStep($stepIndex, $documentType);
        if (empty($assignedUserIds)) {
            return collect();
        }

        return User::whereIn('id', $assignedUserIds)->get();
    }

    private function notifyTransferWorkflowStepReady(TransferDocument $document, int $stepIndex, ?int $excludeUserId = null): void
    {
        $stepNames = $this->getTransferStepNames($document->type);
        $stepName = $stepNames[$stepIndex] ?? 'Next Step';
        $documentRef = $document->document_no ?? $document->id;
        $typeLabel = $this->getTransferTypeLabel($document->type);

        foreach ($this->getUsersForWorkflowStep($stepIndex, $document->type) as $notifyUser) {
            if ($excludeUserId && (int) $notifyUser->id === $excludeUserId) {
                continue;
            }

            WorkflowNotificationService::createNotification(
                'transfer',
                'transfer_document',
                $document->id,
                'workflow_step_ready',
                "{$typeLabel} Workflow Ready",
                "{$typeLabel} document #{$documentRef} is ready for '{$stepName}'.",
                (int) $notifyUser->id
            );
        }
    }

    private function notifyNextTransferWorkflowStep(TransferDocument $document, int $completedStepIndex, int $uploaderId): void
    {
        $nextStepIndex = $completedStepIndex + 1;
        $stepNames = $this->getTransferStepNames($document->type);
        $currentStepName = $stepNames[$completedStepIndex] ?? 'Step';
        $nextStepName = $stepNames[$nextStepIndex] ?? 'Next Step';
        $documentRef = $document->document_no ?? $document->id;
        $typeLabel = $this->getTransferTypeLabel($document->type);

        foreach ($this->getUsersForWorkflowStep($nextStepIndex, $document->type) as $notifyUser) {
            if ((int) $notifyUser->id === $uploaderId) {
                continue;
            }

            WorkflowNotificationService::createNotification(
                'transfer',
                'transfer_document',
                $document->id,
                'workflow_step_completed',
                "{$typeLabel} Workflow Step Completed",
                "The {$typeLabel} workflow step '{$currentStepName}' for document #{$documentRef} has been completed. Please proceed with '{$nextStepName}'.",
                (int) $notifyUser->id
            );
        }
    }

    private function notifyTransferWorkflowReadyForCompletion(TransferDocument $document, int $uploaderId): void
    {
        $documentRef = $document->document_no ?? $document->id;
        $typeLabel = $this->getTransferTypeLabel($document->type);

        foreach (User::where('role', 'admin')->get() as $adminUser) {
            if ((int) $adminUser->id === $uploaderId) {
                continue;
            }

            WorkflowNotificationService::createNotification(
                'transfer',
                'transfer_document',
                $document->id,
                'workflow_ready_for_completion',
                "{$typeLabel} Workflow Ready for Completion",
                "All workflow steps for {$typeLabel} document #{$documentRef} have been uploaded. Please mark the transfer workflow as completed.",
                (int) $adminUser->id
            );
        }
    }

    private function userCanUploadWorkflowStep($user, int $stepIndex, ?string $documentType = null): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $assignedUserIds = $this->getAssignedUserIdsForStep($stepIndex, $documentType);

        return !empty($assignedUserIds) && in_array((int) $user->id, $assignedUserIds, true);
    }

    private function getTransferCattleOptions()
    {
        $query = Cattle::where('status', 'Active')->orderBy('tag_no');

        $columns = ['id', 'tag_no', 'category'];

        // Used by transfer forms to restrict available tags by selected "from" location.
        if (Schema::hasColumn('cattle', 'operating_unit')) {
            $columns[] = 'operating_unit';
        }

        if (Schema::hasColumn('cattle', 'coat_colour')) {
            $columns[] = DB::raw('coat_colour as colour');
        } elseif (Schema::hasColumn('cattle', 'color')) {
            $columns[] = DB::raw('color as colour');
        }

        $results = $query->get($columns);

        if (!Schema::hasColumn('cattle', 'coat_colour') && !Schema::hasColumn('cattle', 'color')) {
            return $results->map(function ($item) {
                $item->colour = null;
                return $item;
            });
        }

        return $results;
    }

    private function validateLivestockTagsMatchFromLocation(string $fromLocation, array $livestockRows): void
    {
        $from = trim($fromLocation);
        if ($from === '' || empty($livestockRows)) {
            return;
        }

        // If operating_unit is not available, do not block transfers.
        if (!Schema::hasColumn('cattle', 'operating_unit')) {
            return;
        }

        $tagNos = [];
        foreach ($livestockRows as $row) {
            $tag = trim((string) ($row['tag_no'] ?? ''));
            if ($tag !== '') {
                $tagNos[] = $tag;
            }
        }
        $tagNos = array_values(array_unique($tagNos));
        if (empty($tagNos)) {
            return;
        }

        $cattleByTag = Cattle::query()
            ->where('status', 'Active')
            ->whereIn('tag_no', $tagNos)
            ->get(['tag_no', 'operating_unit'])
            ->keyBy('tag_no');

        $errors = [];
        foreach ($livestockRows as $idx => $row) {
            $tag = trim((string) ($row['tag_no'] ?? ''));
            if ($tag === '') {
                continue;
            }

            $cattle = $cattleByTag->get($tag);
            if (!$cattle) {
                $errors["livestock.$idx.tag_no"] = "Tag no. {$tag} is not found or not active.";
                continue;
            }

            if (trim((string) ($cattle->operating_unit ?? '')) !== $from) {
                $errors["livestock.$idx.tag_no"] = "Tag no. {$tag} is not in the selected From location ({$from}).";
            }
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

private function typeHistory(Request $request, string $type, string $component)
    {
        if ($response = $this->denyUnlessTypePermission($type, 'view')) {
            return $response;
        }

        try {
            $search = $request->get('search', '');
            $dateFrom = $request->get('date_from', '');
            $dateTo = $request->get('date_to', '');
            $fromOperatingUnit = $request->get('from_operating_unit', '');
            $toOperatingUnit = $request->get('to_operating_unit', '');

            $query = TransferDocument::query()->where('type', $type);

            if ($search) {
                $query->where('document_no', 'like', "%{$search}%");
            }

            if ($dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            }

            if ($fromOperatingUnit) {
                $query->where('from_location', $fromOperatingUnit);
            }

            if ($toOperatingUnit) {
                $query->where('to_location', $toOperatingUnit);
            }

            $fromOperatingUnits = TransferDocument::query()
                ->where('type', $type)
                ->whereNotNull('from_location')
                ->where('from_location', '!=', '')
                ->distinct()
                ->orderBy('from_location')
                ->pluck('from_location')
                ->values();

            $toOperatingUnits = TransferDocument::query()
                ->where('type', $type)
                ->whereNotNull('to_location')
                ->where('to_location', '!=', '')
                ->distinct()
                ->orderBy('to_location')
                ->pluck('to_location')
                ->values();

            $documents = $query->with(['livestock', 'creator'])
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString();

            return Inertia::render($component, [
                'documents' => $documents,
                'documentType' => $type,
                'transferWorkflowAssignment' => Schema::hasTable('transfer_workflow_assignments')
                    ? TransferWorkflowAssignment::first()
                    : null,
                'filters' => [
                    'search' => $search,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'from_operating_unit' => $fromOperatingUnit,
                    'to_operating_unit' => $toOperatingUnit,
                ],
                'fromOperatingUnits' => $fromOperatingUnits,
                'toOperatingUnits' => $toOperatingUnits,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in typeHistory for ' . $type . ': ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function ctvHistory(Request $request)
    {
        \Log::error('CTV History accessed');
        return $this->typeHistory($request, TransferDocument::TYPE_CTV, 'Transfer/CtvHistory');
    }

    public function receivalHistory(Request $request)
    {
        \Log::error('Receival History accessed');
        return $this->typeHistory($request, TransferDocument::TYPE_RECEIVAL, 'Transfer/ReceivalHistory');
    }

    public function sivHistory(Request $request)
    {
        \Log::error('SIV History accessed');
        return $this->typeHistory($request, TransferDocument::TYPE_SIV, 'Transfer/SivHistory');
    }

    public function createCtv()
    {
        if ($response = $this->denyUnlessModulePermission('Transfer CTV', 'create')) {
            return $response;
        }

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cattle = $this->getTransferCattleOptions();

        return Inertia::render('Transfer/CreateCtv', [
            'estates' => $estates,
            'cattle' => $cattle,
        ]);
    }

    public function createReceival()
    {
        if ($response = $this->denyUnlessModulePermission('Transfer Receival', 'create')) {
            return $response;
        }

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cattle = $this->getTransferCattleOptions();

        return Inertia::render('Transfer/CreateReceival', [
            'estates' => $estates,
            'cattle' => $cattle,
        ]);
    }

    public function createSiv()
    {
        if ($response = $this->denyUnlessModulePermission('Transfer SIV', 'create')) {
            return $response;
        }

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cattle = $this->getTransferCattleOptions();

        return Inertia::render('Transfer/CreateSiv', [
            'estates' => $estates,
            'cattle' => $cattle,
        ]);
    }

    public function editSiv($id)
    {
        if ($response = $this->denyUnlessModulePermission('Transfer SIV', 'edit')) {
            return $response;
        }

        $document = TransferDocument::with(['livestock'])
            ->where('type', TransferDocument::TYPE_SIV)
            ->findOrFail($id);

        if ($response = $this->ensureTransferCanBeModified($document)) {
            return $response;
        }

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cattle = $this->getTransferCattleOptions();

        return Inertia::render('Transfer/EditSiv', [
            'document' => $document,
            'estates' => $estates,
            'cattle' => $cattle,
        ]);
    }

    public function editReceival($id)
    {
        if ($response = $this->denyUnlessModulePermission('Transfer Receival', 'edit')) {
            return $response;
        }

        $document = TransferDocument::with(['livestock'])
            ->where('type', TransferDocument::TYPE_RECEIVAL)
            ->findOrFail($id);

        if ($response = $this->ensureTransferCanBeModified($document)) {
            return $response;
        }

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cattle = $this->getTransferCattleOptions();

        return Inertia::render('Transfer/EditReceival', [
            'document' => $document,
            'estates' => $estates,
            'cattle' => $cattle,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:CTV,Receival,SIV',
            'form_document_no' => 'nullable|string|max:100',
            'revision_no' => 'nullable|string|max:50',
            'from_location' => 'required|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:255',
            'driver_tel' => 'nullable|string|max:20',
            'driver_ic' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'siv_no' => 'nullable|string|max:100',
            'receipt_no' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:255',
            'livestock' => 'required|array|min:1',
            'livestock.*.tag_no' => 'required|string|max:50',
            'livestock.*.category' => 'required|string|max:50',
            'livestock.*.colour' => 'nullable|string|max:50',
            'livestock.*.weight' => 'nullable|numeric',
            'livestock.*.unit_cost' => 'nullable|numeric|min:0',
            'livestock.*.value' => 'nullable|numeric|min:0',
            'livestock.*.condition_good' => 'nullable|boolean',
            'livestock.*.condition_not_good' => 'nullable|boolean',
            'livestock.*.remarks' => 'nullable|string',
            'livestock.*.purpose' => 'nullable|string|max:100',
            'livestock.*.yard' => 'nullable|string|max:100',
        ]);

        $this->validateLivestockTagsMatchFromLocation(
            (string) ($validated['from_location'] ?? ''),
            (array) ($validated['livestock'] ?? [])
        );

        $module = $this->getPermissionModuleForType($validated['type']);
        if (!$this->userHasModulePermission(Auth::user(), $module, 'create')) {
            return redirect()->route('access-denied');
        }

        $createdDocument = null;

        DB::transaction(function () use ($validated, &$createdDocument) {
            $totalValue = 0;

            $document = new TransferDocument([
                'type' => $validated['type'],
                'form_document_no' => $validated['form_document_no'] ?? null,
                'revision_no' => $validated['revision_no'] ?? null,
                'from_location' => $validated['from_location'],
                'to_location' => $validated['to_location'] ?? null,
                'date' => $validated['date'],
                'time' => $validated['time'] ?? now()->format('H:i'),
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_tel' => $validated['driver_tel'] ?? null,
                'driver_ic' => $validated['driver_ic'] ?? null,
                'address' => $validated['address'] ?? null,
                'siv_no' => $validated['siv_no'] ?? null,
                'receipt_no' => $validated['receipt_no'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'total_cattle' => count($validated['livestock']),
                'total_value' => 0,
                'status' => TransferDocument::STATUS_PENDING,
                'current_step' => TransferDocument::STEP_ISSUED,
                'created_by' => Auth::id(),
            ]);

            $document->generateDocumentNo();
            $document->save();

            foreach ($validated['livestock'] as $index => $item) {
                $lineValue = (float) ($item['value'] ?? ((float) ($item['weight'] ?? 0) * (float) ($item['unit_cost'] ?? 0)));
                $totalValue += $lineValue;

                TransferLivestock::create([
                    'transfer_document_id' => $document->id,
                    'tag_no' => $item['tag_no'],
                    'category' => $item['category'],
                    'colour' => $item['colour'] ?? null,
                    'weight' => $item['weight'] ?? null,
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'value' => $lineValue,
                    'condition_good' => $item['condition_good'] ?? false,
                    'condition_not_good' => $item['condition_not_good'] ?? false,
                    'remarks' => $item['remarks'] ?? null,
                    'purpose' => $item['purpose'] ?? null,
                    'yard' => $item['yard'] ?? null,
                    'sort_order' => $index,
                ]);

            }

            $document->update([
                'total_value' => $totalValue,
            ]);

            TransferApproval::create([
                'transfer_document_id' => $document->id,
                'approver_id' => Auth::id(),
                'step' => TransferDocument::STEP_ISSUED,
                'action' => true,
                'comments' => 'Document issued',
            ]);

            $createdDocument = $document->fresh();
        });

        if ($createdDocument && in_array($createdDocument->type, [
            TransferDocument::TYPE_CTV,
            TransferDocument::TYPE_SIV,
            TransferDocument::TYPE_RECEIVAL,
        ], true)) {
            $this->notifyTransferWorkflowStepReady($createdDocument, 0, (int) Auth::id());
        }

        $redirectRoute = match($validated['type']) {
            'CTV' => 'transfer.ctv.index',
            'Receival' => 'transfer.receival.index',
            'SIV' => 'transfer.siv.index',
            default => 'transfer.pending',
        };

        return redirect()->route($redirectRoute)
            ->with('success', 'Document created successfully.');
    }

    public function show($id)
    {
        $document = TransferDocument::with(['livestock', 'approvals.approver', 'creator'])
            ->findOrFail($id);

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        return Inertia::render('Transfer/Show', [
            'document' => $document,
        ]);
    }

    public function ctvWorkflow($id)
    {
        $document = TransferDocument::with(['livestock', 'approvals.approver', 'creator'])
            ->where('type', TransferDocument::TYPE_CTV)
            ->findOrFail($id);

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        if ($response = $this->ensureTransferWorkflowAccessible($document)) {
            return $response;
        }

        $this->syncEndorsementStepFromDocuments($document);

        return Inertia::render('Transfer/CtvWorkflow', [
            'document' => $document,
            'transferWorkflowAssignment' => Schema::hasTable('transfer_workflow_assignments') ? TransferWorkflowAssignment::first() : null,
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function sivWorkflow($id)
    {
        $document = TransferDocument::with(['livestock', 'approvals.approver', 'creator'])
            ->where('type', TransferDocument::TYPE_SIV)
            ->findOrFail($id);

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        if ($response = $this->ensureTransferWorkflowAccessible($document)) {
            return $response;
        }

        $this->syncEndorsementStepFromDocuments($document);

        return Inertia::render('Transfer/SivWorkflow', [
            'document' => $document,
            'transferWorkflowAssignment' => Schema::hasTable('transfer_workflow_assignments') ? TransferWorkflowAssignment::first() : null,
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function receivalWorkflow($id)
    {
        $document = TransferDocument::with(['livestock', 'approvals.approver', 'creator'])
            ->where('type', TransferDocument::TYPE_RECEIVAL)
            ->findOrFail($id);

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        if ($response = $this->ensureTransferWorkflowAccessible($document)) {
            return $response;
        }

        $this->syncEndorsementStepFromDocuments($document);

        return Inertia::render('Transfer/CtvWorkflow', [
            'document' => $document,
            'transferWorkflowAssignment' => Schema::hasTable('transfer_workflow_assignments') ? TransferWorkflowAssignment::first() : null,
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function editCtv($id)
    {
        if ($response = $this->denyUnlessModulePermission('Transfer CTV', 'edit')) {
            return $response;
        }

        $document = TransferDocument::with(['livestock'])
            ->where('type', TransferDocument::TYPE_CTV)
            ->findOrFail($id);

        if ($response = $this->ensureCtvCanBeModified($document)) {
            return $response;
        }

        $estates = Estate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cattle = $this->getTransferCattleOptions();

        return Inertia::render('Transfer/EditCtv', [
            'document' => $document,
            'estates' => $estates,
            'cattle' => $cattle,
        ]);
    }

    public function updateCtv(Request $request, $id)
    {
        $document = TransferDocument::with('livestock')
            ->where('type', TransferDocument::TYPE_CTV)
            ->findOrFail($id);

        if ($response = $this->denyUnlessTypePermission($document->type, 'edit')) {
            return $response;
        }

        if ($this->isTransferDocumentCompleted($document)) {
            return back()->withErrors(['error' => 'Completed CTV records cannot be edited.']);
        }

        $validated = $request->validate([
            'form_document_no' => 'nullable|string|max:100',
            'revision_no' => 'nullable|string|max:50',
            'from_location' => 'required|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable',
            'livestock' => 'required|array|min:1',
            'livestock.*.tag_no' => 'required|string|max:50',
            'livestock.*.category' => 'required|string|max:50',
            'livestock.*.colour' => 'nullable|string|max:50',
            'livestock.*.weight' => 'nullable|numeric',
            'livestock.*.condition_good' => 'nullable|boolean',
            'livestock.*.condition_not_good' => 'nullable|boolean',
            'livestock.*.remarks' => 'nullable|string',
            'livestock.*.purpose' => 'nullable|string|max:100',
        ]);

        $this->validateLivestockTagsMatchFromLocation(
            (string) ($validated['from_location'] ?? ''),
            (array) ($validated['livestock'] ?? [])
        );

        DB::transaction(function () use ($document, $validated) {
            $document->update([
                'form_document_no' => $validated['form_document_no'] ?? null,
                'revision_no' => $validated['revision_no'] ?? null,
                'from_location' => $validated['from_location'],
                'to_location' => $validated['to_location'] ?? null,
                'date' => $validated['date'],
                'time' => $validated['time'] ?? $document->time,
                'total_cattle' => count($validated['livestock']),
            ]);

            $document->livestock()->delete();

            foreach ($validated['livestock'] as $index => $item) {
                TransferLivestock::create([
                    'transfer_document_id' => $document->id,
                    'tag_no' => $item['tag_no'],
                    'category' => $item['category'],
                    'colour' => $item['colour'] ?? null,
                    'weight' => $item['weight'] ?? null,
                    'condition_good' => $item['condition_good'] ?? false,
                    'condition_not_good' => $item['condition_not_good'] ?? false,
                    'remarks' => $item['remarks'] ?? null,
                    'purpose' => $item['purpose'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $document->update([
                'status' => TransferDocument::STATUS_PENDING,
                'current_step' => TransferDocument::STEP_ISSUED,
                'endorsement_step' => 0,
                'endorsement_documents' => null,
                'is_reopened' => false,
            ]);

            $document->approvals()->delete();
        });

        return redirect()->route('transfer.ctv.index')->with('success', 'CTV updated successfully. Workflow reset to step 1.');
    }

    public function updateSiv(Request $request, $id)
    {
        $document = TransferDocument::with('livestock')
            ->where('type', TransferDocument::TYPE_SIV)
            ->findOrFail($id);

        if ($response = $this->denyUnlessTypePermission($document->type, 'edit')) {
            return $response;
        }

        if ($this->isTransferDocumentCompleted($document)) {
            return back()->withErrors(['error' => 'Completed SIV records cannot be edited until the workflow is reopened.']);
        }

        $validated = $request->validate([
            'from_location' => 'required|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:255',
            'driver_tel' => 'nullable|string|max:20',
            'driver_ic' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'siv_no' => 'nullable|string|max:100',
            'receipt_no' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:255',
            'livestock' => 'required|array|min:1',
            'livestock.*.tag_no' => 'required|string|max:50',
            'livestock.*.category' => 'required|string|max:50',
            'livestock.*.colour' => 'nullable|string|max:50',
            'livestock.*.weight' => 'nullable|numeric',
            'livestock.*.unit_cost' => 'nullable|numeric|min:0',
            'livestock.*.value' => 'nullable|numeric|min:0',
            'livestock.*.remarks' => 'nullable|string',
        ]);

        $this->validateLivestockTagsMatchFromLocation(
            (string) ($validated['from_location'] ?? ''),
            (array) ($validated['livestock'] ?? [])
        );

        DB::transaction(function () use ($document, $validated) {
            $totalValue = 0;

            $document->update([
                'from_location' => $validated['from_location'],
                'to_location' => $validated['to_location'] ?? null,
                'date' => $validated['date'],
                'time' => $validated['time'] ?? $document->time,
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_tel' => $validated['driver_tel'] ?? null,
                'driver_ic' => $validated['driver_ic'] ?? null,
                'address' => $validated['address'] ?? null,
                'siv_no' => $validated['siv_no'] ?? null,
                'receipt_no' => $validated['receipt_no'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'total_cattle' => count($validated['livestock']),
            ]);

            $document->livestock()->delete();

            foreach ($validated['livestock'] as $index => $item) {
                $lineValue = (float) ($item['value'] ?? ((float) ($item['weight'] ?? 0) * (float) ($item['unit_cost'] ?? 0)));
                $totalValue += $lineValue;

                TransferLivestock::create([
                    'transfer_document_id' => $document->id,
                    'tag_no' => $item['tag_no'],
                    'category' => $item['category'],
                    'colour' => $item['colour'] ?? null,
                    'weight' => $item['weight'] ?? null,
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'value' => $lineValue,
                    'condition_good' => false,
                    'condition_not_good' => false,
                    'remarks' => $item['remarks'] ?? null,
                    'purpose' => null,
                    'sort_order' => $index,
                ]);
            }

            $document->update([
                'total_value' => $totalValue,
                'status' => TransferDocument::STATUS_PENDING,
                'current_step' => TransferDocument::STEP_ISSUED,
                'endorsement_step' => 0,
                'endorsement_documents' => null,
                'is_reopened' => false,
            ]);

            $document->approvals()->delete();
        });

        return redirect()->route('transfer.siv.index')->with('success', 'SIV updated successfully. Workflow reset to step 1.');
    }

    public function updateReceival(Request $request, $id)
    {
        $document = TransferDocument::with('livestock')
            ->where('type', TransferDocument::TYPE_RECEIVAL)
            ->findOrFail($id);

        if ($response = $this->denyUnlessTypePermission($document->type, 'edit')) {
            return $response;
        }

        if ($this->isTransferDocumentCompleted($document)) {
            return back()->withErrors(['error' => 'Completed Receival records cannot be edited until the workflow is reopened.']);
        }

        $validated = $request->validate([
            'from_location' => 'required|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:255',
            'driver_tel' => 'nullable|string|max:20',
            'driver_ic' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'livestock' => 'required|array|min:1',
            'livestock.*.tag_no' => 'required|string|max:50',
            'livestock.*.category' => 'required|string|max:50',
            'livestock.*.colour' => 'nullable|string|max:50',
            'livestock.*.weight' => 'nullable|numeric',
            'livestock.*.unit_cost' => 'nullable|numeric|min:0',
            'livestock.*.value' => 'nullable|numeric|min:0',
            'livestock.*.condition_good' => 'nullable|boolean',
            'livestock.*.condition_not_good' => 'nullable|boolean',
            'livestock.*.remarks' => 'nullable|string',
            'livestock.*.purpose' => 'nullable|string|max:100',
            'livestock.*.yard' => 'nullable|string|max:100',
        ]);

        $this->validateLivestockTagsMatchFromLocation(
            (string) ($validated['from_location'] ?? ''),
            (array) ($validated['livestock'] ?? [])
        );

        DB::transaction(function () use ($document, $validated) {
            $totalValue = 0;

            $document->update([
                'from_location' => $validated['from_location'],
                'to_location' => $validated['to_location'] ?? null,
                'date' => $validated['date'],
                'time' => $validated['time'] ?? $document->time,
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_tel' => $validated['driver_tel'] ?? null,
                'driver_ic' => $validated['driver_ic'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'total_cattle' => count($validated['livestock']),
            ]);

            $document->livestock()->delete();

            foreach ($validated['livestock'] as $index => $item) {
                $lineValue = (float) ($item['value'] ?? ((float) ($item['weight'] ?? 0) * (float) ($item['unit_cost'] ?? 0)));
                $totalValue += $lineValue;

                TransferLivestock::create([
                    'transfer_document_id' => $document->id,
                    'tag_no' => $item['tag_no'],
                    'category' => $item['category'],
                    'colour' => $item['colour'] ?? null,
                    'weight' => $item['weight'] ?? null,
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'value' => $lineValue,
                    'condition_good' => $item['condition_good'] ?? false,
                    'condition_not_good' => $item['condition_not_good'] ?? false,
                    'remarks' => $item['remarks'] ?? null,
                    'purpose' => $item['purpose'] ?? null,
                    'yard' => $item['yard'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $document->update([
                'total_value' => $totalValue,
                'status' => TransferDocument::STATUS_PENDING,
                'current_step' => TransferDocument::STEP_ISSUED,
                'endorsement_step' => 0,
                'endorsement_documents' => null,
                'is_reopened' => false,
            ]);

            $document->approvals()->delete();
        });

        return redirect()->route('transfer.receival.index')->with('success', 'Receival updated successfully. Workflow reset to step 1.');
    }

    public function destroyCtv($id)
    {
        $document = TransferDocument::where('type', TransferDocument::TYPE_CTV)->findOrFail($id);

        if ($this->isTransferDocumentCompleted($document)) {
            return redirect()->route('transfer.ctv.index')->withErrors(['error' => 'Completed CTV records cannot be deleted.']);
        }

        $document->delete();

        return redirect()->route('transfer.ctv.index')->with('success', 'CTV deleted successfully.');
    }

    public function destroySiv($id)
    {
        if ($response = $this->denyUnlessModulePermission('Transfer SIV', 'delete')) {
            return $response;
        }

        $document = TransferDocument::where('type', TransferDocument::TYPE_SIV)->findOrFail($id);

        if ($this->isTransferDocumentCompleted($document)) {
            return redirect()->route('transfer.siv.index')->withErrors([
                'error' => 'Completed SIV records cannot be deleted until the workflow is reopened.',
            ]);
        }

        $tagNos = $this->getTransferDocumentTagNos($document);
        $document->delete();
        $this->syncSoldStatusForTags($tagNos);

        return redirect()->route('transfer.siv.index')->with('success', 'SIV deleted successfully.');
    }

    public function destroyReceival($id)
    {
        if ($response = $this->denyUnlessModulePermission('Transfer Receival', 'delete')) {
            return $response;
        }

        $document = TransferDocument::where('type', TransferDocument::TYPE_RECEIVAL)->findOrFail($id);

        if ($this->isTransferDocumentCompleted($document)) {
            return redirect()->route('transfer.receival.index')->withErrors([
                'error' => 'Completed Receival records cannot be deleted until the workflow is reopened.',
            ]);
        }

        $tagNos = $this->getTransferDocumentTagNos($document);
        $document->delete();
        $this->syncSoldStatusForTags($tagNos);

        return redirect()->route('transfer.receival.index')->with('success', 'Receival deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'type' => 'required|string|in:CTV,Receival,SIV',
        ]);

        $type = match ($validated['type']) {
            'CTV' => TransferDocument::TYPE_CTV,
            'Receival' => TransferDocument::TYPE_RECEIVAL,
            'SIV' => TransferDocument::TYPE_SIV,
        };

        $module = $this->getPermissionModuleForType($type);
        if (!$this->userHasModulePermission(Auth::user(), $module, 'delete')) {
            return redirect()->route('access-denied');
        }

        $query = TransferDocument::where('type', $type)->whereIn('id', $validated['ids']);

        $completedCount = (clone $query)
            ->where('status', TransferDocument::STATUS_COMPLETED)
            ->count();

        if ($completedCount > 0) {
            return redirect()->back()->withErrors(['error' => 'Completed records cannot be deleted. Reopen the workflow first if changes are needed.']);
        }

        $tagNos = [];
        if (in_array($type, [TransferDocument::TYPE_SIV, TransferDocument::TYPE_RECEIVAL], true)) {
            $tagNos = TransferLivestock::query()
                ->whereIn('transfer_document_id', $validated['ids'])
                ->pluck('tag_no')
                ->all();
        }

        $deleted = $query->delete();

        if (!empty($tagNos)) {
            $this->syncSoldStatusForTags($tagNos);
        }

        return redirect()->back()->with('success', $deleted . ' record(s) deleted successfully.');
    }

    public function approve(Request $request, $id)
    {
        $document = TransferDocument::findOrFail($id);

        if ($response = $this->denyUnlessTypePermission($document->type, 'view')) {
            return $response;
        }

        $validated = $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comments' => 'nullable|string',
        ]);

        $steps = TransferDocument::getWorkflowSteps();
        $currentIndex = array_search($document->current_step, $steps);
        $nextStep = $steps[$currentIndex + 1] ?? null;

        DB::transaction(function () use ($document, $validated, $nextStep) {
            TransferApproval::create([
                'transfer_document_id' => $document->id,
                'approver_id' => Auth::id(),
                'step' => $document->current_step,
                'action' => $validated['action'] === 'approved',
                'comments' => $validated['comments'],
            ]);

            if ($validated['action'] === 'approved' && $nextStep) {
                $document->update([
                    'current_step' => $nextStep,
                    'status' => $nextStep === TransferDocument::STEP_COMPLETED ? TransferDocument::STATUS_COMPLETED : TransferDocument::STATUS_IN_PROGRESS,
                ]);
            } elseif ($validated['action'] === 'rejected') {
                $document->update([
                    'status' => TransferDocument::STATUS_REJECTED,
                    'rejection_reason' => $validated['comments'],
                ]);
            } elseif ($validated['action'] === 'returned') {
                $document->update([
                    'status' => TransferDocument::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Action recorded successfully!');
    }

    public function uploadEndorsement(Request $request, $id)
    {
        $request->validate([
            'signed_document' => 'required|file|mimes:pdf|max:10240',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'step_index' => 'required|integer|min:0',
        ]);

        $document = TransferDocument::findOrFail($id);
        $user = Auth::user();

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        $stepIndex = (int) $request->step_index;
        $stepCount = $this->getWorkflowStepCount($document->type);

        if ($stepIndex > ($stepCount - 1)) {
            return back()->withErrors(['error' => 'Invalid workflow step']);
        }

        if (!$this->userCanUploadWorkflowStep($user, $stepIndex, $document->type)) {
            return back()->withErrors(['error' => 'You do not have permission to upload for this step']);
        }

        if ($document->status === TransferDocument::STATUS_COMPLETED) {
            return back()->withErrors(['error' => 'This document has been completed. No further uploads are allowed.']);
        }

        $rawDocs = $document->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }
        
        $currentStep = $document->endorsement_step ?? 0;
        $nextStepKey = strval($stepIndex + 1);
        
        if ($stepIndex === ($stepCount - 1)) {
            $canUpload = ($stepIndex <= $currentStep);
        } else {
            $canUpload = ($stepIndex === $currentStep) || 
                         ($stepIndex < $currentStep && !isset($endorsementDocs[$nextStepKey]));
        }

        if (!$canUpload) {
            return back()->withErrors(['error' => 'Cannot upload at this step']);
        }

        $file = $request->file('signed_document');
        $filename = 'transfer_' . $document->id . '_step' . $stepIndex . '_' . time() . '.pdf';
        $path = $file->storeAs('transfer_endorsements', $filename, 'public');

        $endorsementDocs[strval($stepIndex)] = [
            'name' => $request->name,
            'date' => $request->date,
            'file_path' => $path,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->toDateTimeString(),
        ];
        
        $endorsementDocsObject = (object) $endorsementDocs;

        $newStep = $currentStep;
        if ($stepIndex === $currentStep && $stepIndex < ($stepCount - 1)) {
            $newStep = $stepIndex + 1;
            $this->notifyNextTransferWorkflowStep($document, $stepIndex, (int) $user->id);
        } elseif ($stepIndex === ($stepCount - 1)) {
            $newStep = $stepCount;
            $this->notifyTransferWorkflowReadyForCompletion($document, (int) $user->id);
        } else {
            $newStep = $currentStep;
        }

        $document->update([
            'endorsement_documents' => $endorsementDocsObject,
            'endorsement_step' => $newStep,
        ]);

        return back()->with('success', 'Document uploaded successfully');
    }

    public function downloadEndorsement($id, $stepIndex)
    {
        $document = TransferDocument::findOrFail($id);

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        $user = Auth::user();
        $stepIndex = (int) $stepIndex;

        if ($user->role !== 'admin') {
            $canViewOwn = $this->userCanUploadWorkflowStep($user, $stepIndex, $document->type);
            $canViewPrevious = $this->userCanUploadWorkflowStep($user, $stepIndex + 1, $document->type);
            if (!$canViewOwn && !$canViewPrevious) {
                abort(403, 'You can only view your own document or the previous person\'s document');
            }
        }

        $rawDocs = $document->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }
        $stepKey = strval($stepIndex);
        
        if (!isset($endorsementDocs[$stepKey])) {
            abort(404, 'Document not found');
        }

        $filePath = $endorsementDocs[$stepKey]['file_path'];
        
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($filePath);
    }

    public function downloadEndorsementForm(Request $request, $id, $stepIndex)
    {
        $document = TransferDocument::with('livestock')->findOrFail($id);

        if ($response = $this->ensureTransferViewPermission($document)) {
            return $response;
        }

        $maxStep = $this->getWorkflowStepCount($document->type) - 1;
        $stepIndex = max(0, min($maxStep, (int) $stepIndex));
        $customCtvNo = trim((string) $request->query('ctv_no', ''));

        if ($document->type === TransferDocument::TYPE_RECEIVAL) {
            $stepNames = [
                0 => 'Prepared By',
                1 => 'Witness By',
                2 => 'Verified By',
            ];

            $stepRoleNames = [
                0 => 'Assigned User',
                1 => 'Assigned User',
                2 => 'Assigned User',
            ];
        } elseif ($document->type === TransferDocument::TYPE_SIV) {
            $stepNames = [
                0 => 'Requested By',
                1 => 'Approved By',
                2 => 'Verified By',
                3 => 'Received By',
            ];

            $stepRoleNames = [
                0 => 'Sr. Assistant Livestock',
                1 => 'Livestock Manager',
                2 => 'Livestock Supervisor',
                3 => 'Assigned User',
            ];
        } else {
            $stepNames = $this->getCtvStepNames();
            $stepRoleNames = $this->getCtvStepRoleNames();
        }

        $pdf = Pdf::loadView('transfer.endorsement-form', [
            'document' => $document,
            'stepIndex' => $stepIndex,
            'stepName' => $stepNames[$stepIndex] ?? 'Endorsement',
            'stepRoleName' => $stepRoleNames[$stepIndex] ?? 'Officer',
            'customCtvNo' => $customCtvNo,
            'workflowRoles' => $document->type === TransferDocument::TYPE_CTV
                ? $this->getCtvWorkflowRoles()
                : null,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'transfer_' . ($document->document_no ?? $document->id) . '_step_' . ($stepIndex + 1) . '_form.pdf';

        return $pdf->download($filename);
    }

    public function markAsCompleted($id)
    {
        $user = Auth::user();
        $document = TransferDocument::findOrFail($id);

        if ($response = $this->denyUnlessTypePermission($document->type, 'view')) {
            return $response;
        }

        if (($user->role ?? null) !== 'admin') {
            return redirect()->route('access-denied');
        }

        $rawDocs = $document->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $stepCount = $this->getWorkflowStepCount($document->type);

        for ($i = 0; $i < $stepCount; $i++) {
            if (!isset($endorsementDocs[(string) $i])) {
                return back()->withErrors(['error' => 'All workflow steps must be uploaded before marking as completed']);
            }
        }

        try {
            DB::beginTransaction();

            $document->update([
                'status' => TransferDocument::STATUS_COMPLETED,
                'current_step' => TransferDocument::STEP_COMPLETED,
                'endorsement_step' => $stepCount,
                'is_reopened' => false,
            ]);

            $freshDocument = $document->fresh();
            $this->syncCattleFromTransfer($freshDocument);
            $this->syncSoldStatusFromSaleDocument($freshDocument);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to mark transfer as completed: ' . $e->getMessage()]);
        }

        // Notify the creator that the transfer workflow is completed
        if ($document->created_by) {
            \App\Services\WorkflowNotificationService::createNotification(
                'transfer',
                'transfer_document',
                $document->id,
                'completed',
                'Transfer Workflow Completed',
                "Your transfer document #{$document->id} has been completed.",
                $document->created_by
            );
        }

        return back()->with('success', 'Transfer workflow marked as completed.');
    }

    public function reopen($id)
    {
        $user = Auth::user();
        $document = TransferDocument::findOrFail($id);

        if ($response = $this->denyUnlessTypePermission($document->type, 'view')) {
            return $response;
        }

        if (($user->role ?? null) !== 'admin') {
            return redirect()->route('access-denied');
        }

        if ($document->status !== TransferDocument::STATUS_COMPLETED) {
            return back()->withErrors(['error' => 'Only completed transfer workflows can be reopened']);
        }

        $stepCount = $this->getWorkflowStepCount($document->type);
        $endorsementDocs = $this->normalizeEndorsementDocuments($document->endorsement_documents);
        $newStep = $this->resolveEndorsementStepFromDocuments($endorsementDocs, $stepCount);

        try {
            DB::beginTransaction();

            $document->update([
                'status' => TransferDocument::STATUS_IN_PROGRESS,
                'current_step' => TransferDocument::STEP_RECEIVED,
                'endorsement_step' => $newStep,
                'is_reopened' => true,
            ]);

            $freshDocument = $document->fresh();
            $this->revertCattleFromTransfer($freshDocument);
            $this->syncSoldStatusFromSaleDocument($freshDocument);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to reopen transfer workflow: ' . $e->getMessage()]);
        }

        return back()->with('success', 'Transfer workflow reopened. You can edit records and upload workflow steps again.');
    }

    public function getPendingCounts()
    {
        $counts = TransferDocument::select('current_step')
            ->whereIn('status', [TransferDocument::STATUS_PENDING, TransferDocument::STATUS_IN_PROGRESS])
            ->groupBy('current_step')
            ->selectRaw('current_step, count(*) as count')
            ->pluck('count', 'current_step');

        $steps = TransferDocument::getWorkflowSteps();
        $result = [];
        foreach ($steps as $step) {
            $result[$step] = $counts[$step] ?? 0;
        }
        $result['total'] = array_sum($counts->toArray());

        return response()->json($result);
    }
}
