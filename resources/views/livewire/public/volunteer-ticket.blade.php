<div>
    @if ($expired)
        <div class="text-center py-12">
            <flux:icon name="clock" class="mx-auto size-12 mb-4" style="color: #9a9a9a;" />
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Link Expired') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('This magic link has expired. Please request a new one from the event organizer.') }}</p>
        </div>
    @elseif ($ticket)
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="font-bebas text-white leading-none" style="font-size: clamp(2rem, 5vw, 2.8rem); letter-spacing: 0.04em;">{{ __('Your Ticket') }}</h1>
            <div class="accent-bar mt-3"><span></span><span></span><span></span></div>
            <p class="mt-3" style="color: #a1a1aa;">{{ $this->shiftSignups->first()?->shift?->volunteerJob?->event?->name ?? $ticket->project->name }}</p>
        </div>

        {{-- Volunteer info --}}
        <div class="mb-6">
            <span class="text-sm" style="color: #9a9a9a;">{{ __('Volunteer') }}</span>
            <h2 class="font-bebas text-white text-xl" style="letter-spacing: 0.04em;">{{ $volunteer->full_name }}</h2>
        </div>

        {{-- QR Code --}}
        <div class="mb-8 flex justify-center">
            <div class="rounded-lg bg-white p-4" style="box-shadow: 0 4px 16px rgba(0,0,0,0.3);">
                <div class="size-64">
                    {!! $ticket->qrCodeSvg() !!}
                </div>
            </div>
        </div>

        {{-- Portal link --}}
        <div class="mb-8 text-center">
            <a href="{{ route('volunteer.portal', $magicToken) }}" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border: 2px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); border-radius: 4px; font-size: 0.875rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; text-decoration: none; transition: border-color 0.2s, color 0.2s;">
                <flux:icon name="clipboard-document-list" variant="mini" class="size-4" />
                {{ __('Manage Your Shifts') }}
            </a>
        </div>

        {{-- Shift assignments --}}
        @if ($this->shiftSignups->isNotEmpty())
            <div>
                <h2 class="font-bebas text-white text-lg mb-3" style="letter-spacing: 0.04em;">{{ __('Your Shifts') }}</h2>
                <div class="space-y-3">
                    @foreach ($this->shiftSignups as $signup)
                        <div wire:key="signup-{{ $signup->id }}" class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border-left: 4px solid var(--brand);">
                            <div class="font-medium text-white">{{ $signup->shift->volunteerJob->name }}</div>
                            <div class="mt-1 text-sm" style="color: #a1a1aa;">
                                @php $tz = $signup->shift->volunteerJob->event->project->timezone ?? 'UTC'; @endphp
                                {{ $signup->shift->shift_date->setTimezone($tz)->format('M d, Y') }} — {{ $signup->shift->displayTimeRange($tz) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
