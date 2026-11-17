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

<td {{ $attributes->merge(['class' => 'px-4 py-3 whitespace-nowrap text-sm text-slate-700 ' . $alignClasses]) }}>
    {{ $slot }}
</td>
