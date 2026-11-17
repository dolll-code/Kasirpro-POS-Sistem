@props(['messages' => []])

@php
    $messages = (array) $messages;
@endphp

@if (!empty($messages))
    <ul {{ $attributes->merge(['class' => 'text-xs text-rose-600 font-medium space-y-1 mt-1.5']) }}>
        @foreach ($messages as $message)
            <li class="flex items-center gap-1">
                <svg class="h-3 w-3 shrink-0 text-rose-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif
