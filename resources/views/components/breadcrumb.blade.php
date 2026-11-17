@props(['items' => []])

<nav class="flex" aria-label="Breadcrumb">
    <ol role="list" class="flex items-center space-x-2 text-xs">
        <li>
            <a href="{{ url('/') }}" class="text-slate-400 hover:text-slate-600 flex items-center">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="sr-only">Home</span>
            </a>
        </li>

        @foreach($items as $label => $url)
            <li class="flex items-center">
                <svg class="h-3.5 w-3.5 text-slate-300 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path d="M5.555 17.776l8-16 .894.448-8 16-.894-.448z" />
                </svg>
                @if($url && !$loop->last)
                    <a href="{{ $url }}" class="ml-2 font-medium text-slate-500 hover:text-slate-700">
                        {{ $label }}
                    </a>
                @else
                    <span class="ml-2 font-semibold text-slate-800" aria-current="page">
                        {{ $label }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
