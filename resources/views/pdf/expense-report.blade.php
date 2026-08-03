@extends('reports.pdf-layout')

@section('title', 'Expense Report')
@section('report_name', 'Expense Analysis')

@section('content')
    <table class="summary-cards">
        <tr>
            <td class="card" width="23%">
                <div class="card-title">Total Expenses</div>
                <div class="card-value" style="color: #dc2626;">${{ number_format($data['summary']['total'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Outstanding</div>
                <div class="card-value" style="color: #f59e0b;">${{ number_format($data['summary']['outstanding'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Average Monthly</div>
                <div class="card-value">${{ number_format($data['summary']['average_monthly'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Largest Category</div>
                <div class="card-value">{{ $data['summary']['largest_category']['name'] ?? 'N/A' }}</div>
            </td>
        </tr>
    </table>

    <h4 style="margin-bottom: 5px; color: #334155;">Expenses by Category</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Amount ($)</th>
                <th class="text-right">Share (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['chart'] as $row)
                <tr>
                    <td>{{ $row['category'] }}</td>
                    <td class="text-right">${{ number_format($row['amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['percentage'], 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h4 style="margin: 20px 0 5px; color: #334155;">Expense Details</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Vendor</th>
                <th class="text-right">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['transactions'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['vendor'] }}</td>
                    <td class="text-right">${{ number_format($row['amount'], 2) }}</td>
                    <td>{{ ucfirst($row['status']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection