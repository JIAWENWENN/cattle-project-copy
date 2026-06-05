<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LIVESTOCK MORTALITY CERTIFICATE (LMC) - {{ $case->lmc_no ?? $case->id }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            color: #000;
            font-size: 11px;
            line-height: 1.2;
        }
        .page {
            width: 100%;
            padding: 10px;
        }
        .center {
            text-align: center;
        }
        .company-logo {
            text-align: center;
            margin-bottom: 5px;
        }
        .company-logo img {
            width: 100px;
            height: auto;
        }
        .company-name {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .company-sub {
            text-align: center;
            font-size: 11px;
            margin-bottom: 10px;
        }
        .divider {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
        }
        .title-box {
            border: 1px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            padding: 4px 0;
            margin-bottom: 10px;
            background-color: #f8f8f8;
        }
        table.layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.layout-table td {
            vertical-align: top;
        }
        .header-info {
            width: 100%;
            margin-bottom: 15px;
        }
        .header-info td {
            font-size: 12px;
        }
        .lmc-no {
            font-size: 16px;
            font-weight: bold;
        }
        
        /* Particulars section */
        .particulars {
            line-height: 1.5;
        }
        .particulars strong {
            display: inline-block;
            margin-bottom: 2px;
        }
        
        /* Checkbox styling */
        .checkbox-item {
            display: inline-block;
            margin-right: 5px;
        }
        .box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            margin-right: 2px;
            vertical-align: middle;
        }
        .box.checked {
            background-color: #000;
        }
        
        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 10px;
        }
        table.data-table th,
        table.data-table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            font-size: 10px;
        }
        table.data-table th {
            font-weight: bold;
            background-color: #f8f8f8;
        }
        .section-title {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 2px;
            margin-top: 10px;
        }
        
        .value-cell {
            height: 40px;
            vertical-align: middle;
        }
        
        /* Endorsement */
        .endorsement {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #000;
        }
        .endorsement td.sig-box {
            border: 1px solid #000;
            vertical-align: top;
            padding: 6px;
            height: 110px;
        }
        .sig-label {
            font-size: 10px;
            font-weight: bold;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            margin-top: 40px;
            margin-bottom: 4px;
        }
        .sig-role {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .sig-field {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .border-bottom {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 100px;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/sawit-kinabalu-logo.png');
    $logoDataUri = null;
    if (file_exists($logoPath)) {
        try {
            $logoBinary = file_get_contents($logoPath);
            if ($logoBinary !== false) {
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($logoBinary);
            }
        } catch (\Throwable $e) {
            $logoDataUri = null;
        }
    }
    
    $pm = $case->postmortemExamination;
    
    $formatDate = function ($value) {
        if (!$value) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('d/M/y');
        } catch (\Throwable $e) {
            return '';
        }
    };
    
    $catRaw = strtoupper(trim((string) ($case->category ?? $case->cattle->category ?? '')));
    $catNormalized = str_replace([' ', '-'], '', $catRaw);
    $catIs = function (array $aliases) use ($catNormalized) {
        foreach ($aliases as $alias) {
            $aliasNormalized = str_replace([' ', '-'], '', strtoupper($alias));
            if ($catNormalized === $aliasNormalized) {
                return true;
            }
        }
        return false;
    };
    $coatColour = $case->coat_colour ?? $case->cattle->coat_colour ?? $case->cattle->colour ?? '';
@endphp

<div class="page">
    <div class="company-logo">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Sawit Kinabalu">
        @endif
    </div>
    <div class="company-name">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
    <div class="company-sub">Wholly owned by Sawit Kinabalu Sdn. Bhd. - (Co. No. 403109-W)</div>
    
    <div class="divider"></div>
    
    <div class="title-box">LIVESTOCK MORTALITY CERTIFICATE (LMC)</div>
    
    <table class="layout-table header-info">
        <tr>
            <td style="width: 60%;">
                Date & Time : <span class="border-bottom" style="min-width: 150px;">{{ $case->death_date ? \Carbon\Carbon::parse($case->death_date)->format('d / M / y') : '' }} {{ $case->time_of_death ? \Carbon\Carbon::parse($case->time_of_death)->format('H:i') : '' }}</span>
            </td>
            <td style="width: 40%; text-align: right;">
                <span style="font-weight: bold;">LMC No:</span> <span class="lmc-no">{{ $case->lmc_no ?? $case->id }}</span>
            </td>
        </tr>
    </table>
    
    <table class="layout-table">
        <tr>
            <td style="width: 45%; padding-right: 15px;" class="particulars">
                <strong>Livestock Particulars:</strong>
                <table style="width: 100%; border: none; border-collapse: collapse; margin-top: 3px; font-size: 11px;">
                    <tr>
                        <td style="width: 75px; border: none; padding: 4px 0; vertical-align: bottom;">Tag No:</td>
                        <td style="border: none; border-bottom: 1px solid #000; padding: 4px 0; vertical-align: bottom;">{{ $case->cattle->tag_no ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 4px 0; vertical-align: bottom;">Colour/Coat:</td>
                        <td style="border: none; border-bottom: 1px solid #000; padding: 4px 0; vertical-align: bottom;">{{ $coatColour }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 8px 0 2px 0; vertical-align: bottom;">Category:</td>
                        <td style="border: none; padding: 8px 0 2px 0; white-space: nowrap; vertical-align: bottom;">
                            <span class="checkbox-item"><span class="box {{ $catIs(['B/B', 'BB']) ? 'checked' : '' }}"></span>B/B</span>
                            <span class="checkbox-item"><span class="box {{ $catIs(['B/C', 'BC']) ? 'checked' : '' }}"></span>B/C</span>
                            <span class="checkbox-item"><span class="box {{ $catIs(['W/B', 'WB']) ? 'checked' : '' }}"></span>W/B</span>
                            <span class="checkbox-item"><span class="box {{ $catIs(['H']) ? 'checked' : '' }}"></span>H</span>
                            <span class="checkbox-item"><span class="box {{ $catIs(['M/C', 'MC']) ? 'checked' : '' }}"></span>M/C</span>
                            <span class="checkbox-item"><span class="box {{ $catIs(['F/C', 'FC']) ? 'checked' : '' }}"></span>F/C</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 8px 0 2px 0; vertical-align: bottom;">BCS:</td>
                        <td style="border: none; border-bottom: 1px solid #000; padding: 8px 0 2px 0; vertical-align: bottom;"></td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 4px 0; vertical-align: bottom;">Herd:</td>
                        <td style="border: none; border-bottom: 1px solid #000; padding: 4px 0; vertical-align: bottom;"></td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 4px 0; vertical-align: bottom;">Location:</td>
                        <td style="border: none; border-bottom: 1px solid #000; padding: 4px 0; vertical-align: bottom;">{{ $case->location ?? $case->cattle->location_block ?? '' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 55%;">
                <strong>Case History:</strong>
                <table class="data-table" style="margin-top: 5px;">
                    <tr>
                        <th style="width: 40%">Causes Of Death</th>
                        <th style="width: 30%">Treatment</th>
                        <th style="width: 30%">Additional Info</th>
                    </tr>
                    <tr>
                        <td style="height: 60px; vertical-align: middle;">{{ $case->cause_of_death ?? '' }}</td>
                        <td style="vertical-align: middle;">{{ $case->treatment ?? '' }}</td>
                        <td style="vertical-align: middle;">{{ $case->initial_notes ?? '' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <div class="title-box" style="margin-top: 15px;">POST MORTEM</div>
    
    <div class="section-title">External Findings</div>
    <table class="data-table">
        <tr>
            <th style="width: 12.5%">Skins</th>
            <th style="width: 12.5%">Eyes</th>
            <th style="width: 12.5%">Mouth</th>
            <th style="width: 12.5%">Nostrils</th>
            <th style="width: 12.5%">Ear</th>
            <th style="width: 12.5%">Limbs</th>
            <th style="width: 12.5%">Anus</th>
            <th style="width: 12.5%">Genital</th>
        </tr>
        <tr>
            <td class="value-cell">{{ $pm?->external_skin ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_eyes ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_mouth ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_nostrils ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_ears ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_limbs ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_anus ?? '' }}</td>
            <td class="value-cell">{{ $pm?->external_genital ?? '' }}</td>
        </tr>
    </table>
    
    <div class="section-title">Internal Findings</div>
    <table class="data-table">
        <tr>
            <th style="width: 12.5%">Subcutaneous</th>
            <th style="width: 12.5%">Heart</th>
            <th style="width: 12.5%">Trachea</th>
            <th style="width: 12.5%">Lung<br><span style="font-size:8px;font-weight:normal;">Floating Test: +/-</span></th>
            <th style="width: 12.5%">Diaphragma<br><span style="font-size:8px;font-weight:normal;">Test: +/-</span></th>
            <th style="width: 12.5%">Kidney</th>
            <th style="width: 12.5%">Reproductive Organ</th>
            <th style="width: 12.5%">Joint</th>
        </tr>
        <tr>
            <td class="value-cell">{{ $pm?->subcutaneous_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->heart_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->trachea_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->lung_floating_test ?? '' }}</td>
            <td class="value-cell">{{ $pm?->diaphragma_test ?? '' }}</td>
            <td class="value-cell">{{ $pm?->kidney_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->reproductive_organ_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->joint_findings ?? '' }}</td>
        </tr>
    </table>
    
    <table class="data-table">
        <tr>
            <th colspan="4" style="width: 50%">Stomach</th>
            <th colspan="2" style="width: 25%">Intestine</th>
            <th rowspan="2" style="width: 8.33%">Bladder</th>
            <th rowspan="2" style="width: 8.33%">Liver</th>
            <th rowspan="2" style="width: 8.33%">Spleen</th>
        </tr>
        <tr>
            <th style="width: 12.5%">Rumen</th>
            <th style="width: 12.5%">Reticulum</th>
            <th style="width: 12.5%">Omasum</th>
            <th style="width: 12.5%">Abomasum</th>
            <th style="width: 12.5%">Small</th>
            <th style="width: 12.5%">Colon</th>
        </tr>
        <tr>
            <td class="value-cell">{{ $pm?->rumen_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->reticulum_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->omasum_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->abomasum_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->small_intestine_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->colon_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->urinary_bladder_findings ?? $pm?->bladder_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->liver_findings ?? '' }}</td>
            <td class="value-cell">{{ $pm?->spleen_findings ?? '' }}</td>
        </tr>
    </table>
    
    <div class="title-box" style="margin-top: 15px;">ENDORSEMENT</div>
    
    <table class="endorsement">
        <tr>
            <td class="sig-box" style="width: 20%;">
                <div class="sig-label">Issued by:</div>
                <div class="sig-line"></div>
                <div class="sig-role">Sr. Assistant Livestock</div>
                <div class="sig-field">Name:</div>
                <div class="sig-field">Date:</div>
            </td>
            <td class="sig-box" style="width: 20%;">
                <div class="sig-label">Verified by:</div>
                <div class="sig-line"></div>
                <div class="sig-role">Sr. Assistant Security</div>
                <div class="sig-field">Name:</div>
                <div class="sig-field">Date:</div>
            </td>
            <td class="sig-box" style="width: 20%;">
                <div class="sig-label">Checked by:</div>
                <div class="sig-line"></div>
                <div class="sig-role">Supervisor Livestock</div>
                <div class="sig-field">Name:</div>
                <div class="sig-field">Date:</div>
            </td>
            <td class="sig-box" style="width: 20%;">
                <div class="sig-label">Witness by:</div>
                <div class="sig-line"></div>
                <div class="sig-role">Estate Management</div>
                <div class="sig-field">Name:</div>
                <div class="sig-field">Date:</div>
            </td>
            <td class="sig-box" style="width: 20%;">
                <div class="sig-label">Approved by:</div>
                <div class="sig-line"></div>
                <div class="sig-role">Livestock Manager / OIC</div>
                <div class="sig-field">Name:</div>
                <div class="sig-field">Date:</div>
            </td>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #000; padding: 6px; font-size: 10px;">
                Original Copy : SKFP Office<br>
                2nd Copy : SAL<br>
                3rd Copy : Issuing Office
            </td>
        </tr>
    </table>
    
</div>
</body>
</html>
