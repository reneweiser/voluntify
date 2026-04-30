<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.volunteers', $event)" wire:navigate aria-label="{{ __('Back to volunteers') }}" />
        <flux:heading size="xl">{{ $volunteer->full_name }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        {{-- Info card --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <flux:heading size="lg">{{ __('Volunteer Info') }}</flux:heading>
                    @if ($this->isAlreadyPromoted)
                        <flux:badge size="sm" color="emerald">{{ __('Staff Member') }}</flux:badge>
                    @endif
                </div>
                @if ($this->canPromote)
                    <div class="flex items-center gap-2">
                        <flux:button variant="danger" size="sm" icon="trash" wire:click="$set('showDeleteModal', true)">
                            {{ __('Volunteer löschen') }}
                        </flux:button>
                        @if ($volunteer->isEmailVerified())
                            <flux:button variant="ghost" size="sm" icon="envelope" wire:click="resendTicketEmail" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="resendTicketEmail">{{ __('QR-Code erneut senden') }}</span>
                                <span wire:loading wire:target="resendTicketEmail">{{ __('Wird gesendet...') }}</span>
                            </flux:button>
                        @endif
                        <flux:button variant="primary" size="sm" icon="arrow-up-circle" wire:click="$set('showPromoteModal', true)">
                            {{ __('Promote to Staff') }}
                        </flux:button>
                    </div>
                @endif
            </div>
            @if ($successMessage !== '')
                <flux:callout variant="success" class="mb-4">{{ $successMessage }}</flux:callout>
            @endif
            <flux:error name="resend" class="mb-4" />
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Name') }}</flux:text>
                    <flux:text>{{ $volunteer->full_name }}</flux:text>
                </div>
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Email') }}</flux:text>
                    <flux:text>{{ $volunteer->email }}</flux:text>
                </div>
                @if ($volunteer->phone)
                    <div>
                        <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Phone') }}</flux:text>
                        <flux:text>{{ $volunteer->phone }}</flux:text>
                    </div>
                @endif
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Arrival Status') }}</flux:text>
                    @if ($this->arrival)
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="emerald">{{ __('Arrived') }}</flux:badge>
                            <flux:text size="sm">{{ $this->arrival->scanned_at->format('M d, Y g:i A') }}</flux:text>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="zinc">{{ __('Not arrived') }}</flux:badge>
                            @can('scan', $event)
                                <flux:button size="xs" variant="primary" wire:click="markAsArrived" wire:confirm="{{ __('Volunteer als angekommen markieren?') }}">
                                    {{ __('Als angekommen markieren') }}
                                </flux:button>
                            @endcan
                        </div>
                        <flux:error name="arrival" />
                    @endif
                </div>
            </div>
        </div>

        {{-- Custom field responses --}}
        @if ($this->customFieldResponses->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
                <flux:heading size="lg" class="mb-4">{{ __('Registration Info') }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->customFieldResponses as $response)
                        <div>
                            <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">
                                {{ $response->field->label }}@if ($response->field->trashed()) ({{ __('archived') }})@endif
                            </flux:text>
                            <flux:text>{{ $response->field->type->displayValue($response->value) }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Shift assignments --}}
        <flux:heading size="lg" class="mb-4">{{ __('Shift Assignments') }}</flux:heading>

        @if ($this->shiftSignups->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="briefcase" class="size-8 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('No shift assignments') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This volunteer has no shift assignments for this event.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Job') }}</flux:table.column>
                    <flux:table.column>{{ __('Shift Time') }}</flux:table.column>
                    <flux:table.column>{{ __('Signed Up') }}</flux:table.column>
                    <flux:table.column>{{ __('Attendance') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->shiftSignups as $signup)
                        <flux:table.row :key="'signup-'.$signup->id">
                            <flux:table.cell>{{ $signup->shift->volunteerJob->name }}</flux:table.cell>
                            @php $tz = $event->project->timezone ?? 'UTC'; @endphp
                            <flux:table.cell>{{ $signup->shift->shift_date->setTimezone($tz)->format('M d') }} — {{ $signup->shift->displayTimeRange($tz) }}</flux:table.cell>
                            <flux:table.cell>{{ $signup->signed_up_at->format('M d, Y') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($signup->attendanceRecord)
                                    @php
                                        $color = match($signup->attendanceRecord->status) {
                                            \App\Enums\AttendanceStatus::OnTime => 'emerald',
                                            \App\Enums\AttendanceStatus::Late => 'amber',
                                            \App\Enums\AttendanceStatus::NoShow => 'red',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge size="sm" :color="$color">{{ $signup->attendanceRecord->status->name }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Unmarked') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </div>
        @endif

        {{-- Gear assignments --}}
        @if ($this->volunteerGear->isNotEmpty())
            <flux:heading size="lg" class="mt-6 mb-4">{{ __('Gear') }}</flux:heading>

            <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Item') }}</flux:table.column>
                    <flux:table.column>{{ __('Size') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->volunteerGear as $gear)
                        <flux:table.row :key="'gear-'.$gear->id">
                            <flux:table.cell>{{ $gear->gearItem->name }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($gear->size)
                                    <flux:badge size="sm" color="zinc">{{ $gear->size }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($gear->quantity_entitled !== null)
                                    <span class="text-sm font-medium">{{ $gear->totalPickedUp() }} / {{ $gear->quantity_entitled }}</span>
                                @elseif ($gear->isPickedUp())
                                    <flux:badge size="sm" color="emerald">{{ __('Picked up') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Not picked up') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @can('trackGearPickup', $event)
                                    @if ($gear->quantity_entitled !== null)
                                        @if ($gear->remainingQuantity() > 0)
                                            <flux:button size="xs" variant="ghost" wire:click="recordGearPickup({{ $gear->id }})" aria-label="{{ __('Record pickup') }}">
                                                <flux:icon name="plus" class="size-4" />
                                            </flux:button>
                                        @endif
                                        @if ($gear->totalPickedUp() > 0)
                                            <flux:button size="xs" variant="ghost" wire:click="undoGearPickup({{ $gear->id }})" aria-label="{{ __('Undo last pickup') }}">
                                                <flux:icon name="minus" class="size-4" />
                                            </flux:button>
                                        @endif
                                    @else
                                        @if ($gear->isPickedUp())
                                            <flux:button size="xs" variant="ghost" wire:click="undoGearPickup({{ $gear->id }})" aria-label="{{ __('Undo pickup') }}">
                                                <flux:icon name="arrow-uturn-left" class="size-4" />
                                            </flux:button>
                                        @else
                                            <flux:button size="xs" variant="ghost" wire:click="recordGearPickup({{ $gear->id }})" aria-label="{{ __('Mark as picked up') }}">
                                                <flux:icon name="hand-raised" class="size-4" />
                                            </flux:button>
                                        @endif
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </div>

            <flux:error name="gear" />
        @endif

        {{-- Promote modal --}}
        <flux:modal wire:model="showPromoteModal" focusable class="max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Befördern') }}</flux:heading>
                <flux:text>{{ __(':name zum Team hinzufügen.', ['name' => $volunteer->full_name]) }}</flux:text>

                <div>
                    <flux:select wire:model.live="promoteRole" :label="__('Rolle')">
                        <flux:select.option value="organizer">{{ __('Organizer — Vollzugriff') }}</flux:select.option>
                        <flux:select.option value="volunteer_admin">{{ __('Volunteer Admin — Scanner-Zugriff') }}</flux:select.option>
                    </flux:select>
                </div>

                @if ($promoteRole === 'volunteer_admin')
                    @if ($this->vaScanners->isEmpty())
                        <flux:callout variant="warning">
                            {{ __('Kein VA-Scanner vorhanden. Erstelle zuerst einen Scanner vom Typ "Volunteer Admin".') }}
                        </flux:callout>
                    @else
                        <flux:select wire:model="selectedScannerId" :label="__('VA-Scanner auswählen')">
                            <flux:select.option value="">{{ __('Bitte wählen...') }}</flux:select.option>
                            @foreach ($this->vaScanners as $scanner)
                                <flux:select.option :value="$scanner->id">{{ $scanner->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                @else
                    <flux:text size="sm" class="text-zinc-500">{{ __('Es wird ein Benutzerkonto erstellt und Login-Daten per E-Mail gesendet.') }}</flux:text>
                @endif

                <flux:error name="promote" />

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showPromoteModal', false)">{{ __('Abbrechen') }}</flux:button>
                    <flux:button variant="primary" wire:click="promoteVolunteer" :disabled="$promoteRole === 'volunteer_admin' && $this->vaScanners->isEmpty()">
                        {{ __('Befördern') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal wire:model="showDeleteModal" focusable class="max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Volunteer löschen') }}</flux:heading>
                <flux:text>{{ __('Dieses Event öffnet nur den Einstiegspunkt. Gelöscht wird das gesamte Volunteer-Profil im Projekt :project.', ['project' => $event->project->name]) }}</flux:text>
                <flux:callout variant="warning">
                    {{ __('Alle zugehörigen Projekt-Daten werden unwiderruflich entfernt: Schicht-Anmeldungen, Tickets/QR-Codes, Gear-Zuweisungen und persönliche Daten.') }}
                </flux:callout>
                <flux:checkbox
                    wire:model.live="deleteConfirmed"
                    :label="__('Ich bestätige die endgültige Löschung des gesamten Volunteer-Profils.')"
                />

                <flux:error name="delete" />

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Abbrechen') }}</flux:button>
                    <flux:button variant="danger" wire:click="deleteVolunteer" :disabled="! $deleteConfirmed">
                        {{ __('Volunteer endgültig löschen') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </x-events.layout>
</div>
