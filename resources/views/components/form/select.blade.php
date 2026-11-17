@props([
    'disabled' => false,
    'error' => false
])

@php
    $baseClasses = 'block w-full rounded-lg border text-sm transition-colors duration-150 focus:outline-none focus:ring-2 sm:leading-6 px-3 py-2 bg-white ';
    $stateClasses = $error
        ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20 bg-rose-50/30'
        : 'border-slate-300 text-slate-900 focus:border-slate-600 focus:ring-slate-600/20 hover:border-slate-400';
    $disabledClasses = $disabled ? 'bg-slate-100 text-slate-500 border-slate-200 cursor-not-allowed select-none' : '';
@endphp

<select {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => $baseClasses . $stateClasses . ' ' . $disabledClasses]) }}>
    {{ $slot }}
</select>
