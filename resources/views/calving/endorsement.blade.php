<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>LCC Form - {{ $stepName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4 portrait; margin: 12mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            line-height: 1.35;
        }

        .page {
            width: 100%;
        }

        .certificate {
            border: 1px solid #222;
            padding: 16px 18px 14px 18px;
            min-height: 265mm;
        }

        .header {
            text-align: center;
        }

        .logo-wrap {
            margin-bottom: 6px;
        }

        .logo-image {
            display: inline-block;
            width: 72px;
            height: auto;
        }

        .company-line {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.25;
        }

        .title-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .title-row td {
            vertical-align: top;
        }

        .title-spacer {
            width: 28%;
        }

        .title-center {
            text-align: center;
            width: 44%;
        }

        .title-ms {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .title-en {
            font-size: 12px;
            font-weight: 700;
        }

        .lcc-cell {
            width: 28%;
            text-align: right;
            font-weight: 700;
            font-size: 13px;
            padding-top: 3px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 14px;
            margin-bottom: 7px;
        }

        .section-title i {
            font-style: italic;
            font-weight: 600;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-table td {
            vertical-align: top;
            padding: 3px 0;
        }

        .num-col {
            width: 18px;
            padding-right: 4px;
        }

        .label-col {
            width: 255px;
            padding-right: 6px;
        }

        .label-col i {
            font-style: italic;
        }

        .line-value {
            border-bottom: 1px solid #333;
            min-height: 18px;
            padding: 1px 4px 2px 4px;
        }

        .line-value.blank {
            color: transparent;
        }

        .options-inline > span {
            display: inline-block;
            min-width: 170px;
            margin-right: 14px;
            margin-bottom: 2px;
            vertical-align: top;
        }

        .checkbox-block {
            padding-top: 2px;
        }

        .checkbox-line {
            margin-bottom: 4px;
        }

        .checkbox-line > span {
            display: inline-block;
            min-width: 165px;
            margin-right: 14px;
            margin-bottom: 2px;
        }

        .checkbox-mark {
            display: inline-block;
            min-width: 11px;
            margin-left: 12px;
            vertical-align: middle;
        }

        .checkbox-symbol {
            display: inline-block;
            width: 11px;
            min-width: 11px;
            height: 11px;
            border: 1px solid #111;
            text-align: center;
            line-height: 10px;
            font-size: 9px;
            font-weight: 700;
            vertical-align: middle;
        }

        .location-fields {
            border-bottom: 1px solid #333;
            min-height: 18px;
            padding: 1px 4px 2px 4px;
        }

        .location-fields .split {
            display: inline-block;
            margin-right: 24px;
        }

        .approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 11px;
        }

        .approval-table td {
            border: 1px solid #333;
            padding: 6px 7px;
            vertical-align: top;
        }

        .signature-row td {
            height: 52px;
        }

        .signature-label {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .signature-line {
            height: 30px;
            border-bottom: 1px solid #333;
        }

        .role-row td {
            height: 34px;
            vertical-align: bottom;
            font-size: 10px;
        }

        .role-meta {
            font-weight: 700;
        }

        .meta-line {
            margin-top: 4px;
            font-size: 10px;
            line-height: 1.3;
        }

        .copy-note {
            margin-top: 12px;
            font-size: 10px;
            line-height: 1.35;
        }

        .form-foot {
            margin-top: 4px;
            font-size: 9px;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/sawit-kinabalu-logo.png');

        $textOrDash = function ($value) {
            return filled($value) ? $value : '-';
        };

        $formatDate = function ($value) {
            return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
        };

        $checked = function ($condition) {
            return $condition
                ? '<span class="checkbox-symbol">v</span>'
                : '<span class="checkbox-symbol"></span>';
        };

        $lccNo = trim((string) ($calvingRecord->lcc_running_number ?? ''));
        if ($lccNo === '') {
            $lccNo = 'LCC-' . ($calvingRecord->id ?? '-');
        }

        $isMale = strtoupper((string) $calvingRecord->sex) === 'MC';
        $isFemale = strtoupper((string) $calvingRecord->sex) === 'FC';

        $normalize = function ($value) {
            $value = strtolower(trim((string) $value));
            $value = preg_replace('/\s+/', ' ', $value ?? '');
            return $value;
        };

        $conditionValue = $normalize($calvingRecord->general_condition ?? '');
        $abnormalValues = ['abnormal', 'not good', 'poor', 'sick', 'critical'];
        $isAbnormal = in_array($conditionValue, $abnormalValues, true);
        $isNormal = !$isAbnormal && $conditionValue !== '';

        $colourOptions = [
            'Kelabu (Grey)',
            'Madu (Honey)',
            'Merah (Red)',
            'Hitam (Black)',
            'Berjalur (Stripe)',
        ];

        $isColourMatch = function ($value, $label) use ($normalize) {
            $valueNormalized = $normalize($value);
            $labelNormalized = $normalize($label);

            if ($valueNormalized === '' || $labelNormalized === '') {
                return false;
            }

            if ($valueNormalized === $labelNormalized) {
                return true;
            }

            // Support labels like "Kelabu (Grey)" matching values like "Grey" or "Kelabu".
            if (preg_match('/^(.+?)\s*\((.+)\)$/', $labelNormalized, $matches)) {
                $primary = $normalize($matches[1] ?? '');
                $secondary = $normalize($matches[2] ?? '');
                if ($valueNormalized === $primary || $valueNormalized === $secondary) {
                    return true;
                }
            }

            $synonyms = [
                'kelabu (grey)' => ['kelabu', 'grey', 'gray'],
                'madu (honey)' => ['madu', 'honey', 'brown', 'tan', 'chestnut'],
                'merah (red)' => ['merah', 'red'],
                'hitam (black)' => ['hitam', 'black'],
                'berjalur (stripe)' => ['berjalur', 'stripe', 'striped', 'brindle', 'spotted'],
            ];

            $candidates = $synonyms[$labelNormalized] ?? [];
            return in_array($valueNormalized, $candidates, true);
        };
    @endphp

    <div class="page">
        <div class="certificate">
            <div class="header">
                <div class="logo-wrap">
                    @if (file_exists($logoPath))
                        <img src="{{ $logoPath }}" alt="Sawit Kinabalu Logo" class="logo-image">
                    @endif
                </div>
                <div class="company-line">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
                <div class="company-line">Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-W)</div>

                <table class="title-row">
                    <tr>
                        <td class="title-spacer"></td>
                        <td class="title-center">
                            <div class="title-ms">SIJIL KELAHIRAN TERNAKAN</div>
                            <div class="title-en">(LIVESTOCK CALVING CERTIFICATE)</div>
                        </td>
                        <td class="lcc-cell">
                            LCC NO : {{ $lccNo }}
                        </td>
                    </tr>
                </table>
            </div>

            <div class="section-title">BUTIRAN ANAK <i>(Case Detail)</i> :</div>
            <table class="detail-table">
                <tr>
                    <td class="num-col">1.</td>
                    <td class="label-col">No. Pengenalan <i>(Identification Tag)</i> :</td>
                    <td><div class="line-value">{{ $textOrDash($calvingRecord->tag_no) }}</div></td>
                </tr>
                <tr>
                    <td class="num-col">2.</td>
                    <td class="label-col">Cattle Number Request Form :</td>
                    <td><div class="line-value">{{ $textOrDash($calvingRecord->cattle_no_request_form) }}</div></td>
                </tr>
                <tr>
                    <td class="num-col">3.</td>
                    <td class="label-col">Tarikh Lahir <i>(Date of Birth)</i> :</td>
                    <td><div class="line-value">{{ $formatDate($calvingRecord->calving_date) }}</div></td>
                </tr>
                <tr>
                    <td class="num-col">4.</td>
                    <td class="label-col">Jantina <i>(Gender)</i> :</td>
                    <td class="options-inline">
                        <span>Male (Jantan)<span class="checkbox-mark">{!! $checked($isMale) !!}</span></span>
                        <span>Female (Betina)<span class="checkbox-mark">{!! $checked($isFemale) !!}</span></span>
                    </td>
                </tr>
                <tr>
                    <td class="num-col">5.</td>
                    <td class="label-col">Warna <i>(Coat Colour)</i> :</td>
                    <td class="checkbox-block">
                        <div class="checkbox-line">
                            <span>{{ $colourOptions[0] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->colour, $colourOptions[0])) !!}</span></span>
                            <span>{{ $colourOptions[1] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->colour, $colourOptions[1])) !!}</span></span>
                            <span>{{ $colourOptions[2] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->colour, $colourOptions[2])) !!}</span></span>
                        </div>
                        <div class="checkbox-line">
                            <span>{{ $colourOptions[3] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->colour, $colourOptions[3])) !!}</span></span>
                            <span>{{ $colourOptions[4] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->colour, $colourOptions[4])) !!}</span></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="num-col">6.</td>
                    <td class="label-col">Kondisi Anak Sapi <i>(Cattle Condition)</i> :</td>
                    <td class="options-inline">
                        <span>Normal<span class="checkbox-mark">{!! $checked($isNormal) !!}</span></span>
                        <span>Abnormal<span class="checkbox-mark">{!! $checked($isAbnormal) !!}</span></span>
                    </td>
                </tr>
            </table>

            <div class="section-title">BUTIRAN INDUK <i>(Breeder's Details)</i> :</div>
            <table class="detail-table">
                <tr>
                    <td class="num-col">1.</td>
                    <td class="label-col">No. Pengenalan Indung <i>(Dam's Identification Tag)</i> :</td>
                    <td><div class="line-value">{{ $textOrDash($calvingRecord->dam_tag_no) }}</div></td>
                </tr>
                <tr>
                    <td class="num-col">2.</td>
                    <td class="label-col">Baka Indung <i>(Dam's Breed)</i> :</td>
                    <td><div class="line-value">Brahman</div></td>
                </tr>
                <tr>
                    <td class="num-col">3.</td>
                    <td class="label-col">Warna <i>(Coat Colour)</i> :</td>
                    <td class="checkbox-block">
                        <div class="checkbox-line">
                            <span>{{ $colourOptions[0] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->dam_colour, $colourOptions[0])) !!}</span></span>
                            <span>{{ $colourOptions[1] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->dam_colour, $colourOptions[1])) !!}</span></span>
                            <span>{{ $colourOptions[2] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->dam_colour, $colourOptions[2])) !!}</span></span>
                        </div>
                        <div class="checkbox-line">
                            <span>{{ $colourOptions[3] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->dam_colour, $colourOptions[3])) !!}</span></span>
                            <span>{{ $colourOptions[4] }}<span class="checkbox-mark">{!! $checked($isColourMatch($calvingRecord->dam_colour, $colourOptions[4])) !!}</span></span>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="section-title">BUTIRAN LAIN <i>(Other Details)</i> :</div>
            <table class="detail-table">
                <tr>
                    <td class="num-col">1.</td>
                    <td class="label-col">Nama Pekerja <i>(Worker's Name)</i> :</td>
                    <td><div class="line-value">{{ $textOrDash($calvingRecord->worker_name) }}</div></td>
                </tr>
                <tr>
                    <td class="num-col">2.</td>
                    <td class="label-col">Kumpulan Ternakan <i>(Herds)</i> :</td>
                    <td><div class="line-value">{{ $textOrDash($calvingRecord->herd) }}</div></td>
                </tr>
                <tr>
                    <td class="num-col">3.</td>
                    <td class="label-col">Lokasi <i>(Location)</i> :</td>
                    <td>
                        <div class="location-fields">
                            <span class="split">Block : {{ $textOrDash($calvingRecord->location_block) }}</span>
                            <span class="split">Phase : {{ $textOrDash($calvingRecord->location_phase) }}</span>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="approval-table">
                <tr class="signature-row">
                    <td>
                        <div class="signature-label">Issued by :</div>
                        <div class="signature-line"></div>
                    </td>
                    <td>
                        <div class="signature-label">Verified by :</div>
                        <div class="signature-line"></div>
                    </td>
                    <td>
                        <div class="signature-label">Checked by :</div>
                        <div class="signature-line"></div>
                    </td>
                    <td>
                        <div class="signature-label">Witness by :</div>
                        <div class="signature-line"></div>
                    </td>
                    <td>
                        <div class="signature-label">Approved by :</div>
                        <div class="signature-line"></div>
                    </td>
                </tr>
                <tr class="role-row">
                    <td>
                        <div class="role-meta">Sr. Assistant Livestock</div>
                        <div class="meta-line">Name : ____________________<br>Date : ____________________</div>
                    </td>
                    <td>
                        <div class="role-meta">Sr. Assistant Security</div>
                        <div class="meta-line">Name : ____________________<br>Date : ____________________</div>
                    </td>
                    <td>
                        <div class="role-meta">Supervisor Livestock</div>
                        <div class="meta-line">Name : ____________________<br>Date : ____________________</div>
                    </td>
                    <td>
                        <div class="role-meta">Estate Management</div>
                        <div class="meta-line">Name : ____________________<br>Date : ____________________</div>
                    </td>
                    <td>
                        <div class="role-meta">Livestock Manager / OC</div>
                        <div class="meta-line">Name : ____________________<br>Date : ____________________</div>
                    </td>
                </tr>
            </table>

            <div class="copy-note">
                <div>Original Copy : SKFP Office</div>
                <div>2nd Copy : SAL</div>
                <div>3rd Copy : Issuing Office</div>
            </div>

        </div>
    </div>
</body>
</html>
