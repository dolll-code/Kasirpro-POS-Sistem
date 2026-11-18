@extends('layouts.app')

@section('title', 'Penerimaan Barang Fisik PO #' . $purchase->purchase_number)

@section('content')
<div class="space-y-6" x-data="receivingForm()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="{{ route('dashboard') }}" class="hover:text-slate-700">Beranda</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="{{ route('purchases.index') }}" class="hover:text-slate-700">Pembelian</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="{{ route('purchases.show', $purchase) }}" class="hover:text-slate-700">{{ $purchase->purchase_number }}</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-slate-800 font-medium">Penerimaan Barang (Goods Receipt)</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Penerimaan Barang Fisik (Goods Receipt)</h1>
            <p class="text-sm text-slate-500 mt-0.5">Catat barang datang dari pemasok <strong class="text-slate-800">{{ $purchase->supplier?->name }}</strong> untuk PO #{{ $purchase->purchase_number }}.</p>
        </div>

        <div>
            <a href="{{ route('purchases.show', $purchase) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Detail PO
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('purchases.receipts.store', $purchase) }}" class="space-y-6">
        @csrf

        <!-- Receipt Header Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <h2 class="text-base font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">Informasi Penerimaan Fisik</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Waktu Kedatangan Fisik</label>
                    <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Biaya Tambahan Dikapitalisasi (Rp)
                        <span class="text-slate-400 font-normal">(Ongkir / Asuransi)</span>
                    </label>
                    <input type="number" step="any" min="0" name="capitalizable_additional_cost" x-model.number="capitalizable_cost" class="w-full py-2 px-3 text-sm font-mono border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                    <p class="text-[11px] text-slate-400 mt-0.5">Dialokasikan proporsional ke HPP barang yang diterima sah (accepted).</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Catatan Penerimaan / Surat Jalan</label>
                    <input type="text" name="notes" placeholder="Contoh: No. Surat Jalan SJ-1029..." class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>
            </div>
        </div>

        <!-- Receipt Lines Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Verifikasi Fisik per Barang</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Pastikan rumus dipenuhi: <code class="font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-800">Fisik Diterima = Diterima Sah (Accepted) + Ditolak (Rejected)</code>.
                    Barang ditolak tidak akan menambah stok fisik toko.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-3 w-10 text-center">#</th>
                            <th class="px-3 py-3 min-w-[220px]">Produk & Satuan</th>
                            <th class="px-3 py-3 w-28 text-right">Sisa Pesanan</th>
                            <th class="px-3 py-3 w-36 text-right">Fisik Datang <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-3 w-36 text-right">Diterima (Acc) <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-3 w-36 text-right">Ditolak (Rej)</th>
                            <th class="px-3 py-3 min-w-[180px]">Alasan Penolakan (Jika ada)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <template x-for="(line, idx) in lines" :key="line.purchase_item_id">
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-3 py-3 text-center text-slate-400 font-mono" x-text="idx + 1"></td>
                                <td class="px-3 py-3">
                                    <input type="hidden" :name="'items[' + idx + '][purchase_item_id]'" :value="line.purchase_item_id">
                                    <span class="font-semibold text-slate-900 block" x-text="line.name"></span>
                                    <span class="text-[11px] font-mono text-slate-400" x-text="line.sku + ' • Satuan: ' + line.unit"></span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-700" x-text="line.remaining"></td>
                                <td class="px-3 py-3 text-right">
                                    <input type="number" step="any" min="0" :name="'items[' + idx + '][physically_received_qty]'" x-model.number="line.physical" @input="onPhysicalChange(line)" class="w-full py-1.5 px-2 text-sm text-right font-mono border border-slate-300 rounded focus:ring-1 focus:ring-slate-500">
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <input type="number" step="any" min="0" :name="'items[' + idx + '][accepted_qty]'" x-model.number="line.accepted" @input="onAcceptedChange(line)" class="w-full py-1.5 px-2 text-sm text-right font-mono border border-slate-300 rounded focus:ring-1 focus:ring-slate-500 text-emerald-700 font-semibold">
                                    <span x-show="line.accepted > line.remaining" class="block text-[10px] text-rose-500 mt-0.5">Melebihi sisa!</span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <input type="number" step="any" min="0" :name="'items[' + idx + '][rejected_qty]'" x-model.number="line.rejected" @input="onRejectedChange(line)" class="w-full py-1.5 px-2 text-sm text-right font-mono border border-slate-300 rounded focus:ring-1 focus:ring-slate-500 text-rose-600">
                                </td>
                                <td class="px-3 py-3">
                                    <input type="text" :name="'items[' + idx + '][rejection_reason]'" x-model="line.rejection_reason" placeholder="Contoh: Kemasan sobek, kedaluwarsa..." class="w-full py-1.5 px-2 text-xs border border-slate-300 rounded focus:ring-1 focus:ring-slate-500">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-3">
            <a href="{{ route('purchases.show', $purchase) }}" class="px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition">
                Posting Penerimaan & Mutasi Stok
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function receivingForm() {
    return {
        capitalizable_cost: 0,
        lines: @json($purchase->items->map(fn($it) => [
            'purchase_item_id' => $it->id,
            'name' => $it->product_name_snapshot,
            'sku' => $it->sku_snapshot,
            'unit' => $it->unit_snapshot,
            'remaining' => (float)$it->remainingQty()->toString(),
            'physical' => (float)$it->remainingQty()->toString(),
            'accepted' => (float)$it->remainingQty()->toString(),
            'rejected' => 0,
            'rejection_reason' => '',
        ])),
        onPhysicalChange(line) {
            line.accepted = Math.min(line.physical, line.remaining);
            line.rejected = Math.max(0, line.physical - line.accepted);
        },
        onAcceptedChange(line) {
            line.rejected = Math.max(0, line.physical - line.accepted);
        },
        onRejectedChange(line) {
            line.accepted = Math.max(0, line.physical - line.rejected);
        }
    };
}
</script>
@endpush
