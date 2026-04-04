<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('events.show', $event)" wire:navigate aria-label="{{ __('Back to event') }}" />
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
        </div>
    </div>

    <x-events.layout :event="$event">
        <div class="space-y-6">
            {{-- Template type selector --}}
            <flux:field>
                <flux:label>Vorlagentyp</flux:label>
                <flux:select wire:model.live="selectedType">
                    @foreach (\App\Enums\EmailTemplateType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">
                            {{ $type->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Customization indicator --}}
            @if ($this->isCustomized)
                <flux:badge color="blue">Angepasst</flux:badge>
            @else
                <flux:badge color="zinc">Standard-Vorlage</flux:badge>
            @endif

            {{-- Editor --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-4">
                    <flux:field>
                        <flux:label>Betreff</flux:label>
                        <flux:input wire:model="subject" />
                        <flux:error name="subject" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Inhalt</flux:label>
                        <flux:textarea wire:model="body" rows="10" />
                        <flux:error name="body" />
                    </flux:field>

                    <div class="flex gap-2">
                        <flux:button variant="primary" wire:click="saveTemplate">
                            Vorlage speichern
                        </flux:button>
                        <flux:button variant="subtle" wire:click="previewTemplate">
                            Vorschau
                        </flux:button>
                        @if ($this->isCustomized)
                            <flux:button variant="danger" wire:click="resetToDefault" wire:confirm="Diese Vorlage auf den Standard zurücksetzen?">
                                Auf Standard zurücksetzen
                            </flux:button>
                        @endif
                    </div>
                </div>

                {{-- Placeholder reference --}}
                <flux:card>
                    <div class="flex items-center gap-2 mb-3">
                        <flux:icon name="code-bracket" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        <flux:heading size="sm">Verfügbare Platzhalter</flux:heading>
                    </div>
                    <div class="space-y-2">
                        @foreach ($this->availablePlaceholders as $placeholder)
                            <div class="flex items-center gap-2">
                                <code class="rounded bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-2 py-1 text-sm font-mono">{{ '{' . '{' . $placeholder . '}' . '}' }}</code>
                            </div>
                        @endforeach
                    </div>
                    <flux:text size="sm" class="mt-4 text-zinc-500">
                        Verwende diese Variablen in Betreff oder Text. Sie werden beim Versand durch die tatsächlichen Werte ersetzt.
                    </flux:text>
                    @if ($selectedType === 'signup_confirmation')
                        <flux:text size="sm" class="mt-2 text-zinc-500">
                            Hinweis: {{ '{' . '{shifts_summary}' . '}' }} listet alle gewählten Schichten auf. {{ '{' . '{job_name}' . '}' }}, {{ '{' . '{shift_date}' . '}' }} und {{ '{' . '{shift_time}' . '}' }} beziehen sich nur auf die erste Schicht.
                        </flux:text>
                    @endif
                </flux:card>
            </div>

            {{-- Preview panel --}}
            @if ($showPreview)
                <flux:card>
                    <flux:heading size="sm" class="mb-4">Vorschau</flux:heading>
                    <div class="mb-3">
                        <flux:text size="sm" class="font-medium">Betreff:</flux:text>
                        <flux:text>{{ $previewSubject }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="font-medium">Inhalt:</flux:text>
                        <div class="mt-1 rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4 prose dark:prose-invert prose-sm max-w-none">
                            {!! \Illuminate\Support\Str::markdown($previewBody, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>
    </x-events.layout>
</div>
