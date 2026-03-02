<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('events.show', $event)" wire:navigate />
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
        </div>
    </div>

    <x-events.tab-nav :event="$event" />

    <div class="space-y-6">
        {{-- Template type selector --}}
        <flux:field>
            <flux:label>{{ __('Template Type') }}</flux:label>
            <flux:select wire:model.live="selectedType">
                @foreach (\App\Enums\EmailTemplateType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">
                        {{ __(str_replace('_', ' ', ucwords($type->value, '_'))) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        {{-- Customization indicator --}}
        @if ($this->isCustomized)
            <flux:badge color="blue">{{ __('Customized') }}</flux:badge>
        @else
            <flux:badge color="zinc">{{ __('Using default') }}</flux:badge>
        @endif

        {{-- Editor --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <flux:field>
                    <flux:label>{{ __('Subject') }}</flux:label>
                    <flux:input wire:model="subject" />
                    <flux:error name="subject" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Body') }}</flux:label>
                    <flux:textarea wire:model="body" rows="10" />
                    <flux:error name="body" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="saveTemplate">
                        {{ __('Save Template') }}
                    </flux:button>
                    <flux:button variant="subtle" wire:click="previewTemplate">
                        {{ __('Preview') }}
                    </flux:button>
                    @if ($this->isCustomized)
                        <flux:button variant="danger" wire:click="resetToDefault" wire:confirm="{{ __('Reset this template to its default content?') }}">
                            {{ __('Reset to Default') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            {{-- Placeholder reference --}}
            <flux:card>
                <div class="flex items-center gap-2 mb-3">
                    <flux:icon name="code-bracket" class="size-5 text-emerald-600 dark:text-emerald-400" />
                    <flux:heading size="sm">{{ __('Available Placeholders') }}</flux:heading>
                </div>
                <div class="space-y-2">
                    @foreach ($this->availablePlaceholders as $placeholder)
                        <div class="flex items-center gap-2">
                            <code class="rounded bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-2 py-1 text-sm font-mono">@{{ '{{' . $placeholder . '}}' }}</code>
                        </div>
                    @endforeach
                </div>
                <flux:text size="sm" class="mt-4 text-zinc-500">
                    {{ __('Use these variables in your subject or body. They will be replaced with actual values when the email is sent.') }}
                </flux:text>
            </flux:card>
        </div>

        {{-- Preview panel --}}
        @if ($showPreview)
            <flux:card>
                <flux:heading size="sm" class="mb-4">{{ __('Preview') }}</flux:heading>
                <div class="mb-3">
                    <flux:text size="sm" class="font-medium">{{ __('Subject:') }}</flux:text>
                    <flux:text>{{ $previewSubject }}</flux:text>
                </div>
                <div>
                    <flux:text size="sm" class="font-medium">{{ __('Body:') }}</flux:text>
                    <div class="mt-1 rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4 prose dark:prose-invert prose-sm max-w-none">
                        {!! \Illuminate\Support\Str::markdown($previewBody) !!}
                    </div>
                </div>
            </flux:card>
        @endif
    </div>
</div>
