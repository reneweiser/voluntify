<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
        </div>
        <flux:badge size="lg">{{ $this->recipientCount }} {{ __('Empfänger:innen') }}</flux:badge>
    </div>

    <x-projects.layout :project="$project">

    {{-- Compose form --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">{{ __('Neue Ankündigung') }}</flux:heading>

        <div class="space-y-4">
            {{-- Filters --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:select wire:model.live="selectedEventId" :label="__('Event (optional)')">
                    <flux:select.option value="">{{ __('Alle Events') }}</flux:select.option>
                    @foreach ($this->events as $event)
                        <flux:select.option :value="$event->id">{{ $event->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($selectedEventId)
                    <flux:select wire:model.live="selectedJobId" :label="__('Aufgabe (optional)')">
                        <flux:select.option value="">{{ __('Alle Aufgaben') }}</flux:select.option>
                        @foreach ($this->jobs as $job)
                            <flux:select.option :value="$job->id">{{ $job->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($selectedJobId)
                    <flux:select wire:model.live="selectedShiftId" :label="__('Schicht (optional)')">
                        <flux:select.option value="">{{ __('Alle Schichten') }}</flux:select.option>
                        @foreach ($this->shifts as $shift)
                            <flux:select.option :value="$shift->id">
                                @php $tz = $project->timezone ?? 'UTC'; @endphp
                                {{ $shift->shift_date->setTimezone($tz)->format('d.m.Y') }} {{ $shift->displayTimeRange($tz) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            {{-- Content --}}
            <flux:input wire:model="subject" :label="__('Betreff')" :placeholder="__('Betreff der E-Mail')" />
            <flux:textarea wire:model="body" :label="__('Nachricht')" :placeholder="__('Deine Nachricht an die Helfer:innen...')" rows="6" />

            {{-- Schedule --}}
            <flux:input type="datetime-local" wire:model="sendAt" :label="__('Geplanter Versand (optional)')" />
            <flux:text size="sm" class="text-zinc-500">{{ __('Leer lassen für sofortigen Versand.') }}</flux:text>

            {{-- Errors --}}
            @error('subject') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
            @error('body') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror

            {{-- Actions --}}
            <div class="flex justify-end">
                <flux:button variant="primary" icon="paper-airplane" wire:click="confirmSend">
                    {{ $sendAt ? __('Planen') : __('Senden') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- History --}}
    @if ($this->history->isNotEmpty())
        <flux:heading size="lg" class="mb-4">{{ __('Verlauf') }}</flux:heading>
        <div class="space-y-3">
            @foreach ($this->history as $announcement)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4" wire:key="ann-{{ $announcement->id }}">
                    <div class="flex items-center justify-between mb-2">
                        <flux:heading size="sm">{{ $announcement->subject }}</flux:heading>
                        <div class="flex items-center gap-2">
                            @if ($announcement->isSent())
                                <flux:badge size="sm" color="emerald">{{ __('Gesendet') }}</flux:badge>
                                <flux:text size="sm">{{ $announcement->recipient_count }} {{ __('Empfänger') }}</flux:text>
                            @elseif ($announcement->isScheduled())
                                <flux:badge size="sm" color="amber">{{ __('Geplant') }}</flux:badge>
                                <flux:text size="sm">{{ $announcement->send_at->format('d.m.Y H:i') }}</flux:text>
                            @else
                                <flux:badge size="sm" color="sky">{{ __('In Warteschlange') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ $announcement->creator?->name }} &middot; {{ $announcement->created_at->diffForHumans() }}
                        @if ($announcement->event)
                            &middot; {{ $announcement->event->name }}
                        @endif
                    </flux:text>
                </div>
            @endforeach
        </div>
    @endif

    </x-projects.layout>

    {{-- Send confirmation modal --}}
    <flux:modal wire:model="showConfirmModal" focusable class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Ankündigung senden?') }}</flux:heading>
            <flux:text>
                {{ $sendAt
                    ? __('Die Ankündigung wird am :date an :count Helfer:innen gesendet.', ['date' => $sendAt, 'count' => $this->recipientCount])
                    : __('Die Ankündigung wird sofort an :count Helfer:innen gesendet.', ['count' => $this->recipientCount])
                }}
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="send">
                    {{ __('Bestätigen') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
