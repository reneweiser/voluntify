<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.tab-nav :event="$event" />

    @if (session('conflict'))
        <flux:callout variant="warning" class="mb-4">{{ session('conflict') }}</flux:callout>
    @endif

    <div class="flex items-center justify-between mb-6">
        <flux:heading size="lg">{{ __('Attendance') }}</flux:heading>
    </div>

    {{-- Shift selector --}}
    <div class="mb-6">
        <flux:select wire:model.live="selectedShiftId" placeholder="{{ __('Select a shift...') }}">
            @foreach ($this->shifts as $shift)
                <flux:select.option :value="$shift->id" wire:key="shift-option-{{ $shift->id }}">
                    {{ $shift->job_name }}: {{ $shift->starts_at->format('M d, g:i A') }} — {{ $shift->ends_at->format('g:i A') }}
                    ({{ $shift->attended_count }}/{{ $shift->signups_count }} {{ __('marked') }})
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($selectedShiftId)
        @if ($this->signups->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="users" class="size-8 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('No signups for this shift') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No volunteers have signed up for this shift yet.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Volunteer') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Arrived') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->signups as $signup)
                        <flux:table.row :key="'signup-'.$signup->id">
                            <flux:table.cell>{{ $signup->volunteer->name }}</flux:table.cell>
                            <flux:table.cell>{{ $signup->volunteer->email }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($signup->volunteer->eventArrivals->isNotEmpty())
                                    <flux:badge size="sm" color="emerald">{{ __('Yes') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                @endif
                            </flux:table.cell>
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
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button
                                        size="xs"
                                        :variant="$signup->attendanceRecord?->status === \App\Enums\AttendanceStatus::OnTime ? 'primary' : 'ghost'"
                                        wire:click="markStatus({{ $signup->id }}, 'on_time')"
                                        title="{{ __('On Time') }}"
                                    >
                                        <flux:icon name="check" class="size-4 sm:hidden" />
                                        <span class="hidden sm:inline">{{ __('On Time') }}</span>
                                    </flux:button>
                                    <flux:button
                                        size="xs"
                                        :variant="$signup->attendanceRecord?->status === \App\Enums\AttendanceStatus::Late ? 'primary' : 'ghost'"
                                        wire:click="markStatus({{ $signup->id }}, 'late')"
                                        title="{{ __('Late') }}"
                                    >
                                        <flux:icon name="clock" class="size-4 sm:hidden" />
                                        <span class="hidden sm:inline">{{ __('Late') }}</span>
                                    </flux:button>
                                    <flux:button
                                        size="xs"
                                        :variant="$signup->attendanceRecord?->status === \App\Enums\AttendanceStatus::NoShow ? 'danger' : 'ghost'"
                                        wire:click="markStatus({{ $signup->id }}, 'no_show')"
                                        title="{{ __('No Show') }}"
                                    >
                                        <flux:icon name="x-mark" class="size-4 sm:hidden" />
                                        <span class="hidden sm:inline">{{ __('No Show') }}</span>
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </div>
        @endif
    @else
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                <flux:icon name="clipboard-document-check" class="size-8 text-amber-600 dark:text-amber-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('Select a shift') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Choose a shift from the dropdown above to start tracking attendance.') }}</flux:text>
        </div>
    @endif
</div>
