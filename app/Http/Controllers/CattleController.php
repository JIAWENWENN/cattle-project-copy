<?php

namespace App\Http\Controllers;

use App\Models\Cattle;
use App\Models\CalvingRecord;
use App\Models\CattleHealthRecord;
use App\Models\CattleCustomField;
use App\Models\Estate;
use App\Models\PastureBlock;
use App\Models\PasturePhase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use TCPDF;

class CattleController extends Controller
{
    private function getStatusOptions(): array
    {
        return [
            ['id' => 'status-active', 'value' => 'Active'],
            ['id' => 'status-sold', 'value' => 'Sold'],
            ['id' => 'status-deceased', 'value' => 'Deceased'],
            ['id' => 'status-missing', 'value' => 'Missing'],
        ];
    }

    private function isCalvingManagedRecord(Cattle $cattle): bool
    {
        if (!empty($cattle->calving_record_id)) {
            return true;
        }

        $remarks = (string) ($cattle->remarks ?? '');
        return str_starts_with($remarks, 'Auto-created from Calving Record #');
    }

    private function getCalvingLockedFields(): array
    {
        return [
            'tag_no',
            'lcc_running_number',
            'coat_colour',
            'birth_date',
            'gender',
            'general_condition',
            'dam_tag',
            'dam_colour',
            'sire_tag',
            'sire_coat_colour',
        ];
    }

private function getCalvingSyncedValues(Cattle $cattle): array
    {
        $calvingRecord = null;

        if (!empty($cattle->calving_record_id)) {
            $calvingRecord = CalvingRecord::find($cattle->calving_record_id);
        }

        if (!$calvingRecord && !empty($cattle->tag_no)) {
            $calvingRecord = CalvingRecord::where('tag_no', $cattle->tag_no)
                ->orderByDesc('id')
                ->first();
        }

        // Only sync calving data when the record is fully completed
        if (!$calvingRecord || $calvingRecord->status !== 'completed') {
            return [
                'tag_no' => $cattle->tag_no,
                'lcc_running_number' => $cattle->lcc_running_number,
                'category' => $cattle->category,
                'coat_colour' => $cattle->coat_colour,
                'birth_date' => $cattle->birth_date,
                'gender' => $cattle->gender,
                'general_condition' => $cattle->general_condition,
                'dam_tag' => $cattle->dam_tag,
                'dam_colour' => $cattle->dam_colour,
                'sire_tag' => $cattle->sire_tag,
                'sire_coat_colour' => $cattle->sire_coat_colour,
                'location_block' => $cattle->location_block,
                'location_phase' => $cattle->location_phase,
            ];
        }

        return [
            'tag_no' => $calvingRecord->tag_no,
            'lcc_running_number' => $calvingRecord->lcc_running_number,
            'category' => $cattle->category,
            'coat_colour' => $calvingRecord->colour,
            'birth_date' => $calvingRecord->calving_date,
            'gender' => $calvingRecord->sex === 'MC' ? 'Male' : 'Female',
            'general_condition' => $calvingRecord->general_condition,
            'location_block' => $calvingRecord->location_block,
            'location_phase' => $calvingRecord->location_phase,
            'dam_tag' => $calvingRecord->dam_tag_no,
            'dam_colour' => $calvingRecord->dam_colour,
            'sire_tag' => $calvingRecord->sire_tag_no,
            'sire_coat_colour' => $calvingRecord->sire_colour,
            'remarks' => $calvingRecord->remarks,
        ];
    }

    private function getMergedOptionsWithIds(string $fieldType, ?string $cattleColumn = null): array
    {
        $options = collect(CattleCustomField::getOptionsWithIds($fieldType));

        if ($cattleColumn) {
            $existingValues = Cattle::query()
                ->whereNotNull($cattleColumn)
                ->where($cattleColumn, '!=', '')
                ->distinct()
                ->orderBy($cattleColumn)
                ->pluck($cattleColumn)
                ->map(function ($value) {
                    return [
                        'id' => 'db-' . md5($value),
                        'value' => $value,
                    ];
                });

            $options = $options->merge($existingValues);
        }

        return $options
            ->filter(fn ($option) => !empty($option['value']))
            ->unique(fn ($option) => mb_strtolower(trim($option['value'])))
            ->values()
            ->all();
    }

