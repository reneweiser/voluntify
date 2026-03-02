<div>
    @if ($expired)
        <div class="text-center py-12">
            <flux:icon name="clock" class="mx-auto size-12 text-zinc-400 dark:text-zinc-500 mb-4" />
            <flux:heading size="lg">Link Expired</flux:heading>
            <flux:text class="mt-2">This magic link has expired. Please request a new one from the event organizer.</flux:text>
        </div>
    @elseif ($ticket)
        {{-- Event header --}}
        <div class="mb-8">
            <flux:heading size="xl">Your Ticket</flux:heading>
            <flux:text class="mt-1">{{ $ticket->event->name }}</flux:text>
        </div>

        {{-- Volunteer info --}}
        <div class="mb-6">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Volunteer</flux:text>
            <flux:heading size="lg">{{ $volunteer->name }}</flux:heading>
        </div>

        {{-- QR Code --}}
        <div class="mb-8 flex justify-center">
            <div class="rounded-xl bg-white p-4 shadow-sm border border-zinc-200 dark:border-zinc-700">
                <div class="size-64">
                    {!! $ticket->qrCodeSvg() !!}
                </div>
            </div>
        </div>

        {{-- Shift assignments --}}
        @if ($this->shiftSignups->isNotEmpty())
            <div>
                <flux:heading size="base" class="mb-3">Your Shifts</flux:heading>
                <div class="space-y-3">
                    @foreach ($this->shiftSignups as $signup)
                        <div wire:key="signup-{{ $signup->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $signup->shift->volunteerJob->name }}</div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $signup->shift->starts_at->format('M d, Y g:i A') }} &mdash; {{ $signup->shift->ends_at->format('g:i A') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
