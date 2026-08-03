@extends('reports.pdf-layout')

@section('title', 'Revenue Report')
@section('report_name', 'Revenue Breakdown')

@section('content')
    <table class="summary-cards">
        <tr>
            <td class="card" width="23%">
                <div class="card-title">Today</div>
                <div class="card-value">${{ number_format($data['summary']['today'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">This Month</div>
                <div class="card-value">${{ number_format($data['summary']['month'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">This Year</div>
                <div class="card-value">${{ number_format($data['summary']['year'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Total Revenue</div>
                <div class="card-value">${{ number_format($data['summary']['total'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <h4 style="margin-bottom: 5px; color: #334155;">Revenue Sources</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Source Category</th>
                <th class="text-right">Amount ($)</th>
                <th class="text-right">Share (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['table'] as $row)
                <tr>
                    <td>{{ $row['source'] }}</td>
                    <td class="text-right">${{ number_format($row['amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['percentage'], 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection