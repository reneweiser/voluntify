<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('Custom Registration Fields') }}</flux:heading>
        </div>

        {{-- Existing fields --}}
        @if ($this->customFields->isNotEmpty())
            <div class="space-y-3 mb-8">
                @foreach ($this->customFields as $field)
                    <div wire:key="field-{{ $field->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $field->label }}
                                @if ($field->required)
                                    <span class="text-red-500">*</span>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ ucfirst($field->type->value) }}
                                @if ($field->type->value === 'select' && !empty($field->options['choices']))
                                    &mdash; {{ implode(', ', $field->options['choices']) }}
                                @elseif ($field->type->value === 'text' && !empty($field->options['multiline']))
                                    &mdash; {{ __('Multiline') }}
                                @endif
                            </div>
                        </div>
                        <flux:button variant="danger" size="sm" wire:click="removeField({{ $field->id }})" wire:confirm="{{ __('Remove this field? Existing responses will be preserved but the field will no longer appear on the signup form.') }}">
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center mb-8">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="adjustments-horizontal" class="size-8 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('No custom fields yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add custom registration fields to collect additional information from volunteers.') }}</flux:text>
            </div>
        @endif

        {{-- Templates --}}
        <div class="mb-8">
            <flux:heading size="sm" class="mb-3">{{ __('Quick Templates') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->templates as $key => $template)
                    <flux:button size="sm" variant="ghost" wire:click="applyTemplate('{{ $key }}')">
                        {{ $template['label'] }}
                    </flux:button>
                @endforeach
            </div>
        </div>

        {{-- Add new field form --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
            <flux:heading size="sm" class="mb-4">{{ __('Add Field') }}</flux:heading>

            <form wire:submit="addField" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Label') }}</flux:label>
                    <flux:input wire:model="newFieldLabel" placeholder="{{ __('e.g. Emergency Contact, Dietary Restrictions') }}" />
                    <flux:error name="newFieldLabel" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select wire:model.live="newFieldType">
                        <flux:select.option value="text">{{ __('Text') }}</flux:select.option>
                        <flux:select.option value="select">{{ __('Select / Dropdown') }}</flux:select.option>
                        <flux:select.option value="checkbox">{{ __('Checkbox') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                @if ($newFieldType === 'text')
                    <flux:field>
                        <flux:checkbox wire:model="newFieldMultiline" label="{{ __('Multiline (textarea)') }}" />
                    </flux:field>
                @endif

                @if ($newFieldType === 'select')
                    <flux:field>
                        <flux:label>{{ __('Options') }}</flux:label>
                        <flux:input wire:model="newFieldOptions" placeholder="{{ __('Option A, Option B, Option C') }}" />
                        <flux:description>{{ __('Comma-separated list of options.') }}</flux:description>
                        <flux:error name="newFieldOptions" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:checkbox wire:model="newFieldRequired" label="{{ __('Required') }}" />
                </flux:field>

                <flux:button type="submit" variant="primary">
                    {{ __('Add Field') }}
                </flux:button>
            </form>
        </div>

        {{-- Signup warning modal --}}
        @if ($showSignupWarning)
            <flux:modal wire:model="showSignupWarning" class="max-w-sm">
                <div class="space-y-4">
                    <flux:heading size="lg">{{ __('Existing Signups') }}</flux:heading>
                    <flux:text>{{ __('Volunteers have already signed up without this field. Only new signups will be required to answer. Continue?') }}</flux:text>
                    <div class="flex gap-2 justify-end">
                        <flux:button variant="ghost" wire:click="dismissWarning">{{ __('Cancel') }}</flux:button>
                        <flux:button variant="primary" wire:click="confirmAddField">{{ __('Continue') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-events.layout>
</div>
