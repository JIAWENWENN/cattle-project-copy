<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Calving Record - {{ $monthYear }} - {{ $operatingUnit }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            color: #000;
            font-size: 9px;
            line-height: 1.15;
        }
        .page { width: 100%; }
        .header {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }
        .header td { vertical-align: top; }
        .header-left { width: 65%; }
        .company-wrap {
            border-collapse: collapse;
            width: 100%;
        }
        .logo-cell {
            width: 75px;
            vertical-align: top;
            text-align: center;
            padding-right: 6px;
        }
        .logo-cell img {
            width: 62px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .company-cell {
            vertical-align: top;
            padding-top: 3px;
        }
        .company-line-1 {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .company-line-2 { font-size: 9px; }
        .header-right {
            width: 35%;
            text-align: left;
            padding-left: 8px;
        }
        .right-line {
            font-size: 10px;
            margin-bottom: 3px;
            text-align: right;
        }
        .right-line .value { font-weight: 700; }
        .title-box {
            width: 95mm;
            border: 1px solid #000;
            margin: 0 auto 8px auto;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 0;
        }
        table.main {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #000;
            margin-bottom: 8px;
        }
        table.main th,
        table.main td {
            border: 1px solid #000;
            padding: 2px 2px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.main th {
            text-align: center;
            font-size: 8px;
            font-weight: 700;
        }
        table.main td {
            font-size: 8px;
            height: 12px;
        }
        .center { text-align: center; }
    </style>
</head>
<body>
@php
    $rows = $records->take(20)->values();

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

    $formatDate = function ($value) {
        if (!$value) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/y');
        } catch (\Throwable $e) {
            return '';
        }
    };

    $formatSex = function ($value) {
        $v = strtoupper((string) $value);
        if ($v === 'MC') return 'm/c';
        if ($v === 'FC') return 'f/c';
        return $value ?: '';
    };

    $formatLocation = function ($record) {
        $block = trim((string) ($record->location_block ?? ''));
        $phase = trim((string) ($record->location_phase ?? ''));
        if ($block !== '' && $phase !== '') return $block . ' / ' . $phase;
        return $block !== '' ? $block : $phase;
    };
@endphp

<div class="page">
    <table class="header">
        <tr>
            <td class="header-left">
                <table class="company-wrap">
                    <tr>
                        <td class="logo-cell">
                            @if($logoDataUri)
                                <img src="{{ $logoDataUri }}" alt="Sawit Kinabalu">
                            @endif
                        </td>
                        <td class="company-cell">
                            <div class="company-line-1">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
                            <div class="company-line-2">Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="header-right">
                <div class="right-line">Month/Year : <span class="value">{{ $monthYear }}</span></div>
                <div class="right-line">Operating Unit : <span class="value">{{ $operatingUnit }}</span></div>
            </td>
        </tr>
    </table>

    <div class="title-box">Monthly Calving Record</div>

    <table class="main">
        <colgroup>
            <col style="width: 3%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 8%">
            <col style="width: 4%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 7%">
            <col style="width: 6%">
            <col style="width: 7%">
            <col style="width: 5%">
            <col style="width: 7%">
            <col style="width: 8%">
        </colgroup>
        <thead>
        <tr>
            <th>No.</th>
            <th>Date of Birth</th>
            <th>Tag No.</th>
            <th>LCC No.</th>
            <th>Cattle No Request</th>
            <th>Sex</th>
            <th>Calf Colour</th>
            <th>Dam Tag No.</th>
            <th>Dam Colour</th>
            <th>Sire Tag No.</th>
            <th>Sire Colour</th>
            <th>Condition</th>
            <th>Worker</th>
            <th>Herd</th>
            <th>Location (Block/Phase)</th>
            <th>Remarks</th>
        </tr>
        </thead>
        <tbody>
        @for($i = 0; $i < 20; $i++)
            @php $record = $rows[$i] ?? null; @endphp
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td class="center">{{ $record ? $formatDate($record->calving_date) : '' }}</td>
                <td>{{ $record->tag_no ?? '' }}</td>
                <td>{{ $record->lcc_running_number ?? '' }}</td>
                <td>{{ $record->cattle_no_request_form ?? '' }}</td>
                <td class="center">{{ $record ? $formatSex($record->sex) : '' }}</td>
                <td>{{ $record->colour ?? '' }}</td>
                <td>{{ $record->dam_tag_no ?? '' }}</td>
                <td>{{ $record->dam_colour ?? '' }}</td>
                <td>{{ $record->sire_tag_no ?? '' }}</td>
                <td>{{ $record->sire_colour ?? '' }}</td>
                <td class="center">{{ $record->general_condition ?? '' }}</td>
                <td>{{ $record->worker_name ?? '' }}</td>
                <td>{{ $record->herd ?? '' }}</td>
                <td>{{ $record ? $formatLocation($record) : '' }}</td>
                <td>{{ $record->remarks ?? '' }}</td>
            </tr>
        @endfor
        </tbody>
    </table>
</div>
</body>
</html>
