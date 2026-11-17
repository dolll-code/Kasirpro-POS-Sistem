@extends('layouts.app', [
    'title' => 'Detail Pemasok: ' . $supplier->name,
    'breadcrumbs' => [
        'Master Data' => route('master.index'),
        'Pemasok' => route('suppliers.index'),
        $supplier->name => null
    ]
])

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $supplier->name }}</h1>
            @if($supplier->is_active)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Aktif (Dapat Menerima PO)
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                    Nonaktif (Tidak Dapat Menerima PO Baru)
                </span>
            @endif
        </div>
        <div class="flex gap-2">
            @can('suppliers.manage')
                <a href="{{ route('suppliers.edit', $supplier) }}" class="inline-flex items-center px-3.5 py-2 rounded-md bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition gap-1.5">
                    Ubah Pemasok
                </a>
            @endcan
            <a href="{{ route('suppliers.index') }}" class="inline-flex items-center px-3.5 py-2 rounded-md border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                &larr; Kembali
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs divide-y divide-slate-100">
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Kode Pemasok</span>
                <span class="text-base font-mono font-bold text-slate-900 mt-1 block">{{ $supplier->supplier_code }}</span>
            </div>

            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Kontak Person</span>
                <span class="text-base font-medium text-slate-900 mt-1 block">{{ $supplier->contact_person ?? '-' }}</span>
            </div>

            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Telepon / WhatsApp</span>
                <span class="text-base font-medium text-slate-900 mt-1 block">{{ $supplier->phone ?? '-' }}</span>
            </div>

            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Alamat Email</span>
                <span class="text-base font-medium text-slate-900 mt-1 block">{{ $supplier->email ?? '-' }}</span>
            </div>

            <div class="sm:col-span-2">
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Alamat Kantor / Gudang</span>
                <p class="text-sm text-slate-700 mt-1">{{ $supplier->address ?? '-' }}</p>
            </div>

            @if($supplier->notes)
                <div class="sm:col-span-2">
                    <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Catatan Tambahan</span>
                    <p class="text-sm text-slate-700 mt-1">{{ $supplier->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
