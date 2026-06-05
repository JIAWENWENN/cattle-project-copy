<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cattle Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #34554a;
        }
        
        .header h1 {
            font-size: 24px;
            color: #34554a;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            font-size: 12px;
            color: #666;
        }
        
        .header .date {
            font-size: 10px;
            color: #999;
            margin-top: 5px;
        }
        
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            gap: 15px;
        }
        
        .stat-card {
            flex: 1;
            background: linear-gradient(135deg, #34554a 0%, #2a443a 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        
        .stat-card .value {
            font-size: 22px;
            font-weight: bold;
            margin-top: 3px;
        }
        
        .table-container {
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        
        thead {
            background-color: #34554a;
            color: white;
        }
        
        th {
            padding: 10px 6px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 7px;
            letter-spacing: 0.3px;
        }
        
        td {
            padding: 8px 6px;
            border-bottom: 1px solid #eee;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tbody tr:hover {
            background-color: #f0f7f5;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: 600;
        }
        
        .status-sold {
            background-color: #cce5ff;
            color: #004085;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: 600;
        }
        
        .status-deceased {
            background-color: #f8d7da;
            color: #721c24;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: 600;
        }
        
        .status-sick {
            background-color: #fff3cd;
            color: #856404;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: 600;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #999;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .summary-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .summary-section h3 {
            font-size: 12px;
            color: #34554a;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        
        .summary-item {
            text-align: center;
            padding: 8px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        
        .summary-item .label {
            font-size: 8px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .summary-item .value {
            font-size: 16px;
            font-weight: bold;
            color: #34554a;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🐄 Sawit Kinabalu Cattle Management</h1>
        <p class="subtitle">Cattle Report</p>
        <p class="date">Generated on: {{ $date }}</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="label">Total Cattle</div>
            <div class="value">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
            <div class="label">Active</div>
            <div class="value">{{ $stats['active'] }}</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
            <div class="label">Sold</div>
            <div class="value">{{ $stats['sold'] }}</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);">
            <div class="label">Deceased</div>
            <div class="value">{{ $stats['deceased'] }}</div>
        </div>
    </div>

    <div class="summary-section">
        <h3>📊 Cattle Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">Bulls</div>
                <div class="value">{{ $cattles->whereIn('category', ['BB', 'WB'])->count() }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Cows</div>
                <div class="value">{{ $cattles->where('category', 'BC')->count() }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Heifers</div>
                <div class="value">{{ $cattles->where('category', 'H')->count() }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Calves</div>
                <div class="value">{{ $cattles->whereIn('category', ['MC', 'FC'])->count() }}</div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">Tag No</th>
                    <th style="width: 8%;">Category</th>
                    <th style="width: 6%;">Gender</th>
                    <th style="width: 8%;">Weight</th>
                    <th style="width: 10%;">Location</th>
                    <th style="width: 10%;">Yard</th>
                    <th style="width: 8%;">Block</th>
                    <th style="width: 8%;">Condition</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 8%;">Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cattles as $cattle)
                <tr>
                    <td><strong>{{ $cattle->tag_no }}</strong></td>
                    <td>{{ $cattle->category }}</td>
                    <td>{{ $cattle->gender }}</td>
                    <td>{{ $cattle->receival_weight ? $cattle->receival_weight . ' kg' : '-' }}</td>
                    <td>{{ $cattle->placement_yard ?: '-' }}</td>
                    <td>{{ $cattle->location_block ?: '-' }}</td>
                    <td>{{ $cattle->location_phase ?: '-' }}</td>
                    <td>
                        @if($cattle->general_condition == 'Excellent')
                            <span style="color: #155724;">{{ $cattle->general_condition }}</span>
                        @elseif($cattle->general_condition == 'Good')
                            <span style="color: #004085;">{{ $cattle->general_condition }}</span>
                        @elseif($cattle->general_condition == 'Fair')
                            <span style="color: #856404;">{{ $cattle->general_condition }}</span>
                        @elseif($cattle->general_condition == 'Poor')
                            <span style="color: #721c24;">{{ $cattle->general_condition }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($cattle->status == 'Active')
                            <span class="status-active">{{ $cattle->status }}</span>
                        @elseif($cattle->status == 'Sold')
                            <span class="status-sold">{{ $cattle->status }}</span>
                        @elseif($cattle->status == 'Deceased')
                            <span class="status-deceased">{{ $cattle->status }}</span>
                        @else
                            <span class="status-sick">{{ $cattle->status }}</span>
                        @endif
                    </td>
                    <td>{{ $cattle->created_at->format('Y-m-d') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} Sawit Kinabalu Cattle Management System - This is a computer generated document</p>
        <p>Total Records: {{ count($cattles) }}</p>
    </div>
</body>
</html>
