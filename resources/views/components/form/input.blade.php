@props([
    'disabled' => false,
    'error' => false,
    'type' => 'text'
])

@php
    $baseClasses = 'block w-full rounded-lg border text-sm transition-colors duration-150 focus:outline-none focus:ring-2 sm:leading-6 px-3 py-2 ';
    $stateClasses = $error
        ? 'border-rose-300 text-rose-900 placeholder-rose-300 focus:border-rose-500 focus:ring-rose-500/20 bg-rose-50/30'
        : 'border-slate-300 text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500/20 bg-white hover:border-slate-400';
    $disabledClasses = $disabled ? 'bg-slate-100 text-slate-500 border-slate-200 cursor-not-allowed select-none' : '';
@endphp

<input type="{{ $type }}"
       {{ $disabled ? 'disabled' : '' }}
       {{ $attributes->merge(['class' => $baseClasses . $stateClasses . ' ' . $disabledClasses]) }}>
