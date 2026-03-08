<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.volunteers', $event)" wire:navigate />
        <flux:heading size="xl">{{ $volunteer->name }}</flux:heading>
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
                    <flux:button variant="primary" size="sm" icon="arrow-up-circle" wire:click="$set('showPromoteModal', true)">
                        {{ __('Promote to Staff') }}
                    </flux:button>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Name') }}</flux:text>
                    <flux:text>{{ $volunteer->name }}</flux:text>
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
                        <flux:badge size="sm" color="zinc">{{ __('Not arrived') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

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
                            <flux:table.cell>{{ $signup->shift->starts_at->format('M d, g:i A') }} — {{ $signup->shift->ends_at->format('g:i A') }}</flux:table.cell>
                            <flux:table.cell>{{ $signup->signed_up_at->format('M d, Y') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($signup->attendanceRecord)
                                    @php
                                        $color = match($signup->attendanceRecord->status) {
                                            \App\Enums\AttendanceStatus::OnTime => 'emerald',
                                            \App\Enums\AttendanceStatus::Late => 'amber',
                                            \App\Enums\AttendanceStatus::NoShow => 'red',
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

        {{-- Promote modal --}}
        <flux:modal wire:model="showPromoteModal">
            <flux:heading>{{ __('Promote to Staff') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Promote :name to a staff member. They will receive login credentials via email.', ['name' => $volunteer->name]) }}</flux:text>

            <div class="mt-4">
                <flux:field>
                    <flux:label>{{ __('Role') }}</flux:label>
                    <flux:select wire:model="promoteRole">
                        <flux:select.option value="volunteer_admin">{{ __('Volunteer Admin') }}</flux:select.option>
                        <flux:select.option value="entrance_staff">{{ __('Entrance Staff') }}</flux:select.option>
                        <flux:select.option value="organizer">{{ __('Organizer') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="promoteRole" />
                </flux:field>
            </div>

            <div class="mt-6 flex gap-2">
                <flux:button variant="primary" wire:click="promoteVolunteer">{{ __('Promote') }}</flux:button>
                <flux:button variant="ghost" wire:click="$set('showPromoteModal', false)">{{ __('Cancel') }}</flux:button>
            </div>
        </flux:modal>
    </x-events.layout>
</div>
