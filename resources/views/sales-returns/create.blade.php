@extends('layouts.app')

@section('title', 'Ajukan Retur')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div><a href="{{ route('sales.show', $sale) }}" class="text-sm text-sky-400">← {{ $sale->transaction_number }}</a><h1 class="mt-2 text-2xl font-bold text-white">Ajukan Retur</h1><p class="text-sm text-slate-400">Nilai retur dihitung dari snapshot transaksi asli.</p></div>
    @if($errors->any())<div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('sales-returns.store', $sale) }}" class="space-y-5 rounded-xl border border-slate-700 bg-slate-800 p-6">@csrf
        <label class="block text-sm text-slate-300">Item penjualan<select name="sale_item_id" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"><option value="">Pilih item</option>@foreach($sale->items as $item)<option value="{{ $item->id }}" @selected(old('sale_item_id') == $item->id)>{{ $item->product_name_snapshot }} · SKU {{ $item->sku_snapshot }} · terjual {{ $item->quantity }} · netto Rp {{ number_format((float) $item->net_line_total, 2, ',', '.') }}</option>@endforeach</select></label>
        <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm text-slate-300">Quantity retur<input name="quantity" type="number" min="0.001" step="0.001" value="{{ old('quantity') }}" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"></label><label class="block text-sm text-slate-300">Kondisi<select name="condition" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white"><option value="resellable">Layak jual</option><option value="damaged">Rusak</option></select></label></div>
        <label class="block text-sm text-slate-300">Alasan retur<textarea name="reason" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white">{{ old('reason') }}</textarea></label>
        <label class="block text-sm text-slate-300">Catatan tambahan<textarea name="notes" maxlength="5000" rows="2" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white">{{ old('notes') }}</textarea></label>
        <div class="flex justify-end gap-3"><a href="{{ route('sales.show', $sale) }}" class="rounded-lg border border-slate-600 px-4 py-2 text-slate-300">Batal</a><button class="rounded-lg bg-sky-600 px-4 py-2 font-semibold text-white">Kirim Pengajuan</button></div>
    </form>
</div>
@endsection
