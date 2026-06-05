<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Operation Master List</title>
<style>
  @page {
    margin: 5mm;
  }

  body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #fff;
    line-height: 1.15;
  }

  .container {
    width: 100%;
    margin: auto;
  }

  .title {
    text-align: center;
    font-weight: bold;
    font-size: 15px;
    margin-bottom: 6px;
  }

  .header-info {
    width: 100%;
    margin-bottom: 5px;
  }

  .header-left {
    float: left;
    width: 70%;
  }

  .header-left div {
    margin-bottom: 3px;
    font-size: 11px;
  }

  .line {
    display: inline-block;
    border-bottom: 1px solid #000;
    min-width: 180px;
    margin-right: 18px;
    padding: 0 4px 1px;
  }

  .month-line {
    min-width: 110px;
  }

  .header-right {
    float: right;
    width: 28%;
    text-align: right;
    font-size: 11px;
    font-weight: bold;
  }

  .doml-num {
    display: inline-block;
    border-bottom: 1px solid #000;
    min-width: 90px;
    margin-left: 5px;
    text-align: center;
    padding: 0 4px 1px;
  }

  .clear {
    clear: both;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    table-layout: fixed;
  }

  th, td {
    border: 1px solid #000;
    padding: 2px 3px;
    text-align: left;
    vertical-align: middle;
    height: 20px;
    line-height: 1.1;
  }

  th {
    text-align: center;
    font-weight: bold;
    background: #fff;
  }

  .kategori-col {
    width: 14.5%;
  }

  .rekod-col {
    width: 7.5%;
    text-align: center;
  }

  .day-col {
    width: 5%;
    text-align: center;
  }

  .lembu-col {
    width: 8.5%;
    text-align: center;
  }

  .catatan-col {
    width: 18.5%;
  }

  .malay {
    font-style: italic;
    font-size: 9px;
    display: block;
  }

  .hari-cell {
    padding: 0;
  }

  .hari-cell div {
    border-bottom: 1px solid #000;
    padding: 2px;
    text-align: center;
    font-size: 9px;
  }

  .hari-cell div:last-child {
    border-bottom: none;
  }

  .number-cell {
    text-align: center;
  }

  .notes-cell {
    font-size: 9px;
    word-wrap: break-word;
  }

  .total-row {
    font-weight: bold;
  }

  .signature-header {
    text-decoration: underline;
    font-weight: bold;
    text-align: left;
  }

  .sig-row td {
    height: 21px;
  }

  .nama-header {
    text-decoration: underline;
    font-weight: bold;
  }

  .category-row td {
    height: 21px;
  }

  .day-heading {
    font-size: 9px;
    line-height: 1.05;
    white-space: normal;
  }

  .small-head {
    height: 14px;
    font-size: 9px;
  }

  .signature-header-row td {
    height: 18px;
  }
</style>
</head>
<body>
@php
    $fixedDayLabels = ['Sab', 'Ahd', 'Isn', 'Sel', 'Rab', 'Kha', 'Jum/Tutup'];
    $visibleDays = array_slice($daysInMonth ?? [], 0, 7);
    $visibleDayCount = count($visibleDays);
    $visibleDays = array_pad($visibleDays, 7, null);
    $dutyPersonNames = array_pad(array_values($dutyPersonNames ?? []), 4, '');

    $rowsByName = collect($categoryRows ?? [])->keyBy('name');
    $categoryNames = [
        'Breeder Bull',
        'Breeder Cow',
        'Weaner Bull',
        'Heifers',
        'Male Calf',
        'Female Calf',
    ];
    $totalRow = collect($categoryRows ?? [])->firstWhere('is_total', true);
@endphp

