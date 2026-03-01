<div>
    {{-- Event header --}}
    <div class="mb-8">
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
        @if ($event->description)
            <flux:text class="mt-2">{{ $event->description }}</flux:text>
        @endif
        <div class="mt-3 flex flex-wrap gap-4">
            <flux:text size="sm">
                {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('g:i A') }}
            </flux:text>
            @if ($event->location)
                <flux:text size="sm">{{ $event->location }}</flux:text>
            @endif
        </div>
    </div>

    @if ($signupComplete)
        {{-- Success state --}}
        <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto h-12 w-12 text-green-500" />
            <flux:heading size="lg" class="mt-4">{{ __("You're signed up!") }}</flux:heading>
            <flux:text class="mt-2">{{ __('Check your email for a confirmation with your ticket details.') }}</flux:text>
        </div>
    @else
        {{-- Signup form --}}
        <form wire:submit="signup">
            {{-- Jobs & shifts selection --}}
            <div class="space-y-6 mb-8">
                <flux:heading size="lg">{{ __('Choose a Shift') }}</flux:heading>

                @foreach ($this->jobs as $job)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700" wire:key="job-{{ $job->id }}">
                        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
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
                                    class="flex items-center justify-between p-3 rounded-md border cursor-pointer transition-colors
                                        {{ $isFull ? 'border-zinc-200 dark:border-zinc-700 opacity-50 cursor-not-allowed' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/50' }}
                                        {{ $selectedShiftId == $shift->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                    wire:key="shift-{{ $shift->id }}"
                                >
                                    <div class="flex items-center gap-3">
                                        <input type="radio" name="shift" value="{{ $shift->id }}"
                                            wire:model="selectedShiftId"
                                            {{ $isFull ? 'disabled' : '' }}
                                            class="accent-blue-600"
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
                                        <flux:badge size="sm" color="green">{{ __('Open') }}</flux:badge>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @error('selectedShiftId')
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
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Sign Up to Volunteer') }}
            </flux:button>
        </form>
    @endif
</div>
