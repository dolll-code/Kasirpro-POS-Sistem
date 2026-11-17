<div class="space-y-3 mb-6">
    <!-- Success Alert -->
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition class="rounded-lg bg-emerald-50 p-4 border border-emerald-200">
            <div class="flex items-start">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 text-sm text-emerald-800 font-medium">
                    {{ session('success') }}
                </div>
                <div class="ml-auto pl-3">
                    <button type="button" @click="show = false" class="inline-flex rounded-md p-1.5 text-emerald-500 hover:bg-emerald-100 focus:outline-none">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Alert -->
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-transition class="rounded-lg bg-rose-50 p-4 border border-rose-200">
            <div class="flex items-start">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-rose-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 text-sm text-rose-800 font-medium">
                    {{ session('error') }}
                </div>
                <div class="ml-auto pl-3">
                    <button type="button" @click="show = false" class="inline-flex rounded-md p-1.5 text-rose-500 hover:bg-rose-100 focus:outline-none">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Warning Alert -->
    @if(session('warning'))
        <div x-data="{ show: true }" x-show="show" x-transition class="rounded-lg bg-amber-50 p-4 border border-amber-200">
            <div class="flex items-start">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 text-sm text-amber-800 font-medium">
                    {{ session('warning') }}
                </div>
                <div class="ml-auto pl-3">
                    <button type="button" @click="show = false" class="inline-flex rounded-md p-1.5 text-amber-500 hover:bg-amber-100 focus:outline-none">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Validation Errors List -->
    @if(isset($errors) && $errors->any())
        <div x-data="{ show: true }" x-show="show" x-transition class="rounded-lg bg-rose-50 p-4 border border-rose-200">
            <div class="flex items-start">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-rose-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-semibold text-rose-800">Terdapat kesalahan pada input formulir:</h3>
                    <ul role="list" class="mt-1.5 list-disc pl-5 text-xs text-rose-700 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="ml-auto pl-3">
                    <button type="button" @click="show = false" class="inline-flex rounded-md p-1.5 text-rose-500 hover:bg-rose-100 focus:outline-none">
                        <span class="sr-only">Tutup</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
