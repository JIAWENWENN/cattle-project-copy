<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Cattle Return</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            color: #000;
        }
        .header-table {
            width: 100%;
            border: none;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
            border: none;
        }
        .logo-cell {
            width: 110px;
        }
        .logo-cell img {
            width: 100px;
            height: auto;
        }
        .company-info {
            padding-top: 10px;
        }
        .company-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 4px;
        }
        .company-sub {
            font-size: 10px;
            margin-bottom: 2px;
        }
        .info-row {
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: bold;
            display: table;
            width: 100%;
        }
        .info-row .left {
            display: table-cell;
            text-align: left;
        }
        .info-row .right {
            display: table-cell;
            text-align: right;
            padding-right: 50px;
        }
        .table-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
            text-align: left;
            border: 1px solid #000;
            padding: 2px 5px;
            display: inline-block;
        }
        table.main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.main-table th, table.main-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
            font-size: 7px; /* Very small to fit 42 columns */
        }
        table.main-table th {
            font-weight: bold;
        }
        .signature-section {
            margin-top: 30px;
            display: table;
            width: 100%;
            text-align: center;
        }
        .signature-box {
            display: table-cell;
            width: 25%;
            padding: 5px;
            font-size: 10px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 40px auto 5px auto;
            width: 70%;
        }
        .signature-title {
            font-weight: bold;
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
@endphp

    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="Sawit Kinabalu">
                @endif
            </td>
            <td class="company-info">
                <div class="company-name">SAWIT KINABALU FARM PRODUCT SDN. BHD. (465571-P)</div>
                <div class="company-sub">(Wholly owned by SAWIT KINABALU SDN. BHD. 403109-W)</div>
                <div class="company-sub">Locked Bag No. 28, Apas Road 91000 Tawau, Sabah</div>
            </td>
        </tr>
    </table>

    <div class="info-row">
        <div class="left">WEEKLY CATTLE RETURN FOR THE MONTH OF : &nbsp;&nbsp;&nbsp;{{ strtoupper($monthLabel ?? '') }}</div>
    </div>
    <div class="info-row">
        <div class="left">DATE OF SUBMISSION : &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $submissionLabel ?? '' }}</div>
    </div>

    <div style="border: 1px solid #000; display: block; border-bottom: none; width: 100%;">
        <div style="font-weight: bold; font-size: 10px; padding: 3px 5px;">1. PERFORMANCE STATISTIC</div>
    </div>
    
    <table class="main-table">
        <thead>
            <tr>
                <th rowspan="2">NO.</th>
                <th rowspan="2" style="width: 60px;">HERD</th>
                <th colspan="7">OPENING BALANCE (Head)</th>
                <th colspan="2">CALVING<br>(Head)</th>
                <th colspan="6">MORTALITY (Head)</th>
                <th colspan="6">SALE (Head)</th>
                <th colspan="6">TRANSFER IN (Head)</th>
                <th colspan="6">TRANSFER OUT (Head)</th>
                <th colspan="7">CLOSING BALANCE (Head)</th>
            </tr>
            <tr>
                <!-- OPENING BALANCE -->
                <th>B/B</th><th>B/C</th><th>W/B</th><th>H</th><th>M/C</th><th>F/C</th><th>TOTAL</th>
                <!-- CALVING -->
                <th>M/C</th><th>F/C</th>
                <!-- MORTALITY -->
                <th>B/B</th><th>B/C</th><th>W/B</th><th>H</th><th>M/C</th><th>F/C</th>
                <!-- SALE -->
                <th>B/B</th><th>B/C</th><th>W/B</th><th>H</th><th>M/C</th><th>F/C</th>
                <!-- TRANSFER IN -->
                <th>B/B</th><th>B/C</th><th>W/B</th><th>H</th><th>M/C</th><th>F/C</th>
                <!-- TRANSFER OUT -->
                <th>B/B</th><th>B/C</th><th>W/B</th><th>H</th><th>M/C</th><th>F/C</th>
                <!-- CLOSING BALANCE -->
                <th>B/B</th><th>B/C</th><th>W/B</th><th>H</th><th>M/C</th><th>F/C</th><th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: left; padding-left: 2px;">{{ $row['herd'] }}</td>
                    
                    <!-- OPENING BALANCE -->
                    <td>{{ $row['opening']['B/B'] ?? 0 }}</td>
                    <td>{{ $row['opening']['B/C'] ?? 0 }}</td>
                    <td>{{ $row['opening']['W/B'] ?? 0 }}</td>
                    <td>{{ $row['opening']['H'] ?? 0 }}</td>
                    <td>{{ $row['opening']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['opening']['F/C'] ?? 0 }}</td>
                    <td style="font-weight: bold;">{{ $row['opening']['TOTAL'] ?? 0 }}</td>
                    
                    <!-- CALVING -->
                    <td>{{ $row['calving']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['calving']['F/C'] ?? 0 }}</td>

                    <!-- MORTALITY -->
                    <td>{{ $row['mortality']['B/B'] ?? 0 }}</td>
                    <td>{{ $row['mortality']['B/C'] ?? 0 }}</td>
                    <td>{{ $row['mortality']['W/B'] ?? 0 }}</td>
                    <td>{{ $row['mortality']['H'] ?? 0 }}</td>
                    <td>{{ $row['mortality']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['mortality']['F/C'] ?? 0 }}</td>

                    <!-- SALE -->
                    <td>{{ $row['sale']['B/B'] ?? 0 }}</td>
                    <td>{{ $row['sale']['B/C'] ?? 0 }}</td>
                    <td>{{ $row['sale']['W/B'] ?? 0 }}</td>
                    <td>{{ $row['sale']['H'] ?? 0 }}</td>
                    <td>{{ $row['sale']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['sale']['F/C'] ?? 0 }}</td>

                    <!-- TRANSFER IN -->
                    <td>{{ $row['transfer_in']['B/B'] ?? 0 }}</td>
                    <td>{{ $row['transfer_in']['B/C'] ?? 0 }}</td>
                    <td>{{ $row['transfer_in']['W/B'] ?? 0 }}</td>
                    <td>{{ $row['transfer_in']['H'] ?? 0 }}</td>
                    <td>{{ $row['transfer_in']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['transfer_in']['F/C'] ?? 0 }}</td>

                    <!-- TRANSFER OUT -->
                    <td>{{ $row['transfer_out']['B/B'] ?? 0 }}</td>
                    <td>{{ $row['transfer_out']['B/C'] ?? 0 }}</td>
                    <td>{{ $row['transfer_out']['W/B'] ?? 0 }}</td>
                    <td>{{ $row['transfer_out']['H'] ?? 0 }}</td>
                    <td>{{ $row['transfer_out']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['transfer_out']['F/C'] ?? 0 }}</td>

                    <!-- CLOSING BALANCE -->
                    <td>{{ $row['closing']['B/B'] ?? 0 }}</td>
                    <td>{{ $row['closing']['B/C'] ?? 0 }}</td>
                    <td>{{ $row['closing']['W/B'] ?? 0 }}</td>
                    <td>{{ $row['closing']['H'] ?? 0 }}</td>
                    <td>{{ $row['closing']['M/C'] ?? 0 }}</td>
                    <td>{{ $row['closing']['F/C'] ?? 0 }}</td>
                    <td style="font-weight: bold;">{{ $row['closing']['TOTAL'] ?? 0 }}</td>
                </tr>
            @endforeach
            
            @for ($i = count($rows); $i < 6; $i++)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td></td>
                    @for ($j = 0; $j < 40; $j++)
                        <td></td>
                    @endfor
                </tr>
            @endfor

            <tr>
                <td colspan="2" style="font-weight: bold;">TOTAL</td>
                
                <!-- OPENING BALANCE -->
                <td style="font-weight: bold;">{{ $totals['opening']['B/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['opening']['B/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['opening']['W/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['opening']['H'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['opening']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['opening']['F/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['opening']['TOTAL'] ?? 0 }}</td>

                <!-- CALVING -->
                <td style="font-weight: bold;">{{ $totals['calving']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['calving']['F/C'] ?? 0 }}</td>

                <!-- MORTALITY -->
                <td style="font-weight: bold;">{{ $totals['mortality']['B/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['mortality']['B/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['mortality']['W/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['mortality']['H'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['mortality']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['mortality']['F/C'] ?? 0 }}</td>

                <!-- SALE -->
                <td style="font-weight: bold;">{{ $totals['sale']['B/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['sale']['B/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['sale']['W/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['sale']['H'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['sale']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['sale']['F/C'] ?? 0 }}</td>

                <!-- TRANSFER IN -->
                <td style="font-weight: bold;">{{ $totals['transfer_in']['B/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_in']['B/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_in']['W/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_in']['H'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_in']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_in']['F/C'] ?? 0 }}</td>

                <!-- TRANSFER OUT -->
                <td style="font-weight: bold;">{{ $totals['transfer_out']['B/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_out']['B/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_out']['W/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_out']['H'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_out']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['transfer_out']['F/C'] ?? 0 }}</td>

                <!-- CLOSING BALANCE -->
                <td style="font-weight: bold;">{{ $totals['closing']['B/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['closing']['B/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['closing']['W/B'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['closing']['H'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['closing']['M/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['closing']['F/C'] ?? 0 }}</td>
                <td style="font-weight: bold;">{{ $totals['closing']['TOTAL'] ?? 0 }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div>Prepared by,</div>
            <div class="signature-line"></div>
            <div class="signature-title">Sr. Asst Livestock / Supervisor</div>
        </div>
        <div class="signature-box">
            <div>Verified by,</div>
            <div class="signature-line"></div>
            <div class="signature-title">Estate Assistant Manager</div>
        </div>
        <div class="signature-box">
            <div>Checked by,</div>
            <div class="signature-line"></div>
            <div class="signature-title">Livestock Supervisor</div>
        </div>
        <div class="signature-box">
            <div>Approved by,</div>
            <div class="signature-line"></div>
            <div class="signature-title">Officer-In-Charge</div>
        </div>
    </div>

</body>
</html>
