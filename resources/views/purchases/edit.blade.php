@extends('layouts.app')

@section('title', 'Ubah Draft Purchase Order #' . $purchase->purchase_number)

@section('content')
<div class="space-y-6" x-data="purchaseOrderEditForm()">
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
                    <li class="text-slate-800 font-medium">Ubah Draft</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Ubah Draft PO: {{ $purchase->purchase_number }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">Perbarui rincian pesanan barang sebelum dokumen disahkan menjadi status dipesan.</p>
        </div>

        <div>
            <a href="{{ route('purchases.show', $purchase) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Detail
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('purchases.update', $purchase) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Header Info Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <h2 class="text-base font-semibold text-slate-900 mb-4 pb-2 border-b border-slate-100">Informasi Pemesanan & Pemasok</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Pemasok / Supplier <span class="text-rose-500">*</span></label>
                    <select name="supplier_id" required class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ old('supplier_id', $purchase->supplier_id) == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }} ({{ $sup->supplier_code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Tanggal Pesanan <span class="text-rose-500">*</span></label>
                    <input type="date" name="order_date" value="{{ old('order_date', $purchase->order_date->format('Y-m-d')) }}" required class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Estimasi Tanggal Tiba</label>
                    <input type="date" name="expected_date" value="{{ old('expected_date', $purchase->expected_date?->format('Y-m-d')) }}" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Catatan / Referensi</label>
                    <input type="text" name="notes" value="{{ old('notes', $purchase->notes) }}" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Daftar Barang Pesanan</h2>
                    <p class="text-xs text-slate-400">Pilih produk, tentukan kuantitas pemesanan, dan harga beli per unit.</p>
                </div>
                <button type="button" @click="addItem()" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Baris Barang
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2.5 w-10 text-center">#</th>
                            <th class="px-3 py-2.5 min-w-[240px]">Produk <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 w-36 text-right">Kuantitas <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 w-44 text-right">Harga Beli Satuan (Rp) <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 w-36 text-right">Diskon Baris (Rp)</th>
                            <th class="px-3 py-2.5 w-44 text-right">Subtotal Baris (Rp)</th>
                            <th class="px-3 py-2.5 w-12 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-3 py-2.5 text-center text-xs font-mono text-slate-400" x-text="index + 1"></td>
                                <td class="px-3 py-2.5">
                                    <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" required class="w-full py-1.5 px-2.5 text-sm border border-slate-300 rounded-md focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                                        <option value="">Pilih Produk...</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">
                                                {{ $prod->name }} ({{ $prod->sku }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <input type="number" step="any" min="0.001" :name="'items[' + index + '][ordered_qty]'" x-model.number="item.ordered_qty" required class="w-full py-1.5 px-2 text-sm text-right font-mono border border-slate-300 rounded-md focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <input type="number" step="any" min="0" :name="'items[' + index + '][unit_cost]'" x-model.number="item.unit_cost" required class="w-full py-1.5 px-2 text-sm text-right font-mono border border-slate-300 rounded-md focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <input type="number" step="any" min="0" :name="'items[' + index + '][line_discount]'" x-model.number="item.line_discount" class="w-full py-1.5 px-2 text-sm text-right font-mono border border-slate-300 rounded-md focus:ring-1 focus:ring-slate-500 focus:border-slate-500">
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono font-semibold text-slate-800 whitespace-nowrap">
                                    <span x-text="formatNumber(lineNet(item))"></span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <button type="button" @click="removeItem(index)" class="text-slate-400 hover:text-rose-600 transition p-1" title="Hapus baris" x-show="items.length > 1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Discounts Section -->
            <div class="mt-6 pt-4 border-t border-slate-200 flex flex-col lg:flex-row justify-between gap-6">
                <div class="max-w-md text-xs text-slate-400 space-y-1">
                    <p class="font-medium text-slate-600">Ketentuan Kalkulasi PO:</p>
                    <p>• Diskon dokumen akan dialokasikan secara proporsional ke setiap baris barang pesanan.</p>
                    <p>• Estimasi biaya perolehan (ongkir/asuransi) dapat disesuaikan pada saat penerimaan barang fisik.</p>
                </div>

                <div class="w-full lg:w-96 space-y-2.5 font-mono text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal Kotor Barang:</span>
                        <span class="font-semibold text-slate-800" x-text="formatNumber(grossSubtotal())"></span>
                    </div>

                    <div class="flex justify-between text-slate-600">
                        <span>Total Diskon Baris:</span>
                        <span class="text-rose-600" x-text="'- ' + formatNumber(itemDiscountTotal())"></span>
                    </div>

                    <div class="flex justify-between items-center text-slate-600 pt-1">
                        <span class="text-xs font-sans">Diskon Dokumen Header (Rp):</span>
                        <input type="number" step="any" min="0" name="document_discount_total" x-model.number="document_discount_total" class="w-36 py-1 px-2 text-sm text-right font-mono border border-slate-300 rounded focus:ring-1 focus:ring-slate-500">
                    </div>

                    <div class="flex justify-between items-center text-slate-600 pt-1">
                        <span class="text-xs font-sans">Estimasi Biaya Tambahan (Rp):</span>
                        <input type="number" step="any" min="0" name="additional_cost_estimate" x-model.number="additional_cost_estimate" class="w-36 py-1 px-2 text-sm text-right font-mono border border-slate-300 rounded focus:ring-1 focus:ring-slate-500">
                    </div>

                    <div class="flex justify-between items-center pt-2 border-t border-slate-200 text-base font-bold text-slate-900">
                        <span class="font-sans">Estimasi Total Tagihan:</span>
                        <span class="text-blue-700" x-text="formatNumber(estimatedPayable())"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-3">
            <a href="{{ route('purchases.show', $purchase) }}" class="px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 rounded-lg shadow-sm transition">
                Perbarui Draft PO
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function purchaseOrderEditForm() {
    return {
        document_discount_total: {{ old('document_discount_total', (float)$purchase->document_discount_total) }},
        additional_cost_estimate: {{ old('additional_cost_estimate', (float)$purchase->additional_cost_estimate) }},
        items: @json($purchase->items->map(fn($it) => [
            'product_id' => $it->product_id,
            'ordered_qty' => (float)$it->ordered_qty,
            'unit_cost' => (float)$it->unit_cost,
            'line_discount' => (float)$it->line_discount,
        ])),
        addItem() {
            this.items.push({ product_id: '', ordered_qty: 1, unit_cost: 0, line_discount: 0 });
        },
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },
        lineGross(item) {
            const qty = parseFloat(item.ordered_qty) || 0;
            const cost = parseFloat(item.unit_cost) || 0;
            return qty * cost;
        },
        lineNet(item) {
            const gross = this.lineGross(item);
            const disc = parseFloat(item.line_discount) || 0;
            return Math.max(0, gross - disc);
        },
        grossSubtotal() {
            return this.items.reduce((acc, it) => acc + this.lineGross(it), 0);
        },
        itemDiscountTotal() {
            return this.items.reduce((acc, it) => acc + (parseFloat(it.line_discount) || 0), 0);
        },
        netSubtotal() {
            return Math.max(0, this.grossSubtotal() - this.itemDiscountTotal());
        },
        estimatedPayable() {
            const net = this.netSubtotal();
            const docDisc = parseFloat(this.document_discount_total) || 0;
            const addCost = parseFloat(this.additional_cost_estimate) || 0;
            return Math.max(0, net - docDisc + addCost);
        },
        formatNumber(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
@endpush
