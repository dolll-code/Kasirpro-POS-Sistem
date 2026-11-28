@extends('layouts.app')

@section('title', 'Mulai Stok Opname')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <a href="{{ route('stock-opnames.index') }}" class="text-sm text-sky-400">&larr; Daftar opname</a>
        <h1 class="mt-2 text-2xl font-bold text-white">Mulai Sesi Stok Opname</h1>
        <p class="text-sm text-slate-400">Produk terpilih dikunci dari semua mutasi stok sampai sesi posted atau cancelled. Snapshot stok dan HPP diambil sekarang.</p>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-rose-700 bg-rose-950/40 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('stock-opnames.store') }}" class="space-y-4 rounded-xl border border-slate-700 bg-slate-800 p-6">
        @csrf
        <div class="flex items-center justify-between border-b border-slate-700 pb-3">
            <h2 class="font-semibold text-white">Pilih Produk</h2>
            <label class="text-xs text-slate-300"><input type="checkbox" id="select-all" class="mr-1 rounded border-slate-600 bg-slate-900"> Pilih semua</label>
        </div>
        <div class="max-h-96 divide-y divide-slate-700 overflow-y-auto rounded-lg border border-slate-700">
            @forelse($products as $product)
                <label class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 hover:bg-slate-700/30">
                    <span class="flex items-center gap-3">
                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="product-checkbox rounded border-slate-600 bg-slate-900" @checked(in_array($product->id, old('product_ids', []), true))>
                        <span>
                            <span class="block text-sm font-medium text-white">{{ $product->name }}</span>
                            <span class="block text-xs text-slate-400 font-mono">{{ $product->sku }}{{ $product->barcode ? ' / '.$product->barcode : '' }} &middot; {{ $product->category?->name }} &middot; {{ $product->unit?->symbol }}</span>
                        </span>
                    </span>
                    <span class="text-right text-xs text-slate-400">
                        <span class="block">On-hand: <span class="text-slate-200">{{ $product->quantity_on_hand }}</span></span>
                        <span class="block">HPP: Rp {{ number_format((float) $product->average_cost, 2, ',', '.') }}</span>
                    </span>
                </label>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-400">Tidak ada produk aktif yang dapat diopname.</p>
            @endforelse
        </div>
        <label class="block text-sm text-slate-300">Catatan sesi
            <textarea name="notes" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900 text-white">{{ old('notes') }}</textarea>
        </label>
        <div class="flex justify-end">
            <button class="rounded-lg bg-emerald-600 px-5 py-2 font-semibold text-white hover:bg-emerald-500">Mulai dan Kunci Produk</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('select-all')?.addEventListener('change', function (event) {
    document.querySelectorAll('.product-checkbox').forEach(cb => { cb.checked = event.target.checked; });
});
</script>
@endpush
@endsection
