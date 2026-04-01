<div
    x-data="scannerApp({
        scannerId: {{ $scannerId }},
        scannerType: '{{ $scannerType }}',
        modes: @js($modes),
        scannerToken: '{{ $scannerToken }}',
        dataUrl: '{{ $this->dataUrl }}',
        syncUrl: '{{ $this->syncUrl }}',
        gearPickupUrl: '{{ $this->gearPickupUrl }}'
    })"
    class="flex min-h-screen flex-col"
>
    {{-- Header --}}
    <header class="flex items-center justify-between bg-zinc-800 px-4 py-3">
        <h1 class="text-lg font-semibold text-white">{{ $scannerName }}</h1>
        <span
            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="scannerType === 'entry_staff'
                ? 'bg-blue-500/20 text-blue-300'
                : 'bg-purple-500/20 text-purple-300'"
            x-text="scannerType === 'entry_staff' ? 'Entry Staff' : 'Volunteer Admin'"
        ></span>
    </header>

    @if ($hintText)
        <div class="bg-zinc-800/50 px-4 py-2 text-sm text-zinc-400">
            {{ $hintText }}
        </div>
    @endif

    {{-- Scanner content --}}
    <main class="flex flex-1 flex-col p-4">
        @if ($scannerType === 'entry_staff')
            {{-- Entry Staff: QR viewfinder + result panel --}}
            <div class="flex flex-1 flex-col items-center justify-center space-y-4">
                <div id="scanner-viewfinder" class="aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                </div>

                {{-- Result panel --}}
                <div
                    x-show="state !== 'idle' && state !== 'scanning'"
                    x-transition
                    role="alert"
                    aria-live="assertive"
                    class="w-full max-w-sm rounded-xl p-4"
                    :class="{
                        'bg-emerald-500/10 border border-emerald-500/30': state === 'confirmed',
                        'bg-amber-500/10 border border-amber-500/30': state === 'duplicate',
                        'bg-red-500/10 border border-red-500/30': state === 'invalid',
                        'bg-zinc-800 border border-zinc-700': state === 'loading'
                    }"
                    x-cloak
                >
                    <p class="text-center text-sm text-white" x-text="resultMessage"></p>
                </div>
            </div>
        @else
            {{-- Volunteer Admin: QR viewfinder + volunteer panel + gear pickup --}}
            <div class="flex flex-1 flex-col items-center justify-center space-y-4">
                <div id="scanner-viewfinder" class="aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                </div>

                {{-- Volunteer info panel --}}
                <div
                    x-show="selectedVolunteer"
                    x-transition
                    class="w-full max-w-sm rounded-xl border border-zinc-700 bg-zinc-800 p-4"
                    x-cloak
                >
                    <p class="text-lg font-semibold text-white" x-text="selectedVolunteer?.name"></p>
                    <p class="text-sm text-zinc-400" x-text="selectedVolunteer?.email"></p>
                </div>
            </div>
        @endif
    </main>
</div>
