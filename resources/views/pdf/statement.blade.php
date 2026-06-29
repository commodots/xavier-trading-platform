 <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account Statement</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        .header {
            text-align: center;
            padding: 15px 0;
            border-bottom: 2px solid #0047AB;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            color: #0047AB;
            font-size: 20px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .info {
            margin-bottom: 15px;
            padding: 8px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .info-row {
            display: flex;
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }
        th {
            background: #0047AB;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            padding: 5px 4px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 9px;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .status {
            text-align: center;
        }
        .status-completed {
            color: #28a745;
            font-weight: bold;
        }
        .status-pending {
            color: #cc8800;
            font-weight: bold;
        }
        .status-failed {
            color: #dc3545;
            font-weight: bold;
        }
        .currency-col {
            text-align: center;
        }
        .reference {
            word-wrap: break-word;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>XAVIER TRADING PLATFORM</h1>
        <p>Account Statement</p>
    </div>

    <div class="info">
        <div class="info-row">
            <span class="info-label">Period:</span>
            <span>{{ $period['from'] }} to {{ $period['to'] }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Generated On:</span>
            <span>{{ date('Y-m-d H:i:s') }}</span>
        </div>
        @if(isset($current_balances) && count($current_balances) > 0)
        <div class="info-row">
            <span class="info-label">Current Balances:</span>
            <span>
                @foreach($current_balances as $currency => $balance)
                    <strong>{{ $currency }}:</strong> {{ number_format($balance, 2) }} &nbsp;&nbsp;
                @endforeach
            </span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">Total Transactions:</span>
            <span>{{ $summary['transaction_count'] }}</span>
        </div>
    </div>

    @php
        $currencySymbols = [
            'USD' => '$',
            'NGN' => '&#8358;',
            'EUR' => '&#8364;',
            'GBP' => '&#163;'
        ];
        
        $stockTickers = ['AAPL', 'TSLA', 'GOOGL', 'MSFT', 'AMZN', 'META', 'NVDA', 'GOOG', 'NFLX', 'INTC'];
    @endphp
    
    <table>
        <thead>
            <tr>
                <th width="18%">Date/Time</th>
                <th width="19%">Reference</th>
                <th width="16%">Transaction Type</th>
                <th width="9%">Currency</th>
                <th width="12%">Amount</th>
                <th width="12%">Bal Before</th>
                <th width="12%">Bal After</th>
                <th width="9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ledger as $item)
            @php 
                $tx = $item['transaction']; 
                $currency = $tx->asset ?? 'USD';
                // Show BTC as USD with $ symbol
                $symbol = '';
                if (isset($currencySymbols[$currency])) {
                    $symbol = $currencySymbols[$currency];
                } elseif (in_array($currency, array_merge($stockTickers, ['BTC', 'ETH', 'SOL', 'XRP', 'ADA', 'DOT']))) {
                    $symbol = '$';
                    $currency = 'USD';
                } else {
                    $symbol = $currency . ' ';
                }
                
                // Format transaction type
                $type = ucfirst(strtolower($tx->type ?? 'N/A'));
                if (strtolower($type) === 'trade' && !empty($item['trade_direction'])) {
                    $type .= ' (' . ucfirst(strtolower($item['trade_direction'])) . ')';
                }
                
                // Format date with AM/PM
                $formattedDate = 'N/A';
                if ($tx->created_at) {
                    $formattedDate = $tx->created_at->format('m/d h:i A');
                }
                
                // Format balances without minus sign
                $balanceBefore = abs($item['balance_before']);
                $balanceAfter = abs($item['balance_after']);
            @endphp
            <tr>
                <td>{{ $formattedDate }}</td>
                <td class="reference">{{ $tx->reference ?? 'N/A' }}</td>
                <td>{{ $type }}</td>
                <td class="currency-col">{{ $currency }}</td>
                <td class="amount">{!! $symbol !!}{{ number_format($tx->amount ?? 0, 2) }}</td>
                <td class="amount">{!! $symbol !!}{{ number_format($balanceBefore, 2) }}</td>
                <td class="amount">{!! $symbol !!}{{ number_format($balanceAfter, 2) }}</td>
                <td class="status">
                    <span class="status-{{ strtolower($tx->status ?? 'pending') }}">
                        {{ ucfirst($tx->status ?? 'Pending') }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="info" style="margin-top: 15px;">
        @php
            $totals = $summary['totals'] ?? [];
            $currencySymbolsDisplay = [
                'USD' => '$',
                'NGN' => '&#8358;',
                'EUR' => '&#8364;',
                'GBP' => '&#163;'
            ];
        @endphp
        
        @if(count($totals) > 0)
            @foreach($totals as $key => $total)
                @php
                    $parts = explode('_', $key);
                    $direction = $parts[0] ?? ''; // in or out
                    $ccy = $parts[1] ?? 'USD';
                    $sym = $currencySymbolsDisplay[$ccy] ?? ($ccy . ' ');
                    $color = $direction === 'in' ? '#28a745' : '#dc3545';
                    $label = $direction === 'in' ? 'Total ' . $ccy . ' In' : 'Total ' . $ccy . ' Out';
                @endphp
                <div class="info-row">
                    <span class="info-label">{{ $label }}:</span>
                    <span style="color: {{ $color }}; font-weight: bold;">{!! $sym !!}{{ number_format($total, 2) }}</span>
                </div>
            @endforeach
        @else
            <div class="info-row">
                <span class="info-label">Total Amount In:</span>
                <span style="color: #28a745; font-weight: bold;">{{ number_format($summary['total_in'], 2) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Amount Out:</span>
                <span style="color: #dc3545; font-weight: bold;">{{ number_format($summary['total_out'], 2) }}</span>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This is a system-generated statement. For inquiries, contact support@xavier.com</p>
        <p>&copy; {{ date('Y') }} Xavier Trading Platform. All rights reserved.</p>
    </div>
</body>
</html>
