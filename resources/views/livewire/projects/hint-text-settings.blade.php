<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-6">
        <flux:button variant="ghost" icon="arrow-left" :href="route('projects.show', $project)" wire:navigate aria-label="{{ __('Back to project') }}" />
        <flux:heading size="xl">{{ __('Hinweistexte') }} &mdash; {{ $project->name }}</flux:heading>
    </div>

    <flux:text class="mb-6 text-zinc-500 dark:text-zinc-400">
        {{ __('Konfiguriere Hilfetexte, die Freiwilligen an verschiedenen Stellen angezeigt werden.') }}
    </flux:text>

    <div class="space-y-4">
        @foreach (\App\Enums\HintLocation::cases() as $location)
            @php
                $hint = $this->hints[$location->value] ?? ['text' => null, 'enabled' => false];
                $isEditing = $editingLocation === $location->value;
            @endphp
            <flux:card wire:key="hint-{{ $location->value }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-2">
                            <flux:heading size="sm">{{ $location->label() }}</flux:heading>
                            @if ($hint['text'])
                                <flux:badge size="sm" :color="$hint['enabled'] ? 'emerald' : 'zinc'">
                                    {{ $hint['enabled'] ? __('Aktiv') : __('Deaktiviert') }}
                                </flux:badge>
                            @endif
                        </div>

                        @if ($isEditing)
                            <div class="space-y-3">
                                <flux:textarea wire:model="editText" rows="3" placeholder="{{ __('Hinweistext eingeben...') }}" />
                                <flux:error name="editText" />
                                <div class="flex gap-2">
                                    <flux:button variant="primary" size="sm" wire:click="saveHint">
                                        {{ __('Speichern') }}
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" wire:click="cancelEditing">
                                        {{ __('Abbrechen') }}
                                    </flux:button>
                                </div>
                            </div>
                        @elseif ($hint['text'])
                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-300">{{ $hint['text'] }}</flux:text>
                        @else
                            <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">{{ __('Nicht konfiguriert') }}</flux:text>
                        @endif
                    </div>

                    @if (! $isEditing)
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($hint['text'])
                                <flux:button variant="subtle" size="sm" icon="arrow-path" wire:click="toggleEnabled('{{ $location->value }}')" aria-label="{{ __('Toggle') }}">
                                    {{ $hint['enabled'] ? __('Deaktivieren') : __('Aktivieren') }}
                                </flux:button>
                                <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteHint('{{ $location->value }}')" wire:confirm="{{ __('Diesen Hinweistext wirklich löschen?') }}" aria-label="{{ __('Delete') }}" />
                            @endif
                            <flux:button variant="subtle" size="sm" icon="pencil" wire:click="startEditing('{{ $location->value }}')">
                                {{ $hint['text'] ? __('Bearbeiten') : __('Erstellen') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
