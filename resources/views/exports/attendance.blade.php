<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10pt;
            color: #18181b;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e4e4e7;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
            font-weight: bold;
            color: #09090b;
        }
        .header p {
            margin: 5px 0 0;
            color: #71717a;
        }
        .meta {
            margin-bottom: 20px;
            display: table;
            width: 100%;
        }
        .meta-item {
            display: table-cell;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f4f4f5;
            text-align: left;
            padding: 10px;
            font-weight: bold;
            border-bottom: 1px solid #e4e4e7;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #f4f4f5;
        }
        .status {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-present { background-color: #ecfdf5; color: #065f46; }
        .status-late { background-color: #fffbeb; color: #92400e; }
        .status-absent { background-color: #fef2f2; color: #991b1b; }
        .status-excused { background-color: #eff6ff; color: #1e40af; }
        .footer {
            text-align: center;
            font-size: 8pt;
            color: #a1a1aa;
            margin-top: 50px;
            border-top: 1px solid #e4e4e7;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendance Report</h1>
        <p>Generated on {{ now()->format('F j, Y, g:i A') }}</p>
    </div>

    <div class="meta">
        <div class="meta-item">
            <strong>Period:</strong> {{ $startDate->format('M j, Y') }} - {{ $endDate->format('M j, Y') }}
        </div>
        <div class="meta-item" style="text-align: right;">
            <strong>Total Records:</strong> {{ count($records) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ $record->scanned_at->format('M j, Y') }}</td>
                    <td>
                        <strong>{{ $record->student?->name ?? 'N/A' }}</strong><br>
                        <small style="color: #71717a;">{{ $record->student?->student_number ?? '' }}</small>
                    </td>
                    <td>{{ $record->subject?->name ?? 'N/A' }}</td>
                    <td>
                        <span class="status status-{{ strtolower($record->status) }}">
                            {{ $record->status }}
                        </span>
                    </td>
                    <td>{{ $record->scanned_at->format('h:i A') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        &copy; {{ date('Y') }} QR Attendance Portal. All rights reserved.
    </div>
</body>
</html>