<div class="container">
  <div class="title">DAILY OPERATION MASTER LIST</div>

  <div class="header-info">
    <div class="header-left">
      <div>
        Ladang : <span class="line">{{ $ladang ?: '' }}</span>
        Kumpulan : <span class="line"></span>
      </div>
      <div>
        Bulan : <span class="line month-line">{{ $monthName }} {{ $year }}</span>
        Minggu : <span class="line month-line">{{ $weekLabel }}</span>
      </div>
    </div>
    <div class="header-right">
      NO. DOML <span class="doml-num">{{ $domlNumber }}</span>
    </div>
    <div class="clear"></div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="kategori-col" rowspan="3">Kategori</th>
        <th class="rekod-col" rowspan="3">Rekod<br>Terdahulu</th>
        <th class="day-col hari-cell" rowspan="3">
          <div>Hari</div>
          <div>Tarikh</div>
          <div>Blok</div>
        </th>
        @foreach($fixedDayLabels as $label)
          <th class="day-col day-heading">{{ $label }}</th>
        @endforeach
        <th class="lembu-col" rowspan="3">Lembu<br>Terlepas</th>
        <th class="catatan-col" rowspan="3">Catatan dengan Rujukan Dokumen</th>
      </tr>
      <tr>
        @foreach($visibleDays as $day)
          <th class="day-col small-head">{{ $day['day'] ?? '' }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach($visibleDays as $day)
          <th class="day-col small-head"></th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($categoryNames as $name)
        @php
          $row = $rowsByName->get($name, []);
          $daily = array_slice($row['daily'] ?? [], 0, $visibleDayCount);
          $daily = array_pad($daily, 7, '');
        @endphp
        <tr class="category-row">
          <td>{{ $row['name'] ?? $name }}<span class="malay">{{ $row['name_my'] ?? '' }}</span></td>
          <td class="number-cell">{{ $row['previous'] ?? '' }}</td>
          <td></td>
          @foreach($daily as $value)
            <td class="number-cell">{{ $value }}</td>
          @endforeach
          <td class="number-cell">{{ $row['strayed'] ?? '' }}</td>
          <td class="notes-cell">{{ $row['notes'] ?? '' }}</td>
        </tr>
      @endforeach

      <tr>
        <td>Strayed Cattle<span class="malay">Lembu Terlepas</span></td>
        <td></td>
        <td></td>
        @for($i = 0; $i < 7; $i++)
          <td></td>
        @endfor
        <td></td>
        <td></td>
      </tr>
      <tr>
        <td>Category<span class="malay">Kategori</span></td>
        <td></td>
        <td></td>
        @for($i = 0; $i < 7; $i++)
          <td></td>
        @endfor
        <td></td>
        <td></td>
      </tr>
      @php
        $totalDaily = array_slice($totalRow['daily'] ?? [], 0, $visibleDayCount);
        $totalDaily = array_pad($totalDaily, 7, '');
      @endphp
      <tr class="total-row">
        <td>Total<span class="malay">Jumlah</span></td>
        <td class="number-cell">{{ $totalRow['previous'] ?? '' }}</td>
        <td></td>
        @foreach($totalDaily as $value)
          <td class="number-cell">{{ $value }}</td>
        @endforeach
        <td class="number-cell">{{ $totalRow['strayed'] ?? '' }}</td>
        <td></td>
      </tr>

      <tr class="signature-header-row">
        <td class="signature-header" colspan="3">Semak &amp; Tandatangan bagi :</td>
        <td colspan="7"></td>
        <td colspan="2" class="nama-header">Nama orang yang bertugas:</td>
      </tr>
      <tr class="sig-row">
        <td colspan="3">Pengembala</td>
        <td colspan="7"></td>
        <td colspan="2">1. {{ $dutyPersonNames[0] ?? '' }}</td>
      </tr>
      <tr class="sig-row">
        <td colspan="3">Pemb Kanan Ternakan</td>
        <td colspan="7"></td>
        <td colspan="2">2. {{ $dutyPersonNames[1] ?? '' }}</td>
      </tr>
      <tr class="sig-row">
        <td colspan="3">Pemb Kanan Keselamatan</td>
        <td colspan="7"></td>
        <td colspan="2">3. {{ $dutyPersonNames[2] ?? '' }}</td>
      </tr>
      <tr class="sig-row">
        <td colspan="3">Wakil Ladang</td>
        <td colspan="7"></td>
        <td colspan="2">4. {{ $dutyPersonNames[3] ?? '' }}</td>
      </tr>
    </tbody>
  </table>
</div>
</body>
</html>
