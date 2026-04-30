<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.show', $event)" wire:navigate aria-label="{{ __('Back to overview') }}" />
        <flux:heading size="xl">{{ __('Settings') }} &mdash; {{ $event->name }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        <form wire:submit="saveEvent" class="space-y-6">
            {{-- General --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('General') }}</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Event Name') }}</flux:label>
                        <flux:input wire:model="form.name" />
                        <flux:error name="form.name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Description') }}</flux:label>
                        <flux:textarea wire:model="form.description" rows="3" />
                        <flux:error name="form.description" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Location') }}</flux:label>
                        <flux:input wire:model="form.location" />
                        <flux:error name="form.location" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Title Image') }}</flux:label>
                        @if ($event->titleImageUrl() && !$form->titleImage)
                            <div class="flex items-center gap-3 mb-2">
                                <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="h-20 w-32 object-cover rounded" />
                                <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteImage" wire:confirm="{{ __('Remove this image?') }}">
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>
                        @endif
                        <flux:input type="file" wire:model="form.titleImage" accept="image/jpeg,image/png,image/webp" />
                        <flux:error name="form.titleImage" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Dates --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Dates') }}</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Starts At') }}</flux:label>
                        <flux:input type="datetime-local" wire:model="form.startsAt" />
                        <flux:error name="form.startsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Ends At') }}</flux:label>
                        <flux:input type="datetime-local" wire:model="form.endsAt" />
                        <flux:error name="form.endsAt" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Signup --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Signup') }}</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Visibility') }}</flux:label>
                        <flux:select wire:model="form.visibility">
                            @foreach (\App\Enums\EventVisibility::cases() as $vis)
                                <flux:select.option value="{{ $vis->value }}">{{ $vis->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ __('Private events are not shown on the project website. Share the direct link to allow signups.') }}</flux:description>
                    </flux:field>

                    @if ($this->availableProjects->isNotEmpty())
                        <flux:field>
                            <flux:label>{{ __('Project') }}</flux:label>
                            <flux:select wire:model="selectedProjectId" wire:change="updateProject" placeholder="{{ __('None') }}">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach ($this->availableProjects as $project)
                                    <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Priority Shift Unlock Threshold (%)') }}</flux:label>
                        <flux:input type="number" wire:model="form.priorityUnlockThresholdPercent" min="0" max="100" placeholder="{{ __('Disabled') }}" />
                        <flux:description>{{ __('Leave empty to disable shift priority gating for this event. 80 means regular shifts unlock once 80% of priority slots are filled.') }}</flux:description>
                        <flux:error name="form.priorityUnlockThresholdPercent" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Attendance --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Attendance') }}</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Attendance Grace Period (minutes)') }}</flux:label>
                        <flux:input type="number" wire:model="form.attendanceGraceMinutes" min="0" max="120" placeholder="{{ __('No grace period — leave empty') }}" />
                        <flux:description>{{ __('Minutes after shift start within which a scan is still marked as on-time. Leave empty for no grace period.') }}</flux:description>
                        <flux:error name="form.attendanceGraceMinutes" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Notifications --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Benachrichtigungen') }}</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Benachrichtigungs-E-Mail') }}</flux:label>
                        <flux:input type="email" wire:model="form.notificationEmail" placeholder="{{ __('organizer@example.com') }}" />
                        <flux:description>{{ __('Empfänger für Stornierungsberichte und andere Benachrichtigungen zu diesem Event. Wenn leer, wird die Kontakt-E-Mail des Projekts verwendet.') }}</flux:description>
                        <flux:error name="form.notificationEmail" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Email link --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Email') }}</flux:heading>

                <flux:text>
                    <a href="{{ route('events.emails', $event) }}" wire:navigate class="text-accent hover:underline">
                        {{ __('Manage email templates') }} &rarr;
                    </a>
                </flux:text>
            </flux:card>

            {{-- Actions --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                <flux:button variant="ghost" :href="route('events.show', $event)" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </x-events.layout>
</div>
