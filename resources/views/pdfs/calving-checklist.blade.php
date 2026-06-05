<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LCC Calving Checklist - {{ $record->lcc_running_number ?: $record->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
            margin: 0 auto;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #34554a;
        }
        .header h1 {
            font-size: 16px;
            color: #34554a;
            margin-bottom: 3px;
        }
        .header .sub {
            font-size: 11px;
            color: #666;
        }
        .company-info {
            font-size: 8px;
            color: #888;
            margin-bottom: 10px;
            text-align: center;
        }
        .form-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #34554a;
            color: white;
            font-size: 9px;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        .section {
            margin-bottom: 12px;
        }
        .section-title {
            background: #34554a;
            color: white;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 6px;
        }
        .field {
            margin-bottom: 4px;
        }
        .field label {
            font-weight: bold;
            color: #666;
            font-size: 8px;
            text-transform: uppercase;
            display: block;
        }
        .field .value {
            padding: 3px 5px;
            background: #f5f5f5;
            border-radius: 3px;
            font-size: 9px;
            min-height: 18px;
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 8px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 9px;
        }
        .checkbox-item .box {
            width: 14px;
            height: 14px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }
        .checkbox-item.checked .box::after {
            content: "✓";
            font-weight: bold;
        }
        .endorsement-section {
            margin-top: 15px;
            page-break-inside: avoid;
        }
        .endorsement-title {
            background: #e8e8e8;
            padding: 6px 8px;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 8px;
            border-left: 4px solid #34554a;
        }
        .endorsement-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
        }
        .endorsement-box {
            border: 1px solid #ccc;
            padding: 6px;
            min-height: 70px;
            background: #fafafa;
            font-size: 8px;
        }
        .endorsement-box.filled {
            background: #f0f8f0;
            border-color: #34554a;
        }
        .endorsement-box h5 {
            font-size: 7px;
            text-transform: uppercase;
            color: #34554a;
            margin-bottom: 4px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 2px;
        }
        .endorsement-box .name-line {
            margin-bottom: 2px;
        }
        .endorsement-box .name-line span {
            border-bottom: 1px solid #333;
            display: inline-block;
            min-width: 60px;
        }
        .endorsement-box .date-line {
            margin-bottom: 2px;
        }
        .endorsement-box .date-line span {
            border-bottom: 1px solid #333;
            display: inline-block;
            min-width: 50px;
        }
        .signature-area {
            margin-top: 4px;
            height: 30px;
            display: flex;
            align-items: flex-end;
        }
        .signature-area img {
            max-height: 28px;
            max-width: 100%;
        }
        .signature-area .empty-sig {
            color: #999;
            font-size: 7px;
            font-style: italic;
        }
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 7px;
            color: #999;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            background: #34554a;
            color: white;
            font-size: 8px;
            border-radius: 3px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 50px;
            color: rgba(200, 200, 200, 0.1);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    @php
    $currentDate = now()->format('d/m/Y');
    $endorsementDocs = $record->endorsement_documents ?? [];
    $workflowSteps = \App\Models\CalvingChecklist::DOC_WORKFLOW_STEPS;
    @endphp

    <div class="watermark">{{ $currentDate }}</div>

    <div class="page">
        <div class="header">
            <h1>LIVESTOCK CALVING CHECKLIST (LCC)</h1>
            <div class="sub">{{ $record->company_name ?? 'SAWIT KINABALU FARM PRODUCTS SDN BHD' }}</div>
        </div>

        <div class="company-info">
            {{ $record->ownership ?? 'Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)' }}<br>
            MCC No.: {{ $record->mcc_no ?? '00023' }} | {{ $record->form_no ?? 'FORM 1B' }}
        </div>

        <div style="text-align: center; margin-bottom: 15px;">
            <span class="form-badge">LCC Running No.: {{ $record->lcc_running_number ?? $record->id }}</span>
        </div>

        <div class="section">
            <div class="section-title">RECORD INFORMATION</div>
            <div class="grid-4">
                <div class="field">
                    <label>Month/Year</label>
                    <div class="value">{{ $record->month_year }}</div>
                </div>
                <div class="field">
                    <label>Operating Unit</label>
                    <div class="value">{{ $record->operating_unit }}</div>
                </div>
                <div class="field">
                    <label>Week</label>
                    <div class="value">{{ $record->week ?? '-' }}</div>
                </div>
                <div class="field">
                    <label>Calving Date</label>
                    <div class="value">{{ $record->calving_date ? \Carbon\Carbon::parse($record->calving_date)->format('d/m/Y') : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">CALF INFORMATION</div>
            <div class="grid-4">
                <div class="field">
                    <label>Tag Number</label>
                    <div class="value"><strong>{{ $record->tag_no }}</strong></div>
                </div>
                <div class="field">
                    <label>Sex</label>
                    <div class="value">{{ $record->sex }}</div>
                </div>
                <div class="field">
                    <label>Colour</label>
                    <div class="value">{{ $record->colour }}</div>
                </div>
                <div class="field">
                    <label>Dam/Bull Tag No.</label>
                    <div class="value">{{ $record->dam_tag_no ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">LOCATION</div>
            <div class="grid-2">
                <div class="field">
                    <label>Block</label>
                    <div class="value">{{ $record->location_block ?: '-' }}</div>
                </div>
                <div class="field">
                    <label>Phase</label>
                    <div class="value">{{ $record->location_phase ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">POST-CALVING CHECKLIST</div>
            <div class="grid-2" style="margin-bottom: 10px;">
                <div class="field">
                    <label>General Condition</label>
                    <div class="value">{{ $record->general_condition ?? 'Good' }}</div>
                </div>
                <div class="field">
                    <label>Tagging Checklist Date</label>
                    <div class="value">{{ $record->tagging_checklist_date ? \Carbon\Carbon::parse($record->tagging_checklist_date)->format('d/m/Y') : '-' }}</div>
                </div>
            </div>
            <div class="field" style="margin-bottom: 6px;">
                <label>Treatment Given</label>
            </div>
            <div class="checkbox-grid">
                <div class="checkbox-item {{ $record->treatment_iodine ? 'checked' : '' }}">
                    <div class="box"></div>
                    <span>Iodine</span>
                </div>
                <div class="checkbox-item {{ $record->treatment_woundsarex ? 'checked' : '' }}">
                    <div class="box"></div>
                    <span>Woundsarex</span>
                </div>
                <div class="checkbox-item {{ $record->colostrum_feeding_24h ? 'checked' : '' }}">
                    <div class="box"></div>
                    <span>Colostrum Feeding (24H)</span>
                </div>
                <div class="checkbox-item {{ $record->maminume ? 'checked' : '' }}">
                    <div class="box"></div>
                    <span>Maminume</span>
                </div>
            </div>
        </div>

        @if($record->remarks)
        <div class="section">
            <div class="section-title">REMARKS</div>
            <div class="value">{{ $record->remarks }}</div>
        </div>
        @endif

        <div class="endorsement-section">
            <div class="endorsement-title">ENDORSEMENT SECTION</div>
            <div class="endorsement-grid">
                @php
                $stepLabels = [
                    0 => '1. Issued by',
                    1 => '2. Verified by',
                    2 => '3. Checked by',
                    3 => '4. Witnessed by',
                    4 => '5. Approved by',
                ];
                $roleLabels = [
                    0 => 'Sr. Assistant Livestock',
                    1 => 'Sr. Assistant Security',
                    2 => 'Supervisor Livestock',
                    3 => 'Penyelia Security',
                    4 => 'Livestock Manager/OIC',
                ];
                $byFields = [
                    0 => 'issued_by',
                    1 => 'verified_by',
                    2 => 'checked_by',
                    3 => 'witnessed_by',
                    4 => 'approved_by',
                ];
                $docFields = [
                    0 => 'issued_document',
                    1 => 'verified_document',
                    2 => 'checked_document',
                    3 => 'witnessed_document',
                    4 => 'approved_document',
                ];
                @endphp

                @for($i = 0; $i < 5; $i++)
                    @php
                    $doc = $endorsementDocs[$i] ?? null;
                    $isFilled = $doc && isset($doc['path']);
                    $byField = $byFields[$i];
                    $name = $record->{$byField . '_name'} ?? '';
                    $date = $record->{$byField . '_date'} ? \Carbon\Carbon::parse($record->{$byField . '_date'})->format('d/m/Y') : '';
                    @endphp
                    <div class="endorsement-box {{ $isFilled ? 'filled' : '' }}">
                        <h5>{{ $stepLabels[$i] }}<br><small>{{ $roleLabels[$i] }}</small></h5>
                        <div class="name-line">Name: <span>{{ $name }}</span></div>
                        <div class="date-line">Date: <span>{{ $date }}</span></div>
                        <div class="signature-area">
                            @if($isFilled)
                                @if(file_exists(storage_path('app/' . $doc['path'])))
                                    <img src="{{ storage_path('app/' . $doc['path']) }}" alt="Signature">
                                @else
                                    <span class="empty-sig">[Document Uploaded]</span>
                                @endif
                            @else
                                <span class="empty-sig">[Awaiting Signature]</span>
                            @endif
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        <div class="footer">
            <p>Document ID: {{ $record->id }} | Generated on: {{ $currentDate }} | Status: {{ ucfirst($record->workflow_status ?? 'pending') }} | Current Step: {{ ($record->endorsement_step ?? 0) + 1 }} of 5</p>
            <p>SAWIT KINABALU FARM PRODUCTS SDN BHD - Livestock Calving Documentation System</p>
        </div>
    </div>
</body>
</html>
