@props([
    'title' => 'Terjadi Kesalahan Sistem',
    'message' => 'Sistem tidak dapat memproses permintaan Anda saat ini. Silakan coba beberapa saat lagi.',
    'requestId' => null
])

<div {{ $attributes->merge(['class' => 'text-center py-10 px-6 bg-rose-50/50 rounded-xl border border-rose-200/80']) }}>
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 mb-3">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
    </div>
    <h3 class="text-base font-semibold text-rose-900">{{ $title }}</h3>
    <p class="mt-1.5 text-xs text-rose-700 max-w-md mx-auto leading-relaxed">{{ $message }}</p>

    @php
        $reqId = $requestId ?? request()->header('X-Request-Id');
    @endphp

    @if($reqId)
        <div class="mt-4 inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-white border border-rose-200 text-[11px] font-mono text-slate-500 shadow-xs">
            <span class="text-slate-400">Request ID:</span>
            <span class="font-semibold text-rose-700 select-all">{{ $reqId }}</span>
        </div>
    @endif

    @isset($action)
        <div class="mt-5">
            {{ $action }}
        </div>
    @endisset
</div>
