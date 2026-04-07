<div>
    @if ($expired)
        <div class="text-center py-12">
            <flux:icon name="clock" class="mx-auto size-12 mb-4" style="color: #9a9a9a;" />
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Link Expired') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('This magic link has expired. Please request a new one from the event organizer.') }}</p>
        </div>
    @elseif ($volunteer)
        {{-- Identity banner --}}
        <div class="mb-8">
            <h1 class="font-bebas text-white leading-none" style="font-size: clamp(2rem, 5vw, 2.8rem); letter-spacing: 0.04em;">{{ __('Your Portal') }}</h1>
            <div class="accent-bar mt-3"><span></span><span></span><span></span></div>
            <p class="mt-3" style="color: #a1a1aa;">{{ __('Welcome back, :name', ['name' => $volunteer->full_name]) }}</p>
        </div>

        {{-- Back to ticket --}}
        @if ($hasTicket)
            <div class="mb-6">
                <a href="{{ route('volunteer.ticket', $magicToken) }}" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border: 2px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); border-radius: 4px; font-size: 0.875rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; text-decoration: none; transition: border-color 0.2s, color 0.2s;">
                    <flux:icon name="ticket" variant="mini" class="size-4" />
                    {{ __('View Your Ticket') }}
                </a>
            </div>
        @endif

        {{-- Success banner --}}
        @if ($successMessage)
            <div class="rounded-lg p-3 mb-6 text-sm" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2); color: #6ee7b7;">
                {{ $successMessage }}
            </div>
        @endif

        {{-- Portal top banner hint --}}
        @if ($this->hintPortalTopBanner)
            <div class="rounded-lg p-3 mb-6 text-sm" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); color: #93c5fd;">
                {{ $this->hintPortalTopBanner }}
            </div>
        @endif

        {{-- Upcoming Shifts --}}
        <div class="mb-8">
            <h2 class="font-bebas text-white text-lg mb-3" style="letter-spacing: 0.04em;">{{ __('Upcoming Shifts') }}</h2>

            @if ($this->hintPortalShiftsSection)
                <p class="mb-3 text-sm" style="color: #9a9a9a;">{{ $this->hintPortalShiftsSection }}</p>
            @endif

            @if ($this->upcomingSignups->isEmpty())
                <p style="color: #9a9a9a;">{{ __('No upcoming shifts.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($this->upcomingSignups as $signup)
                        @php
                            $event = $signup->shift->volunteerJob->event;
                            $project = $event->project;
                            $canCancel = $project->isCancellationAllowed() && $signup->isCancellable($project->cancellation_cutoff_hours);
                        @endphp
                        <div wire:key="upcoming-{{ $signup->id }}" class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border-left: 4px solid var(--brand);">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium text-white">{{ $signup->shift->volunteerJob->name }}</div>
                                    <div class="mt-1 text-sm" style="color: #a1a1aa;">
                                        {{ $event->name }}
                                    </div>
                                    <div class="mt-1 text-sm" style="color: #a1a1aa;">
                                        {{ $signup->shift->shift_date->setTimezone($project->timezone ?? 'UTC')->format('M d, Y') }} — {{ $signup->shift->displayTimeRange($project->timezone ?? 'UTC') }}
                                    </div>
                                    @if ($project->isCancellationAllowed())
                                        <div class="mt-2 text-xs" style="color: #9a9a9a;">
                                            {{ __('Cancellation allowed up to :hours hours before the shift', ['hours' => $project->cancellation_cutoff_hours]) }}
                                        </div>
                                    @endif
                                </div>
                                @if ($canCancel)
                                    <button wire:click="confirmCancel({{ $signup->id }})" class="shrink-0 text-sm font-medium px-3 py-1.5 rounded" style="background: rgba(230,57,70,0.15); color: #fca5a5; border: none; cursor: pointer; transition: background 0.2s;">
                                        {{ __('Cancel') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Event Gear --}}
        @if ($this->gearAssignments->isNotEmpty())
            <div class="mb-8">
                <h2 class="font-bebas text-white text-lg mb-3" style="letter-spacing: 0.04em;">{{ __('Event Gear') }}</h2>
                <div class="space-y-3">
                    @foreach ($this->gearAssignments as $gear)
                        <div wire:key="gear-{{ $gear->id }}" class="flex items-center justify-between rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                            <div>
                                <div class="font-medium text-white">{{ $gear->gearItem->name }}</div>
                                <div class="mt-1 text-sm" style="color: #a1a1aa;">
                                    {{ $gear->gearItem->project->name }}
                                    @if ($gear->size)
                                        &middot; {{ __('Size:') }} {{ $gear->size }}
                                    @endif
                                </div>
                            </div>
                            @if ($gear->isPickedUp())
                                <span class="text-xs font-semibold px-2.5 py-1 rounded" style="background: rgba(5,150,105,0.15); color: #6ee7b7;">{{ __('Picked Up') }}</span>
                            @else
                                <span class="text-xs font-semibold px-2.5 py-1 rounded" style="background: rgba(255,255,255,0.08); color: #9a9a9a;">{{ __('Not Picked Up') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Registration Info --}}
        @if ($this->customFieldResponses->isNotEmpty())
            <div class="mb-8">
                <h2 class="font-bebas text-white text-lg mb-3" style="letter-spacing: 0.04em;">{{ __('Registration Info') }}</h2>
                @foreach ($this->customFieldResponses->groupBy(fn ($r) => $r->field->event?->name ?? $r->field->project?->name ?? __('General')) as $eventName => $responses)
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: #9a9a9a;">{{ $eventName }}</p>
                        <div class="space-y-2">
                            @foreach ($responses as $response)
                                <div class="flex items-center justify-between rounded-lg p-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                                    <span class="text-sm" style="color: #9a9a9a;">{{ $response->field->label }}</span>
                                    <span class="text-sm text-white">{{ $response->field->type->displayValue($response->value) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Announcements --}}
        <div class="mb-8">
            <h2 class="font-bebas text-white text-lg mb-3" style="letter-spacing: 0.04em;">{{ __('Announcements') }}</h2>

            @if ($this->announcements->isEmpty())
                <p style="color: #9a9a9a;">{{ __('No announcements.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($this->announcements as $announcement)
                        <div wire:key="announcement-{{ $announcement->id }}" class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="font-medium text-white">{{ $announcement->subject }}</div>
                            <p class="mt-2 text-sm" style="color: #a1a1aa;">{{ $announcement->body }}</p>
                            <div class="mt-2 text-xs" style="color: #9a9a9a;">
                                {{ $announcement->event->name }} &middot; {{ $announcement->sent_at->diffForHumans() }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Past Shifts --}}
        @if ($this->pastSignups->isNotEmpty())
            <div class="mb-8">
                <h2 class="font-bebas text-white text-lg mb-3" style="letter-spacing: 0.04em;">{{ __('Past Shifts') }}</h2>
                <div class="space-y-3">
                    @foreach ($this->pastSignups as $signup)
                        <div wire:key="past-{{ $signup->id }}" class="rounded-lg p-4" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); opacity: 0.6;">
                            <div class="font-medium text-white">{{ $signup->shift->volunteerJob->name }}</div>
                            <div class="mt-1 text-sm" style="color: #a1a1aa;">
                                {{ $signup->shift->volunteerJob->event->name }}
                            </div>
                            <div class="mt-1 text-sm" style="color: #a1a1aa;">
                                {{ $signup->shift->shift_date->setTimezone($project->timezone ?? 'UTC')->format('M d, Y') }} — {{ $signup->shift->displayTimeRange($project->timezone ?? 'UTC') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Cancel confirmation modal --}}
        @if ($cancellingSignupId)
            <flux:modal wire:model="cancellingSignupId" class="max-w-sm">
                <div class="space-y-4">
                    <h3 class="font-bebas text-white text-xl" style="letter-spacing: 0.04em;">{{ __('Cancel Signup?') }}</h3>
                    <p style="color: #a1a1aa;">{{ __('Are you sure you want to cancel this shift signup? Your spot will be freed for other volunteers.') }}</p>
                    <div class="flex gap-2 justify-end">
                        <button wire:click="dismissCancel" style="padding: 0.5rem 1rem; background: transparent; color: rgba(255,255,255,0.7); border: 2px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.875rem; font-weight: 600; cursor: pointer;">
                            {{ __('Keep') }}
                        </button>
                        <button wire:click="cancelSignup" wire:loading.attr="disabled" style="padding: 0.5rem 1rem; background: var(--red); color: white; border-radius: 4px; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; transition: opacity 0.2s;">
                            <span wire:loading.remove wire:target="cancelSignup">{{ __('Yes, Cancel Signup') }}</span>
                            <span wire:loading wire:target="cancelSignup">{{ __('Cancelling...') }}</span>
                        </button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Privacy notice --}}
        <div class="mt-8 text-center">
            <p class="text-sm" style="color: #9a9a9a;">
                {{ __('This portal is linked to your volunteer profile. Do not share this URL.') }}
            </p>
        </div>
    @endif
</div>
