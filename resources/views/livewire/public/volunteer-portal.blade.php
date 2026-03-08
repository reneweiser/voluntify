<div>
    @if ($expired)
        <div class="text-center py-12">
            <flux:icon name="clock" class="mx-auto size-12 text-zinc-400 dark:text-zinc-500 mb-4" />
            <flux:heading size="lg">{{ __('Link Expired') }}</flux:heading>
            <flux:text class="mt-2">{{ __('This magic link has expired. Please request a new one from the event organizer.') }}</flux:text>
        </div>
    @elseif ($volunteer)
        {{-- Identity banner --}}
        <div class="mb-8">
            <flux:heading size="xl">{{ __('Your Portal') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Welcome back, :name', ['name' => $volunteer->name]) }}</flux:text>
        </div>

        {{-- Success banner --}}
        @if ($successMessage)
            <flux:callout variant="success" class="mb-6">{{ $successMessage }}</flux:callout>
        @endif

        {{-- Upcoming Shifts --}}
        <div class="mb-8">
            <flux:heading size="lg" class="mb-3">{{ __('Upcoming Shifts') }}</flux:heading>

            @if ($this->upcomingSignups->isEmpty())
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No upcoming shifts.') }}</flux:text>
            @else
                <div class="space-y-3">
                    @foreach ($this->upcomingSignups as $signup)
                        @php
                            $event = $signup->shift->volunteerJob->event;
                            $canCancel = $event->isCancellationAllowed() && $signup->isCancellable($event->cancellation_cutoff_hours);
                        @endphp
                        <div wire:key="upcoming-{{ $signup->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $signup->shift->volunteerJob->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $event->name }}
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $signup->shift->starts_at->format('M d, Y g:i A') }} &mdash; {{ $signup->shift->ends_at->format('g:i A') }}
                                    </div>
                                    @if ($event->isCancellationAllowed())
                                        <div class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
                                            {{ __('Cancellation allowed up to :hours hours before the shift', ['hours' => $event->cancellation_cutoff_hours]) }}
                                        </div>
                                    @endif
                                </div>
                                @if ($canCancel)
                                    <flux:button variant="danger" size="sm" wire:click="confirmCancel({{ $signup->id }})">
                                        {{ __('Cancel') }}
                                    </flux:button>
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
                <flux:heading size="lg" class="mb-3">{{ __('Event Gear') }}</flux:heading>
                <div class="space-y-3">
                    @foreach ($this->gearAssignments as $gear)
                        <div wire:key="gear-{{ $gear->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $gear->gearItem->name }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $gear->gearItem->event->name }}
                                    @if ($gear->size)
                                        &middot; {{ __('Size:') }} {{ $gear->size }}
                                    @endif
                                </div>
                            </div>
                            @if ($gear->picked_up_at)
                                <flux:badge size="sm" color="emerald">{{ __('Picked Up') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Not Picked Up') }}</flux:badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Announcements --}}
        <div class="mb-8">
            <flux:heading size="lg" class="mb-3">{{ __('Announcements') }}</flux:heading>

            @if ($this->announcements->isEmpty())
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No announcements.') }}</flux:text>
            @else
                <div class="space-y-3">
                    @foreach ($this->announcements as $announcement)
                        <div wire:key="announcement-{{ $announcement->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $announcement->subject }}</div>
                            <flux:text class="mt-2">{{ $announcement->body }}</flux:text>
                            <div class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
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
                <flux:heading size="lg" class="mb-3">{{ __('Past Shifts') }}</flux:heading>
                <div class="space-y-3">
                    @foreach ($this->pastSignups as $signup)
                        <div wire:key="past-{{ $signup->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white/50 dark:bg-zinc-800/50 p-4 opacity-75">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $signup->shift->volunteerJob->name }}</div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $signup->shift->volunteerJob->event->name }}
                            </div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $signup->shift->starts_at->format('M d, Y g:i A') }} &mdash; {{ $signup->shift->ends_at->format('g:i A') }}
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
                    <flux:heading size="lg">{{ __('Cancel Signup?') }}</flux:heading>
                    <flux:text>{{ __('Are you sure you want to cancel this shift signup? Your spot will be freed for other volunteers.') }}</flux:text>
                    <div class="flex gap-2 justify-end">
                        <flux:button variant="ghost" wire:click="dismissCancel">{{ __('Keep') }}</flux:button>
                        <flux:button variant="danger" wire:click="cancelSignup" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="cancelSignup">{{ __('Yes, Cancel Signup') }}</span>
                            <span wire:loading wire:target="cancelSignup">{{ __('Cancelling...') }}</span>
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Privacy notice --}}
        <div class="mt-8 text-center">
            <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">
                {{ __('This portal is linked to your volunteer profile. Do not share this URL.') }}
            </flux:text>
        </div>
    @endif
</div>
