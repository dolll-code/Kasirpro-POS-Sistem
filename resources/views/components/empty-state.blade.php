@props([
    'title' => 'Belum ada data',
    'description' => 'Data yang Anda cari saat ini belum tersedia atau belum ditambahkan.',
    'icon' => null
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-6 bg-white rounded-xl border border-dashed border-slate-300']) }}>
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
        @if($icon)
            {{ $icon }}
        @else
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
            </svg>
        @endif
    </div>
    <h3 class="text-sm font-semibold text-slate-800">{{ $title }}</h3>
    <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">{{ $description }}</p>
    
    @isset($action)
        <div class="mt-5">
            {{ $action }}
        </div>
    @endisset
</div>
