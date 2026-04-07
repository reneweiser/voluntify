<div
    class="flex flex-1 items-center justify-center p-6"
    @if ($formDisabled) wire:poll.30s="$refresh" @endif
>
    <div class="w-full max-w-sm space-y-6 text-center">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $scannerName }}</h1>
            <p class="mt-1 text-sm text-zinc-400">
                Window: {{ $startsAt }} &ndash; {{ $endsAt }}
            </p>
        </div>

        @if ($errorMessage)
            <div role="alert" aria-live="assertive" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                {{ $errorMessage }}
            </div>
        @endif

        @if (! $formDisabled)
            <form wire:submit="authenticate" class="space-y-4">
                <div>
                    <label for="authCode" class="mb-1 block text-sm font-medium text-zinc-300">
                        Enter 6-digit code
                    </label>
                    <input
                        id="authCode"
                        wire:model="authCode"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-center text-2xl font-mono tracking-widest text-white placeholder-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                        placeholder="000000"
                        autofocus
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-zinc-900"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Authenticate</span>
                    <span wire:loading>Verifying...</span>
                </button>
            </form>
        @endif
    </div>
</div>
