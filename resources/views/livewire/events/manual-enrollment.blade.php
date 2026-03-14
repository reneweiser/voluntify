<div class="mx-auto max-w-7xl p-6">
    <div class="mb-4 flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.show', $event)" wire:navigate />
        <flux:heading size="xl">{{ __('Manual Enrollment') }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        <div class="space-y-6">
            {{-- Search for volunteer --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Select Volunteer') }}</flux:heading>

                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search by name or email...') }}"
                    icon="magnifying-glass"
                />

                @if (strlen($search) >= 2)
                    <div class="mt-3 space-y-2">
                        @forelse ($this->volunteers as $volunteer)
                            <div
                                wire:click="selectVolunteer({{ $volunteer->id }})"
                                class="flex cursor-pointer items-center justify-between rounded-lg border p-3 transition
                                    {{ $selectedVolunteerId === $volunteer->id
                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600' }}"
                            >
                                <div>
                                    <flux:text class="font-medium">{{ $volunteer->name }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-500">{{ $volunteer->email }}</flux:text>
                                </div>
                                @if ($selectedVolunteerId === $volunteer->id)
                                    <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                                @endif
                            </div>
                        @empty
                            <flux:text size="sm" class="py-2 text-center text-zinc-500">{{ __('No volunteers found') }}</flux:text>
                        @endforelse
                    </div>
                @endif
            </flux:card>

            {{-- Shift selection --}}
            @if ($this->selectedVolunteer)
                <flux:card>
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Select Shifts') }}</flux:heading>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('Enrolling: :name', ['name' => $this->selectedVolunteer->name]) }}
                            </flux:text>
                        </div>
                        <flux:button variant="ghost" size="sm" wire:click="clearSelection">{{ __('Change') }}</flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach ($this->jobs as $job)
                            <div>
                                <flux:heading size="base" class="mb-2">{{ $job->name }}</flux:heading>
                                <div class="space-y-2">
                                    @foreach ($job->shifts as $shift)
                                        @php
                                            $remaining = $shift->spotsRemaining();
                                            $isFull = $remaining === 0;
                                        @endphp
                                        <label class="flex items-center gap-3 rounded-lg border p-3
                                            {{ $isFull ? 'border-zinc-200 opacity-50 dark:border-zinc-700' : 'border-zinc-200 dark:border-zinc-700' }}">
                                            <flux:checkbox
                                                wire:model="selectedShifts"
                                                value="{{ $shift->id }}"
                                                :disabled="$isFull"
                                            />
                                            <div class="flex-1">
                                                <flux:text size="sm">
                                                    {{ $shift->starts_at->format('M d, g:i A') }} &ndash; {{ $shift->ends_at->format('g:i A') }}
                                                </flux:text>
                                            </div>
                                            <flux:badge size="sm" :color="$isFull ? 'red' : 'emerald'">
                                                {{ $isFull ? __('Full') : $remaining.' '.__('spots') }}
                                            </flux:badge>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2">
                                <flux:switch wire:model="sendNotification" />
                                <flux:text size="sm">{{ __('Send notification email') }}</flux:text>
                            </label>

                            <flux:button
                                variant="primary"
                                wire:click="enroll"
                                :disabled="empty($selectedShifts)"
                            >
                                {{ __('Enroll') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- Enrollment result --}}
            @if ($enrollmentResult)
                <flux:card>
                    @if ($enrollmentResult['newSignups'] > 0)
                        <flux:callout variant="success" class="mb-3">
                            {{ __(':count shift(s) enrolled successfully.', ['count' => $enrollmentResult['newSignups']]) }}
                        </flux:callout>
                    @endif

                    @if ($enrollmentResult['skippedFull'] > 0)
                        <flux:callout variant="warning" class="mb-3">
                            {{ __(':count shift(s) skipped (full).', ['count' => $enrollmentResult['skippedFull']]) }}
                        </flux:callout>
                    @endif

                    @if ($enrollmentResult['skippedDuplicate'] > 0)
                        <flux:callout variant="warning">
                            {{ __(':count shift(s) skipped (already enrolled).', ['count' => $enrollmentResult['skippedDuplicate']]) }}
                        </flux:callout>
                    @endif
                </flux:card>
            @endif
        </div>
    </x-events.layout>
</div>