    private function getCustomFieldsPayload(): array
    {
        $pastureBlocks = collect(\DB::table('pasture_blocks')->select('name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
            ->merge(\DB::table('cattle')->select('location_block as name')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('name'))
            ->merge(\DB::table('calving_records')->select('location_block as name')->whereNotNull('location_block')->where('location_block', '!=', '')->distinct()->pluck('name'))
            ->map(fn ($name) => ucwords(strtolower(trim($name))))
            ->filter()
            ->unique()
            ->sort()
            ->map(fn ($name, $idx) => ['id' => 'block-' . $idx, 'value' => $name])
            ->values()
            ->all();

        $pasturePhases = collect(\DB::table('pasture_phases')->select('name as name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
            ->merge(\DB::table('cattle')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
            ->merge(\DB::table('calving_records')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
            ->map(fn ($name) => ucwords(strtolower(trim($name))))
            ->filter()
            ->unique()
            ->sort()
            ->map(fn ($name, $idx) => ['id' => 'phase-' . $idx, 'value' => $name])
            ->values()
            ->all();

        $pasturePhases = collect(\DB::table('pasture_phases')->select('name')->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name'))
            ->merge(\DB::table('cattle')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
            ->merge(\DB::table('calving_records')->select('location_phase as name')->whereNotNull('location_phase')->where('location_phase', '!=', '')->distinct()->pluck('name'))
            ->filter()
            ->unique(fn ($name) => mb_strtolower(trim($name)))
            ->sort()
            ->map(fn ($name, $idx) => ['id' => 'phase-' . $idx, 'value' => $name])
            ->values()
            ->all();

        $coatColours = collect(array_merge(
            CattleCustomField::getOptionsWithIds('coat_colour'),
            CattleCustomField::getOptionsWithIds('calving_colour')
        ))->unique('value')->values()->toArray();

        $damColours = collect(array_merge(
            CattleCustomField::getOptionsWithIds('dam_colour'),
            CattleCustomField::getOptionsWithIds('calving_dam_colour')
        ))->unique('value')->values()->toArray();

$sireColours = collect(array_merge(
            CattleCustomField::getOptionsWithIds('sire_coat_colour'),
            CattleCustomField::getOptionsWithIds('calving_sire_colour')
        ))->unique('value')->values()->toArray();

        $generalConditions = collect(array_merge(
            CattleCustomField::getOptionsWithIds('general_condition'),
            CattleCustomField::getOptionsWithIds('calving_general_condition')
        ))->unique('value')->values()->toArray();

        $categoryOptions = CattleCustomField::where('field_type', 'category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'value', 'field_type'])
            ->toArray();

        return [
            'category' => $categoryOptions,
            'status' => $this->getStatusOptions(),
            'coat_colour' => $coatColours,
            'general_condition' => $generalConditions,
            'location_block' => $pastureBlocks,
            'location_phase' => $pasturePhases,
            'dam_category' => $categoryOptions,
            'dam_colour' => $damColours,
            'sire_category' => $categoryOptions,
            'sire_coat_colour' => $sireColours,
        ];
    }

    public function index()
    {
        $operatingUnits = Estate::where('is_active', true)
            ->with('pastureBlocks.phases')
            ->orderBy('name')
            ->get();

        return Inertia::render('Cattle/Index', [
            'cattles' => Cattle::latest()->get(),
            'customFields' => $this->getCustomFieldsPayload(),
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function create()
    {
        // This often duplicates index logic if the modal is on the index page,
        // but keeping it here as per your structure.
        $operatingUnits = Estate::where('is_active', true)
            ->with('pastureBlocks.phases')
            ->orderBy('name')
            ->get();

        return Inertia::render('Cattle/Index', [
            'cattles' => Cattle::latest()->get(),
            'customFields' => $this->getCustomFieldsPayload(),
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Basic Information
            'tag_no' => 'required|unique:cattle',
            'lcc_running_number' => 'nullable|string|max:50',
            'category' => 'required|string|max:255',
            'coat_colour' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'required|in:Male,Female',

            // Receival & Condition
            'receival_weight' => 'nullable|numeric|min:0',
            'general_condition' => 'nullable|string',
            'operating_unit' => 'nullable|string|max:255',
            'location_block' => 'nullable|string',
            'location_phase' => 'nullable|string',

            // Genealogy (Optional)
            'dam_tag' => 'nullable|string',
            'dam_category' => 'nullable|string|max:255',
            'dam_colour' => 'nullable|string',

            // Sire (Father) Genealogy
            'sire_tag' => 'nullable|string|max:255',
            'sire_category' => 'nullable|string|max:255',
            'sire_coat_colour' => 'nullable|string|max:255',

            // Milestones (Optional)
            'weaning_weight' => 'nullable|numeric|min:0',
            'yearling_weight' => 'nullable|numeric|min:0',

            // Additional
            'status' => 'nullable|in:Active,Sold,Deceased,Missing',
            'remarks' => 'nullable|string',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('cattle-profiles', 'public');
            $validated['profile_picture'] = $path;
        }

        Cattle::create($validated);

        return redirect()->back()->with('success', 'Cattle registered successfully!');
    }

    public function update(Request $request, Cattle $cattle)
    {
        $validated = $request->validate([
            // Basic Information
            'tag_no' => 'required|unique:cattle,tag_no,' . $cattle->id,
            'lcc_running_number' => 'nullable|string|max:50',
            'category' => 'required|string|max:255',
            'coat_colour' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'required|in:Male,Female',

            // Receival & Condition
            'receival_weight' => 'nullable|numeric|min:0',
            'general_condition' => 'nullable|string',
            'operating_unit' => 'nullable|string|max:255',
            'location_block' => 'nullable|string',
            'location_phase' => 'nullable|string',

            // Genealogy (Optional)
            'dam_tag' => 'nullable|string',
            'dam_category' => 'nullable|string|max:255',
            'dam_colour' => 'nullable|string',

            // Sire (Father) Genealogy
            'sire_tag' => 'nullable|string|max:255',
            'sire_category' => 'nullable|string|max:255',
            'sire_coat_colour' => 'nullable|string|max:255',

            // Milestones (Optional)
            'weaning_weight' => 'nullable|numeric|min:0',
            'yearling_weight' => 'nullable|numeric|min:0',

            // Additional
            'status' => 'nullable|in:Active,Sold,Deceased,Missing',
            'remarks' => 'nullable|string',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('cattle-profiles', 'public');
            $validated['profile_picture'] = $path;
        }

        // For LCC/Calving-managed cattle, lock all LCC-origin fields from direct edits in Cattle module.
        if ($this->isCalvingManagedRecord($cattle)) {
            $calvingSyncedValues = $this->getCalvingSyncedValues($cattle);
            foreach ($this->getCalvingLockedFields() as $field) {
                if (array_key_exists($field, $calvingSyncedValues)) {
                    $validated[$field] = $calvingSyncedValues[$field];
                }
            }
        }

        $cattle->update($validated);

        return redirect()->back()->with('success', 'Cattle updated successfully!');
    }

    public function show(Cattle $cattle)
    {
        $cattle->load(['movements.document', 'breedingRecords']);

        $healthRecords = CattleHealthRecord::query()
            ->where('cattle_id', $cattle->id)
            ->where('source_type', 'treatment')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $mortalityRecords = CattleHealthRecord::query()
            ->where('cattle_id', $cattle->id)
            ->where('source_type', 'mortality')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $operatingUnits = Estate::where('is_active', true)
            ->with('pastureBlocks.phases')
            ->orderBy('name')
            ->get();

        return Inertia::render('Cattle/Show', [
            'cattle' => $cattle,
            'healthRecords' => $healthRecords,
            'mortalityRecords' => $mortalityRecords,
            'customFields' => $this->getCustomFieldsPayload(),
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function destroy(Cattle $cattle)
    {
        $cattle->delete();

        return redirect()->back()->with('success', 'Cattle deleted successfully!');
    }

    /**
     * Store a new custom field option.
     */
    public function storeCustomField(Request $request)
    {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'value' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('cattle_custom_fields')
                    ->where('field_type', $request->input('field_type')),
            ],
        ], [
            'value.unique' => 'This option already exists for the selected field type.',
        ]);

        CattleCustomField::create([
            'field_type' => $validated['field_type'],
            'value' => $validated['value'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return redirect()->back()->with('success', 'Option added successfully!');
    }

    /**
     * Update a custom field option.
     */
    public function updateCustomField(Request $request, CattleCustomField $customField)
    {
        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('cattle_custom_fields')
                    ->where('field_type', $customField->field_type)
                    ->ignore($customField->id),
            ],
        ], [
            'value.unique' => 'This option already exists for the selected field type.',
        ]);

        $customField->update(['value' => $validated['value']]);

        return redirect()->back()->with('success', 'Option updated successfully!');
    }

    /**
     * Delete a custom field option.
     */
    public function destroyCustomField(CattleCustomField $customField)
    {
        $customField->delete();

        return redirect()->back()->with('success', 'Option deleted successfully!');
    }

    /**
     * Get all custom field options for a field type.
     */
    public function getCustomFields(string $fieldType)
    {
        $fields = CattleCustomField::where('field_type', $fieldType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'value', 'field_type']);

        return response()->json($fields);
    }

    /**
     * Export cattle data to CSV, Excel, or PDF.
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $search = $request->get('search', '');
        $category = $request->get('category', '');
        $status = $request->get('status', '');

        $query = Cattle::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('tag_no', 'like', "%{$search}%")
                  ->orWhere('location_block', 'like', "%{$search}%")
                  ->orWhere('location_phase', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $cattles = $query->get();

        $filename = 'cattle_report_' . date('Y-m-d_His');

        switch ($format) {
            case 'xlsx':
                return $this->exportToExcel($cattles, $filename);
            case 'pdf':
                return $this->exportToPdf($cattles, $filename);
            case 'csv':
            default:
                return $this->exportToCsv($cattles, $filename);
        }
    }

    /**
     * Export to CSV format.
     */
    private function exportToCsv($cattles, $filename)
    {
        $headers = [
            'Tag No', 'Category', 'Coat Colour', 'Birth Date', 'Gender',
            'Receival Weight', 'General Condition',
            'Location Block', 'Location Phase', 'Dam Tag', 'Dam Category', 'Dam Colour',
            'Sire Tag', 'Sire Category', 'Sire Coat Colour', 'Weaning Weight',
            'Yearling Weight', 'Status', 'Remarks', 'Created At'
        ];

        $rows = [];
        foreach ($cattles as $cattle) {
            $rows[] = [
                $cattle->tag_no,
                $cattle->category,
                $cattle->coat_colour,
                $cattle->birth_date,
                $cattle->gender,
                $cattle->receival_weight,
                $cattle->general_condition,
                $cattle->location_block,
                $cattle->location_phase,
                $cattle->dam_tag,
                $cattle->dam_category,
                $cattle->dam_colour,
                $cattle->sire_tag,
                $cattle->sire_category,
                $cattle->sire_coat_colour,
                $cattle->weaning_weight,
                $cattle->yearling_weight,
                $cattle->status,
                $cattle->remarks,
                $cattle->created_at
            ];
        }

        $callback = function() use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->streamDownload($callback, $filename . '.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"'
        ]);
    }

    /**
     * Export to Excel format.
     */
    private function exportToExcel($cattles, $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'A1' => 'Tag No', 'B1' => 'Category', 'C1' => 'Coat Colour', 'D1' => 'Birth Date',
            'E1' => 'Gender', 'F1' => 'Receival Weight', 'G1' => 'General Condition', 'H1' => 'Location Block',
            'I1' => 'Location Phase', 'J1' => 'Dam Tag', 'K1' => 'Dam Category',
            'L1' => 'Dam Colour', 'M1' => 'Sire Tag', 'N1' => 'Sire Category',
            'O1' => 'Sire Coat Colour', 'P1' => 'Weaning Weight', 'Q1' => 'Yearling Weight',
            'R1' => 'Status', 'S1' => 'Remarks', 'T1' => 'Created At'
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style the header row
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF34554A'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);

        // Add data rows
        $row = 2;
        foreach ($cattles as $cattle) {
            $sheet->setCellValue('A' . $row, $cattle->tag_no);
            $sheet->setCellValue('B' . $row, $cattle->category);
            $sheet->setCellValue('C' . $row, $cattle->coat_colour);
            $sheet->setCellValue('D' . $row, $cattle->birth_date);
            $sheet->setCellValue('E' . $row, $cattle->gender);
            $sheet->setCellValue('F' . $row, $cattle->receival_weight);
            $sheet->setCellValue('G' . $row, $cattle->general_condition);
            $sheet->setCellValue('H' . $row, $cattle->location_block);
            $sheet->setCellValue('I' . $row, $cattle->location_phase);
            $sheet->setCellValue('J' . $row, $cattle->dam_tag);
            $sheet->setCellValue('K' . $row, $cattle->dam_category);
            $sheet->setCellValue('L' . $row, $cattle->dam_colour);
            $sheet->setCellValue('M' . $row, $cattle->sire_tag);
            $sheet->setCellValue('N' . $row, $cattle->sire_category);
            $sheet->setCellValue('O' . $row, $cattle->sire_coat_colour);
            $sheet->setCellValue('P' . $row, $cattle->weaning_weight);
            $sheet->setCellValue('Q' . $row, $cattle->yearling_weight);
            $sheet->setCellValue('R' . $row, $cattle->status);
            $sheet->setCellValue('S' . $row, $cattle->remarks);
            $sheet->setCellValue('T' . $row, $cattle->created_at);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        
        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"'
        ]);
    }

    /**
     * Export to PDF format.
     */
    private function exportToPdf($cattles, $filename)
    {
        $stats = [
            'total' => count($cattles),
            'active' => $cattles->where('status', 'Active')->count(),
            'sold' => $cattles->where('status', 'Sold')->count(),
            'deceased' => $cattles->where('status', 'Deceased')->count(),
            'bulls' => $cattles->whereIn('category', ['BB', 'WB'])->count(),
            'cows' => $cattles->where('category', 'BC')->count(),
            'heifers' => $cattles->where('category', 'H')->count(),
            'calves' => $cattles->whereIn('category', ['MC', 'FC'])->count(),
        ];

        // Create new PDF document - Landscape for more columns
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Sawit Kinabalu Cattle Management');
        $pdf->SetAuthor('Sawit Kinabalu');
        $pdf->SetTitle('Cattle Report - Full Data Export');
        $pdf->SetSubject('Cattle Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        
        // Set margins
        $pdf->SetMargins(10, 35, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(52, 85, 74);
        $pdf->Cell(0, 12, 'Sawit Kinabalu Cattle Management', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'Complete Cattle Report - All Data Export', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        
        // Line
        $pdf->SetDrawColor(52, 85, 74);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY() + 3, 287, $pdf->GetY() + 3);
        
        // Table header - Full data columns
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(52, 85, 74);
        $pdf->SetFillColor(52, 85, 74);
        $pdf->SetTextColor(255, 255, 255);
        
        // Compact headers for fitting all columns
        $headers = [
            'Tag No', 'Category', 'Gender', 'Wt(kg)',
            'Coat Colour', 'Birth', 'Cond.', 'Status',
            'Unit', 'Block', 'Phase', 'General',
            'Dam', 'Sire', 'Wean Wt', 'Yr Wt'
        ];
        // Reduced widths to fit all columns on landscape A4
        $widths = [20, 16, 14, 16, 20, 18, 16, 18, 20, 16, 14, 18, 16, 16, 16, 16];
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Table body
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        
        $fill = false;
        foreach ($cattles as $cattle) {
            $pdf->Cell($widths[0], 5, substr($cattle->tag_no ?: '-', 0, 10), 1, 0, 'L', $fill);
            $pdf->Cell($widths[1], 5, substr($cattle->category ?: '-', 0, 8), 1, 0, 'L', $fill);
            $pdf->Cell($widths[2], 5, substr($cattle->gender ?: '-', 0, 6), 1, 0, 'C', $fill);
            $pdf->Cell($widths[3], 5, $cattle->receival_weight ?: '-', 1, 0, 'C', $fill);
            $pdf->Cell($widths[4], 5, substr($cattle->coat_colour ?: '-', 0, 10), 1, 0, 'L', $fill);
            $pdf->Cell($widths[5], 5, $cattle->birth_date ? date('d/m/y', strtotime($cattle->birth_date)) : '-', 1, 0, 'C', $fill);
            $pdf->Cell($widths[6], 5, substr($cattle->general_condition ?: '-', 0, 6), 1, 0, 'C', $fill);
            
            // Status with color
            if ($cattle->status == 'Active') {
                $pdf->SetFillColor(212, 237, 218);
                $pdf->SetTextColor(21, 87, 36);
            } elseif ($cattle->status == 'Sold') {
                $pdf->SetFillColor(204, 229, 255);
                $pdf->SetTextColor(0, 64, 133);
            } elseif ($cattle->status == 'Deceased') {
                $pdf->SetFillColor(248, 215, 218);
                $pdf->SetTextColor(114, 28, 36);
            } else {
                $pdf->SetFillColor(255, 243, 205);
                $pdf->SetTextColor(133, 100, 4);
            }
            $pdf->Cell($widths[7], 5, substr($cattle->status ?: '-', 0, 8), 1, 0, 'C', true);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->Cell($widths[8], 5, substr($cattle->location_block ?: '-', 0, 10), 1, 0, 'L', $fill);
            $pdf->Cell($widths[9], 5, substr($cattle->location_phase ?: '-', 0, 8), 1, 0, 'L', $fill);
            $pdf->Cell($widths[10], 5, '-', 1, 0, 'L', $fill);
            $pdf->Cell($widths[11], 5, substr($cattle->general_condition ?: '-', 0, 8), 1, 0, 'L', $fill);
            $pdf->Cell($widths[12], 5, substr($cattle->dam_tag ?: '-', 0, 8), 1, 0, 'L', $fill);
            $pdf->Cell($widths[13], 5, substr($cattle->sire_tag ?: '-', 0, 8), 1, 0, 'L', $fill);
            $pdf->Cell($widths[14], 5, $cattle->weaning_weight ?: '-', 1, 0, 'C', $fill);
            $pdf->Cell($widths[15], 5, $cattle->yearling_weight ?: '-', 1, 0, 'C', $fill);
            
            $pdf->Ln();
            $fill = !$fill;
            
            // Page break if needed
            if ($pdf->GetY() > 190) {
                $pdf->AddPage();
                // Repeat header
                $pdf->SetFont('helvetica', 'B', 6);
                $pdf->SetTextColor(52, 85, 74);
                $pdf->SetFillColor(52, 85, 74);
                $pdf->SetTextColor(255, 255, 255);
                
                foreach ($headers as $i => $header) {
                    $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 5);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFillColor(255, 255, 255);
            }
        }
        
        // Footer
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 8, 'Total Records: ' . count($cattles), 0, 1, 'C');
        $pdf->Cell(0, 5, '© ' . date('Y') . ' Sawit Kinabalu Cattle Management System', 0, 1, 'C');
        
        // Output the PDF
        return $pdf->Output($filename . '.pdf', 'D');
    }
}
