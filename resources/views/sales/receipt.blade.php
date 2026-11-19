<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Belanja - {{ $sale->transaction_number }}</title>
    <style>
        @page {
            margin: 0;
            size: {{ $width == '80' ? '80mm auto' : '58mm auto' }};
        }
        body {
            font-family: 'Courier New', Courier, monospace, monospace;
            font-size: {{ $width == '80' ? '12px' : '10px' }};
            line-height: 1.25;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 8px 6px;
            width: {{ $width == '80' ? '76mm' : '54mm' }};
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .divider-double {
            border-top: 1px double #000;
            margin: 5px 0;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table td {
            vertical-align: top;
            padding: 1px 0;
        }
        .reprint-banner {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .no-print {
            background-color: #f1f5f9;
            padding: 8px;
            text-align: center;
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 10px;
            font-family: sans-serif;
            font-size: 12px;
        }
        .no-print button {
            background-color: #0f172a;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .no-print a {
            margin-left: 10px;
            color: #475569;
            text-decoration: none;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0 2px; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button type="button" onclick="window.print()">Cetak Ulang Struk</button>
        <a href="{{ route('sales.receipt', ['sale' => $sale, 'width' => '58']) }}">58 mm</a> |
        <a href="{{ route('sales.receipt', ['sale' => $sale, 'width' => '80']) }}">80 mm</a>
    </div>

    @if($isReprint)
        <div class="reprint-banner">
            *** SALINAN / REPRINT ***
        </div>
    @endif

    <!-- Store Profile Snapshot (PRD §5.2, §6.5) -->
    <div class="text-center">
        <div class="font-bold" style="font-size: 14px;">{{ $sale->store_snapshot['store_name'] ?? 'KasirPro Store' }}</div>
        <div>{{ $sale->store_snapshot['address'] ?? '-' }}</div>
        <div>Telp: {{ $sale->store_snapshot['phone'] ?? '-' }}</div>
    </div>

    <div class="divider"></div>

    <!-- Transaction Header -->
    <table class="table">
        <tr>
            <td>No: {{ $sale->transaction_number }}</td>
            <td class="text-right">{{ $sale->completed_at ? $sale->completed_at->format('d/m/y H:i') : '-' }}</td>
        </tr>
        <tr>
            <td colspan="2">Kasir: {{ $sale->cashier?->name }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Items Listing -->
    <table class="table">
        @foreach($sale->items as $item)
            <tr>
                <td colspan="2" class="font-bold">{{ $item->product_name_snapshot }}</td>
            </tr>
            <tr>
                <td class="text-left">
                    {{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : number_format((float) $item->quantity, 3) }} x {{ number_format((float) $item->unit_price_snapshot, 0, ',', '.') }}
                    @if((float) $item->totalDiscount() > 0)
                        <span style="font-size: 9px;">(disc -{{ number_format((float) $item->totalDiscount(), 0, ',', '.') }})</span>
                    @endif
                </td>
                <td class="text-right font-bold">{{ number_format((float) $item->net_line_total, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="divider"></div>

    <!-- Summary Totals -->
    <table class="table">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">{{ number_format((float) $sale->gross_total, 0, ',', '.') }}</td>
        </tr>
        @if((float) $sale->item_discount_total > 0)
            <tr>
                <td>Diskon Item</td>
                <td class="text-right">-{{ number_format((float) $sale->item_discount_total, 0, ',', '.') }}</td>
            </tr>
        @endif
        @if((float) $sale->transaction_discount_total > 0)
            <tr>
                <td>Diskon Nota</td>
                <td class="text-right">-{{ number_format((float) $sale->transaction_discount_total, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="font-bold" style="font-size: 13px;">
            <td>TOTAL</td>
            <td class="text-right">{{ number_format((float) $sale->grand_total, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>TUNAI</td>
            <td class="text-right">{{ number_format((float) ($sale->payment?->cash_tendered ?? 0), 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>KEMBALI</td>
            <td class="text-right">{{ number_format((float) ($sale->payment?->change_amount ?? 0), 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Footer Snapshot -->
    <div class="text-center" style="margin-top: 6px;">
        <div>{{ $sale->store_snapshot['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!' }}</div>
        <div style="font-size: 8px; margin-top: 4px; color: #555;">KasirPro POS &bull; www.kasirpro.local</div>
    </div>

</body>
</html>
