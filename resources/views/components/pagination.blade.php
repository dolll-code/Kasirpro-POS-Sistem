@props([
    'currentPage' => 1,
    'totalPages' => 1,
    'totalItems' => 0,
    'from' => 1,
    'to' => 1,
    'prevUrl' => null,
    'nextUrl' => null
])

<div class="flex items-center justify-between">
    <div class="flex flex-1 justify-between sm:hidden">
        @if($prevUrl)
            <a href="{{ $prevUrl }}" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Sebelumnya</a>
        @else
            <span class="relative inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">Sebelumnya</span>
        @endif

        @if($nextUrl)
            <a href="{{ $nextUrl }}" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Berikutnya</a>
        @else
            <span class="relative ml-3 inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed">Berikutnya</span>
        @endif
    </div>

    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
            <p class="text-xs text-slate-600">
                Menampilkan <span class="font-semibold text-slate-800">{{ $from }}</span> sampai <span class="font-semibold text-slate-800">{{ $to }}</span> dari <span class="font-semibold text-slate-800">{{ $totalItems }}</span> data
            </p>
        </div>
        <div>
            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                @if($prevUrl)
                    <a href="{{ $prevUrl }}" class="relative inline-flex items-center rounded-l-md px-2 py-1.5 text-slate-500 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Sebelumnya</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span class="relative inline-flex items-center rounded-l-md px-2 py-1.5 text-slate-300 ring-1 ring-inset ring-slate-200 bg-slate-50 cursor-not-allowed">
                        <span class="sr-only">Sebelumnya</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif

                <span class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-400 bg-slate-100 focus:z-20">
                    {{ $currentPage }}
                </span>

                @if($nextUrl)
                    <a href="{{ $nextUrl }}" class="relative inline-flex items-center rounded-r-md px-2 py-1.5 text-slate-500 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Berikutnya</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span class="relative inline-flex items-center rounded-r-md px-2 py-1.5 text-slate-300 ring-1 ring-inset ring-slate-200 bg-slate-50 cursor-not-allowed">
                        <span class="sr-only">Berikutnya</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </nav>
        </div>
    </div>
</div>
