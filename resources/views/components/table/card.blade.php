@props([
    'title' => null,
    'description' => null,
    'actions' => null
])

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    @if($title || $actions)
        <div class="px-5 py-4 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50">
            <div>
                @if($title)
                    <h2 class="text-base font-semibold text-slate-800">{{ $title }}</h2>
                @endif
                @if($description)
                    <p class="text-xs text-slate-500 mt-0.5">{{ $description }}</p>
                @endif
            </div>
            @if($actions)
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/50">
            {{ $footer }}
        </div>
    @endisset
</div>
