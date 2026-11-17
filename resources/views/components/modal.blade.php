@props([
    'name',
    'title' => 'Konfirmasi Tindakan',
    'maxWidth' => 'md'
])

@php
    $maxWidthClasses = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth] ?? 'sm:max-w-md';
@endphp

<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
     x-on:keydown.escape.window="show = false"
     x-show="show"
     x-cloak
     class="relative z-50"
     role="dialog"
     aria-modal="true">

    <!-- Backdrop -->
    <div x-show="show"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto p-4 sm:p-6 md:p-20 flex min-h-full items-end sm:items-center justify-center text-center">
        <!-- Modal panel -->
        <div x-show="show"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             @click.away="show = false"
             class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 w-full {{ $maxWidthClasses }} border border-slate-200">

            <!-- Modal Header -->
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-base font-semibold leading-6 text-slate-900">
                    {{ $title }}
                </h3>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 rounded-lg p-1 hover:bg-slate-100">
                    <span class="sr-only">Tutup</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-5 py-4 text-sm text-slate-600">
                {{ $slot }}
            </div>

            <!-- Modal Footer -->
            @isset($footer)
                <div class="px-5 py-3.5 bg-slate-50/70 border-t border-slate-200 flex flex-row-reverse gap-2">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
