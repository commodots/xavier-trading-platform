@extends('pdf.pdf-layout')

@section('title', 'Revenue Report')
@section('report_name', 'Revenue Analysis')

@section('content')
    <table class="summary-cards">
        <tr>
            <td class="card" width="23%">
                <div class="card-title">Total Revenue</div>
                <div class="card-value" style="color: #0047AB;">₦{{ number_format($data['summary']['total_revenue'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">This Month</div>
                <div class="card-value">₦{{ number_format($data['summary']['revenue_this_month'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">This Year</div>
                <div class="card-value">₦{{ number_format($data['summary']['revenue_this_year'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Transactions</div>
                <div class="card-value">{{ $data['summary']['transaction_count'] ?? 0 }}</div>
            </td>
        </tr>
    </table>

    <h4 style="margin-bottom: 5px; color: #334155;">Monthly Revenue</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Period</th>
                <th class="text-right">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($data['charts'][0]))
                @foreach($data['charts'][0]['labels'] as $index => $period)
                    <tr>
                        <td>{{ $period }}</td>
                        <td class="text-right">₦{{ number_format($data['charts'][0]['series'][0]['data'][$index] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <h4 style="margin: 20px 0 5px; color: #334155;">Revenue by Source</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Source</th>
                <th class="text-right">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($data['charts'][1]))
                @foreach($data['charts'][1]['labels'] as $index => $source)
                    <tr>
                        <td>{{ $source }}</td>
                        <td class="text-right">₦{{ number_format($data['charts'][1]['series'][0]['data'][$index] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <h4 style="margin: 20px 0 5px; color: #334155;">Revenue Register</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Source</th>
                <th>Reference</th>
                <th>Description</th>
                <th>User</th>
                <th class="text-right">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($data['table']['data']))
                @foreach($data['table']['data'] as $row)
                    <tr>
                        <td>{{ $row['date'] ?? 'N/A' }}</td>
                        <td>{{ $row['source_label'] ?? $row['source'] ?? 'N/A' }}</td>
                        <td>{{ $row['reference'] ?? 'N/A' }}</td>
                        <td>{{ $row['description'] ?? 'N/A' }}</td>
                        <td>{{ $row['user_id'] ?? 'N/A' }}</td>
                        <td class="text-right">₦{{ number_format($row['amount'] ?? 0, 2) }}</td>
                        <td>{{ ucfirst($row['status'] ?? 'N/A') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endsection