@extends('reports.pdf-layout')

@section('title', 'Expense Report')
@section('report_name', 'Expense Analysis')

@section('content')
    <table class="summary-cards">
        <tr>
            <td class="card" width="23%">
                <div class="card-title">Total Expenses</div>
                <div class="card-value" style="color: #dc2626;">₦{{ number_format($data['summary']['total_expenses'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Expense Count</div>
                <div class="card-value" style="color: #f59e0b;">{{ $data['summary']['expense_count'] ?? 0 }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Approved</div>
                <div class="card-value">₦{{ number_format($data['summary']['approved_expenses'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Paid</div>
                <div class="card-value">₦{{ number_format($data['summary']['paid_expenses'] ?? 0, 2) }}</div>
            </td>
        </tr>
        <tr>
            <td class="card" width="23%">
                <div class="card-title">Draft</div>
                <div class="card-value">₦{{ number_format($data['summary']['draft_expenses'] ?? 0, 2) }}</div>
            </td>
            <td width="2%"></td>
            <td class="card" width="23%">
                <div class="card-title">Cancelled</div>
                <div class="card-value">₦{{ number_format($data['summary']['cancelled_expenses'] ?? 0, 2) }}</div>
            </td>
        </tr>
    </table>

    <h4 style="margin-bottom: 5px; color: #334155;">Monthly Expenses</h4>
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
                        <td class="text-right">₦{{ number_format($data['charts'][0]['values'][$index] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <h4 style="margin: 20px 0 5px; color: #334155;">Expenses by Category</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($data['charts'][1]))
                @foreach($data['charts'][1]['labels'] as $index => $category)
                    <tr>
                        <td>{{ $category }}</td>
                        <td class="text-right">₦{{ number_format($data['charts'][1]['values'][$index] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <h4 style="margin: 20px 0 5px; color: #334155;">Expense Details</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Expense No</th>
                <th>Date</th>
                <th>Category</th>
                <th>Vendor</th>
                <th>Department</th>
                <th class="text-right">Amount</th>
                <th>Payment Method</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($data['table']['data']))
                @foreach($data['table']['data'] as $row)
                    <tr>
                        <td>{{ $row['expense_no'] ?? 'N/A' }}</td>
                        <td>{{ $row['expense_date'] ?? 'N/A' }}</td>
                        <td>{{ $row['category'] ?? 'N/A' }}</td>
                        <td>{{ $row['vendor'] ?? 'N/A' }}</td>
                        <td>{{ $row['department'] ?? 'N/A' }}</td>
                        <td class="text-right">₦{{ number_format($row['amount'] ?? 0, 2) }}</td>
                        <td>{{ ucfirst($row['payment_method'] ?? 'N/A') }}</td>
                        <td>{{ ucfirst($row['status'] ?? 'N/A') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endsection
