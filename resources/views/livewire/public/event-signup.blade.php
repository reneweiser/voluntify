<div>
    {{-- Title image with hero treatment --}}
    @if ($event->titleImageUrl())
        <div class="mb-8 -mx-6 sm:mx-0 relative">
            <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="w-full max-h-72 object-cover sm:rounded-xl" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent sm:rounded-xl"></div>
        </div>
    @endif

    {{-- Event header --}}
    <div class="mb-8">
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
        @if ($event->description)
            <flux:text class="mt-2">{{ $event->description }}</flux:text>
        @endif
        <div class="mt-4 flex flex-wrap gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 text-sm text-emerald-700 dark:text-emerald-300">
                <flux:icon name="calendar" variant="mini" class="size-4" />
                {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('g:i A') }}
            </span>
            @if ($event->location)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 text-sm text-emerald-700 dark:text-emerald-300">
                    <flux:icon name="map-pin" variant="mini" class="size-4" />
                    {{ $event->location }}
                </span>
            @endif
        </div>
    </div>

    @if ($signupComplete)
        {{-- Success state --}}
        <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/30 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="check-circle" class="size-10 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __("You're signed up!") }}</flux:heading>
            <flux:text class="mt-2">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</flux:text>
            @if ($warningMessage)
                <flux:callout variant="warning" class="mt-4">{{ $warningMessage }}</flux:callout>
            @endif
        </div>
    @else
        {{-- Signup form --}}
        <form wire:submit="signup">
            {{-- Jobs & shifts selection --}}
            <div class="space-y-6 mb-8">
                <flux:heading size="lg">{{ __('Choose Your Shifts') }}</flux:heading>
                @if (count($selectedShiftIds) > 0)
                    <flux:text size="sm" class="mt-1">{{ count($selectedShiftIds) }} {{ __('shift(s) selected') }}</flux:text>
                @endif

                @foreach ($this->jobs as $job)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden" wire:key="job-{{ $job->id }}">
                        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <flux:heading size="sm">{{ $job->name }}</flux:heading>
                            @if ($job->description)
                                <flux:text size="sm" class="mt-1">{{ $job->description }}</flux:text>
                            @endif
                        </div>

                        <div class="p-4 space-y-3">
                            @foreach ($job->shifts as $shift)
                                @php
                                    $isFull = $shift->signups_count >= $shift->capacity;
                                    $spotsLeft = max(0, $shift->capacity - $shift->signups_count);
                                @endphp
                                <label
                                    class="flex items-center justify-between p-3 rounded-xl border-2 cursor-pointer transition-all duration-200
                                        {{ $isFull ? 'border-zinc-200 dark:border-zinc-700 opacity-50 cursor-not-allowed' : 'border-zinc-200 dark:border-zinc-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10' }}
                                        {{ in_array($shift->id, $selectedShiftIds) ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 shadow-sm' : '' }}"
                                    wire:key="shift-{{ $shift->id }}"
                                >
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" value="{{ $shift->id }}"
                                            wire:model="selectedShiftIds"
                                            {{ $isFull ? 'disabled' : '' }}
                                            class="accent-emerald-600"
                                        />
                                        <div>
                                            <flux:text size="sm" class="font-medium">
                                                {{ $shift->starts_at->format('M d, g:i A') }} &mdash; {{ $shift->ends_at->format('g:i A') }}
                                            </flux:text>
                                            <flux:text size="sm">
                                                {{ $spotsLeft }} {{ __('spots remaining') }}
                                            </flux:text>
                                        </div>
                                    </div>
                                    @if ($isFull)
                                        <flux:badge size="sm" color="red">{{ __('Full') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="emerald">{{ __('Open') }}</flux:badge>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @error('selectedShiftIds')
                <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
            @enderror

            {{-- Volunteer info --}}
            <div class="space-y-4 mb-6">
                <flux:heading size="lg">{{ __('Your Information') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="volunteerName" placeholder="{{ __('Your full name') }}" />
                    <flux:error name="volunteerName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input type="email" wire:model="volunteerEmail" placeholder="{{ __('your@email.com') }}" />
                    <flux:error name="volunteerEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Phone') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                    <flux:input type="tel" wire:model="volunteerPhone" placeholder="{{ __('+1 555 123 4567') }}" />
                    <flux:error name="volunteerPhone" />
                </flux:field>
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Sign Up to Volunteer') }}
            </flux:button>
        </form>
    @endif
</div>
