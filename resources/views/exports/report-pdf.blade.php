<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 28px 44px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1f2937; font-size: 10px; }
        h1 { font-size: 17px; margin: 0 0 2px; color: #111827; }
        .muted { color: #6b7280; font-size: 9px; }
        .brand { display: block; margin-bottom: 10px; }
        .brand-logo { max-height: 44px; max-width: 140px; margin-bottom: 4px; }
        .meta { margin: 10px 0 14px; padding: 8px 10px; border: 1px solid #d1d5db; background: #f9fafb; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #e5e7eb; font-size: 9px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .summary { width: 100%; margin-bottom: 8px; }
        .summary td { border: 0; padding: 2px 0; }
        .summary td:last-child { text-align: right; font-weight: bold; }
        .footer-note { margin-top: 14px; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="brand">
        @if($store->logo_path && file_exists(storage_path('app/private/'.$store->logo_path)))
            <img class="brand-logo" src="{{ storage_path('app/private/'.$store->logo_path) }}" alt="Logo">
        @endif
        <strong>{{ $store->name }}</strong>
        @if($store->address)<div class="muted">{{ $store->address }}</div>@endif
        @if($store->phone)<div class="muted">{{ $store->phone }}</div>@endif
    </div>

    <h1>{{ $title }}</h1>
    <div class="meta">
        <div><strong>Periode:</strong> {{ $period['from_date'] }} s/d {{ $period['to_date'] }} (Asia/Jakarta)</div>
        <div><strong>Dibuat:</strong> {{ $generated_at->format('d/m/Y H:i:s') }} WIB</div>
        <div><strong>Pembuat:</strong> {{ $generated_by }}</div>
        @if($filters)<div><strong>Filter:</strong> {{ collect($filters)->map(fn ($v, $k) => $k.'='.$v)->implode(', ') }}</div>@endif
    </div>

    @if(! empty($summary))
        <h3 style="font-size: 11px; margin: 0 0 5px;">Ringkasan</h3>
        <table class="summary">
            @foreach($summary as $key => $value)
                <tr><td>{{ $summary_labels[$key] ?? $key }}</td><td>@if($key === 'operating_margin'){{ $value === null ? '—' : $value.'%' }}@else{{ is_numeric($value) ? 'Rp '.number_format((float) $value, 2, ',', '.') : $value }}@endif</td></tr>
            @endforeach
        </table>
    @endif

    <table>
        <thead><tr>@foreach($columns as $column)<th class="{{ in_array($column['key'], ['grand_total', 'payable_total', 'return_total', 'amount', 'quantity_on_hand', 'average_cost', 'stock_value', 'net_sales', 'discounts', 'cogs', 'quantity', 'value'], true) ? 'num' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($rows as $dataRow)
            <tr>@foreach($columns as $column)@php($cell = $dataRow[$column['key']] ?? null)<td class="{{ in_array($column['key'], ['grand_total', 'payable_total', 'return_total', 'amount', 'quantity_on_hand', 'average_cost', 'stock_value', 'net_sales', 'discounts', 'cogs', 'quantity', 'value'], true) ? 'num' : '' }}">@if(is_numeric($cell) && ! in_array($column['key'], ['quantity', 'quantity_on_hand'], true) && ! str_contains($column['key'], 'date') && ! str_ends_with($column['key'], '_at'))Rp {{ number_format((float) $cell, 2, ',', '.') }}@else{{ $cell }}@endif</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($columns) }}">Tidak ada data pada periode/filter ini.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="footer-note">Dokumen dibuat otomatis oleh KasirPro. Laba Rugi Sederhana bukan laporan akuntansi formal.</div>
</body>
</html>
