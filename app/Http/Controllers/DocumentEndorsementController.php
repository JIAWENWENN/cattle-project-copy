<?php

namespace App\Http\Controllers;

use App\Models\DocumentEndorsement;
use App\Models\MortalityCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentEndorsementController extends Controller
{
    // Workflow steps configuration
    private $workflowSteps = [
        0 => ['role' => 'livestock', 'label' => 'Issued by', 'field' => 'issued', 'role_name' => 'Sr. Assistant Livestock'],
        1 => ['role' => 'security', 'label' => 'Verified by', 'field' => 'verified', 'role_name' => 'Sr. Assistant Security'],
        2 => ['role' => 'supervisor', 'label' => 'Checked by', 'field' => 'checked', 'role_name' => 'Supervisor Livestock'],
        3 => ['role' => 'penyelia', 'label' => 'Witnessed by', 'field' => 'witnessed', 'role_name' => 'Estate Management'],
        4 => ['role' => 'manager', 'label' => 'Approved by', 'field' => 'approved', 'role_name' => 'Livestock Manager/OIC'],
    ];

    /**
     * Display document endorsement list
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = DocumentEndorsement::with(['creator', 'mortalityCase'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by step based on user role
        if ($request->has('step') && $request->step !== '') {
            $query->where('current_step', $request->step);
        }

        $documents = $query->paginate(10);

        // Get documents pending action for current user
        $pendingDocuments = collect();
        if ($user->role !== 'admin') {
            $currentStep = array_search($user->role, array_column($this->workflowSteps, 'role'));
            if ($currentStep !== false) {
                $pendingDocuments = DocumentEndorsement::where('current_step', $currentStep)
                    ->where('status', 'pending')
                    ->get();
            }
        }

        return inertia('Mortality/DocumentEndorsement', [
            'documents' => $documents,
            'pendingDocuments' => $pendingDocuments,
            'workflowSteps' => $this->workflowSteps,
            'userRole' => $user->role,
            'canDownload' => $this->canDownload($user),
            'canUpload' => $this->canUpload($user),
        ]);
    }

    /**
     * Create document from mortality case
     */
    public function createFromCase($caseId)
    {
        $case = MortalityCase::with(['cattle', 'postmortemExamination'])->findOrFail($caseId);
        
        $document = DocumentEndorsement::create([
            'mortality_case_id' => $case->id,
            'lmc_no' => $case->lmc_no,
            'tag_no' => $case->cattle->tag_no ?? 'N/A',
            'category' => $case->category,
            'death_date' => $case->death_date,
            'clinical_signs' => $case->initial_notes,
            'treatment' => $case->treatment_notes,
            'preliminary_diagnosis' => $case->category,
            'location' => $case->cattle->location_block ?? 'N/A',
            'herd' => $case->cattle->location_block ?? 'N/A',
            'external_skin' => $case->postmortemExamination->external_skin ?? null,
            'external_eyes' => $case->postmortemExamination->external_eyes ?? null,
            'external_mouth' => $case->postmortemExamination->external_mouth ?? null,
            'external_nostrils' => $case->postmortemExamination->external_nostrils ?? null,
            'external_ears' => $case->postmortemExamination->external_ears ?? null,
            'external_limbs' => $case->postmortemExamination->external_limbs ?? null,
            'external_anus' => $case->postmortemExamination->external_anus ?? null,
            'external_genital' => $case->postmortemExamination->external_genital ?? null,
            'external_general' => $case->postmortemExamination->external_general ?? null,
            'heart_findings' => $case->postmortemExamination->heart_findings ?? null,
            'trachea_findings' => $case->postmortemExamination->trachea_findings ?? null,
            'lung_floating_test' => $case->postmortemExamination->lung_floating_test ?? null,
            'lung_floating_test_details' => $case->postmortemExamination->lung_floating_test_details ?? null,
            'diaphragma_test' => $case->postmortemExamination->diaphragma_test ?? null,
            'diaphragma_test_details' => $case->postmortemExamination->diaphragma_test_details ?? null,
            'kidney_findings' => $case->postmortemExamination->kidney_findings ?? null,
            'urinary_bladder_findings' => $case->postmortemExamination->urinary_bladder_findings ?? null,
            'rumen_findings' => $case->postmortemExamination->rumen_findings ?? null,
            'reticulum_findings' => $case->postmortemExamination->reticulum_findings ?? null,
            'omasum_findings' => $case->postmortemExamination->omasum_findings ?? null,
            'abomasum_findings' => $case->postmortemExamination->abomasum_findings ?? null,
            'small_intestine_findings' => $case->postmortemExamination->small_intestine_findings ?? null,
            'confirmed_cause_of_death' => $case->postmortemExamination->confirmed_cause_of_death ?? null,
            'additional_notes' => $case->postmortemExamination->additional_notes ?? null,
            'current_step' => 0,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('document-endorsement.index')->with('success', 'Document created successfully');
    }

    /**
     * Download document for endorsement
     */
    public function download($id)
    {
        $user = Auth::user();
        $document = DocumentEndorsement::findOrFail($id);

        // Check permission
        if (!$this->canDownloadDocument($user, $document)) {
            abort(403, 'You do not have permission to download this document');
        }

        // Get the previous document for viewing (the uploaded file from previous step)
        $previousDocument = null;
        if ($document->current_step > 0) {
            $previousStep = $document->current_step - 1;
            $previousField = $this->workflowSteps[$previousStep]['field'];
            $previousDocumentField = $previousField . '_document';
            $previousDocument = $document->$previousDocumentField;
        }

        // Generate PDF
        $pdf = $this->generatePDF($document, $previousDocument, $user);
        
        $filename = 'PM_Examination_' . $document->lmc_no . '_Step' . ($document->current_step + 1) . '.pdf';
        
        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Upload signed document
     */
    public function uploadSigned(Request $request, $id)
    {
        $user = Auth::user();
        $document = DocumentEndorsement::findOrFail($id);

        // Check permission
        if (!$this->canUploadDocument($user, $document)) {
            abort(403, 'You do not have permission to upload to this document');
        }

        $request->validate([
            'signed_document' => 'required|file|mimes:pdf|max:10240', // Max 10MB
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $step = $document->current_step;
        $stepConfig = $this->workflowSteps[$step];

        // Store the uploaded file
        $file = $request->file('signed_document');
        $filename = Str::slug($document->lmc_no) . '_' . $stepConfig['field'] . '_' . time() . '.pdf';
        $path = $file->storeAs('document_endorsements', $filename);

        // Update document
        $nameField = $stepConfig['field'] . '_by_name';
        $dateField = $stepConfig['field'] . '_by_date';
        $docField = $stepConfig['field'] . '_document';

        $document->update([
            $nameField => $request->name,
            $dateField => $request->date,
            $docField => $path,
            'updated_by' => Auth::id(),
        ]);

        // Move to next step or complete
        $nextStep = $step + 1;
        if ($nextStep < count($this->workflowSteps)) {
            $document->update(['current_step' => $nextStep]);
        } else {
            $document->update(['status' => 'completed']);
            
            // Notify the creator that the document is completed
            if ($document->created_by) {
                \App\Services\WorkflowNotificationService::createNotification(
                    'document_endorsement',
                    'document_endorsement',
                    $document->id,
                    'completed',
                    'Document Endorsement Completed',
                    "Your document endorsement #{$document->id} has been completed.",
                    $document->created_by
                );
            }
        }

        return redirect()->route('document-endorsement.index')->with('success', 'Document signed and uploaded successfully');
    }

    /**
     * Show document details
     */
    public function show($id)
    {
        $document = DocumentEndorsement::with(['mortalityCase', 'creator', 'updater'])
            ->findOrFail($id);

        return inertia('Mortality/DocumentEndorsementShow', [
            'document' => $document,
            'workflowSteps' => $this->workflowSteps,
            'userRole' => Auth::user()->role,
            'canDownload' => $this->canDownloadDocument(Auth::user(), $document),
            'canUpload' => $this->canUploadDocument(Auth::user(), $document),
        ]);
    }

    /**
     * Check if user can download
     */
    private function canDownload($user)
    {
        if ($user->role === 'admin') {
            return true;
        }
        return isset($this->workflowSteps[array_search($user->role, array_column($this->workflowSteps, 'role'))]);
    }

    /**
     * Check if user can upload
     */
    private function canUpload($user)
    {
        if ($user->role === 'admin') {
            return false; // Admin can only download, not sign
        }
        return isset($this->workflowSteps[array_search($user->role, array_column($this->workflowSteps, 'role'))]);
    }

    /**
     * Check if user can download specific document
     */
    private function canDownloadDocument($user, $document)
    {
        if ($user->role === 'admin') {
            return true;
        }

        // Can download if it's their turn or previous step
        $userStep = array_search($user->role, array_column($this->workflowSteps, 'role'));
        if ($userStep === false) {
            return false;
        }

        return $document->current_step >= $userStep && $document->status !== 'completed';
    }

    /**
     * Check if user can upload to specific document
     */
    private function canUploadDocument($user, $document)
    {
        if ($user->role === 'admin') {
            return false;
        }

        $userStep = array_search($user->role, array_column($this->workflowSteps, 'role'));
        if ($userStep === false) {
            return false;
        }

        return $document->current_step === $userStep && $document->status !== 'completed';
    }

    /**
     * Generate PDF content
     */
    private function generatePDF($document, $previousDocument = null, $user = null)
    {
        $html = view('documents.endorsement', [
            'document' => $document,
            'workflowSteps' => $this->workflowSteps,
            'previousDocument' => $previousDocument,
            'currentUser' => $user,
            'currentDate' => now()->format('d/m/Y'),
        ])->render();

        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'arial',
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
