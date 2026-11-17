@props([
    'align' => 'left'
])

@php
    $alignClasses = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ][$align] ?? 'text-left';
@endphp

<th scope="col" {{ $attributes->merge(['class' => 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 bg-slate-50/80 ' . $alignClasses]) }}>
    {{ $slot }}
</th>
