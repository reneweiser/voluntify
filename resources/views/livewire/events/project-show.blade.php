<div class="mx-auto max-w-7xl p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->canCreateEvents)
                <flux:button variant="primary" size="sm" icon="plus" wire:click="$set('showCreateEventModal', true)">
                    {{ __('Create Event') }}
                </flux:button>
            @endif

            @if ($this->canManage)
                <flux:button variant="subtle" size="sm" icon="document-duplicate" wire:click="$set('showCloneModal', true)">
                    {{ __('Duplizieren') }}
                </flux:button>
                @if ($project->isPendingDeletion())
                    <flux:button variant="primary" size="sm" icon="arrow-uturn-left" wire:click="restoreProject">
                        {{ __('Wiederherstellen') }}
                    </flux:button>
                @else
                    <flux:button variant="danger" size="sm" icon="trash" wire:click="$set('showDeleteModal', true)">
                        {{ __('Löschen') }}
                    </flux:button>
                @endif
            @endif
        </div>
    </div>

    <x-projects.layout :project="$project">
    {{-- Pending deletion warning --}}
    @if ($project->isPendingDeletion())
        <flux:callout variant="warning" class="mb-6">
            {{ __('Dieses Projekt ist zur Löschung vorgemerkt und wird am :date endgültig gelöscht.', ['date' => $project->deletion_requested_at->addDays(30)->format('d.m.Y')]) }}
        </flux:callout>
    @endif

    {{-- Public link --}}
    <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
            <flux:icon name="link" class="size-4" />
        </div>
        <div>
            <flux:text size="sm" class="font-medium text-emerald-800 dark:text-emerald-200">
                {{ __('Public project page:') }}
            </flux:text>
            <flux:text size="sm" class="mt-1 font-mono text-emerald-700 dark:text-emerald-300 break-all">
                {{ $this->publicUrl }}
            </flux:text>
        </div>
    </div>

    {{-- Title image --}}
    @if ($project->titleImageUrl() && !$editing)
        <div class="mb-6">
            <img src="{{ $project->titleImageUrl() }}" alt="{{ $project->name }}" class="w-full max-h-64 object-cover rounded-xl shadow-sm" />
        </div>
    @endif

    {{-- Project details / edit form --}}
    <flux:card class="mb-6">
        @if ($editing)
            <form wire:submit="saveProject" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Project Name') }}</flux:label>
                    <flux:input wire:model="projectForm.name" />
                    <flux:error name="projectForm.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="projectForm.description" rows="3" />
                    <flux:error name="projectForm.description" />
                </flux:field>

                <flux:separator class="my-2" />

                <flux:heading size="sm" class="mb-2">{{ __('E-Mail-Einstellungen') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Absendername') }}</flux:label>
                    <flux:input wire:model="projectForm.senderName" placeholder="{{ __('Name der in E-Mails als Absender angezeigt wird') }}" />
                    <flux:error name="projectForm.senderName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Kontakt-E-Mail') }}</flux:label>
                    <flux:input type="email" wire:model="projectForm.contactEmail" placeholder="{{ __('Antwort-Adresse für Freiwillige') }}" />
                    <flux:error name="projectForm.contactEmail" />
                </flux:field>

                <flux:separator class="my-2" />

                <flux:heading size="sm" class="mb-2">{{ __('Zeitzone') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Projekt-Zeitzone') }}</flux:label>
                    <flux:select wire:model="projectForm.timezone">
                        @php
                            $regions = [
                                'Europe' => DateTimeZone::EUROPE,
                                'America' => DateTimeZone::AMERICA,
                                'Asia' => DateTimeZone::ASIA,
                                'Africa' => DateTimeZone::AFRICA,
                                'Pacific' => DateTimeZone::PACIFIC,
                                'Atlantic' => DateTimeZone::ATLANTIC,
                                'Australia' => DateTimeZone::AUSTRALIA,
                                'Arctic' => DateTimeZone::ARCTIC,
                                'Antarctica' => DateTimeZone::ANTARCTICA,
                                'Indian' => DateTimeZone::INDIAN,
                            ];
                        @endphp
                        <flux:select.option value="UTC">UTC</flux:select.option>
                        @foreach ($regions as $label => $region)
                            <optgroup label="{{ $label }}">
                                @foreach (DateTimeZone::listIdentifiers($region) as $tz)
                                    <flux:select.option :value="$tz">{{ str_replace('_', ' ', $tz) }}</flux:select.option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Alle Zeiten in Events, Schichten und Scannern werden in dieser Zeitzone angezeigt.') }}</flux:description>
                    <flux:error name="projectForm.timezone" />
                </flux:field>

                <flux:separator class="my-2" />

                <flux:heading size="sm" class="mb-2">{{ __('Stornierung') }}</flux:heading>

                <flux:field>
                    <flux:checkbox wire:model.live="projectForm.cancellationEnabled" label="{{ __('Stornierung aktivieren') }}" />
                    <flux:description>{{ __('Erlaubt Freiwilligen, ihre Anmeldungen bis zur Vorlaufzeit selbst zu stornieren.') }}</flux:description>
                </flux:field>

                @if ($projectForm->cancellationEnabled)
                    <flux:field>
                        <flux:label>{{ __('Vorlaufzeit in Stunden') }}</flux:label>
                        <flux:input type="number" wire:model="projectForm.cancellationCutoffHours" min="1" max="168" />
                        <flux:description>{{ __('Bis wie viele Stunden vor Schichtbeginn eine Stornierung möglich ist.') }}</flux:description>
                        <flux:error name="projectForm.cancellationCutoffHours" />
                    </flux:field>
                @endif

                <flux:separator class="my-2" />

                <flux:field>
                    <flux:label>{{ __('Title Image') }}</flux:label>
                    @if ($project->titleImageUrl() && !$projectForm->titleImage)
                        <div class="flex items-center gap-3 mb-2">
                            <img src="{{ $project->titleImageUrl() }}" alt="{{ $project->name }}" class="h-20 w-32 object-cover rounded" />
                            <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteImage" wire:confirm="{{ __('Remove this image?') }}">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    @endif
                    <flux:input type="file" wire:model="projectForm.titleImage" accept="image/jpeg,image/png,image/webp" />
                    <flux:error name="projectForm.titleImage" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                    <flux:button variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        @else
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    @if ($project->description)
                        <div>
                            <flux:text size="sm" class="font-medium">{{ __('Description') }}</flux:text>
                            <flux:text class="mt-1">{{ $project->description }}</flux:text>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">{{ __('No description set.') }}</flux:text>
                    @endif

                    @if ($project->sender_name || $project->contact_email)
                        <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700 space-y-2">
                            <flux:text size="sm" class="font-medium">{{ __('E-Mail-Einstellungen') }}</flux:text>
                            @if ($project->sender_name)
                                <div>
                                    <flux:text size="sm" class="text-zinc-500">{{ __('Absendername') }}</flux:text>
                                    <flux:text size="sm">{{ $project->sender_name }}</flux:text>
                                </div>
                            @endif
                            @if ($project->contact_email)
                                <div>
                                    <flux:text size="sm" class="text-zinc-500">{{ __('Kontakt-E-Mail') }}</flux:text>
                                    <flux:text size="sm">{{ $project->contact_email }}</flux:text>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700 space-y-2">
                        <flux:text size="sm" class="font-medium">{{ __('Stornierung') }}</flux:text>
                        @if ($project->cancellation_enabled)
                            <flux:text size="sm">{{ __(':hours Stunden Vorlaufzeit', ['hours' => $project->cancellation_cutoff_hours]) }}</flux:text>
                        @else
                            <flux:text size="sm" class="text-zinc-400">{{ __('Deaktiviert') }}</flux:text>
                        @endif
                    </div>
                </div>

                @if ($this->canManage)
                    <flux:button variant="subtle" size="sm" icon="pencil" wire:click="startEditing">
                        {{ __('Edit') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </flux:card>

    {{-- Member events --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Events in this Project') }}</flux:heading>
        </div>

        @if ($this->memberEvents->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                <flux:text>{{ __('No events in this project yet.') }}</flux:text>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->memberEvents as $event)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 p-4" wire:key="member-{{ $event->id }}">
                        <a href="{{ route('events.show', $event) }}" wire:navigate class="flex items-center gap-3 hover:text-emerald-600 dark:hover:text-emerald-400">
                            <flux:icon name="calendar" variant="mini" class="size-5 text-zinc-400" />
                            <div>
                                <flux:heading size="sm">{{ $event->name }}</flux:heading>
                                <flux:text size="sm">{{ $event->starts_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </a>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="match($event->status) {
                                \App\Enums\EventStatus::PublishedOpen => 'emerald',
                                \App\Enums\EventStatus::PublishedClosed => 'yellow',
                                \App\Enums\EventStatus::Draft => 'amber',
                                \App\Enums\EventStatus::Archived => 'zinc',
                            }">
                                {{ $event->status->label() }}
                            </flux:badge>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    </x-projects.layout>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="showDeleteModal" focusable class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Projekt löschen') }}</flux:heading>
            <flux:text>{{ __('Das Projekt wird in 30 Tagen endgültig gelöscht. Du kannst es in dieser Zeit jederzeit wiederherstellen.') }}</flux:text>
            <flux:input type="password" wire:model="deletePassword" :label="__('Passwort bestätigen')" :placeholder="__('Dein aktuelles Passwort')" />
            @error('deletePassword')
                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
            @enderror
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="requestDeletion">
                    {{ __('Löschung anfordern') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Clone modal --}}
    <flux:modal wire:model="showCloneModal" focusable class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Projekt duplizieren') }}</flux:heading>
            <flux:text>{{ __('Alle Events, Jobs, Schichten und Konfigurationen werden kopiert. Freiwillige und Anmeldungen werden nicht übernommen.') }}</flux:text>
            <flux:input
                type="number"
                wire:model="cloneDateOffset"
                :label="__('Datumsverschiebung (Tage)')"
                :placeholder="__('z.B. 365 für nächstes Jahr')"
            />
            <flux:text size="sm" class="text-zinc-500">{{ __('Leer lassen, um die gleichen Daten zu verwenden.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmCloneProject">
                    {{ __('Duplizieren') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Create Event Modal --}}
    @if ($this->canCreateEvents)
    <flux:modal wire:model.self="showCreateEventModal" class="md:w-96">
        <form wire:submit="createEvent" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Event') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create a new event in this project.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Event Name') }}</flux:label>
                <flux:input wire:model="eventForm.name" placeholder="{{ __('e.g. Summer Carnival') }}" />
                <flux:error name="eventForm.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="eventForm.description" rows="3" />
                <flux:error name="eventForm.description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Location') }}</flux:label>
                <flux:input wire:model="eventForm.location" placeholder="{{ __('e.g. Central Park') }}" />
                <flux:error name="eventForm.location" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Starts At') }}</flux:label>
                <flux:input type="datetime-local" wire:model="eventForm.startsAt" />
                <flux:error name="eventForm.startsAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Ends At') }}</flux:label>
                <flux:input type="datetime-local" wire:model="eventForm.endsAt" />
                <flux:error name="eventForm.endsAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Title Image') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                <flux:input type="file" wire:model="eventForm.titleImage" accept="image/jpeg,image/png,image/webp" />
                <flux:error name="eventForm.titleImage" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create Event') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
