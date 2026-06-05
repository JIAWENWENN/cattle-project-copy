<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cattle Transfer Voucher</title>
    <style>
        @page { margin: 14px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        .top-band { height: 8px; background: #666; margin: -18px -18px 8px -18px; }
        .center { text-align: center; }
        .company { font-size: 10px; font-weight: bold; line-height: 1.3; }
        .subtitle { font-size: 9px; }
        .title-wrap { border: 0.8px solid #222; margin-top: 8px; padding: 4px; position: relative; }
        .title { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
        .ctv-no { position: absolute; right: 8px; top: 5px; font-size: 10px; font-weight: bold; }
        .ctv-code { font-size: 13px; letter-spacing: 1px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .meta td { padding: 3px 4px; border: 0.8px solid #222; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .main-table th, .main-table td { border: 0.8px solid #222; padding: 3px 4px; }
        .main-table th { text-align: center; font-size: 9px; }
        .main-table td { height: 16px; font-size: 9px; }
        .text-center { text-align: center; }
        .legend { border: 0.8px solid #222; border-top: none; padding: 4px; font-size: 8.5px; }
        .sign-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .sign-table th, .sign-table td { border: 0.8px solid #222; padding: 3px; vertical-align: top; }
        .sign-head { background: #f3f3f3; text-align: center; font-weight: bold; font-size: 9px; }
        .role { font-size: 8px; color: #333; margin-bottom: 2px; }
        .line { border-bottom: 0.8px solid #777; height: 14px; margin-bottom: 2px; }
        .small { font-size: 8px; }

        .siv-paper { background: #f2f2f2; border: 0.8px solid #111; padding: 10px 10px 6px; }
        .siv-header-space { margin-top: 8px; margin-bottom: 6px; }
        .siv-oval { width: 120px; height: 48px; margin: 0 auto 4px; border-radius: 50%; background: #111; color: #fff; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .siv-oval span { font-weight: bold; font-size: 14px; line-height: 1; letter-spacing: 1px; }
        .siv-oval small { font-size: 8px; letter-spacing: 1px; }
        .siv-title { border: 0.8px solid #111; padding: 4px 6px; margin: 6px 0 8px; text-align: center; position: relative; }
        .siv-title-text { font-size: 12px; font-weight: bold; letter-spacing: 0.6px; }
        .siv-title-mark { position: absolute; right: 18px; top: 4px; width: 16px; height: 16px; border: 1px solid #c40000; border-radius: 50%; }
        .siv-info { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .siv-info td { border: 0.8px solid #111; padding: 4px 6px; vertical-align: top; }
        .siv-dual { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .siv-dual td { border: 0.8px solid #111; width: 50%; vertical-align: top; }
        .siv-section-title { text-align: center; font-weight: bold; text-decoration: underline; font-size: 10px; margin: 4px 0 6px; }
        .siv-row { display: flex; gap: 6px; margin: 6px 8px; font-size: 9px; }
        .siv-row strong { min-width: 90px; }
        .handwritten { font-style: italic; font-weight: 600; }
        .siv-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .siv-table th, .siv-table td { border: 0.8px solid #111; padding: 3px 4px; font-size: 9px; }
        .siv-table th { text-align: center; }
        .siv-table td { height: 16px; }
        .siv-total { text-align: right; padding: 6px 6px 2px; font-size: 10px; font-weight: bold; }
        .siv-signatures { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .siv-signatures td { border: 0.8px solid #111; width: 25%; padding: 6px; vertical-align: top; }
        .siv-sign-title { font-weight: bold; text-align: center; margin-bottom: 6px; }
        .siv-sign-line { border-bottom: 0.8px solid #777; height: 14px; margin: 6px 0; }
        .siv-pen-line { position: absolute; left: 80px; top: 420px; width: 320px; height: 1px; background: #111; transform: rotate(-12deg); }
        .siv-footer { font-size: 8px; margin-top: 6px; }

        .receival-page { font-family: Arial, Helvetica, sans-serif; color: #000; }
        .receival-header { width: 100%; border-collapse: collapse; margin-top: 12px; border: 1.6px solid #000; }
        .receival-header td { border: 0.8px solid #000; padding: 0; vertical-align: middle; }
        .receival-logo-cell { width: 18%; height: 76px; text-align: center; }
        .receival-title-cell { width: 52%; text-align: center; }
        .receival-doc-cell { width: 30%; }
        .receival-logo { height: 44px; display: inline-block; }
        .receival-form-row { height: 26px; font-size: 12px; font-weight: 700; }
        .receival-title-row { height: 38px; font-size: 18px; font-weight: 700; position: relative; }
        .receival-doc-table { width: 100%; height: 76px; border-collapse: collapse; }
        .receival-doc-table td { border: 0.8px solid #000; padding: 3px 5px; font-size: 8.5px; }
        .receival-doc-table td:last-child { text-align: center; font-weight: 600; }
        .receival-meta { width: 100%; border-collapse: collapse; margin: 14px 0 8px; }
        .receival-meta td { width: 50%; padding: 2px 0; font-size: 11px; }
        .receival-meta .label { font-weight: 400; }
        .pen { font-family: "Comic Sans MS", "Bradley Hand ITC", cursive; font-style: italic; font-weight: 700; font-size: 13px; text-transform: uppercase; }
        .receival-table { width: 100%; border-collapse: collapse; border: 1.6px solid #000; }
        .receival-table th, .receival-table td { border: 0.8px solid #000; padding: 2px 3px; font-size: 9px; }
        .receival-table th { font-weight: 700; text-align: center; vertical-align: middle; }
        .receival-table .section-title { height: 22px; font-size: 12px; }
        .receival-table .head-main { height: 20px; }
        .receival-table .head-sub { height: 18px; }
        .receival-table tbody td { height: 24px; vertical-align: middle; }
        .receival-signatures { width: 100%; border-collapse: collapse; border: 1.6px solid #000; margin-top: 16px; }
        .receival-signatures td { width: 33.33%; height: 96px; border: 0.8px solid #000; vertical-align: top; padding: 6px; position: relative; font-size: 9.5px; }
        .receival-sign-title { font-weight: 700; }
        .signature-scribble { text-align: center; margin-top: 14px; font-family: "Comic Sans MS", cursive; font-size: 13px; font-style: italic; }
        .signature-footer { position: absolute; left: 0; right: 0; bottom: 0; height: 32px; border-top: 0.8px solid #000; padding: 4px 6px; font-size: 9px; line-height: 1.45; }
        .stamp { color: #777; font-weight: 700; text-align: center; line-height: 1.25; margin-top: 8px; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/sawit-kinabalu-logo.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    $ctvCode = trim((string) ($customCtvNo ?? ''));
    $livestockRows = $document->livestock ? $document->livestock->values() : collect();
    $roles = $workflowRoles ?? [
        ['title' => 'Issued By (Transferor Estate)', 'role' => 'Position', 'idx' => 0],
        ['title' => 'Approved By (Transferor Estate)', 'role' => 'Livestock Manager/OIC', 'idx' => 1],
        ['title' => 'Transported By (Transferor Estate)', 'role' => 'Driver', 'idx' => 2],
        ['title' => 'Witness By (Transferor Estate)', 'role' => 'Estate Personal', 'idx' => 3],
        ['title' => 'Verified By (Transferor Estate)', 'role' => 'Gate House Security', 'idx' => 4],
        ['title' => 'Witnessed By (Receiving Estate)', 'role' => 'Estate Personal', 'idx' => 5],
        ['title' => 'Received By (Receiving Estate)', 'role' => 'Livestock Supervisor/Assistant', 'idx' => 6],
        ['title' => 'Verified Completion By (Receiving Estate)', 'role' => 'Gate House Security', 'idx' => 7],
    ];
    $isSiv = ($document->type ?? '') === 'SIV';
    $totalCattle = $document->total_cattle ?? $livestockRows->count();
    $totalValue = $document->total_value ?? $livestockRows->sum('value');
    $rawEndorsements = $document->endorsement_documents ?? [];
    if (is_object($rawEndorsements)) {
        $endorsementDocs = json_decode(json_encode($rawEndorsements), true) ?? [];
    } elseif (is_array($rawEndorsements)) {
        $endorsementDocs = $rawEndorsements;
    } else {
        $endorsementDocs = [];
    }
    $formatMoney = function ($value) {
        return is_numeric($value) ? number_format((float) $value, 2) : '';
    };
    $formatNumber = function ($value) {
        if (is_numeric($value)) {
            return number_format((float) $value, 0);
        }
        return $value ?? '';
    };
    $sivSignatureSteps = [
        ['label' => 'Requested By', 'idx' => 0],
        ['label' => 'Verified By', 'idx' => 1],
        ['label' => 'Approved By', 'idx' => 2],
        ['label' => 'Received By', 'idx' => 3],
    ];
    $isReceival = ($document->type ?? '') === 'Receival';
    $receivalSignatureSteps = [
        ['label' => 'Prepared By:', 'idx' => 0],
        ['label' => 'Witness By:', 'idx' => 1],
        ['label' => 'Verified By:', 'idx' => 2],
    ];
@endphp

@if($isReceival)
    <div class="receival-page">
        <table class="receival-header">
            <tr>
                <td rowspan="2" class="receival-logo-cell">
                    @if($logoData)
                        <img src="{{ $logoData }}" alt="Sawit Kinabalu" class="receival-logo">
                    @endif
                </td>
                <td class="receival-title-cell receival-form-row">FORM</td>
                <td rowspan="2" class="receival-doc-cell">
                    <table class="receival-doc-table">
                        <tr><td>Document No.</td><td>{{ $document->form_document_no ?? '' }}</td></tr>
                        <tr><td>Revision No.</td><td>{{ $document->revision_no ?? '' }}</td></tr>
                        <tr><td>Effective Date</td><td>{{ $document->date ? \Illuminate\Support\Carbon::parse($document->date)->format('d F Y') : '' }}</td></tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="receival-title-cell receival-title-row">
                    CATTLE RECEIVAL FORM
                </td>
            </tr>
        </table>

        <table class="receival-meta">
            <tr>
                <td><span class="label">Date Receival:</span> <span class="pen">{{ $document->date ? \Illuminate\Support\Carbon::parse($document->date)->format('d/m/Y') : '' }}</span></td>
                <td><span class="label">Receive From:</span> <span class="pen">{{ $document->from_location ?? '' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Time Receival:</span> <span class="pen">{{ $document->time ? \Illuminate\Support\Carbon::parse($document->time)->format('H:i') : '' }}</span></td>
                <td><span class="label">Vehicle Number:</span> <span class="pen">{{ $document->vehicle_no ?? '' }}</span></td>
            </tr>
        </table>

        <table class="receival-table">
            <thead>
                <tr><th colspan="9" class="section-title">Livestock Particular</th></tr>
                <tr class="head-main">
                    <th rowspan="2" style="width:5%;">No.</th>
                    <th rowspan="2" style="width:17%;">Tag No.</th>
                    <th rowspan="2" style="width:9%;">Category</th>
                    <th rowspan="2" style="width:9%;">Colour</th>
                    <th rowspan="2" style="width:12%;">Receival Weight</th>
                    <th colspan="3" style="width:34%;">General Condition</th>
                    <th rowspan="2" style="width:14%;">Placement Yard</th>
                </tr>
                <tr class="head-sub">
                    <th>Normal (/)</th>
                    <th>Abnormal (/)</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 1; $i <= 20; $i++)
                    @php $row = $livestockRows->get($i - 1); @endphp
                    <tr>
                        <td class="text-center">{{ $i }}</td>
                        <td class="pen">{{ $row->tag_no ?? '' }}</td>
                        <td class="text-center pen">{{ $row->category ?? '' }}</td>
                        <td class="text-center pen">{{ $row->colour ?? '' }}</td>
                        <td class="text-center pen">{{ $row?->weight !== null ? $formatNumber($row->weight) : '' }}</td>
                        <td class="text-center pen">{{ !empty($row?->condition_good) ? '/' : '' }}</td>
                        <td class="text-center pen">{{ !empty($row?->condition_not_good) ? '/' : '' }}</td>
                        <td class="pen">{{ $row->remarks ?? '' }}</td>
                        <td class="pen">{{ $row->yard ?? '' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <table class="receival-signatures">
            <tr>
                @foreach($receivalSignatureSteps as $sigStep)
                    @php
                        $docKey = (string) $sigStep['idx'];
                        $sig = $endorsementDocs[$docKey] ?? $endorsementDocs[$sigStep['idx']] ?? null;
                        $displayName = $sig['name'] ?? '';
                        $displayDate = $sig['date'] ?? '';
                    @endphp
                    <td>
                        <div class="receival-sign-title">{{ $sigStep['label'] }}</div>
                        <div class="signature-scribble">{{ $sig ? $displayName : '' }}</div>
                        <div class="signature-footer">
                            <div>Name: <span class="pen">{{ $displayName }}</span></div>
                            <div>Date: <span class="pen">{{ $displayDate }}</span></div>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
@elseif($isSiv)
    <div class="siv-paper">
        <div class="siv-header-space center">
            @if($logoData)
                <img src="{{ $logoData }}" alt="Sawit Kinabalu" style="height:42px; margin-bottom:4px;">
            @endif
            <div class="company">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
            <div class="subtitle">Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)</div>
        </div>

        <div class="siv-title">
            <div class="siv-title-text">SALES ISSUE VOUCHER</div>
            <div class="siv-title-mark"></div>
        </div>

        <table class="siv-info">
            <tr>
                <td style="width:55%;">
                    <div class="handwritten">DATE &amp; TIME : {{ $document->date ? \Illuminate\Support\Carbon::parse($document->date)->format('d/m/Y') : '-' }}{{ $document->time ? ' ' . \Illuminate\Support\Carbon::parse($document->time)->format('H:i') : '' }}</div>
                    <div class="handwritten" style="margin-top:4px;">TOTAL NO. OF CATTLE : {{ $totalCattle }} {{ $totalCattle ? 'EKOR.' : '' }}</div>
                </td>
                <td style="width:45%;">
                    <div><strong>SIV NO :</strong> <span class="handwritten" style="font-size:12px;">{{ $document->siv_no ?? $document->document_no ?? '' }}</span></div>
                    <div style="margin-top:6px;"><strong>RECEIPT NO. :</strong> <span class="handwritten">{{ $document->receipt_no ?? '' }}</span></div>
                </td>
            </tr>
        </table>

        <table class="siv-dual">
            <tr>
                <td>
                    <div class="siv-section-title">CUSTOMER</div>
                    <div class="siv-row"><strong>NAME :</strong> <span class="handwritten">{{ $document->customer_name ?? '' }}</span></div>
                    <div class="siv-row"><strong>ADDRESS :</strong> <span class="handwritten">{{ $document->address ?? '' }}</span></div>
                    <div class="siv-row"><strong>TEL NO. :</strong> <span class="handwritten">{{ $document->driver_tel ?? '' }}</span></div>
                </td>
                <td>
                    <div class="siv-section-title">TRANSPORTATION</div>
                    <div class="siv-row"><strong>VEHICLE REG NO. :</strong> <span class="handwritten">{{ $document->vehicle_no ?? '' }}</span></div>
                    <div class="siv-row"><strong>NAME OF DRIVER :</strong> <span class="handwritten">{{ $document->driver_name ?? '' }}</span></div>
                    <div class="siv-row"><strong>DRIVER'S IC NO. :</strong> <span class="handwritten">{{ $document->driver_ic ?? '' }}</span></div>
                </td>
            </tr>
        </table>

        <div style="position: relative;">
            <table class="siv-table">
                <thead>
                <tr>
                    <th style="width:4%;">NO.</th>
                    <th style="width:16%;">CATTLE ID</th>
                    <th style="width:10%;">CATEGORY</th>
                    <th style="width:10%;">COLOUR</th>
                    <th style="width:9%;">WEIGHT (KG)</th>
                    <th style="width:10%;">UNIT COST (RM)</th>
                    <th style="width:10%;">VALUE (RM)</th>
                    <th>REMARKS</th>
                </tr>
                </thead>
                <tbody>
                @for($i = 1; $i <= 20; $i++)
                    @php $row = $livestockRows->get($i - 1); @endphp
                    <tr>
                        <td class="text-center">{{ $i }}</td>
                        <td class="handwritten">{{ $row->tag_no ?? '' }}</td>
                        <td class="text-center handwritten">{{ $row->category ?? '' }}</td>
                        <td class="text-center handwritten">{{ $row->colour ?? '' }}</td>
                        <td class="text-center handwritten">{{ $row?->weight !== null ? $formatNumber($row->weight) : '' }}</td>
                        <td class="text-center handwritten">{{ $row?->unit_cost !== null ? $formatMoney($row->unit_cost) : '' }}</td>
                        <td class="text-center handwritten">{{ $row?->value !== null ? $formatMoney($row->value) : '' }}</td>
                        <td class="handwritten">{{ $row->remarks ?? '' }}</td>
                    </tr>
                @endfor
                </tbody>
            </table>
            <div class="siv-pen-line"></div>
        </div>

        <div class="siv-total">
            {{ $totalValue ? number_format((float) $totalValue, 0) : '' }}
        </div>

        <table class="siv-signatures">
            <tr>
                @foreach($sivSignatureSteps as $step)
                    @php
                        $docKey = (string) $step['idx'];
                        $sig = $endorsementDocs[$docKey] ?? $endorsementDocs[$step['idx']] ?? null;
                    @endphp
                    <td>
                        <div class="siv-sign-title">{{ $step['label'] }}</div>
                        <div class="small">Name:</div>
                        <div class="siv-sign-line"></div>
                        <div class="handwritten">{{ $sig['name'] ?? '' }}</div>
                        <div class="small" style="margin-top:4px;">Date:</div>
                        <div class="siv-sign-line"></div>
                        <div class="handwritten">{{ $sig['date'] ?? '' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>

        <div class="siv-footer">
            <strong>Original Copy</strong> : Buyer &nbsp;&nbsp; <strong>2nd Copy</strong> : SKFP Office, Sg. Bolong &nbsp;&nbsp; <strong>3rd Copy</strong> : Security Gate &nbsp;&nbsp; <strong>4th Copy</strong> : Issuing Office
        </div>
    </div>
@else
    <div class="top-band"></div>

    <div class="center">
        @if($logoData)
            <img src="{{ $logoData }}" alt="Sawit Kinabalu" style="height:42px; margin-bottom:4px;">
        @endif
        <div class="company">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
        <div class="subtitle">Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)</div>
    </div>

    <div class="title-wrap center">
        <div class="title">CATTLE TRANSFER VOUCHER</div>
        <div class="ctv-no">CTV NO : <span class="ctv-code">{{ $ctvCode }}</span></div>
    </div>

    <table class="meta">
        <tr>
            <td style="width:50%;"><strong>Transfer OU &amp; Herd :</strong> {{ $document->from_location ?? '-' }}</td>
            <td><strong>Receiving OU &amp; Herd :</strong> {{ $document->to_location ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Date &amp; Time of Transport :</strong> {{ optional($document->date)->format('d/m/Y') }} ({{ $document->time ? \Illuminate\Support\Carbon::parse($document->time)->format('h:i a') : '-' }})</td>
            <td><strong>Vehicle No. :</strong> {{ $document->vehicle_no ?? '-' }}</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
        <tr>
            <th style="width:4%;">No.</th>
            <th style="width:17%;">Tag No.</th>
            <th style="width:9%;">Category</th>
            <th style="width:9%;">Colour</th>
            <th style="width:10%;">Weight (KGS)</th>
            <th style="width:8%;">Good (/)</th>
            <th style="width:10%;">Not Good (/)</th>
            <th style="width:13%;">Remarks</th>
            <th>Transfer Purpose</th>
        </tr>
        </thead>
        <tbody>
        @for($i = 1; $i <= 20; $i++)
            @php $row = $livestockRows->get($i - 1); @endphp
            <tr>
                <td class="text-center">{{ $i }}</td>
                <td>{{ $row->tag_no ?? '' }}</td>
                <td class="text-center">{{ $row->category ?? '' }}</td>
                <td class="text-center">{{ $row->colour ?? '' }}</td>
                <td class="text-center">{{ $row->weight ?? '' }}</td>
                <td class="text-center">{{ !empty($row?->condition_good) ? '✓' : '' }}</td>
                <td class="text-center">{{ !empty($row?->condition_not_good) ? '✓' : '' }}</td>
                <td>{{ $row->remarks ?? '' }}</td>
                <td>{{ $row->purpose ?? '' }}</td>
            </tr>
        @endfor
        </tbody>
    </table>

    <div class="legend">
        <strong>Legend (Transfer Purpose) :</strong>
        a) Green Lot &nbsp; b) Capitalisation &nbsp; c) Recondition &nbsp; d) Weaning &nbsp; e) Normal Transfer &nbsp; f) Others (state)
    </div>

    <table class="sign-table">
        <tr>
            <th class="sign-head" colspan="5">Transferor Estate</th>
        </tr>
        <tr>
            @for($i = 0; $i <= 4; $i++)
                <td style="width:20%;">
                    <div><strong>{{ $roles[$i]['title'] }} :</strong></div>
                    <div class="role">{{ $roles[$i]['role'] }}</div>
                    <div class="small">Name :</div>
                    <div class="line"></div>
                    <div class="small">Date :</div>
                    <div class="line"></div>
                </td>
            @endfor
        </tr>
    </table>

    <table class="sign-table" style="margin-top:-1px;">
        <tr>
            <th class="sign-head" colspan="3">Receiving Estate</th>
        </tr>
        <tr>
            @for($i = 5; $i <= 7; $i++)
                <td style="width:33.33%;">
                    <div><strong>{{ $roles[$i]['title'] }} :</strong></div>
                    <div class="role">{{ $roles[$i]['role'] }}</div>
                    <div class="small">Name :</div>
                    <div class="line"></div>
                    <div class="small">Date :</div>
                    <div class="line"></div>
                </td>
            @endfor
        </tr>
    </table>

    <div class="small" style="margin-top:6px;">
        <strong>Original Copy</strong> : SKFP Office &nbsp;&nbsp; <strong>2nd Copy</strong> : SAL &nbsp;&nbsp; <strong>3rd Copy</strong> : Issuing Office
    </div>
@endif
</body>
</html>
