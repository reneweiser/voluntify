<div class="mx-auto max-w-7xl p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate aria-label="{{ __('Back to events') }}" />
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
            <flux:badge size="sm" :color="match($event->status) {
                \App\Enums\EventStatus::PublishedOpen => 'emerald',
                \App\Enums\EventStatus::PublishedClosed => 'yellow',
                \App\Enums\EventStatus::Draft => 'amber',
                \App\Enums\EventStatus::Archived => 'zinc',
            }">
                {{ $event->status->label() }}
            </flux:badge>
            @if ($event->visibility === \App\Enums\EventVisibility::Private)
                <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
            @endif
        </div>

        @if ($this->canManage)
            {{-- Expanded buttons (lg and up) --}}
            <div class="hidden xl:flex items-center gap-2">
                <flux:button variant="subtle" icon="document-duplicate" wire:click="openCloneModal">
                    {{ __('Clone') }}
                </flux:button>
                @if ($event->isPendingDeletion())
                    <flux:button variant="primary" size="sm" icon="arrow-uturn-left" wire:click="restoreEvent">
                        {{ __('Wiederherstellen') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::Draft)
                    @if ($event->was_previously_published)
                        <flux:button variant="primary" wire:click="publishEvent">
                            {{ __('Erneut veröffentlichen') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" wire:click="publishEvent" wire:confirm="{{ __('Publish this event? It will become publicly accessible.') }}">
                            {{ __('Publish') }}
                        </flux:button>
                    @endif
                    <flux:button variant="danger" size="sm" icon="trash" wire:click="$set('showDeleteModal', true)">
                        {{ __('Löschen') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::PublishedOpen)
                    <flux:button variant="subtle" wire:click="closeRegistration" wire:confirm="{{ __('Close registration? Volunteers will no longer be able to sign up, but the event page remains visible.') }}">
                        {{ __('Close Registration') }}
                    </flux:button>
                    <flux:button variant="subtle" wire:click="revertToDraft" wire:confirm="{{ __('Zurück zu Entwurf? Das Event wird nicht mehr öffentlich sichtbar sein.') }}">
                        {{ __('Zurück zu Entwurf') }}
                    </flux:button>
                    <flux:button variant="subtle" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? It will be removed from public view.') }}">
                        {{ __('Archive') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::PublishedClosed)
                    <flux:button variant="subtle" wire:click="revertToDraft" wire:confirm="{{ __('Zurück zu Entwurf? Das Event wird nicht mehr öffentlich sichtbar sein.') }}">
                        {{ __('Zurück zu Entwurf') }}
                    </flux:button>
                    <flux:button variant="subtle" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? It will be removed from public view.') }}">
                        {{ __('Archive') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::Archived)
                    <flux:button variant="danger" size="sm" icon="trash" wire:click="$set('showDeleteModal', true)">
                        {{ __('Löschen') }}
                    </flux:button>
                @endif
            </div>

            {{-- Collapsed dropdown (below lg) --}}
            <div class="xl:hidden">
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="ellipsis-vertical" aria-label="{{ __('Actions') }}" />
                    <flux:menu>
                        <flux:menu.item icon="document-duplicate" wire:click="openCloneModal">
                            {{ __('Clone') }}
                        </flux:menu.item>
                        @if ($event->isPendingDeletion())
                            <flux:menu.item icon="arrow-uturn-left" wire:click="restoreEvent">
                                {{ __('Wiederherstellen') }}
                            </flux:menu.item>
                        @elseif ($event->status === \App\Enums\EventStatus::Draft)
                            @if ($event->was_previously_published)
                                <flux:menu.item icon="arrow-path" wire:click="publishEvent">
                                    {{ __('Erneut veröffentlichen') }}
                                </flux:menu.item>
                            @else
                                <flux:menu.item icon="globe-alt" wire:click="publishEvent" wire:confirm="{{ __('Publish this event? It will become publicly accessible.') }}">
                                    {{ __('Publish') }}
                                </flux:menu.item>
                            @endif
                            <flux:menu.separator />
                            <flux:menu.item variant="danger" icon="trash" wire:click="$set('showDeleteModal', true)">
                                {{ __('Löschen') }}
                            </flux:menu.item>
                        @elseif ($event->status === \App\Enums\EventStatus::PublishedOpen)
                            <flux:menu.item icon="lock-closed" wire:click="closeRegistration" wire:confirm="{{ __('Close registration? Volunteers will no longer be able to sign up, but the event page remains visible.') }}">
                                {{ __('Close Registration') }}
                            </flux:menu.item>
                            <flux:menu.item icon="pencil-square" wire:click="revertToDraft" wire:confirm="{{ __('Zurück zu Entwurf? Das Event wird nicht mehr öffentlich sichtbar sein.') }}">
                                {{ __('Zurück zu Entwurf') }}
                            </flux:menu.item>
                            <flux:menu.item icon="archive-box" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? It will be removed from public view.') }}">
                                {{ __('Archive') }}
                            </flux:menu.item>
                        @elseif ($event->status === \App\Enums\EventStatus::PublishedClosed)
                            <flux:menu.item icon="pencil-square" wire:click="revertToDraft" wire:confirm="{{ __('Zurück zu Entwurf? Das Event wird nicht mehr öffentlich sichtbar sein.') }}">
                                {{ __('Zurück zu Entwurf') }}
                            </flux:menu.item>
                            <flux:menu.item icon="archive-box" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? It will be removed from public view.') }}">
                                {{ __('Archive') }}
                            </flux:menu.item>
                        @elseif ($event->status === \App\Enums\EventStatus::Archived)
                            <flux:menu.item variant="danger" icon="trash" wire:click="$set('showDeleteModal', true)">
                                {{ __('Löschen') }}
                            </flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        @endif
    </div>

    {{-- Pending deletion warning --}}
    @if ($event->isPendingDeletion())
        <flux:callout variant="warning" class="mb-4">
            {{ __('Dieses Event ist zur Löschung vorgemerkt und wird am :date endgültig gelöscht.', ['date' => $event->deletion_requested_at->addDays(30)->format('d.m.Y')]) }}
        </flux:callout>
    @endif

    {{-- Status errors --}}
    @error('status')
        <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
    @enderror

    {{-- Project badge --}}
    @if ($event->project)
        <div class="mb-4">
            <a href="{{ route('projects.show', $event->project) }}" wire:navigate class="inline-flex items-center gap-1.5">
                <flux:badge size="sm" color="sky" icon="folder">
                    {{ $event->project->name }}
                </flux:badge>
            </a>
        </div>
    @endif

    <x-events.layout :event="$event">
        {{-- Share link for published events --}}
        @if ($this->publicUrl)
            <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="link" class="size-4" />
                </div>
                <div>
                    <flux:text size="sm" class="font-medium text-emerald-800 dark:text-emerald-200">
                        {{ __('Public signup link:') }}
                    </flux:text>
                    <flux:text size="sm" class="mt-1 font-mono text-emerald-700 dark:text-emerald-300 break-all">
                        {{ $this->publicUrl }}
                    </flux:text>
                </div>
            </div>
        @endif

        {{-- Metric cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <flux:card size="sm">
                <div class="flex items-center gap-3">
                    <span class="size-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="user-group" variant="mini" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm">{{ __('Volunteers') }}</flux:text>
                        <flux:heading size="xl">{{ $this->volunteerCount }}</flux:heading>
                    </div>
                </div>
            </flux:card>
            <flux:card size="sm">
                <div class="flex items-center gap-3">
                    <span class="size-2.5 shrink-0 rounded-full bg-amber-500"></span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                        <flux:icon name="briefcase" variant="mini" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm">{{ __('Jobs') }}</flux:text>
                        <flux:heading size="xl">{{ $this->jobCount }}</flux:heading>
                    </div>
                </div>
            </flux:card>
            <flux:card size="sm">
                <div class="flex items-center gap-3">
                    <span class="size-2.5 shrink-0 rounded-full bg-sky-500"></span>
                    <div class="flex size-9 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                        <flux:icon name="clock" variant="mini" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm">{{ __('Shifts') }}</flux:text>
                        <flux:heading size="xl">{{ $this->shiftCount }}</flux:heading>
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Title image --}}
        @if ($event->titleImageUrl())
            <div class="mb-6">
                <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="w-full max-h-64 object-cover rounded-xl shadow-sm" />
            </div>
        @endif

        {{-- Event details (read-only) --}}
        <flux:card>
            <div class="space-y-3">
                @if ($event->description)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Description') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->description }}</flux:text>
                    </div>
                @endif

                @if ($event->location)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Location') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->location }}</flux:text>
                    </div>
                @endif

                <div>
                    <flux:text size="sm" class="font-medium">{{ __('Date & Time') }}</flux:text>
                    <flux:text class="mt-1">
                        {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('M d, Y g:i A') }}
                    </flux:text>
                </div>

                @if ($event->visibility === \App\Enums\EventVisibility::Private)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Visibility') }}</flux:text>
                        <flux:text class="mt-1">{{ __('Private') }}</flux:text>
                    </div>
                @endif

                @if ($event->notification_email)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Benachrichtigungs-E-Mail') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->notification_email }}</flux:text>
                    </div>
                @endif

                @if ($event->attendance_grace_minutes)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Grace Period') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->attendance_grace_minutes }} {{ __('minutes') }}</flux:text>
                    </div>
                @endif
            </div>
        </flux:card>
    </x-events.layout>

    {{-- Re-publish modal --}}
    <flux:modal wire:model="showRepublishModal" focusable class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Erneut veröffentlichen') }}</flux:heading>
            <flux:text>{{ __('Dieses Event wurde bereits veröffentlicht. Angemeldete Helfer:innen werden per E-Mail über die Reaktivierung informiert.') }}</flux:text>
            <flux:textarea
                wire:model="republishNote"
                :label="__('Nachricht an Helfer:innen (optional)')"
                :placeholder="__('z.B. Neuer Termin, geänderter Ort...')"
                rows="3"
            />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmRepublish">
                    {{ __('Veröffentlichen & benachrichtigen') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Clone modal --}}
    <flux:modal wire:model="showCloneModal" focusable class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Event duplizieren') }}</flux:heading>
            <flux:text>{{ __('Ein neues Entwurfs-Event wird mit denselben Jobs und Schichten erstellt.') }}</flux:text>
            <flux:input
                type="number"
                wire:model="cloneDateOffset"
                :label="__('Datumsverschiebung (Tage)')"
                :placeholder="__('z.B. 7 für eine Woche später, -7 für früher')"
            />
            <flux:text size="sm" class="text-zinc-500">{{ __('Leer lassen, um die gleichen Daten zu verwenden.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmClone">
                    {{ __('Duplizieren') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal wire:model="showDeleteModal" focusable class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Event löschen') }}</flux:heading>
            <flux:text>{{ __('Dieses Event wird in 30 Tagen endgültig gelöscht. Du kannst es in dieser Zeit jederzeit wiederherstellen.') }}</flux:text>
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
</div>
