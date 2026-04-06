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

    {{-- Hero image --}}
    @if ($event->titleImageUrl())
        <div class="mb-10 -mx-6 sm:mx-0 relative overflow-hidden sm:rounded-lg">
            <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="w-full max-h-96 object-cover" loading="eager" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#1A1A1A]/70 via-[#1A1A1A]/30 to-transparent sm:rounded-lg"></div>
        </div>
    @endif

    {{-- Event header --}}
    <div class="mb-10">
        <h1 class="font-bebas text-white leading-none" style="font-size: clamp(2.2rem, 5vw, 3.2rem); letter-spacing: 0.04em;">
            {{ $event->name }}
        </h1>
        <div class="accent-bar mt-3"><span></span><span></span><span></span></div>
        @if ($event->description)
            <p class="mt-4" style="color: #a1a1aa; font-size: 1.05rem; line-height: 1.6;">{{ $event->description }}</p>
        @endif
        <div class="mt-5 flex flex-wrap gap-2.5">
            <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; background: rgba(5,150,105,0.15); color: #6ee7b7; border-radius: 4px; font-size: 0.875rem;">
                <flux:icon name="calendar" variant="mini" class="size-4" />
                {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('g:i A') }}
            </span>
            @if ($event->location)
                <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; background: rgba(5,150,105,0.15); color: #6ee7b7; border-radius: 4px; font-size: 0.875rem;">
                    <flux:icon name="map-pin" variant="mini" class="size-4" />
                    {{ $event->location }}
                </span>
            @endif
        </div>
    </div>

    @if ($state === \App\Enums\WizardState::PendingVerification)
        {{-- Pending verification --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(59,130,246,0.15);">
                <flux:icon name="envelope" class="size-10" style="color: #60a5fa;" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;" id="step-heading-{{ \App\Enums\WizardState::PendingVerification->value }}" tabindex="-1">{{ __('Check Your Email') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('We\'ve sent a verification link to :email. Click the link to confirm your signup.', ['email' => e($volunteerEmail)]) }}</p>
            <p class="mt-4 text-sm" style="color: #9a9a9a;">{{ __('Your shifts are held for 20 minutes. Verify your email promptly to secure your spots.') }}</p>
        </div>
    @elseif ($state === \App\Enums\WizardState::Complete)
        {{-- Success --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(5,150,105,0.15);">
                <flux:icon name="check-circle" class="size-10" style="color: var(--brand);" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;" id="step-heading-{{ \App\Enums\WizardState::Complete->value }}" tabindex="-1">{{ __("You're signed up!") }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</p>
            @if ($warningMessage)
                <div class="mt-4 rounded-lg p-3 text-sm text-left" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
                    {{ $warningMessage }}
                </div>
            @endif
        </div>
    @elseif ($state === \App\Enums\WizardState::Expired)
        {{-- Reservation expired --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(245,158,11,0.15);">
                <flux:icon name="clock" class="size-10" style="color: #fbbf24;" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;" id="step-heading-{{ \App\Enums\WizardState::Expired->value }}" tabindex="-1">{{ __('Reservation Expired') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Your shift reservation has expired. Please select your shifts again.') }}</p>
            <button wire:click="restartSignup" class="mt-6 public-btn-primary">
                {{ __('Start Over') }}
            </button>
        </div>
    @else
        {{-- Reservation timer --}}
        @if ($reservationExpiresAt)
            <div x-data="reservationTimer($wire)"
                 x-show="remaining > 0"
                 x-cloak
                 role="timer"
                 aria-label="{{ __('Reservation countdown') }}"
                 :style="{
                     background: remaining > 300 ? 'rgba(5,150,105,0.12)' : remaining > 60 ? 'rgba(245,158,11,0.12)' : 'rgba(230,57,70,0.12)',
                     borderLeft: '4px solid ' + (remaining > 300 ? 'var(--brand)' : remaining > 60 ? '#F4D03F' : '#E63946'),
                 }"
                 class="rounded-lg px-4 py-2.5 mb-8 transition-colors"
                 :class="{ 'urgency-pulse': remaining <= 60 }">
                <div class="flex items-center gap-2 text-sm"
                     :style="{ color: remaining > 300 ? '#6ee7b7' : remaining > 60 ? '#fbbf24' : '#fca5a5' }">
                    <flux:icon name="clock" variant="mini" class="size-4" aria-hidden="true" />
                    <span>
                        {{ __('Reservation expires in') }}
                        <span x-text="formattedTime" class="font-mono font-semibold" aria-hidden="true"></span>
                    </span>
                </div>
                <span class="sr-only" aria-live="polite" x-text="srAnnouncement"></span>
            </div>
        @endif

        {{-- Step progress — numbered circles + dashed connectors (matches "How It Works") --}}
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
        <nav aria-label="{{ __('Signup steps') }}" class="flex items-center justify-between mb-10">
            @foreach ($steps as $stepValue => $label)
                @php
                    $stepIndex = array_search($stepValue, $stepValues);
                    $isActive = $stepValue === $state->value;
                    $isComplete = $stepIndex < $currentIndex;
                    $isFuture = $stepIndex > $currentIndex;
                @endphp
                <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="flex items-center justify-center rounded-full transition-all duration-300 font-bebas text-lg"
                            style="width: 48px; height: 48px; letter-spacing: 0.05em;
                                {{ $isActive ? 'background: var(--brand); color: white; box-shadow: 0 0 20px rgba(5,150,105,0.3);' : '' }}
                                {{ $isComplete ? 'background: rgba(5,150,105,0.2); color: #6ee7b7;' : '' }}
                                {{ $isFuture ? 'background: rgba(255,255,255,0.05); color: #9a9a9a; border: 1px solid rgba(255,255,255,0.1);' : '' }}"
                            @if ($isActive) aria-current="step" @endif
                            @if ($isFuture) aria-disabled="true" @endif>
                            @if ($isComplete)
                                <flux:icon name="check" variant="mini" class="size-5" aria-hidden="true" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <span class="text-[0.65rem] sm:text-xs font-medium"
                            style="{{ $isActive ? 'color: white;' : '' }}{{ $isComplete ? 'color: #6ee7b7;' : '' }}{{ $isFuture ? 'color: #9a9a9a;' : '' }}">
                            {{ $label }}
                        </span>
                    </div>
                    @if (!$loop->last)
                        <div class="flex-1 mx-2 sm:mx-4 h-0" style="border-top: 2px dashed {{ $isComplete ? 'rgba(5,150,105,0.5)' : 'rgba(255,255,255,0.1)' }};" aria-hidden="true"></div>
                    @endif
                </div>
            @endforeach
        </nav>

        @if ($warningMessage && $state === \App\Enums\WizardState::SelectingShifts)
            <div class="rounded-lg p-3 mb-4 text-sm" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
                {{ $warningMessage }}
            </div>
        @endif

        @error('selectedShiftIds')
            <div class="rounded-lg p-3 mb-4 text-sm" style="background: rgba(230,57,70,0.1); border: 1px solid rgba(230,57,70,0.2); color: #fca5a5;">
                {{ $message }}
            </div>
        @enderror

        {{-- Step 1: Select Shifts --}}
        <div x-show="$wire.state === '{{ \App\Enums\WizardState::SelectingShifts->value }}'" x-cloak>
            <div class="space-y-6 mb-8">
                <div>
                    <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;" id="step-heading-{{ \App\Enums\WizardState::SelectingShifts->value }}" tabindex="-1">{{ __('Choose Your Shifts') }}</h2>
                    @if (count($selectedShiftIds) > 0)
                        <p class="mt-1 text-sm" style="color: #a1a1aa;">{{ count($selectedShiftIds) }} {{ __('shift(s) selected') }}</p>
                    @endif
                </div>

                @foreach ($this->jobs as $job)
                    <div class="rounded-lg overflow-hidden" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);" wire:key="job-{{ $job->id }}">
                        <div class="p-4" style="background: rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <h3 class="text-sm font-semibold text-white">{{ $job->name }}</h3>
                            @if ($job->description)
                                <p class="mt-1 text-sm" style="color: #a1a1aa;">{{ $job->description }}</p>
                            @endif
                            @if ($job->instructions)
                                <a href="{{ route('events.jobs.cheat-sheet', ['publicToken' => $event->public_token, 'jobId' => $job->id]) }}" target="_blank"
                                   class="inline-flex items-center gap-1 mt-2 text-sm" style="color: var(--brand); text-decoration: none;">
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
                                    $shiftTimeLabel = $job->name.' — '.$shift->shift_date->format('M d').' '.$shift->displayTimeRange();
                                @endphp
                                <label
                                    class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-all duration-200"
                                    style="border: 2px solid {{ $isSelected && $isConflicting ? 'var(--yellow)' : ($isSelected ? 'var(--brand)' : 'rgba(255,255,255,0.08)') }};
                                           background: {{ $isSelected && $isConflicting ? 'rgba(244,208,63,0.08)' : ($isSelected ? 'rgba(5,150,105,0.08)' : 'transparent') }};
                                           {{ $isFull ? 'opacity: 0.4; cursor: not-allowed;' : '' }}"
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
                                            <span class="text-sm font-medium text-white">
                                                {{ $shift->shift_date->format('M d') }} — {{ $shift->displayTimeRange() }}
                                            </span>
                                            @if ($spotsLeft <= 3 && $spotsLeft > 0)
                                                <span class="block text-sm font-semibold urgency-pulse" style="color: var(--yellow);">
                                                    {{ __('Nur noch :count Plätze!', ['count' => $spotsLeft]) }}
                                                </span>
                                            @else
                                                <span class="block text-sm" style="color: #a1a1aa;">
                                                    {{ $spotsLeft }} {{ __('spots remaining') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($isFull)
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded" style="background: rgba(230,57,70,0.15); color: #fca5a5;">{{ __('Full') }}</span>
                                    @elseif ($isConflicting && $isSelected)
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded" style="background: rgba(244,208,63,0.15); color: #fbbf24;">{{ __('Conflict') }}</span>
                                    @else
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded" style="background: rgba(5,150,105,0.15); color: #6ee7b7;">{{ __('Open') }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if (count($this->overlappingShiftIds) > 0)
                <div class="rounded-lg p-3 mb-4 text-sm" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
                    {{ __('Some selected shifts overlap in time. Deselect the highlighted shifts before continuing.') }}
                </div>
            @endif

            <button wire:click="reserveAndAdvance" wire:loading.attr="disabled" class="w-full public-btn-primary">
                {{ __('Continue') }}
            </button>
        </div>

        {{-- Step 2: Gear & Custom Fields --}}
        @if ($this->hasGearOrFields)
            <div x-show="$wire.state === '{{ \App\Enums\WizardState::GearAndFields->value }}'" x-cloak
                 id="step-heading-{{ \App\Enums\WizardState::GearAndFields->value }}" tabindex="-1">
                {{-- Gear selection --}}
                @if ($this->gearItems->isNotEmpty())
                    <div class="space-y-4 mb-8">
                        <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Event Gear') }}</h2>

                        @foreach ($this->gearItems as $item)
                            <div wire:key="gear-{{ $item->id }}" class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                                <div class="font-medium text-white mb-1">{{ $item->name }}</div>
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
                                    <p class="text-sm" style="color: #9a9a9a;">{{ __('Included with your signup') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Custom registration fields --}}
                @if ($this->customRegistrationFields->isNotEmpty())
                    <div class="space-y-4 mb-8">
                        <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Additional Information') }}</h2>

                        @foreach ($this->customRegistrationFields as $field)
                            <flux:field wire:key="custom-field-{{ $field->id }}">
                                <flux:label>
                                    {{ $field->label }}
                                    @if (!$field->required)
                                        <span style="color: #9a9a9a; font-weight: 400;">({{ __('optional') }})</span>
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
                    <button wire:click="goBack" class="flex-1 public-btn-ghost">
                        {{ __('Back') }}
                    </button>
                    <button wire:click="advanceToPersonalInfo" class="flex-1 public-btn-primary">
                        {{ __('Continue') }}
                    </button>
                </div>
            </div>
        @endif

        {{-- Step 3: Personal Info --}}
        <div x-show="$wire.state === '{{ \App\Enums\WizardState::PersonalInfo->value }}'" x-cloak>
            <div class="space-y-4 mb-6">
                <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;" id="step-heading-{{ \App\Enums\WizardState::PersonalInfo->value }}" tabindex="-1">{{ __('Your Information') }}</h2>

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
                    @if ($this->hintSignupEmail)
                        <p class="mt-1 text-sm" style="color: #9a9a9a;">{{ $this->hintSignupEmail }}</p>
                    @endif
                    <flux:error name="volunteerEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Phone') }} @unless($event->phone_required)<span style="color: #9a9a9a; font-weight: 400;">({{ __('optional') }})</span>@endunless</flux:label>
                    <flux:input type="tel" wire:model="volunteerPhone" placeholder="{{ __('+1 555 123 4567') }}" />
                    @if ($this->hintSignupPhone)
                        <p class="mt-1 text-sm" style="color: #9a9a9a;">{{ $this->hintSignupPhone }}</p>
                    @endif
                    <flux:error name="volunteerPhone" />
                </flux:field>
            </div>

            <p class="mb-4 text-xs flex items-center gap-1" style="color: #9a9a9a;">
                <flux:icon name="lock-closed" variant="micro" class="size-3" />
                {{ __('Deine Daten werden nur für die Event-Organisation verwendet.') }}
            </p>

            <div class="flex gap-3">
                <button wire:click="goBack" class="flex-1 public-btn-ghost">
                    {{ __('Back') }}
                </button>
                <button wire:click="advanceToConfirmation" class="flex-1 public-btn-primary">
                    {{ __('Continue') }}
                </button>
            </div>
        </div>

        {{-- Step 4: Confirm --}}
        <div x-show="$wire.state === '{{ \App\Enums\WizardState::Confirming->value }}'" x-cloak>
            <div class="space-y-6 mb-6">
                <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;" id="step-heading-{{ \App\Enums\WizardState::Confirming->value }}" tabindex="-1">{{ __('Confirm Your Signup') }}</h2>

                @if ($this->hintSignupSummary)
                    <div class="rounded-lg p-3 text-sm" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); color: #93c5fd;">
                        {{ $this->hintSignupSummary }}
                    </div>
                @endif

                {{-- Selected shifts summary --}}
                <div class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                    <h3 class="text-sm font-semibold text-white mb-3">{{ __('Selected Shifts') }}</h3>
                    <div class="space-y-2">
                        @foreach ($this->jobs as $job)
                            @foreach ($job->shifts as $shift)
                                @if (in_array($shift->id, $selectedShiftIds))
                                    <div class="flex items-center gap-2 text-sm" wire:key="confirm-shift-{{ $shift->id }}">
                                        <flux:icon name="check" variant="mini" class="size-4" style="color: var(--brand);" />
                                        <span class="font-medium text-white">{{ $job->name }}:</span>
                                        <span style="color: #a1a1aa;">{{ $shift->shift_date->format('M d') }} — {{ $shift->displayTimeRange() }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Gear summary --}}
                @if ($this->gearItems->isNotEmpty())
                    <div class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                        <h3 class="text-sm font-semibold text-white mb-3">{{ __('Gear') }}</h3>
                        <div class="space-y-1">
                            @foreach ($this->gearItems as $item)
                                <div class="flex items-center gap-2 text-sm" wire:key="confirm-gear-{{ $item->id }}">
                                    <flux:icon name="check" variant="mini" class="size-4" style="color: var(--brand);" />
                                    <span style="color: #a1a1aa;">{{ $item->name }}{{ $item->requires_size && isset($gearSelections[$item->id]) ? ' (' . $gearSelections[$item->id] . ')' : '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Custom field summary --}}
                @if ($this->customRegistrationFields->isNotEmpty())
                    <div class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                        <h3 class="text-sm font-semibold text-white mb-3">{{ __('Additional Information') }}</h3>
                        <div class="space-y-1">
                            @foreach ($this->customRegistrationFields as $field)
                                @if (!empty($customFieldResponses[$field->id]))
                                    <div class="flex items-center gap-2 text-sm" wire:key="confirm-field-{{ $field->id }}">
                                        <span class="font-medium text-white">{{ $field->label }}:</span>
                                        <span style="color: #a1a1aa;">{{ is_bool($customFieldResponses[$field->id]) ? ($customFieldResponses[$field->id] ? __('Yes') : __('No')) : $customFieldResponses[$field->id] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Personal info summary --}}
                <div class="rounded-lg p-4" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                    <h3 class="text-sm font-semibold text-white mb-3">{{ __('Your Information') }}</h3>
                    <div class="space-y-1 text-sm">
                        <div><span class="font-medium text-white">{{ __('Name') }}:</span> <span style="color: #a1a1aa;">{{ $volunteerFirstName }} {{ $volunteerLastName }}</span></div>
                        <div><span class="font-medium text-white">{{ __('Email') }}:</span> <span style="color: #a1a1aa;">{{ $volunteerEmail }}</span></div>
                        @if ($volunteerPhone)
                            <div><span class="font-medium text-white">{{ __('Phone') }}:</span> <span style="color: #a1a1aa;">{{ $volunteerPhone }}</span></div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button wire:click="goBack" class="flex-1 public-btn-ghost">
                    {{ __('Back') }}
                </button>
                <button wire:click="submitSignup" wire:loading.attr="disabled" class="flex-1 public-btn-primary public-btn-cta">
                    <span wire:loading.remove wire:target="submitSignup">{{ __('Sign Up') }}</span>
                    <span wire:loading wire:target="submitSignup">{{ __('Signing up...') }}</span>
                </button>
            </div>
            <p class="mt-3 text-center text-xs flex items-center justify-center gap-1" style="color: #9a9a9a;">
                <flux:icon name="lock-closed" variant="micro" class="size-3" />
                {{ __('Deine Daten werden nur für die Event-Organisation verwendet.') }}
            </p>
        </div>
    @endif
</div>
