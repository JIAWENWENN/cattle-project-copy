<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\Cattle;
use App\Models\CattleHealthRecord;
use App\Models\Estate;
use App\Models\Permission;
use App\Models\TaskNotification;
use App\Models\TreatmentMonthlyWorkflow;
use App\Models\TreatmentWorkflowAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class HealthTreatmentController extends Controller
{
    /**
     * 3-Step Workflow Configuration
     * Step 0: Prepared By - Sr. Assistant Livestock (livestock role)
     * Step 1: Checked By - Supervisor Livestock (supervisor role)
     * Step 2: Approved By - Manager (livestock manager role)
     */
    private $workflowSteps = [
        ['role' => 'livestock', 'field' => 'prepared', 'label' => 'Prepared By', 'role_name' => 'Sr. Assistant Livestock'],
        ['role' => 'supervisor', 'field' => 'checked', 'label' => 'Checked By', 'role_name' => 'Supervisor Livestock'],
        ['role' => 'livestock manager', 'field' => 'approved', 'label' => 'Approved By', 'role_name' => 'Livestock Manager/OIC'],
    ];

    /**
     * Display a listing of treatment records
     */
    public function index()
    {
        $treatments = Treatment::with(['cattle', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
        $categories = Treatment::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        $estates = Estate::with('pastureBlocks')->where('is_active', true)->orderBy('name')->get();
        $cattle = Cattle::where('status', 'Active')->get(['id', 'tag_no', 'category', 'coat_colour', 'location_block', 'location_phase']);
        $treatmentCodes = \App\Models\TreatmentCode::active()->orderBy('label')->get();
        $monthlyWorkflows = TreatmentMonthlyWorkflow::query()
            ->select(['year', 'month', 'operating_unit', 'status', 'is_completed'])
            ->get();

        return Inertia::render('Health/TreatmentRecords', [
            'treatments' => $treatments,
            'workflowSteps' => $this->workflowSteps,
            'workflowAssignment' => TreatmentWorkflowAssignment::first(),
            'estates' => $estates,
            'cattle' => $cattle,
            'treatmentCodes' => $treatmentCodes,
            'categories' => $categories,
            'monthlyWorkflows' => $monthlyWorkflows,
        ]);
    }

    /**
     * Display pending approvals (tasks for current user)
     */
    public function pendingApprovals()
    {
        $user = Auth::user();
        $treatments = Treatment::with(['cattle', 'creator'])
            ->whereIn('status', ['pending', 'under_review'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Health/TreatmentPendingApprovals', [
            'treatments' => $treatments,
            'workflowSteps' => $this->workflowSteps,
            'workflowAssignment' => TreatmentWorkflowAssignment::first(),
            'userRole' => $user->role,
        ]);
    }

    /**
     * Show the form for creating a new treatment record
     */
    public function create()
    {
        $cattle = Cattle::where('status', 'Active')->get(['id', 'tag_no', 'category', 'coat_colour', 'location_block', 'location_phase']);
        $treatmentCodes = \App\Models\TreatmentCode::active()->orderBy('label')->get();
        $estates = Estate::with('pastureBlocks')->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Health/RecordTreatment', [
            'cattle' => $cattle,
            'treatmentCodes' => $treatmentCodes,
            'estates' => $estates,
        ]);
    }

    /**
     * Show the form for editing a treatment record
     */
    public function edit($id)
    {
        $treatment = Treatment::with('cattle')->findOrFail($id);
        $cattle = Cattle::where('status', 'Active')->get(['id', 'tag_no', 'category', 'coat_colour', 'location_block', 'location_phase']);
        $treatmentCodes = \App\Models\TreatmentCode::active()->orderBy('label')->get();
        $estates = Estate::with('pastureBlocks')->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Health/RecordTreatment', [
            'cattle' => $cattle,
            'treatmentCodes' => $treatmentCodes,
            'estates' => $estates,
            'treatment' => $treatment,
        ]);
    }

    /**
     * Store a newly created treatment record
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cattle_id' => 'required|exists:cattle,id',
            'tag_no' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'operating_unit' => 'nullable|string|max:255',
            'colour' => 'nullable|string|max:100',
            'date' => 'required|date',
            'week' => 'nullable|string|max:50',
            'symptoms' => 'required|string',
            'treatment' => 'required|string',
            'treatment_code' => 'nullable|string|max:100',
            'dosage' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date',
        ]);

        $user = Auth::user();

        // Generate treatment number
        $lastTreatment = Treatment::orderBy('id', 'desc')->first();
        $treatmentNo = 'TRT-' . date('Y') . '-' . str_pad(($lastTreatment ? $lastTreatment->id + 1 : 1), 5, '0', STR_PAD_LEFT);

        $treatment = Treatment::create([
            ...$validated,
            'treatment_no' => $treatmentNo,
            'status' => 'pending',
            'current_step' => 'prepared',
            'endorsement_step' => 0,
            'created_by' => $user->id,
        ]);

        $this->resetMonthlyWorkflowForTreatmentScope($treatment);

        return redirect()->route('health.treatment.index')
            ->with('success', 'Treatment record created successfully');
    }

    /**
     * Update the specified treatment record
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->hasTreatmentPermission($user, 'edit')) {
            return back()->withErrors(['error' => 'You do not have permission to edit treatment records']);
        }

        $treatment = Treatment::findOrFail($id);

        if ($this->isMonthlyWorkflowCompleted(optional($treatment->date)->toDateString(), (string) ($treatment->operating_unit ?? ''))) {
            return back()->withErrors(['error' => 'This monthly treatment workflow has been completed. Reopen monthly workflow before editing records.']);
        }

        $validated = $request->validate([
            'cattle_id' => 'nullable|exists:cattle,id',
            'tag_no' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'operating_unit' => 'nullable|string|max:255',
            'colour' => 'nullable|string|max:100',
            'date' => 'required|date',
            'symptoms' => 'required|string',
            'treatment_code' => 'nullable|string|max:100',
            'dosage' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date',
        ]);

        $treatmentLabel = $validated['treatment_code'] ?? null;
        if (!$treatmentLabel) {
            $treatmentLabel = $treatment->treatment ?: 'General Treatment';
        }

        $incomingData = [
            ...$validated,
            'treatment' => $treatmentLabel,
            'follow_up_date' => !empty($validated['follow_up_required']) ? ($validated['follow_up_date'] ?? null) : null,
        ];

        $currentData = [
            'cattle_id' => $treatment->cattle_id,
            'tag_no' => $treatment->tag_no,
            'category' => $treatment->category,
            'operating_unit' => $treatment->operating_unit,
            'colour' => $treatment->colour,
            'date' => optional($treatment->date)->toDateString(),
            'symptoms' => $treatment->symptoms,
            'treatment_code' => $treatment->treatment_code,
            'dosage' => $treatment->dosage,
            'remarks' => $treatment->remarks,
            'follow_up_required' => (bool) $treatment->follow_up_required,
            'follow_up_date' => optional($treatment->follow_up_date)->toDateString(),
            'treatment' => $treatment->treatment,
        ];

        $hasChanges = (
            (int) ($incomingData['cattle_id'] ?? 0) !== (int) ($currentData['cattle_id'] ?? 0)
            || (string) ($incomingData['tag_no'] ?? '') !== (string) ($currentData['tag_no'] ?? '')
            || (string) ($incomingData['category'] ?? '') !== (string) ($currentData['category'] ?? '')
            || (string) ($incomingData['operating_unit'] ?? '') !== (string) ($currentData['operating_unit'] ?? '')
            || (string) ($incomingData['colour'] ?? '') !== (string) ($currentData['colour'] ?? '')
            || (string) ($incomingData['date'] ?? '') !== (string) ($currentData['date'] ?? '')
            || (string) ($incomingData['symptoms'] ?? '') !== (string) ($currentData['symptoms'] ?? '')
            || (string) ($incomingData['treatment_code'] ?? '') !== (string) ($currentData['treatment_code'] ?? '')
            || (string) ($incomingData['dosage'] ?? '') !== (string) ($currentData['dosage'] ?? '')
            || (string) ($incomingData['remarks'] ?? '') !== (string) ($currentData['remarks'] ?? '')
            || (bool) ($incomingData['follow_up_required'] ?? false) !== (bool) ($currentData['follow_up_required'] ?? false)
            || (string) ($incomingData['follow_up_date'] ?? '') !== (string) ($currentData['follow_up_date'] ?? '')
            || (string) ($incomingData['treatment'] ?? '') !== (string) ($currentData['treatment'] ?? '')
        );

        if ($hasChanges) {
            $oldDate = optional($treatment->date)->toDateString();
            $oldOperatingUnit = (string) ($treatment->operating_unit ?? '');

            $incomingData['status'] = 'pending';
            $incomingData['endorsement_step'] = 0;
            $incomingData['current_step'] = 'prepared';
            $incomingData['endorsement_documents'] = null;
            $incomingData['is_reopened'] = false;

            $this->resetMonthlyWorkflowByScope($oldDate, $oldOperatingUnit);
            $this->resetMonthlyWorkflowByScope((string) ($incomingData['date'] ?? ''), (string) ($incomingData['operating_unit'] ?? ''));
        }

        $treatment->update($incomingData);
        $this->syncTreatmentHealthRecord($treatment->fresh());

        return redirect()->route('health.treatment.index')
            ->with('success', $hasChanges ? 'Treatment record updated successfully. Workflow reset to step 1.' : 'Treatment record updated successfully.');
    }

    /**
     * Display the specified treatment record
     */
    public function show($id)
    {
        $treatment = Treatment::with(['cattle', 'creator'])->findOrFail($id);

        return Inertia::render('Health/TreatmentShow', [
            'treatment' => $treatment,
            'workflowSteps' => $this->workflowSteps,
        ]);
    }

    /**
     * Upload endorsement document for a treatment record
     */
    public function uploadEndorsement(Request $request, $id)
    {
        $request->validate([
            'signed_document' => 'required|file|mimes:pdf|max:20480',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'step_index' => 'required|integer|min:0|max:2', // 3 steps: 0-2
        ]);

        $treatment = Treatment::findOrFail($id);
        $user = Auth::user();
        $stepIndex = (int) $request->step_index;

        // Check if user has permission for this step
        if (!$this->userCanUploadWorkflowStep($user, $stepIndex)) {
            return back()->withErrors(['error' => 'You do not have permission to upload for this step']);
        }

        // Get current endorsement documents as array for manipulation
        $rawDocs = $treatment->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $currentStep = $treatment->endorsement_step ?? 0;

        // Block uploads if treatment is already completed
        if ($treatment->status === 'completed') {
            return back()->withErrors(['error' => 'This treatment record has been completed. No further uploads are allowed.']);
        }

        // Check if can upload (admin can upload any step while not completed)
        if ($user->role === 'admin') {
            $canUpload = true;
        } else {
            $nextStepKey = strval($stepIndex + 1);

            // For steps 0-1: can upload at current step, OR re-upload if next person hasn't uploaded yet
            // For step 2 (manager): can upload/re-upload anytime until admin marks as completed
            if ($stepIndex === 2) {
                // Manager (last step) - can upload/re-upload until treatment is completed
                $canUpload = ($stepIndex <= $currentStep);
            } else {
                // Steps 0-1 - can upload at current step, OR re-upload if next hasn't uploaded
                $canUpload = ($stepIndex === $currentStep) ||
                             ($stepIndex < $currentStep && !isset($endorsementDocs[$nextStepKey]));
            }
        }

        if (!$canUpload) {
            return back()->withErrors(['error' => 'Cannot upload at this step']);
        }

        // Store the file
        $file = $request->file('signed_document');
        $filename = 'treatment_endorsement_' . $treatment->id . '_step' . $stepIndex . '_' . time() . '.pdf';
        $path = $file->storeAs('treatment_endorsements', $filename, 'public');

        // Remove previous file for this step when re-uploading
        $stepKey = strval($stepIndex);
        if (isset($endorsementDocs[$stepKey]['file_path']) && Storage::disk('public')->exists($endorsementDocs[$stepKey]['file_path'])) {
            Storage::disk('public')->delete($endorsementDocs[$stepKey]['file_path']);
        }

        // Update endorsement documents - use string key for consistent JSON object
        $endorsementDocs[$stepKey] = [
            'name' => $request->name,
            'date' => $request->date,
            'file_path' => $path,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        // Convert back to object for storage
        $endorsementDocsObject = (object) $endorsementDocs;

        // Move to next step if uploading at current step
        $newStep = $currentStep;
        if ($stepIndex === $currentStep && $stepIndex < 2) {
            $newStep = $stepIndex + 1;
        }

        // If manager (step 2) uploads, set step to 3 (all uploaded, awaiting admin completion)
        if ($stepIndex === 2) {
            $newStep = 3; // All steps done, awaiting admin to mark as completed
        }

        // Update current_step field based on endorsement progress
        $stepFields = ['prepared', 'checked', 'approved'];
        $currentStepField = $stepFields[min($newStep, 2)] ?? 'approved';

        // Update treatment - DO NOT auto-complete, admin must manually mark as completed
        $treatment->update([
            'endorsement_documents' => $endorsementDocsObject,
            'endorsement_step' => $newStep,
            'current_step' => $currentStepField,
            'status' => 'under_review',
        ]);

// Notify users responsible for the next step (if not the last step and not admin)
        if ($user->role !== 'admin' && $stepIndex === $currentStep && $stepIndex < 2) {
            $nextStepIndex = $stepIndex + 1;
            if (isset($this->workflowSteps[$nextStepIndex])) {
                $nextStepRole = $this->workflowSteps[$nextStepIndex]['role'] ?? null;
                $nextStepLabel = $this->workflowSteps[$nextStepIndex]['label'] ?? 'Next Step';
                $currentStepLabel = $this->workflowSteps[$stepIndex]['label'] ?? 'Current Step';
                
                // Find users with role matching the next step
                $usersToNotify = \App\Models\User::where('role', $nextStepRole)->get();
                
                foreach ($usersToNotify as $notifyUser) {
                    \App\Services\WorkflowNotificationService::createNotification(
                        'health_treatment',
                        'treatment',
                        $treatment->id,
                        $nextStepRole, // action is the role needed
                        'Treatment Workflow Step Completed',
                        "The treatment workflow step '{$currentStepLabel}' has been completed. Please proceed with '{$nextStepLabel}'.",
                        $notifyUser->id
                    );
                }
            }
        }
        return back()->with('success', 'Document uploaded successfully');
    }

    /**
     * Delete endorsement document for a step.
     * Rule: uploader/step owner can delete only before next step is uploaded.
     */
    public function deleteEndorsement($id, $stepIndex)
    {
        $treatment = Treatment::findOrFail($id);
        $user = Auth::user();
        $stepIndex = (int) $stepIndex;

        if ($stepIndex < 0 || $stepIndex > 2) {
            return back()->withErrors(['error' => 'Invalid endorsement step']);
        }

        if ($treatment->status === 'completed') {
            return back()->withErrors(['error' => 'Completed treatment is locked. Delete is not allowed.']);
        }

        if (!$this->userCanUploadWorkflowStep($user, $stepIndex)) {
            return back()->withErrors(['error' => 'You do not have permission to delete this step']);
        }

        $rawDocs = $treatment->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $stepKey = strval($stepIndex);
        if (!isset($endorsementDocs[$stepKey])) {
            return back()->withErrors(['error' => 'No uploaded document found for this step']);
        }

        $nextStepKey = strval($stepIndex + 1);
        if ($stepIndex < 2 && isset($endorsementDocs[$nextStepKey]) && $user->role !== 'admin') {
            return back()->withErrors(['error' => 'Cannot delete because the next step has already endorsed.']);
        }

        $filePath = $endorsementDocs[$stepKey]['file_path'] ?? null;
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        unset($endorsementDocs[$stepKey]);

        $has0 = isset($endorsementDocs['0']);
        $has1 = isset($endorsementDocs['1']);
        $has2 = isset($endorsementDocs['2']);

        $newStep = 0;
        $newCurrentStep = 'prepared';
        $newStatus = 'pending';

        if ($has0 && !$has1) {
            $newStep = 1;
            $newCurrentStep = 'checked';
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && !$has2) {
            $newStep = 2;
            $newCurrentStep = 'approved';
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && $has2) {
            $newStep = 3;
            $newCurrentStep = 'approved';
            $newStatus = 'under_review';
        }

        $treatment->update([
            'endorsement_documents' => (object) $endorsementDocs,
            'endorsement_step' => $newStep,
            'current_step' => $newCurrentStep,
            'status' => $newStatus,
        ]);

        return back()->with('success', 'Endorsement document deleted successfully');
    }

    /**
     * Admin: Mark treatment as completed (locks all uploads)
     */
    public function markAsCompleted($id)
    {
        $user = Auth::user();

        // Only admin can mark as completed
        if ($user->role !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can mark treatments as completed']);
        }

        $treatment = Treatment::findOrFail($id);

        // Check if all 3 steps are uploaded
        $rawDocs = $treatment->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        // Verify all 3 steps have documents
        for ($i = 0; $i < 3; $i++) {
            if (!isset($endorsementDocs[strval($i)])) {
                return back()->withErrors(['error' => 'All 3 endorsement steps must be completed before marking as completed']);
            }
        }

        $treatment->update([
            'status' => 'completed',
            'current_step' => 'approved',
            'is_reopened' => false,
        ]);

        // Create notification for the person who prepared the treatment
        if ($treatment->created_by) {
            \App\Services\WorkflowNotificationService::createNotification(
                'health_treatment',
                'treatment',
                $treatment->id,
                'completed',
                'Treatment Record Completed',
                "Your treatment record #{$treatment->id} has been marked as completed.",
                $treatment->created_by
            );
        }

        $this->syncTreatmentToCattle($treatment->fresh());

        return back()->with('success', 'Treatment record marked as completed. No further uploads are allowed.');
    }

    /**
     * Admin: Reopen a completed treatment for further changes.
     */
    public function reopen($id)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can reopen treatments']);
        }

        $treatment = Treatment::findOrFail($id);

        $rawDocs = $treatment->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        $has0 = isset($endorsementDocs['0']);
        $has1 = isset($endorsementDocs['1']);
        $has2 = isset($endorsementDocs['2']);

        $newStep = 0;
        $newCurrentStep = 'prepared';
        $newStatus = 'pending';

        if ($has0 && !$has1) {
            $newStep = 1;
            $newCurrentStep = 'checked';
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && !$has2) {
            $newStep = 2;
            $newCurrentStep = 'approved';
            $newStatus = 'under_review';
        } elseif ($has0 && $has1 && $has2) {
            $newStep = 3;
            $newCurrentStep = 'approved';
            $newStatus = 'under_review';
        }

        $treatment->update([
            'status' => $newStatus,
            'endorsement_step' => $newStep,
            'current_step' => $newCurrentStep,
            'is_reopened' => true,
        ]);
        $this->syncTreatmentHealthRecord($treatment->fresh());

        return back()->with('success', 'Treatment reopened successfully.');
    }

    /**
     * Download endorsement document
     */
    public function downloadEndorsement($id, $stepIndex)
    {
        $treatment = Treatment::findOrFail($id);
        $user = Auth::user();
        $stepIndex = (int) $stepIndex;

        // Check permission - can only view own step OR previous step (to download previous person's doc)
        $userStepIndex = $this->getUserWorkflowStepIndex($user);
        if ($userStepIndex === null && $user->role !== 'admin') {
            abort(403, 'You do not have permission to view this document');
        }

        // User can view: their own step OR the immediately previous step only
        if ($user->role !== 'admin') {
            $canView = ($stepIndex === $userStepIndex) || ($stepIndex === $userStepIndex - 1);
            if (!$canView) {
                abort(403, 'You can only view your own document or the previous person\'s document');
            }
        }

        // Get current endorsement documents as array
        $rawDocs = $treatment->endorsement_documents;
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

        if (!Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($filePath);
    }

    /**
     * Generate and download Treatment Endorsement Form PDF
     */
    public function downloadEndorsementForm($id)
    {
        $treatment = Treatment::with(['cattle', 'creator'])->findOrFail($id);
        if (!$treatment->date || trim((string) $treatment->operating_unit) === '') {
            return back()->withErrors([
                'error' => 'This treatment does not have a valid date or operating unit to generate the monthly report.',
            ]);
        }

        $reportRequest = new Request([
            'year' => (int) $treatment->date->format('Y'),
            'month' => (int) $treatment->date->format('n'),
            'operating_unit' => (string) $treatment->operating_unit,
        ]);

        return $this->exportReport($reportRequest);
    }

    /**
     * Export treatment records report (PDF) by year/month and operating unit
     */
    public function exportReport(Request $request)
    {
        $year = (int) $request->get('year');
        $month = (int) $request->get('month');
        $operatingUnit = trim((string) $request->get('operating_unit', ''));

        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || $operatingUnit === '') {
            return back()->withErrors([
                'error' => 'Please select a valid year, month, and operating unit before exporting.',
            ]);
        }

        $records = Treatment::with(['creator'])
            ->whereNotNull('date')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('operating_unit', $operatingUnit)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $monthYearLabel = ($monthNames[$month] ?? 'Unknown') . ' ' . substr((string) $year, -2);
        $generatedAt = now()->format('d/m/Y H:i:s');
        $logoPath = public_path('images/sawit-kinabalu-logo.png');
        $logoSrc = '';
        if (is_file($logoPath)) {
            $logoData = @file_get_contents($logoPath);
            if ($logoData !== false) {
                $logoSrc = 'data:image/png;base64,' . base64_encode($logoData);
            }
        }

        $rows = '';
        foreach ($records as $index => $record) {
            $weekLabel = trim((string) ($record->week ?? ''));
            if ($weekLabel === '' && $record->date) {
                $dayOfMonth = (int) $record->date->format('j');
                $weekNumber = (int) ceil($dayOfMonth / 7);
                $weekLabel = 'Week ' . min(5, max(1, $weekNumber));
            } elseif (is_numeric($weekLabel)) {
                $weekLabel = 'Week ' . min(5, max(1, (int) $weekLabel));
            } elseif ($weekLabel === '') {
                $weekLabel = '-';
            }

            $treatmentCode = trim((string) ($record->treatment_code ?: $record->treatment ?: '-'));
            $dosage = trim((string) ($record->dosage ?: ''));
            $treatmentAndDosage = $treatmentCode;
            if ($dosage !== '') {
                $treatmentAndDosage .= ' / ' . $dosage;
            }

            $rows .= '<tr>
                <td class="center">' . ($index + 1) . '</td>
                <td class="center">' . e($weekLabel) . '</td>
                <td class="center">' . e($record->date ? $record->date->format('d/m/Y') : '-') . '</td>
                <td>' . e($record->tag_no ?: '-') . '</td>
                <td>' . e($record->category ?: '-') . '</td>
                <td>' . e($record->colour ?: '-') . '</td>
                <td>' . e($record->symptoms ?: '-') . '</td>
                <td>' . e($treatmentAndDosage) . '</td>
                <td>' . e($record->remarks ?: '-') . '</td>
            </tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9" class="empty">No treatment records found for the selected period and operating unit.</td></tr>';
        }

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin: 18px 20px;
                }
                body {
                    font-family: DejaVu Sans, Arial, sans-serif;
                    font-size: 10px;
                    color: #111827;
                }
                .header {
                    width: 100%;
                    border-bottom: 1px solid #111827;
                    padding-bottom: 8px;
                    margin-bottom: 8px;
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .header-table td {
                    vertical-align: top;
                    border: none;
                    padding: 0;
                }
                .company {
                    width: 36%;
                    font-size: 9px;
                    line-height: 1.35;
                    text-align: center;
                }
                .company-logo {
                    width: 70px;
                    height: auto;
                    margin: 0 auto 6px;
                    display: block;
                }
                .company-name {
                    font-weight: 700;
                }
                .company-sub {
                    margin-top: 2px;
                }
                .title-wrap {
                    width: 34%;
                    text-align: center;
                }
                .title {
                    font-size: 16px;
                    font-weight: 700;
                    margin: 0;
                }
                .meta-wrap {
                    width: 30%;
                    text-align: right;
                    font-size: 10px;
                    line-height: 1.45;
                }
                .meta-label {
                    font-weight: 700;
                }
                .records-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 6px;
                }
                .records-table th,
                .records-table td {
                    border: 1px solid #111827;
                    padding: 5px 6px;
                    font-size: 9px;
                    vertical-align: top;
                }
                .records-table th {
                    text-align: center;
                    font-weight: 700;
                }
                .center {
                    text-align: center;
                }
                .empty {
                    text-align: center;
                    color: #6b7280;
                    padding: 14px 6px;
                }
                .signature-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 18px;
                }
                .signature-table th,
                .signature-table td {
                    border: 1px solid #111827;
                    text-align: center;
                    padding: 8px 10px;
                    font-size: 10px;
                }
                .signature-table th {
                    font-weight: 700;
                }
                .sign-space {
                    height: 48px;
                }
                .footer {
                    margin-top: 10px;
                    text-align: right;
                    font-size: 9px;
                    color: #374151;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <table class="header-table">
                    <tr>
                        <td class="company">
                            <div>
                                <img src="' . e($logoSrc) . '" alt="Sawit Kinabalu Logo" class="company-logo">
                                <div class="company-name">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
                                <div class="company-sub">Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)</div>
                            </div>
                        </td>
                        <td class="title-wrap">
                            <p class="title">Monthly Treatment Record</p>
                        </td>
                        <td class="meta-wrap">
                            <span class="meta-label">Month/Year:</span> ' . e($monthYearLabel) . '<br>
                            <span class="meta-label">Operating Unit:</span> ' . e($operatingUnit) . '
                        </td>
                    </tr>
                </table>
            </div>

            <table class="records-table">
                <thead>
                    <tr>
                        <th style="width:4%;">No.</th>
                        <th style="width:6%;">Week</th>
                        <th style="width:9%;">Date</th>
                        <th style="width:12%;">Tag No.</th>
                        <th style="width:10%;">Category</th>
                        <th style="width:9%;">Colour</th>
                        <th style="width:18%;">Symptoms</th>
                        <th style="width:17%;">Treatment (Code) / Dosage</th>
                        <th style="width:15%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>

            <table class="signature-table">
                <thead>
                    <tr>
                        <th>Prepared By</th>
                        <th>Checked By</th>
                        <th>Approved By</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="sign-space"></td>
                        <td class="sign-space"></td>
                        <td class="sign-space"></td>
                    </tr>
                    <tr>
                        <td>(Sr. Ast. Livestock)</td>
                        <td>(Supervisor Livestock)</td>
                        <td>(Manager Livestock)</td>
                    </tr>
                </tbody>
            </table>

            <p class="footer">Generated on ' . e($generatedAt) . '</p>
        </body>
        </html>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape');
        $filename = 'Treatment_Report_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $operatingUnit) . '_' . $year . '_' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.pdf';

        return $pdf->download($filename);
    }

    private function normalizeRole(?string $role): string
    {
        $r = strtolower(trim((string) $role));
        $r = str_replace(['_', '-'], ' ', $r);
        $r = preg_replace('/\s+/', ' ', $r);

        if (in_array($r, ['manager', 'livestock manager', 'act livestock manager', 'act. livestock manager'], true)) {
            return 'livestock manager';
        }

        return $r;
    }

    private function rolesMatch(?string $a, ?string $b): bool
    {
        return $this->normalizeRole($a) === $this->normalizeRole($b);
    }

    private function getAssignedUserIdsForStep(int $stepIndex): array
    {
        $assignment = TreatmentWorkflowAssignment::first();
        if (!$assignment) {
            return [];
        }

        $ids = match ($stepIndex) {
            0 => $assignment->prepared_by_user_ids,
            1 => $assignment->checked_by_user_ids,
            2 => $assignment->approved_by_user_ids,
            default => [],
        };

        if (!is_array($ids)) {
            $ids = [];
        }

        // Backward compatibility with single-user columns
        if (empty($ids)) {
            $fallbackId = match ($stepIndex) {
                0 => $assignment->prepared_by_user_id,
                1 => $assignment->checked_by_user_id,
                2 => $assignment->approved_by_user_id,
                default => null,
            };
            if ($fallbackId) {
                $ids = [(int) $fallbackId];
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function userCanUploadWorkflowStep($user, int $stepIndex): bool
    {
        if (strtolower((string) ($user->role ?? '')) === 'admin') {
            return true;
        }

        $assignedUserIds = $this->getAssignedUserIdsForStep($stepIndex);

        return !empty($assignedUserIds) && in_array((int) $user->id, $assignedUserIds, true);
    }

    private function getUserWorkflowStepIndex($user): ?int
    {
        if ($user->role === 'admin') {
            return null;
        }

        foreach (array_keys($this->workflowSteps) as $stepIndex) {
            if ($this->userCanUploadWorkflowStep($user, (int) $stepIndex)) {
                return (int) $stepIndex;
            }
        }

        return null;
    }

    private function resolveMonthlyScope(Request $request): array
    {
        $year = (int) $request->get('year');
        $month = (int) $request->get('month');
        $operatingUnit = trim((string) $request->get('operating_unit', ''));

        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || $operatingUnit === '') {
            abort(422, 'Please provide valid year, month, and operating unit.');
        }

        return [$year, $month, $operatingUnit];
    }

    private function recalculateMonthlyWorkflow(TreatmentMonthlyWorkflow $workflow): void
    {
        $docs = is_array($workflow->endorsement_documents) ? $workflow->endorsement_documents : [];

        $has0 = isset($docs['0']) || isset($docs[0]);
        $has1 = isset($docs['1']) || isset($docs[1]);
        $has2 = isset($docs['2']) || isset($docs[2]);

        $step = 0;
        $status = 'pending';

        if ($has0 && !$has1) {
            $step = 1;
            $status = 'under_review';
        } elseif ($has0 && $has1 && !$has2) {
            $step = 2;
            $status = 'under_review';
        } elseif ($has0 && $has1 && $has2) {
            $step = 3;
            $status = $workflow->is_completed ? 'completed' : 'under_review';
        }

        $workflow->endorsement_step = $step;
        if (!$workflow->is_completed) {
            $workflow->status = $status;
            $workflow->completed_at = null;
        }
    }

    private function notifyNextTreatmentMonthlyStepUsers(int $nextStepIndex, int $month, int $year, string $operatingUnit, int $createdBy): void
    {
        $userIds = $this->getAssignedUserIdsForStep($nextStepIndex);
        if (empty($userIds)) {
            return;
        }

        $nextStepLabel = $this->workflowSteps[$nextStepIndex]['label'] ?? 'Next Step';
        $message = "Monthly treatment workflow for {$operatingUnit} ({$month}/{$year}) is now waiting for {$nextStepLabel}.";

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $alreadyNotified = TaskNotification::where('user_id', $userId)
                ->where('type', 'treatment_monthly_workflow_step_ready')
                ->where('message', $message)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            TaskNotification::create([
                'user_id' => $userId,
                'title' => 'Treatment Workflow Step Ready',
                'message' => $message,
                'type' => 'treatment_monthly_workflow_step_ready',
                'is_read' => false,
                'created_by' => $createdBy,
            ]);
        }
    }

    private function notifyAdminsTreatmentWorkflowReadyForCompletion(int $month, int $year, string $operatingUnit, int $createdBy): void
    {
        $adminUsers = User::whereIn('role', ['admin', 'Admin'])->get(['id']);
        $message = "All monthly treatment workflow steps for {$operatingUnit} ({$month}/{$year}) have been uploaded. Please review and mark as complete.";

        foreach ($adminUsers as $adminUser) {
            $alreadyNotified = TaskNotification::where('user_id', (int) $adminUser->id)
                ->where('type', 'treatment_monthly_workflow_ready_for_completion')
                ->where('message', $message)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            TaskNotification::create([
                'user_id' => (int) $adminUser->id,
                'title' => 'Treatment Workflow Ready for Completion',
                'message' => $message,
                'type' => 'treatment_monthly_workflow_ready_for_completion',
                'is_read' => false,
                'created_by' => $createdBy,
            ]);
        }
    }

    public function getMonthlyWorkflow(Request $request)
    {
        [$year, $month, $operatingUnit] = $this->resolveMonthlyScope($request);

        $workflow = TreatmentMonthlyWorkflow::firstOrCreate(
            [
                'year' => $year,
                'month' => $month,
                'operating_unit' => $operatingUnit,
            ],
            [
                'endorsement_step' => 0,
                'endorsement_documents' => [],
                'status' => 'pending',
                'is_completed' => false,
            ]
        );

        $this->recalculateMonthlyWorkflow($workflow);
        $workflow->save();

        return response()->json([
            'workflow' => $workflow,
            'steps' => $this->workflowSteps,
        ]);
    }

    public function uploadMonthlyEndorsement(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'operating_unit' => 'required|string',
            'step_index' => 'required|integer|min:0|max:2',
            'signed_document' => 'required|file|mimes:pdf|max:20480',
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $user = Auth::user();
        $stepIndex = (int) $validated['step_index'];

        $workflow = TreatmentMonthlyWorkflow::firstOrCreate(
            [
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
                'operating_unit' => trim((string) $validated['operating_unit']),
            ],
            [
                'endorsement_step' => 0,
                'endorsement_documents' => [],
                'status' => 'pending',
                'is_completed' => false,
            ]
        );

        if ($workflow->is_completed || $workflow->status === 'completed') {
            return back()->withErrors(['error' => 'Monthly workflow is completed. Reopen first to upload.']);
        }

        if (!$this->userCanUploadWorkflowStep($user, $stepIndex)) {
            return back()->withErrors(['error' => 'You do not have permission for this workflow step.']);
        }

        $docs = is_array($workflow->endorsement_documents) ? $workflow->endorsement_documents : [];
        $currentStep = (int) ($workflow->endorsement_step ?? 0);

        if ($user->role !== 'admin') {
            $nextStepKey = strval($stepIndex + 1);
            if ($stepIndex === 2) {
                $canUpload = ($stepIndex <= $currentStep);
            } else {
                $canUpload = ($stepIndex === $currentStep) || ($stepIndex < $currentStep && !isset($docs[$nextStepKey]));
            }

            if (!$canUpload) {
                return back()->withErrors(['error' => 'Cannot upload at this step.']);
            }
        }

        $file = $request->file('signed_document');
        $filename = 'treatment_monthly_' . $workflow->id . '_step' . $stepIndex . '_' . time() . '.pdf';
        $path = $file->storeAs('treatment_monthly_endorsements', $filename, 'public');

        $stepKey = strval($stepIndex);
        if (isset($docs[$stepKey]['file_path']) && Storage::disk('public')->exists($docs[$stepKey]['file_path'])) {
            Storage::disk('public')->delete($docs[$stepKey]['file_path']);
        }

        $docs[$stepKey] = [
            'name' => $validated['name'],
            'date' => $validated['date'],
            'file_path' => $path,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $workflow->endorsement_documents = $docs;
        $workflow->is_completed = false;
        $this->recalculateMonthlyWorkflow($workflow);
        $workflow->save();

        $newStep = (int) ($workflow->endorsement_step ?? 0);
        if ($newStep > $currentStep && $newStep <= 2) {
            $this->notifyNextTreatmentMonthlyStepUsers(
                $newStep,
                (int) $validated['month'],
                (int) $validated['year'],
                trim((string) $validated['operating_unit']),
                (int) $user->id
            );
        }

        if ($newStep === 3) {
            $this->notifyAdminsTreatmentWorkflowReadyForCompletion(
                (int) $validated['month'],
                (int) $validated['year'],
                trim((string) $validated['operating_unit']),
                (int) $user->id
            );
        }

        return back()->with('success', 'Monthly endorsement uploaded successfully.');
    }

    public function downloadMonthlyEndorsement(Request $request, $stepIndex)
    {
        [$year, $month, $operatingUnit] = $this->resolveMonthlyScope($request);
        $user = Auth::user();
        $stepIndex = (int) $stepIndex;

        $workflow = TreatmentMonthlyWorkflow::where('year', $year)
            ->where('month', $month)
            ->where('operating_unit', $operatingUnit)
            ->firstOrFail();

        $docs = is_array($workflow->endorsement_documents) ? $workflow->endorsement_documents : [];
        $stepKey = strval($stepIndex);
        if (!isset($docs[$stepKey])) {
            abort(404, 'Document not found');
        }

        if ($user->role !== 'admin') {
            $userStepIndex = $this->getUserWorkflowStepIndex($user);

            if ($userStepIndex === null || !($stepIndex === $userStepIndex || $stepIndex === $userStepIndex - 1)) {
                abort(403, 'You can only download your step or previous step.');
            }
        }

        $filePath = $docs[$stepKey]['file_path'] ?? null;
        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($filePath);
    }

    public function deleteMonthlyEndorsement(Request $request, $stepIndex)
    {
        [$year, $month, $operatingUnit] = $this->resolveMonthlyScope($request);
        $user = Auth::user();
        $stepIndex = (int) $stepIndex;

        if ($stepIndex < 0 || $stepIndex > 2) {
            return back()->withErrors(['error' => 'Invalid step.']);
        }

        $workflow = TreatmentMonthlyWorkflow::where('year', $year)
            ->where('month', $month)
            ->where('operating_unit', $operatingUnit)
            ->firstOrFail();

        if ($workflow->is_completed || $workflow->status === 'completed') {
            return back()->withErrors(['error' => 'Monthly workflow is completed. Reopen first to delete.']);
        }

        $docs = is_array($workflow->endorsement_documents) ? $workflow->endorsement_documents : [];
        $stepKey = strval($stepIndex);
        if (!isset($docs[$stepKey])) {
            return back()->withErrors(['error' => 'No uploaded document found for this step.']);
        }

        if (!$this->userCanUploadWorkflowStep($user, $stepIndex)) {
            return back()->withErrors(['error' => 'You do not have permission to delete this step.']);
        }

        $nextStepKey = strval($stepIndex + 1);
        if ($user->role !== 'admin' && $stepIndex < 2 && isset($docs[$nextStepKey])) {
            return back()->withErrors(['error' => 'Cannot delete because next step has already endorsed.']);
        }

        $filePath = $docs[$stepKey]['file_path'] ?? null;
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
        unset($docs[$stepKey]);

        $workflow->endorsement_documents = $docs;
        $workflow->is_completed = false;
        $this->recalculateMonthlyWorkflow($workflow);
        $workflow->save();

        return back()->with('success', 'Monthly endorsement document deleted.');
    }

    public function markMonthlyCompleted(Request $request)
    {
        [$year, $month, $operatingUnit] = $this->resolveMonthlyScope($request);
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can mark monthly workflow as completed.']);
        }

        $workflow = TreatmentMonthlyWorkflow::where('year', $year)
            ->where('month', $month)
            ->where('operating_unit', $operatingUnit)
            ->firstOrFail();

        $docs = is_array($workflow->endorsement_documents) ? $workflow->endorsement_documents : [];
        if (!isset($docs['0']) || !isset($docs['1']) || !isset($docs['2'])) {
            return back()->withErrors(['error' => 'All 3 endorsement steps must be uploaded.']);
        }

        $workflow->update([
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
            'endorsement_step' => 3,
        ]);

        Treatment::whereNotNull('date')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('operating_unit', $operatingUnit)
            ->update(['status' => 'completed']);
        $this->syncTreatmentHealthRecordsByScope($year, $month, $operatingUnit);

        // Notify administrators that the monthly workflow is completed
        $adminUsers = \App\Models\User::where('role', 'admin')->get();
        
        foreach ($adminUsers as $adminUser) {
            \App\Models\TaskNotification::create([
                'user_id' => $adminUser->id,
                'title' => 'Monthly Treatment Workflow Completed',
                'message' => "The monthly treatment workflow for {$operatingUnit} ({$month}/{$year}) has been marked as completed.",
                'type' => 'treatment_monthly_workflow_completed',
                'is_read' => false,
                'created_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Monthly workflow marked as completed.');
    }

    public function reopenMonthly(Request $request)
    {
        [$year, $month, $operatingUnit] = $this->resolveMonthlyScope($request);
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return back()->withErrors(['error' => 'Only admin can reopen monthly workflow.']);
        }

        $workflow = TreatmentMonthlyWorkflow::where('year', $year)
            ->where('month', $month)
            ->where('operating_unit', $operatingUnit)
            ->firstOrFail();

        $workflow->is_completed = false;
        $workflow->completed_at = null;
        $this->recalculateMonthlyWorkflow($workflow);
        $workflow->save();

        Treatment::whereNotNull('date')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('operating_unit', $operatingUnit)
            ->update([
                'status' => $workflow->status === 'pending' ? 'pending' : 'under_review',
                'is_reopened' => true,
            ]);
        $this->syncTreatmentHealthRecordsByScope($year, $month, $operatingUnit);

        return back()->with('success', 'Monthly workflow reopened.');
    }

    /**
     * Get pending counts for API (for badge display)
     */
    public function getPendingCounts()
    {
        $user = Auth::user();

        $counts = [
            'total_pending' => Treatment::whereIn('status', ['pending', 'under_review'])->count(),
            'my_tasks' => 0,
            'awaiting_completion' => Treatment::where('endorsement_step', 3)->whereIn('status', ['pending', 'under_review'])->count(),
        ];

        // Count tasks for current user based on role
        $roleStepMap = [
            'livestock' => 0,
            'supervisor' => 1,
            'manager' => 2,
        ];

        if (isset($roleStepMap[$user->role])) {
            $counts['my_tasks'] = Treatment::where('endorsement_step', $roleStepMap[$user->role])
                ->whereIn('status', ['pending', 'under_review'])
                ->count();
        }

        if ($user->role === 'admin') {
            $counts['my_tasks'] = $counts['awaiting_completion'];
        }

        return response()->json($counts);
    }

    /**
     * Delete a treatment record (admin only)
     */
    public function bulkDelete(Request $request)
    {
        $user = Auth::user();

        if (!$this->hasTreatmentPermission($user, 'delete')) {
            return back()->withErrors(['error' => 'You do not have permission to delete treatment records']);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:treatments,id',
        ]);

        $treatments = Treatment::whereIn('id', $validated['ids'])->get();

        $hasLockedScope = $treatments->contains(function (Treatment $treatment): bool {
            return $this->isMonthlyWorkflowCompleted(
                optional($treatment->date)->toDateString(),
                (string) ($treatment->operating_unit ?? '')
            );
        });

        if ($hasLockedScope) {
            return back()->withErrors(['error' => 'One or more selected records are in a completed monthly workflow. Reopen monthly workflow before deleting.']);
        }

        foreach ($treatments as $treatment) {
            $this->resetMonthlyWorkflowForTreatmentScope($treatment);

            $rawDocs = $treatment->endorsement_documents;
            if (is_object($rawDocs)) {
                $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
            } elseif (is_array($rawDocs)) {
                $endorsementDocs = $rawDocs;
            } else {
                $endorsementDocs = [];
            }

            foreach ($endorsementDocs as $doc) {
                if (isset($doc['file_path']) && Storage::disk('public')->exists($doc['file_path'])) {
                    Storage::disk('public')->delete($doc['file_path']);
                }
            }

            CattleHealthRecord::where('source_type', 'treatment')
                ->where('source_id', $treatment->id)
                ->delete();
        }

        Treatment::whereIn('id', $treatments->pluck('id'))->delete();

        return redirect()->route('health.treatment.index')
            ->with('success', count($validated['ids']) . ' treatment record(s) deleted successfully');
    }

    /**
     * Delete a treatment record (admin only)
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if (!$this->hasTreatmentPermission($user, 'delete')) {
            return back()->withErrors(['error' => 'You do not have permission to delete treatment records']);
        }

        $treatment = Treatment::findOrFail($id);

        $this->resetMonthlyWorkflowForTreatmentScope($treatment);

        // Delete associated files
        $rawDocs = $treatment->endorsement_documents;
        if (is_object($rawDocs)) {
            $endorsementDocs = json_decode(json_encode($rawDocs), true) ?? [];
        } elseif (is_array($rawDocs)) {
            $endorsementDocs = $rawDocs;
        } else {
            $endorsementDocs = [];
        }

        foreach ($endorsementDocs as $doc) {
            if (isset($doc['file_path']) && Storage::disk('public')->exists($doc['file_path'])) {
                Storage::disk('public')->delete($doc['file_path']);
            }
        }

        CattleHealthRecord::where('source_type', 'treatment')
            ->where('source_id', $treatment->id)
            ->delete();

        $treatment->delete();

        return redirect()->route('health.treatment.index')
            ->with('success', 'Treatment record deleted successfully');
    }

    private function syncTreatmentToCattle(Treatment $treatment): void
    {
        if (!$treatment->cattle_id) {
            return;
        }

        CattleHealthRecord::updateOrCreate(
            [
                'source_type' => 'treatment',
                'source_id' => $treatment->id,
            ],
            [
                'cattle_id' => $treatment->cattle_id,
                'reference_no' => $treatment->treatment_no,
                'category' => $treatment->category,
                'operating_unit' => $treatment->operating_unit,
                'colour' => $treatment->colour,
                'date' => $treatment->date,
                'description' => $treatment->symptoms,
                'treatment' => $treatment->treatment,
                'dosage' => $treatment->dosage,
                'follow_up_required' => (bool) $treatment->follow_up_required,
                'notes' => $treatment->remarks,
                'status' => $treatment->status,
                'metadata' => [
                    'treatment_code' => $treatment->treatment_code,
                    'follow_up_date' => optional($treatment->follow_up_date)?->toDateString(),
                ],
            ]
        );
    }

    private function syncTreatmentHealthRecord(Treatment $treatment): void
    {
        if ($treatment->status === 'completed') {
            $this->syncTreatmentToCattle($treatment);
            return;
        }

        CattleHealthRecord::where('source_type', 'treatment')
            ->where('source_id', $treatment->id)
            ->delete();
    }

    private function syncTreatmentHealthRecordsByScope(int $year, int $month, string $operatingUnit): void
    {
        Treatment::whereNotNull('date')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('operating_unit', $operatingUnit)
            ->get()
            ->each(function (Treatment $treatment): void {
                $this->syncTreatmentHealthRecord($treatment);
            });
    }

    private function hasTreatmentPermission($user, string $action): bool
    {
        if (!$user) {
            return false;
        }

        if (strtolower((string) $user->role) === 'admin') {
            return true;
        }

        $permissions = Permission::normalizePermissionList($user->hasPermission('Treatment Record'));
        return in_array('full', $permissions, true) || in_array($action, $permissions, true);
    }

    private function resetMonthlyWorkflowForTreatmentScope(Treatment $treatment): void
    {
        $this->resetMonthlyWorkflowByScope(
            optional($treatment->date)->toDateString(),
            (string) ($treatment->operating_unit ?? '')
        );
    }

    private function resetMonthlyWorkflowByScope(?string $date, ?string $operatingUnit): void
    {
        $unit = trim((string) $operatingUnit);
        if ($date === null || trim($date) === '' || $unit === '') {
            return;
        }

        try {
            $workflowDate = \Carbon\Carbon::parse($date);
        } catch (\Throwable $e) {
            return;
        }

        $workflow = TreatmentMonthlyWorkflow::where('year', (int) $workflowDate->year)
            ->where('month', (int) $workflowDate->month)
            ->where('operating_unit', $unit)
            ->first();

        if (!$workflow) {
            return;
        }

        $docs = is_array($workflow->endorsement_documents) ? $workflow->endorsement_documents : [];
        foreach ($docs as $doc) {
            $filePath = $doc['file_path'] ?? null;
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }

        $workflow->update([
            'endorsement_documents' => [],
            'endorsement_step' => 0,
            'status' => 'pending',
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    private function isMonthlyWorkflowCompleted(?string $date, ?string $operatingUnit): bool
    {
        $unit = trim((string) $operatingUnit);
        if ($date === null || trim($date) === '' || $unit === '') {
            return false;
        }

        try {
            $workflowDate = \Carbon\Carbon::parse($date);
        } catch (\Throwable $e) {
            return false;
        }

        $workflow = TreatmentMonthlyWorkflow::query()
            ->where('year', (int) $workflowDate->year)
            ->where('month', (int) $workflowDate->month)
            ->where('operating_unit', $unit)
            ->first();

        if (!$workflow) {
            return false;
        }

        return (bool) $workflow->is_completed || $workflow->status === 'completed';
    }
}
