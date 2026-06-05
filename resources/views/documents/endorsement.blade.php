<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PM Examination Report - {{ $document->lmc_no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm;
            margin: 0 auto;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #34554a;
        }
        .header h1 {
            font-size: 18px;
            color: #34554a;
            margin-bottom: 5px;
        }
        .header .sub {
            font-size: 12px;
            color: #666;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            background: #34554a;
            color: white;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 8px;
        }
        .field {
            margin-bottom: 5px;
        }
        .field label {
            font-weight: bold;
            color: #666;
            font-size: 9px;
            text-transform: uppercase;
            display: block;
        }
        .field .value {
            padding: 4px 6px;
            background: #f5f5f5;
            border-radius: 3px;
            font-size: 10px;
            min-height: 20px;
        }
        .endorsement-section {
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .endorsement-title {
            background: #e8e8e8;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 10px;
            border-left: 4px solid #34554a;
        }
        .endorsement-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
        }
        .endorsement-box {
            border: 1px solid #ccc;
            padding: 8px;
            min-height: 80px;
            background: #fafafa;
        }
        .endorsement-box.filled {
            background: #f0f8f0;
            border-color: #34554a;
        }
        .endorsement-box h5 {
            font-size: 8px;
            text-transform: uppercase;
            color: #34554a;
            margin-bottom: 5px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 3px;
        }
        .endorsement-box .name-line {
            font-size: 9px;
            margin-bottom: 3px;
        }
        .endorsement-box .name-line span {
            border-bottom: 1px solid #333;
            display: inline-block;
            min-width: 80px;
        }
        .endorsement-box .date-line {
            font-size: 9px;
        }
        .endorsement-box .date-line span {
            border-bottom: 1px solid #333;
            display: inline-block;
            min-width: 60px;
        }
        .signature-area {
            margin-top: 5px;
            height: 40px;
            display: flex;
            align-items: flex-end;
        }
        .signature-area img {
            max-height: 35px;
            max-width: 100%;
        }
        .signature-area .empty-sig {
            color: #999;
            font-size: 8px;
            font-style: italic;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #999;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #34554a;
            color: white;
            font-size: 9px;
            border-radius: 3px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(200, 200, 200, 0.1);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="watermark">{{ $currentDate }}</div>
    
    <div class="page">
        <div class="header">
            <h1>POST-MORTEM EXAMINATION REPORT</h1>
            <div class="sub">Livestock Mortality Documentation</div>
        </div>

        <!-- Case Information -->
        <div class="section">
            <div class="section-title">CASE INFORMATION</div>
            <div class="grid-4">
                <div class="field">
                    <label>LMC Number</label>
                    <div class="value"><strong>{{ $document->lmc_no }}</strong></div>
                </div>
                <div class="field">
                    <label>Tag Number</label>
                    <div class="value">{{ $document->tag_no }}</div>
                </div>
                <div class="field">
                    <label>Category</label>
                    <div class="value">{{ $document->category }}</div>
                </div>
                <div class="field">
                    <label>Date of Death</label>
                    <div class="value">{{ $document->death_date }}</div>
                </div>
                <div class="field">
                    <label>Location</label>
                    <div class="value">{{ $document->location ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Herd</label>
                    <div class="value">{{ $document->herd ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Preliminary COD</label>
                    <div class="value">{{ $document->preliminary_diagnosis ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Confirmed Cause of Death</label>
                    <div class="value"><strong>{{ $document->confirmed_cause_of_death ?: 'Pending' }}</strong></div>
                </div>
            </div>
            <div class="grid-2" style="margin-top: 10px;">
                <div class="field">
                    <label>Clinical Signs / Symptoms</label>
                    <div class="value">{{ $document->clinical_signs ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Treatment Given</label>
                    <div class="value">{{ $document->treatment ?: 'N/A' }}</div>
                </div>
            </div>
        </div>

        <!-- External Findings -->
        <div class="section">
            <div class="section-title">EXTERNAL FINDINGS</div>
            <div class="grid-4">
                <div class="field">
                    <label>Skin</label>
                    <div class="value">{{ $document->external_skin ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Eyes</label>
                    <div class="value">{{ $document->external_eyes ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Mouth</label>
                    <div class="value">{{ $document->external_mouth ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Nostrils</label>
                    <div class="value">{{ $document->external_nostrils ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Ears</label>
                    <div class="value">{{ $document->external_ears ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Limbs</label>
                    <div class="value">{{ $document->external_limbs ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Anus</label>
                    <div class="value">{{ $document->external_anus ?: 'Not Examined' }}</div>
                </div>
                <div class="field">
                    <label>Genital</label>
                    <div class="value">{{ $document->external_genital ?: 'Not Examined' }}</div>
                </div>
            </div>
            @if($document->external_general)
            <div class="field" style="margin-top: 8px;">
                <label>External Findings Notes</label>
                <div class="value">{{ $document->external_general }}</div>
            </div>
            @endif
        </div>

        <!-- Internal Organ Findings -->
        <div class="section">
            <div class="section-title">INTERNAL ORGAN FINDINGS</div>
            <div class="grid-4">
                <div class="field">
                    <label>Heart</label>
                    <div class="value">{{ $document->heart_findings ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Trachea</label>
                    <div class="value">{{ $document->trachea_findings ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Lung Floating Test</label>
                    <div class="value">{{ $document->lung_floating_test ?: 'N/A' }}</div>
                </div>
                @if($document->lung_floating_test_details)
                <div class="field">
                    <label>Lung Test Details</label>
                    <div class="value">{{ $document->lung_floating_test_details }}</div>
                </div>
                @endif
                <div class="field">
                    <label>Diaphragma Test</label>
                    <div class="value">{{ $document->diaphragma_test ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Kidney</label>
                    <div class="value">{{ $document->kidney_findings ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Urinary Bladder</label>
                    <div class="value">{{ $document->urinary_bladder_findings ?: 'N/A' }}</div>
                </div>
            </div>
        </div>

        <!-- Digestive System Findings -->
        <div class="section">
            <div class="section-title">DIGESTIVE SYSTEM (RUMINANT STOMACH)</div>
            <div class="grid-4">
                <div class="field">
                    <label>Rumen</label>
                    <div class="value">{{ $document->rumen_findings ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Reticulum</label>
                    <div class="value">{{ $document->reticulum_findings ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Omasum</label>
                    <div class="value">{{ $document->omasum_findings ?: 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Abomasum</label>
                    <div class="value">{{ $document->abomasum_findings ?: 'N/A' }}</div>
                </div>
                <div class="field" style="grid-column: span 4;">
                    <label>Small Intestine</label>
                    <div class="value">{{ $document->small_intestine_findings ?: 'N/A' }}</div>
                </div>
            </div>
        </div>

        <!-- Additional Notes -->
        @if($document->additional_notes)
        <div class="section">
            <div class="section-title">ADDITIONAL NOTES / FINAL DIAGNOSIS</div>
            <div class="value">{{ $document->additional_notes }}</div>
        </div>
        @endif

        <!-- Endorsement Section -->
        <div class="endorsement-section">
            <div class="endorsement-title">ENDORSEMENT SECTION</div>
            <div class="endorsement-grid">
                <!-- Issued by - Sr. Assistant Livestock -->
                <div class="endorsement-box {{ $document->issued_document ? 'filled' : '' }}">
                    <h5>1. Issued by<br><small>Sr. Assistant Livestock</small></h5>
                    <div class="name-line">Name: <span>{{ $document->issued_by_name ?: '' }}</span></div>
                    <div class="date-line">Date: <span>{{ $document->issued_by_date ? \Carbon\Carbon::parse($document->issued_by_date)->format('d/m/Y') : '' }}</span></div>
                    <div class="signature-area">
                        @if($document->issued_document)
                            @if(file_exists(storage_path('app/' . $document->issued_document)))
                                <img src="{{ storage_path('app/' . $document->issued_document) }}" alt="Signature">
                            @else
                                <span class="empty-sig">[Document Uploaded]</span>
                            @endif
                        @else
                            <span class="empty-sig">[Awaiting Signature]</span>
                        @endif
                    </div>
                </div>

                <!-- Verified by - Sr. Assistant Security -->
                <div class="endorsement-box {{ $document->verified_document ? 'filled' : '' }}">
                    <h5>2. Verified by<br><small>Sr. Assistant Security</small></h5>
                    <div class="name-line">Name: <span>{{ $document->verified_by_name ?: '' }}</span></div>
                    <div class="date-line">Date: <span>{{ $document->verified_by_date ? \Carbon\Carbon::parse($document->verified_by_date)->format('d/m/Y') : '' }}</span></div>
                    <div class="signature-area">
                        @if($document->verified_document)
                            @if(file_exists(storage_path('app/' . $document->verified_document)))
                                <img src="{{ storage_path('app/' . $document->verified_document) }}" alt="Signature">
                            @else
                                <span class="empty-sig">[Document Uploaded]</span>
                            @endif
                        @else
                            <span class="empty-sig">[Awaiting Signature]</span>
                        @endif
                    </div>
                </div>

                <!-- Checked by - Supervisor Livestock -->
                <div class="endorsement-box {{ $document->checked_document ? 'filled' : '' }}">
                    <h5>3. Checked by<br><small>Supervisor Livestock</small></h5>
                    <div class="name-line">Name: <span>{{ $document->checked_by_name ?: '' }}</span></div>
                    <div class="date-line">Date: <span>{{ $document->checked_by_date ? \Carbon\Carbon::parse($document->checked_by_date)->format('d/m/Y') : '' }}</span></div>
                    <div class="signature-area">
                        @if($document->checked_document)
                            @if(file_exists(storage_path('app/' . $document->checked_document)))
                                <img src="{{ storage_path('app/' . $document->checked_document) }}" alt="Signature">
                            @else
                                <span class="empty-sig">[Document Uploaded]</span>
                            @endif
                        @else
                            <span class="empty-sig">[Awaiting Signature]</span>
                        @endif
                    </div>
                </div>

                <!-- Witnessed by - Estate Management -->
                <div class="endorsement-box {{ $document->witnessed_document ? 'filled' : '' }}">
                    <h5>4. Witnessed by<br><small>Estate Management</small></h5>
                    <div class="name-line">Name: <span>{{ $document->witnessed_by_name ?: '' }}</span></div>
                    <div class="date-line">Date: <span>{{ $document->witnessed_by_date ? \Carbon\Carbon::parse($document->witnessed_by_date)->format('d/m/Y') : '' }}</span></div>
                    <div class="signature-area">
                        @if($document->witnessed_document)
                            @if(file_exists(storage_path('app/' . $document->witnessed_document)))
                                <img src="{{ storage_path('app/' . $document->witnessed_document) }}" alt="Signature">
                            @else
                                <span class="empty-sig">[Document Uploaded]</span>
                            @endif
                        @else
                            <span class="empty-sig">[Awaiting Signature]</span>
                        @endif
                    </div>
                </div>

                <!-- Approved by - Livestock Manager/OIC -->
                <div class="endorsement-box {{ $document->approved_document ? 'filled' : '' }}">
                    <h5>5. Approved by<br><small>Livestock Manager/OIC</small></h5>
                    <div class="name-line">Name: <span>{{ $document->approved_by_name ?: '' }}</span></div>
                    <div class="date-line">Date: <span>{{ $document->approved_by_date ? \Carbon\Carbon::parse($document->approved_by_date)->format('d/m/Y') : '' }}</span></div>
                    <div class="signature-area">
                        @if($document->approved_document)
                            @if(file_exists(storage_path('app/' . $document->approved_document)))
                                <img src="{{ storage_path('app/' . $document->approved_document) }}" alt="Signature">
                            @else
                                <span class="empty-sig">[Document Uploaded]</span>
                            @endif
                        @else
                            <span class="empty-sig">[Awaiting Signature]</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Document ID: {{ $document->id }} | Generated on: {{ $currentDate }} | Status: {{ ucfirst($document->status) }} | Current Step: {{ $document->current_step + 1 }} of 5</p>
            <p>SAWIT KINABALU SDN BHD - Livestock Mortality Documentation System</p>
        </div>
    </div>
</body>
</html>
