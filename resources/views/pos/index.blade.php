@extends('layouts.app')

@section('title', 'Mesin Kasir (POS)')

@section('content')
<div x-data="posApp()" x-init="init()" class="h-[calc(100vh-5rem)] flex flex-col md:flex-row gap-4 -m-4 sm:-m-6 lg:-m-8 p-4 bg-slate-900 text-slate-100">
    <!-- Left Column: Product Search, Barcode & Catalog (60% width) -->
    <div class="flex-1 flex flex-col min-w-0 bg-slate-800/80 rounded-xl border border-slate-700/60 overflow-hidden shadow-lg">
        <!-- Top Toolbar: Barcode Input & Keyword Search -->
        <div class="p-3 bg-slate-800 border-b border-slate-700/60 flex flex-col sm:flex-row gap-2.5">
            <!-- Barcode Scanner Input (USB HID Keyboard Wedge) -->
            <div class="relative sm:w-1/2">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                </div>
                <input type="text"
                       x-ref="barcodeInput"
                       x-model="barcodeQuery"
                       @keydown.enter.prevent="handleBarcodeScan()"
                       placeholder="Scan barcode USB [Enter]..."
                       class="w-full pl-10 pr-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent font-mono">
            </div>

            <!-- Product Search (Name / SKU) -->
            <div class="relative sm:w-1/2">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text"
                       x-model="searchQuery"
                       @input.debounce.300ms="searchProducts()"
                       placeholder="Cari nama atau SKU..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-transparent">
            </div>
        </div>

        <!-- Notification Banner / Scanner Message -->
        <div x-show="scannerMessage" 
             x-transition 
             class="px-4 py-2 text-xs font-medium flex items-center justify-between"
             :class="scannerMessageType === 'error' ? 'bg-rose-950/80 text-rose-300 border-b border-rose-800/60' : 'bg-emerald-950/80 text-emerald-300 border-b border-emerald-800/60'">
            <span x-text="scannerMessage"></span>
            <button type="button" @click="scannerMessage = ''" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <!-- Category Pills Filter -->
        <div class="px-3 py-2 bg-slate-800/60 border-b border-slate-700/40 flex items-center gap-1.5 overflow-x-auto text-xs no-scrollbar">
            <button type="button"
                    @click="setCategory(null)"
                    :class="selectedCategory === null ? 'bg-emerald-600 text-white font-semibold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
                    class="px-3 py-1.5 rounded-full shrink-0 transition-colors">
                Semua Produk
            </button>
            @foreach($categories as $category)
                <button type="button"
                        @click="setCategory({{ $category->id }})"
                        :class="selectedCategory === {{ $category->id }} ? 'bg-emerald-600 text-white font-semibold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
                        class="px-3 py-1.5 rounded-full shrink-0 transition-colors">
                    {{ $category->name }}
                </button>
            @endforeach
        </div>

        <!-- Product Grid Catalog -->
        <div class="flex-1 p-3 overflow-y-auto">
            <template x-if="isLoading">
                <div class="flex items-center justify-center h-48 text-slate-400 text-sm">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memuat katalog produk...
                </div>
            </template>

            <template x-if="!isLoading && products.length === 0">
                <div class="flex flex-col items-center justify-center h-48 text-slate-400 text-sm">
                    <p>Tidak ada produk ditemukan.</p>
                    <p class="text-xs text-slate-500 mt-1">Coba gunakan kata kunci pencarian atau kategori lain.</p>
                </div>
            </template>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
                <template x-for="p in products" :key="p.id">
                    <button type="button"
                            @click="addToCart(p)"
                            :disabled="p.available_quantity <= 0"
                            :class="p.available_quantity <= 0 ? 'opacity-40 cursor-not-allowed bg-slate-800/40 border-slate-800' : 'bg-slate-800 hover:bg-slate-750 hover:border-slate-500 border-slate-700/80 active:scale-[0.98]'"
                            class="text-left p-2.5 rounded-lg border flex flex-col justify-between transition-all group shadow-sm">
                        <div>
                            <div class="flex items-start justify-between gap-1 mb-1">
                                <span class="text-[10px] font-mono text-slate-400 truncate" x-text="p.sku"></span>
                                <span x-show="p.available_quantity <= 0" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-rose-950 text-rose-300 border border-rose-800/60">Habis</span>
                                <span x-show="p.available_quantity > 0 && p.stock_status === 'low_stock'" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-800/60">Menipis</span>
                            </div>
                            <h4 class="text-xs font-semibold text-white line-clamp-2 leading-tight group-hover:text-emerald-400 transition-colors" x-text="p.name"></h4>
                        </div>
                        <div class="mt-2.5 pt-2 border-t border-slate-700/40 flex items-baseline justify-between">
                            <span class="text-xs font-bold text-emerald-400" x-text="formatIdr(p.selling_price)"></span>
                            <span class="text-[10px] text-slate-400" x-text="p.available_quantity + ' ' + (p.unit_symbol || 'pcs')"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Right Column: Cart, Live Totals & Payment Panel (40% width) -->
    <div class="w-full md:w-[460px] flex flex-col bg-slate-800/90 rounded-xl border border-slate-700/60 overflow-hidden shadow-xl shrink-0">
        <!-- Cart Header -->
        <div class="px-4 py-3 bg-slate-800 border-b border-slate-700/60 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <h3 class="font-bold text-sm text-white">Keranjang Belanja</h3>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-700 text-slate-300" x-text="cart.length"></span>
            </div>
            <button type="button"
                    @click="clearCart()"
                    x-show="cart.length > 0"
                    class="text-xs text-rose-400 hover:text-rose-300 transition-colors">
                Kosongkan
            </button>
        </div>

        <!-- Cart Item Rows -->
        <div class="flex-1 p-3 overflow-y-auto space-y-2">
            <template x-if="cart.length === 0">
                <div class="flex flex-col items-center justify-center h-full text-slate-400 text-sm py-12">
                    <svg class="h-12 w-12 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p class="font-medium text-slate-300">Keranjang masih kosong</p>
                    <p class="text-xs text-slate-500 mt-1">Scan barcode atau pilih barang di katalog.</p>
                </div>
            </template>

            <template x-for="(item, index) in cart" :key="item.product_id">
                <div class="p-2.5 bg-slate-900/90 rounded-lg border border-slate-700/60 flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <h5 class="text-xs font-bold text-white truncate" x-text="item.name"></h5>
                            <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5">
                                <span x-text="formatIdr(item.selling_price)"></span>
                                <span>&bull;</span>
                                <span class="font-mono text-[10px]" x-text="'Stok: ' + item.available_quantity"></span>
                            </div>
                        </div>
                        <button type="button" @click="removeItem(index)" class="text-slate-500 hover:text-rose-400 p-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Quantity Control & Line Discount -->
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-800">
                        <!-- Quantity Stepper -->
                        <div class="flex items-center border border-slate-700 rounded-lg bg-slate-800 overflow-hidden">
                            <button type="button"
                                    @click="changeQty(index, -1)"
                                    class="px-2 py-1 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                -
                            </button>
                            <input type="text"
                                   x-model="item.quantity"
                                   @change="validateQty(index)"
                                   class="w-12 text-center bg-transparent text-xs font-bold text-white border-0 focus:ring-0 p-0">
                            <button type="button"
                                    @click="changeQty(index, 1)"
                                    class="px-2 py-1 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                +
                            </button>
                        </div>

                        <!-- Item Discount Trigger / Input -->
                        <div class="flex items-center gap-1">
                            <select x-model="item.line_discount_type" @change="recalculateTotals()" class="bg-slate-800 border border-slate-700 rounded text-[10px] text-slate-300 py-0.5 px-1 focus:ring-0">
                                <option value="amount">Rp</option>
                                <option value="percent">%</option>
                            </select>
                            <input type="number"
                                   x-model="item.line_discount_value"
                                   @input="recalculateTotals()"
                                   min="0"
                                   placeholder="0"
                                   class="w-16 bg-slate-800 border border-slate-700 rounded text-right text-xs text-white py-0.5 px-1.5 focus:ring-0">
                        </div>

                        <!-- Line Net Total -->
                        <div class="text-right">
                            <span class="text-xs font-bold text-emerald-400" x-text="formatIdr(item.net_line || calculateLocalLineNet(item))"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Totals & Discounts Section -->
        <div class="p-3 bg-slate-850 border-t border-slate-700/60 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-400">
                <span>Subtotal Barang:</span>
                <span class="font-mono text-slate-200" x-text="formatIdr(totals.gross_total)"></span>
            </div>
            <div class="flex justify-between text-slate-400" x-show="parseFloat(totals.item_discount_total) > 0">
                <span>Diskon Item:</span>
                <span class="font-mono text-rose-400" x-text="'- ' + formatIdr(totals.item_discount_total)"></span>
            </div>

            <!-- Transaction Discount Controls -->
            <div class="pt-1.5 border-t border-slate-700/40 flex items-center justify-between gap-2">
                <span class="text-slate-300">Diskon Transaksi:</span>
                <div class="flex items-center gap-1">
                    <select x-model="txDiscountType" @change="recalculateTotals()" class="bg-slate-900 border border-slate-700 rounded text-[10px] text-slate-300 py-0.5 px-1 focus:ring-0">
                        <option value="amount">Rp</option>
                        <option value="percent">%</option>
                    </select>
                    <input type="number"
                           x-model="txDiscountValue"
                           @input="recalculateTotals()"
                           min="0"
                           placeholder="0"
                           class="w-20 bg-slate-900 border border-slate-700 rounded text-right text-xs text-white py-0.5 px-1.5 focus:ring-0">
                </div>
            </div>

            <!-- Grand Total Display -->
            <div class="pt-2 border-t border-slate-700 flex items-baseline justify-between">
                <span class="text-sm font-bold text-white uppercase">Grand Total:</span>
                <span class="text-xl font-extrabold text-emerald-400 font-mono" x-text="formatIdr(totals.grand_total)"></span>
            </div>
        </div>

        <!-- Payment Section (Cash Only - PRD §5.5, §6.4) -->
        <div class="p-3 bg-slate-900 border-t border-slate-700/80 space-y-2.5">
            <!-- Cash Account Selection -->
            <div class="flex items-center justify-between text-xs">
                <label class="text-slate-400">Kas Penerima:</label>
                <select x-model="selectedCashAccountId" class="bg-slate-800 border border-slate-700 rounded-md text-xs text-white py-1 px-2 focus:ring-emerald-500">
                    @foreach($cashAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ $acc->id === $defaultCashAccount?->id ? 'selected' : '' }}>
                            {{ $acc->name }} ({{ $acc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tender Amount Input -->
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Uang Tunai Diterima (Rp):</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 font-bold text-sm">Rp</span>
                    <input type="text"
                           x-model="cashTendered"
                           @input="calculateChange()"
                           placeholder="0"
                           class="w-full pl-10 pr-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-lg font-bold text-white text-right focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono">
                </div>
            </div>

            <!-- Quick Amount Buttons -->
            <div class="grid grid-cols-4 gap-1.5 text-xs font-medium">
                <button type="button" @click="setTenderExact()" class="py-1.5 bg-slate-800 hover:bg-slate-700 rounded text-emerald-400 font-semibold border border-slate-700 transition-colors">
                    Uang Pas
                </button>
                <button type="button" @click="setTenderQuick(20000)" class="py-1.5 bg-slate-800 hover:bg-slate-700 rounded text-slate-200 border border-slate-700 transition-colors">
                    20 rb
                </button>
                <button type="button" @click="setTenderQuick(50000)" class="py-1.5 bg-slate-800 hover:bg-slate-700 rounded text-slate-200 border border-slate-700 transition-colors">
                    50 rb
                </button>
                <button type="button" @click="setTenderQuick(100000)" class="py-1.5 bg-slate-800 hover:bg-slate-700 rounded text-slate-200 border border-slate-700 transition-colors">
                    100 rb
                </button>
            </div>

            <!-- Kembalian Display -->
            <div class="p-2.5 rounded-lg border flex items-center justify-between"
                 :class="changeAmount >= 0 ? 'bg-emerald-950/40 border-emerald-800/60' : 'bg-rose-950/40 border-rose-800/60'">
                <span class="text-xs font-semibold" :class="changeAmount >= 0 ? 'text-emerald-300' : 'text-rose-300'">
                    <span x-text="changeAmount >= 0 ? 'Kembalian:' : 'Kurang Bayar:'"></span>
                </span>
                <span class="text-base font-extrabold font-mono"
                      :class="changeAmount >= 0 ? 'text-emerald-400' : 'text-rose-400'"
                      x-text="formatIdr(Math.abs(changeAmount))"></span>
            </div>

            <!-- Checkout Submit Button -->
            <button type="button"
                    @click="processCheckout()"
                    :disabled="isSubmitting || cart.length === 0 || changeAmount < 0"
                    :class="(isSubmitting || cart.length === 0 || changeAmount < 0) ? 'opacity-50 cursor-not-allowed bg-emerald-800' : 'bg-emerald-600 hover:bg-emerald-500 active:scale-[0.99]'"
                    class="w-full py-3 rounded-xl font-bold text-white text-sm tracking-wide transition-all flex items-center justify-center gap-2 shadow-lg">
                <template x-if="isSubmitting">
                    <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </template>
                <span x-text="isSubmitting ? 'Memproses Transaksi...' : 'Bayar Tunai & Cetak Struk [F9]'"></span>
            </button>
        </div>
    </div>

    <!-- Success Modal (Post-Checkout Receipt) -->
    <div x-show="showSuccessModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="bg-slate-850 rounded-2xl border border-slate-700 max-w-sm w-full p-6 text-center shadow-2xl">
            <div class="w-14 h-14 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <h3 class="text-lg font-bold text-white mb-1">Transaksi Berhasil!</h3>
            <p class="text-xs font-mono text-emerald-400 mb-4" x-text="lastSale?.transaction_number"></p>

            <div class="p-3 bg-slate-900 rounded-xl border border-slate-800 text-left text-xs space-y-2 mb-6">
                <div class="flex justify-between text-slate-400">
                    <span>Total Belanja:</span>
                    <span class="font-mono text-white font-bold" x-text="formatIdr(lastSale?.grand_total)"></span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Uang Tunai:</span>
                    <span class="font-mono text-white" x-text="formatIdr(lastSale?.cash_tendered)"></span>
                </div>
                <div class="flex justify-between text-slate-400 pt-1 border-t border-slate-800 font-bold">
                    <span class="text-emerald-400">Kembalian:</span>
                    <span class="font-mono text-emerald-400 text-sm" x-text="formatIdr(lastSale?.change_amount)"></span>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <a :href="lastSale?.receipt_url"
                   target="_blank"
                   class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold text-xs transition-colors flex items-center justify-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Cetak Nota Struk
                </a>
                <button type="button"
                        @click="startNewTransaction()"
                        class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg text-xs font-semibold transition-colors">
                    Transaksi Baru [Esc]
                </button>
            </div>
        </div>
    </div>

    <!-- Discount Override / Approval Modal (Maker-Checker PRD §4.2) -->
    <div x-show="showApprovalModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="bg-slate-850 rounded-2xl border border-amber-600/60 max-w-md w-full p-6 text-left shadow-2xl">
            <div class="flex items-center gap-3 text-amber-400 mb-3">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="text-base font-bold text-white">Otorisasi Diskon Supervisor</h3>
            </div>
            <p class="text-xs text-slate-300 mb-4" x-text="approvalErrorMessage || 'Diskon melebihi batas standar kasir. Masukkan kredensial supervisor/admin untuk menyetujui.'"></p>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">ID Pengguna Supervisor:</label>
                    <input type="number" x-model="approvalData.approver_id" placeholder="ID User" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Kata Sandi Supervisor:</label>
                    <input type="password" x-model="approvalData.approver_password" placeholder="Password" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Alasan Diskon Khusus:</label>
                    <input type="text" x-model="approvalData.reason" placeholder="Contoh: Promo loyalitas pelanggan" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white">
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="showApprovalModal = false" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-xs font-semibold text-slate-300">
                    Batal
                </button>
                <button type="button" @click="submitWithApproval()" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 rounded-lg text-xs font-bold text-white">
                    Setujui & Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function posApp() {
    return {
        products: [],
        cart: [],
        isLoading: false,
        isSubmitting: false,
        barcodeQuery: '',
        searchQuery: '',
        selectedCategory: null,
        scannerMessage: '',
        scannerMessageType: 'error',
        selectedCashAccountId: '{{ $defaultCashAccount?->id ?? "" }}',
        cashTendered: '',
        changeAmount: 0,
        txDiscountType: 'amount',
        txDiscountValue: '0',
        totals: {
            gross_total: '0.00',
            item_discount_total: '0.00',
            transaction_discount_total: '0.00',
            grand_total: '0.00'
        },
        lastSale: null,
        showSuccessModal: false,
        showApprovalModal: false,
        approvalErrorMessage: '',
        approvalData: {
            approver_id: '',
            approver_password: '',
            reason: ''
        },

        init() {
            this.fetchInitialProducts();
            this.restoreScannerFocus();

            // Keyboard Shortcuts
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F9') {
                    e.preventDefault();
                    this.processCheckout();
                } else if (e.key === 'Escape' && this.showSuccessModal) {
                    this.startNewTransaction();
                }
            });
        },

        restoreScannerFocus() {
            this.$nextTick(() => {
                if (this.$refs.barcodeInput) {
                    this.$refs.barcodeInput.focus();
                }
            });
        },

        fetchInitialProducts() {
            this.isLoading = true;
            fetch('/api/v1/products/lookup?q=a')
                .then(r => r.json())
                .then(res => {
                    this.products = res.data || [];
                })
                .catch(() => {})
                .finally(() => { this.isLoading = false; });
        },

        searchProducts() {
            this.isLoading = true;
            const url = '/api/v1/products/lookup?q=' + encodeURIComponent(this.searchQuery);
            fetch(url)
                .then(r => r.json())
                .then(res => {
                    this.products = res.data || [];
                })
                .catch(() => {})
                .finally(() => { this.isLoading = false; });
        },

        setCategory(catId) {
            this.selectedCategory = catId;
            this.isLoading = true;
            fetch('/api/v1/products/lookup?q=' + encodeURIComponent(this.searchQuery || ''))
                .then(r => r.json())
                .then(res => {
                    let list = res.data || [];
                    if (catId !== null) {
                        list = list.filter(p => p.category_id === catId);
                    }
                    this.products = list;
                })
                .finally(() => { this.isLoading = false; });
        },

        handleBarcodeScan() {
            const barcode = this.barcodeQuery.trim();
            if (!barcode) return;

            this.barcodeQuery = ''; // Clear immediately to prevent accidental duplicate triggers

            fetch('/pos/barcode/' + encodeURIComponent(barcode))
                .then(async res => {
                    const json = await res.json();
                    if (!res.ok) {
                        throw new Error(json.error?.message || 'Barcode tidak dikenal.');
                    }
                    return json;
                })
                .then(res => {
                    const prod = res.data;
                    this.addToCart(prod);
                    this.scannerMessage = `Berhasil menambahkan [${prod.name}]`;
                    this.scannerMessageType = 'success';
                })
                .catch(err => {
                    this.scannerMessage = err.message;
                    this.scannerMessageType = 'error';
                })
                .finally(() => {
                    this.restoreScannerFocus();
                });
        },

        addToCart(product) {
            const existing = this.cart.find(i => i.product_id === product.id);
            if (existing) {
                existing.quantity = (parseFloat(existing.quantity) + 1).toString();
            } else {
                this.cart.push({
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    selling_price: product.selling_price,
                    available_quantity: product.available_quantity,
                    decimal_precision: product.decimal_precision || 0,
                    quantity: '1',
                    line_discount_type: 'amount',
                    line_discount_value: '0',
                    net_line: product.selling_price
                });
            }
            this.recalculateTotals();
            this.restoreScannerFocus();
        },

        changeQty(index, delta) {
            const item = this.cart[index];
            const newQty = parseFloat(item.quantity) + delta;
            if (newQty <= 0) {
                this.removeItem(index);
                return;
            }
            item.quantity = newQty.toString();
            this.recalculateTotals();
        },

        validateQty(index) {
            const item = this.cart[index];
            const val = parseFloat(item.quantity);
            if (isNaN(val) || val <= 0) {
                item.quantity = '1';
            }
            this.recalculateTotals();
        },

        removeItem(index) {
            this.cart.splice(index, 1);
            this.recalculateTotals();
            this.restoreScannerFocus();
        },

        clearCart() {
            this.cart = [];
            this.recalculateTotals();
            this.cashTendered = '';
            this.changeAmount = 0;
            this.restoreScannerFocus();
        },

        calculateLocalLineNet(item) {
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.selling_price) || 0;
            const gross = qty * price;
            let disc = 0;
            if (item.line_discount_type === 'percent') {
                disc = gross * ((parseFloat(item.line_discount_value) || 0) / 100);
            } else {
                disc = parseFloat(item.line_discount_value) || 0;
            }
            return Math.max(0, gross - disc);
        },

        recalculateTotals() {
            if (this.cart.length === 0) {
                this.totals = {
                    gross_total: '0.00',
                    item_discount_total: '0.00',
                    transaction_discount_total: '0.00',
                    grand_total: '0.00'
                };
                this.calculateChange();
                return;
            }

            // Call server calculation preview for exact deterministic rounding
            fetch('/pos/calculate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    items: this.cart.map(i => ({
                        product_id: i.product_id,
                        quantity: i.quantity,
                        line_discount_type: i.line_discount_type,
                        line_discount_value: i.line_discount_value
                    })),
                    transaction_discount_type: this.txDiscountType,
                    transaction_discount_value: this.txDiscountValue,
                    discount_approval: this.approvalData.approver_id ? this.approvalData : null
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.data) {
                    this.totals = res.data;
                    res.data.lines.forEach((line, idx) => {
                        if (this.cart[idx]) {
                            this.cart[idx].net_line = line.net_line;
                        }
                    });
                }
            })
            .catch(() => {})
            .finally(() => {
                this.calculateChange();
            });
        },

        calculateChange() {
            const tender = parseFloat(this.cashTendered) || 0;
            const grand = parseFloat(this.totals.grand_total) || 0;
            this.changeAmount = tender - grand;
        },

        setTenderExact() {
            this.cashTendered = Math.ceil(parseFloat(this.totals.grand_total) || 0).toString();
            this.calculateChange();
        },

        setTenderQuick(amount) {
            this.cashTendered = amount.toString();
            this.calculateChange();
        },

        processCheckout() {
            if (this.cart.length === 0 || this.changeAmount < 0 || this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;
            this.scannerMessage = '';

            const payload = {
                items: this.cart.map(i => ({
                    product_id: i.product_id,
                    quantity: i.quantity,
                    line_discount_type: i.line_discount_type,
                    line_discount_value: i.line_discount_value
                })),
                transaction_discount_type: this.txDiscountType,
                transaction_discount_value: this.txDiscountValue,
                payment: {
                    method: 'cash',
                    cash_account_id: parseInt(this.selectedCashAccountId),
                    cash_tendered: this.cashTendered
                },
                discount_approval: this.approvalData.approver_id ? this.approvalData : null,
                idempotency_key: crypto.randomUUID()
            };

            fetch('/pos/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(async r => {
                const res = await r.json();
                if (!r.ok) {
                    if (r.status === 422 && res.error?.code === 'DISCOUNT_APPROVAL_REQUIRED') {
                        this.approvalErrorMessage = res.error.message;
                        this.showApprovalModal = true;
                        throw new Error(res.error.message);
                    }
                    throw new Error(res.error?.message || 'Gagal memproses transaksi.');
                }
                return res;
            })
            .then(res => {
                this.lastSale = res.data;
                this.showSuccessModal = true;
                this.cart = [];
                this.recalculateTotals();
            })
            .catch(err => {
                this.scannerMessage = err.message;
                this.scannerMessageType = 'error';
            })
            .finally(() => {
                this.isSubmitting = false;
            });
        },

        submitWithApproval() {
            this.showApprovalModal = false;
            this.processCheckout();
        },

        startNewTransaction() {
            this.showSuccessModal = false;
            this.lastSale = null;
            this.cashTendered = '';
            this.changeAmount = 0;
            this.approvalData = { approver_id: '', approver_password: '', reason: '' };
            this.restoreScannerFocus();
        },

        formatIdr(num) {
            const val = parseFloat(num) || 0;
            return 'Rp ' + val.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }
    };
}
</script>
@endpush
@endsection
