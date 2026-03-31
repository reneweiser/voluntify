<div>
    {{-- Screen reader: announce step transitions and manage focus --}}
    <span
        role="status"
        aria-live="polite"
        aria-atomic="true"
        class="sr-only"
        x-data="{
            announcement: '',
            init() {
                const labels = {
                    '{{ \App\Enums\WizardState::SelectingShifts->value }}': '{{ __('Step 1: Choose Your Shifts') }}',
                    '{{ \App\Enums\WizardState::GearAndFields->value }}': '{{ __('Step 2: Details') }}',
                    '{{ \App\Enums\WizardState::PersonalInfo->value }}': '{{ __('Step 3: Your Information') }}',
                    '{{ \App\Enums\WizardState::Confirming->value }}': '{{ __('Step 4: Confirm Your Signup') }}',
                };
                this.$watch(() => $wire.state, (state) => {
                    this.announcement = labels[state] || '';
                    this.$nextTick(() => {
                        const el = document.getElementById('step-heading-' + state);
                        if (el) { el.focus(); }
                    });
                });
            }
        }"
        x-text="announcement"
    ></span>

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

    @if ($state === \App\Enums\WizardState::PendingVerification)
        {{-- Pending verification state --}}
        <div class="rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/20 border border-blue-200 dark:border-blue-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                <flux:icon name="envelope" class="size-10 text-blue-600 dark:text-blue-400" />
            </div>
            <flux:heading size="lg" class="mt-4" id="step-heading-{{ \App\Enums\WizardState::PendingVerification->value }}" tabindex="-1">{{ __('Check Your Email') }}</flux:heading>
            <flux:text class="mt-2">{{ __('We\'ve sent a verification link to :email. Click the link to confirm your signup.', ['email' => e($volunteerEmail)]) }}</flux:text>
            <flux:text size="sm" class="mt-4 text-zinc-500 dark:text-zinc-400">{{ __('Your shifts are held for 20 minutes. Verify your email promptly to secure your spots.') }}</flux:text>
        </div>
    @elseif ($state === \App\Enums\WizardState::Complete)
        {{-- Success state --}}
        <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/30 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="check-circle" class="size-10 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="lg" class="mt-4" id="step-heading-{{ \App\Enums\WizardState::Complete->value }}" tabindex="-1">{{ __("You're signed up!") }}</flux:heading>
            <flux:text class="mt-2">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</flux:text>
            @if ($warningMessage)
                <flux:callout variant="warning" class="mt-4">{{ $warningMessage }}</flux:callout>
            @endif
        </div>
    @elseif ($state === \App\Enums\WizardState::Expired)
        {{-- Reservation expired state --}}
        <div class="rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/20 border border-amber-200 dark:border-amber-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                <flux:icon name="clock" class="size-10 text-amber-600 dark:text-amber-400" />
            </div>
            <flux:heading size="lg" class="mt-4" id="step-heading-{{ \App\Enums\WizardState::Expired->value }}" tabindex="-1">{{ __('Reservation Expired') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Your shift reservation has expired. Please select your shifts again.') }}</flux:text>
            <flux:button wire:click="restartSignup" variant="primary" class="mt-6">
                {{ __('Start Over') }}
            </flux:button>
        </div>
    @else
        {{-- Reservation timer (visible in steps 2-4) --}}
        @if ($reservationExpiresAt)
            <div x-data="reservationTimer($wire)"
                 x-show="remaining > 0"
                 x-cloak
                 role="timer"
                 aria-label="{{ __('Reservation countdown') }}"
                 :class="{
                     'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300': remaining > 300,
                     'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': remaining <= 300 && remaining > 60,
                     'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 animate-pulse': remaining <= 60,
                 }"
                 class="rounded-lg px-4 py-2 mb-6 transition-colors">
                <div class="flex items-center gap-2 text-sm">
                    <flux:icon name="clock" variant="mini" class="size-4" aria-hidden="true" />
                    <span>
                        {{ __('Reservation expires in') }}
                        <span x-text="formattedTime" class="font-mono font-medium" aria-hidden="true"></span>
                    </span>
                </div>
                {{-- Screen reader: announce at key milestones only (not every second) --}}
                <span class="sr-only" aria-live="polite" x-text="srAnnouncement"></span>
            </div>
        @endif

        {{-- Step progress indicator --}}
        @php
            $steps = [
                \App\Enums\WizardState::SelectingShifts->value => __('Shifts'),
            ];
            if ($this->hasGearOrFields) {
                $steps[\App\Enums\WizardState::GearAndFields->value] = __('Details');
            }
            $steps[\App\Enums\WizardState::PersonalInfo->value] = __('Info');
            $steps[\App\Enums\WizardState::Confirming->value] = __('Confirm');

            $stepValues = array_keys($steps);
            $currentIndex = array_search($state->value, $stepValues);
        @endphp
        <nav aria-label="{{ __('Signup steps') }}" class="flex items-center justify-center gap-2 mb-8">
            @foreach ($steps as $stepValue => $label)
                @php
                    $stepIndex = array_search($stepValue, $stepValues);
                    $isActive = $stepValue === $state->value;
                    $isComplete = $stepIndex < $currentIndex;
                    $isFuture = $stepIndex > $currentIndex;
                @endphp
                @if (!$loop->first)
                    <div class="h-px w-6 {{ $isComplete || $isActive ? 'bg-emerald-400' : 'bg-zinc-300 dark:bg-zinc-600' }}" aria-hidden="true"></div>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors
                    {{ $isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : '' }}
                    {{ $isComplete ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400' : '' }}
                    {{ $isFuture ? 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' : '' }}"
                    @if ($isActive) aria-current="step" @endif
                    @if ($isFuture) aria-disabled="true" @endif>
                    @if ($isComplete)
                        <flux:icon name="check" variant="mini" class="size-3.5" aria-hidden="true" />
                    @else
                        <span aria-hidden="true">{{ $loop->iteration }}</span>
                    @endif
                    {{ $label }}
                </span>
            @endforeach
        </nav>

        @if ($warningMessage && $state === \App\Enums\WizardState::SelectingShifts)
            <flux:callout variant="warning" class="mb-4">{{ $warningMessage }}</flux:callout>
        @endif

        @error('selectedShiftIds')
            <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
        @enderror

        {{-- Step 1: Select Shifts --}}
        <div x-show="$wire.state === '{{ \App\Enums\WizardState::SelectingShifts->value }}'" x-cloak>
            <div class="space-y-6 mb-8">
                <flux:heading size="lg" id="step-heading-{{ \App\Enums\WizardState::SelectingShifts->value }}" tabindex="-1">{{ __('Choose Your Shifts') }}</flux:heading>
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
                            @if ($job->instructions)
                                <a href="{{ route('events.jobs.cheat-sheet', ['publicToken' => $event->public_token, 'jobId' => $job->id]) }}" target="_blank"
                                   class="inline-flex items-center gap-1 mt-2 text-sm text-emerald-600 dark:text-emerald-400 hover:underline">
                                    <flux:icon name="document-text" variant="mini" class="size-4" />
                                    {{ __('View Instructions') }}
                                </a>
                            @endif
                        </div>

                        <div class="p-4 space-y-3">
                            @foreach ($job->shifts as $shift)
                                @php
                                    $spotsLeft = $shift->spotsRemaining();
                                    $isFull = $spotsLeft === 0;
                                    $isSelected = in_array($shift->id, $selectedShiftIds);
                                    $isConflicting = in_array($shift->id, $this->overlappingShiftIds);
                                    $shiftTimeLabel = $job->name.' — '.$shift->starts_at->format('M d, g:i A').' to '.$shift->ends_at->format('g:i A');
                                @endphp
                                <label
                                    class="flex items-center justify-between p-3 rounded-xl border-2 cursor-pointer transition-all duration-200
                                        {{ $isFull ? 'border-zinc-200 dark:border-zinc-700 opacity-50 cursor-not-allowed' : 'border-zinc-200 dark:border-zinc-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10' }}
                                        {{ $isSelected && $isConflicting ? 'border-amber-500 dark:border-amber-500 bg-amber-50 dark:bg-amber-900/20 shadow-sm' : '' }}
                                        {{ $isSelected && ! $isConflicting ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 shadow-sm' : '' }}"
                                    wire:key="shift-{{ $shift->id }}"
                                >
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" value="{{ $shift->id }}"
                                            wire:model.live="selectedShiftIds"
                                            @disabled($isFull)
                                            @if ($isFull) aria-label="{{ $shiftTimeLabel }} — {{ __('Full, no spots available') }}" @endif
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
                                    @elseif ($isConflicting && $isSelected)
                                        <flux:badge size="sm" color="yellow">{{ __('Conflict') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="emerald">{{ __('Open') }}</flux:badge>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if (count($this->overlappingShiftIds) > 0)
                <flux:callout variant="warning" class="mb-4">
                    {{ __('Some selected shifts overlap in time. Deselect the highlighted shifts before continuing.') }}
                </flux:callout>
            @endif

            <flux:button wire:click="reserveAndAdvance" variant="primary" class="w-full">
                {{ __('Continue') }}
            </flux:button>
        </div>

        {{-- Step 2: Gear & Custom Fields --}}
        @if ($this->hasGearOrFields)
            <div x-show="$wire.state === '{{ \App\Enums\WizardState::GearAndFields->value }}'" x-cloak
                 id="step-heading-{{ \App\Enums\WizardState::GearAndFields->value }}" tabindex="-1">
                {{-- Gear selection --}}
                @if ($this->gearItems->isNotEmpty())
                    <div class="space-y-4 mb-8">
                        <flux:heading size="lg">{{ __('Event Gear') }}</flux:heading>

                        @foreach ($this->gearItems as $item)
                            <div wire:key="gear-{{ $item->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 mb-1">{{ $item->name }}</div>
                                @if ($item->requires_size)
                                    <flux:field>
                                        <flux:label>{{ __('Size') }}</flux:label>
                                        <flux:select wire:model="gearSelections.{{ $item->id }}" placeholder="{{ __('Select size...') }}">
                                            @foreach ($item->available_sizes ?? [] as $size)
                                                <flux:select.option :value="$size">{{ $size }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="gearSelections.{{ $item->id }}" />
                                    </flux:field>
                                @else
                                    <flux:text size="sm" class="text-zinc-500">{{ __('Included with your signup') }}</flux:text>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Custom registration fields --}}
                @if ($this->customRegistrationFields->isNotEmpty())
                    <div class="space-y-4 mb-8">
                        <flux:heading size="lg">{{ __('Additional Information') }}</flux:heading>

                        @foreach ($this->customRegistrationFields as $field)
                            <flux:field wire:key="custom-field-{{ $field->id }}">
                                <flux:label>
                                    {{ $field->label }}
                                    @if (!$field->required)
                                        <span class="text-zinc-400 font-normal">({{ __('optional') }})</span>
                                    @endif
                                </flux:label>

                                @if ($field->type->value === 'text' && !empty($field->options['multiline']))
                                    <flux:textarea wire:model="customFieldResponses.{{ $field->id }}" placeholder="{{ $field->options['placeholder'] ?? '' }}" />
                                @elseif ($field->type->value === 'text')
                                    <flux:input wire:model="customFieldResponses.{{ $field->id }}" placeholder="{{ $field->options['placeholder'] ?? '' }}" />
                                @elseif ($field->type->value === 'select')
                                    <flux:select wire:model="customFieldResponses.{{ $field->id }}" placeholder="{{ __('Select...') }}">
                                        @foreach ($field->options['choices'] ?? [] as $choice)
                                            <flux:select.option :value="$choice">{{ $choice }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @elseif ($field->type->value === 'checkbox')
                                    <flux:checkbox wire:model="customFieldResponses.{{ $field->id }}" label="{{ $field->label }}" />
                                @endif

                                <flux:error name="customFieldResponses.{{ $field->id }}" />
                            </flux:field>
                        @endforeach
                    </div>
                @endif

                <div class="flex gap-3">
                    <flux:button wire:click="goBack" variant="ghost" class="flex-1">
                        {{ __('Back') }}
                    </flux:button>
                    <flux:button wire:click="advanceToPersonalInfo" variant="primary" class="flex-1">
                        {{ __('Continue') }}
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- Step 3: Personal Info --}}
        <div x-show="$wire.state === '{{ \App\Enums\WizardState::PersonalInfo->value }}'" x-cloak>
            <div class="space-y-4 mb-6">
                <flux:heading size="lg" id="step-heading-{{ \App\Enums\WizardState::PersonalInfo->value }}" tabindex="-1">{{ __('Your Information') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('First Name') }}</flux:label>
                    <flux:input wire:model="volunteerFirstName" placeholder="{{ __('Your first name') }}" />
                    <flux:error name="volunteerFirstName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Last Name') }}</flux:label>
                    <flux:input wire:model="volunteerLastName" placeholder="{{ __('Your last name') }}" />
                    <flux:error name="volunteerLastName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input type="email" wire:model="volunteerEmail" placeholder="{{ __('your@email.com') }}" />
                    <flux:error name="volunteerEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Phone') }} @unless($event->phone_required)<span class="text-zinc-400 font-normal">({{ __('optional') }})</span>@endunless</flux:label>
                    <flux:input type="tel" wire:model="volunteerPhone" placeholder="{{ __('+1 555 123 4567') }}" />
                    <flux:error name="volunteerPhone" />
                </flux:field>
            </div>

            <div class="flex gap-3">
                <flux:button wire:click="goBack" variant="ghost" class="flex-1">
                    {{ __('Back') }}
                </flux:button>
                <flux:button wire:click="advanceToConfirmation" variant="primary" class="flex-1">
                    {{ __('Continue') }}
                </flux:button>
            </div>
        </div>

        {{-- Step 4: Confirm --}}
        <div x-show="$wire.state === '{{ \App\Enums\WizardState::Confirming->value }}'" x-cloak>
            <div class="space-y-6 mb-6">
                <flux:heading size="lg" id="step-heading-{{ \App\Enums\WizardState::Confirming->value }}" tabindex="-1">{{ __('Confirm Your Signup') }}</flux:heading>

                {{-- Selected shifts summary --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:heading size="sm" class="mb-3">{{ __('Selected Shifts') }}</flux:heading>
                    <div class="space-y-2">
                        @foreach ($this->jobs as $job)
                            @foreach ($job->shifts as $shift)
                                @if (in_array($shift->id, $selectedShiftIds))
                                    <div class="flex items-center gap-2 text-sm" wire:key="confirm-shift-{{ $shift->id }}">
                                        <flux:icon name="check" variant="mini" class="size-4 text-emerald-500" />
                                        <span class="font-medium">{{ $job->name }}:</span>
                                        <span>{{ $shift->starts_at->format('M d, g:i A') }} &mdash; {{ $shift->ends_at->format('g:i A') }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Gear summary --}}
                @if ($this->gearItems->isNotEmpty())
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:heading size="sm" class="mb-3">{{ __('Gear') }}</flux:heading>
                        <div class="space-y-1">
                            @foreach ($this->gearItems as $item)
                                <div class="flex items-center gap-2 text-sm" wire:key="confirm-gear-{{ $item->id }}">
                                    <flux:icon name="check" variant="mini" class="size-4 text-emerald-500" />
                                    <span>{{ $item->name }}{{ $item->requires_size && isset($gearSelections[$item->id]) ? ' (' . $gearSelections[$item->id] . ')' : '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Custom field summary --}}
                @if ($this->customRegistrationFields->isNotEmpty())
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:heading size="sm" class="mb-3">{{ __('Additional Information') }}</flux:heading>
                        <div class="space-y-1">
                            @foreach ($this->customRegistrationFields as $field)
                                @if (!empty($customFieldResponses[$field->id]))
                                    <div class="flex items-center gap-2 text-sm" wire:key="confirm-field-{{ $field->id }}">
                                        <span class="font-medium">{{ $field->label }}:</span>
                                        <span>{{ is_bool($customFieldResponses[$field->id]) ? ($customFieldResponses[$field->id] ? __('Yes') : __('No')) : $customFieldResponses[$field->id] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Personal info summary --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:heading size="sm" class="mb-3">{{ __('Your Information') }}</flux:heading>
                    <div class="space-y-1 text-sm">
                        <div><span class="font-medium">{{ __('Name') }}:</span> {{ $volunteerFirstName }} {{ $volunteerLastName }}</div>
                        <div><span class="font-medium">{{ __('Email') }}:</span> {{ $volunteerEmail }}</div>
                        @if ($volunteerPhone)
                            <div><span class="font-medium">{{ __('Phone') }}:</span> {{ $volunteerPhone }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <flux:button wire:click="goBack" variant="ghost" class="flex-1">
                    {{ __('Back') }}
                </flux:button>
                <flux:button wire:click="submitSignup" variant="primary" class="flex-1">
                    {{ __('Sign Up') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
