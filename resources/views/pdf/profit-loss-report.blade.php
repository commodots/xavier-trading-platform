@extends('reports.pdf-layout')

@section('title', 'Profit & Loss Report')
@section('report_name', 'Profit & Loss Statement')

@section('content')
    <table class="summary-cards">
        <tr>
            <td class="card" width="23%">
                <div class="card-title">Total Income</div>
                <div class="card-value" style="color: #16a34a;">${{ number_format($data['summary']['income'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Total Expenses</div>
                <div class="card-value" style="color: #dc2626;">${{ number_format($data['summary']['expenses'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Net Profit</div>
                <div class="card-value" style="color: #0284c7;">${{ number_format($data['summary']['profit'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Net Margin</div>
                <div class="card-value">{{ number_format($data['summary']['margin'] ?? 0, 2) }}%</div>
            </td>
        </tr>
    </table>

    <table width="100%" style="margin-top: 15px;">
        <tr>
            <td width="48%" valign="top">
                <h4 style="color: #16a34a; margin-bottom: 5px;">Income Sources</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['income'] as $item)
                            <tr>
                                <td>{{ $item['category'] }}</td>
                                <td class="text-right">${{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top">
                <h4 style="color: #dc2626; margin-bottom: 5px;">Expense Categories</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['expenses'] as $item)
                            <tr>
                                <td>{{ $item['category'] }}</td>
                                <td class="text-right">${{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
@endsection