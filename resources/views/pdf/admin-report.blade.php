<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body{font-family:'DejaVu Sans',Arial,sans-serif;font-size:10px;line-height:1.3;color:#333}
        .header{text-align:center;padding:15px 0;border-bottom:2px solid #0047AB;margin-bottom:15px}
        .header h1{margin:0;color:#0047AB;font-size:20px}
        .header p{margin:5px 0 0;color:#666}
        .info{margin-bottom:15px;padding:8px;background:#f5f5f5;border-radius:4px}
        .info-row{display:flex;margin-bottom:3px}
        .info-label{font-weight:bold;width:150px}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th{background:#0047AB;color:white;padding:6px 4px;text-align:left;font-weight:bold;font-size:9px}
        td{padding:5px 4px;border-bottom:1px solid #ddd;font-size:9px}
        tr:nth-child(even){background:#f9f9f9}
        .footer{margin-top:20px;padding-top:10px;border-top:1px solid #ddd;text-align:center;color:#666;font-size:9px}
        .amount{text-align:right;font-weight:bold}
        .status-completed{color:#28a745;font-weight:bold}
        .status-pending{color:#cc8800;font-weight:bold}
        .status-failed{color:#dc3545;font-weight:bold}
    </style>
</head>
<body>
    <div class="header"><h1>XAVIER TRADING PLATFORM</h1><p>{{ $title }}</p></div>
    <div class="info">
        <div class="info-row"><span class="info-label">Period:</span><span>{{ $from }} to {{ $to }}</span></div>
        <div class="info-row"><span class="info-label">Generated:</span><span>{{ date('Y-m-d H:i:s') }}</span></div>
        <div class="info-row"><span class="info-label">Records:</span><span>{{ count($rows) }}</span></div>
    </div>
    <table>
        <thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                @foreach($headers as $h)
                    @php $k = strtolower(str_replace(' ','_',$h)); $v = $row[$k]??$row[$h]??'N/A'; @endphp
                    @if($h === 'Amount')<td class="amount">{{ number_format($v,2) }}</td>
                    @elseif($h === 'Status')<td><span class="status-{{ strtolower($v) }}">{{ ucfirst($v) }}</span></td>
                    @else<td>{{ $v }}</td>@endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer"><p>System-generated report. Contact support@xavier.com</p><p>&copy; {{ date('Y') }} Xavier Trading Platform.</p></div>
</body>
</html>