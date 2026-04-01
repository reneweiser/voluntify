<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
        </div>
        <div class="flex items-center gap-3">
            @if ($websitePublished)
                <flux:badge color="emerald">{{ __('Veröffentlicht') }}</flux:badge>
            @else
                <flux:badge color="zinc">{{ __('Nicht veröffentlicht') }}</flux:badge>
            @endif
        </div>
    </div>

    <x-projects.layout :project="$project">

    {{-- Public URL --}}
    @if ($websitePublished)
        <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="link" class="size-4" />
            </div>
            <div>
                <flux:text size="sm" class="font-medium text-emerald-800 dark:text-emerald-200">
                    {{ __('Öffentlicher Link:') }}
                </flux:text>
                <flux:text size="sm" class="mt-1 font-mono text-emerald-700 dark:text-emerald-300 break-all">
                    {{ $this->publicUrl }}
                </flux:text>
            </div>
        </div>
    @endif

    <form wire:submit="saveWebsite" class="space-y-6">
        {{-- Content --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Inhalte') }}</flux:heading>
            <div class="space-y-4">
                <flux:textarea
                    wire:model="websiteDescription"
                    :label="__('Beschreibung')"
                    :placeholder="__('Beschreibe dein Projekt für die öffentliche Website...')"
                    rows="8"
                />
                <flux:text size="sm" class="text-zinc-500">
                    {{ __('Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, [Links](url)') }}
                </flux:text>
            </div>
        </flux:card>

        {{-- Contact info --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Kontaktinformationen') }}</flux:heading>
            <flux:input
                wire:model="websiteContactInfo"
                :label="__('Kontaktinfo')"
                :placeholder="__('z.B. E-Mail, Telefon, Social Media')"
            />
        </flux:card>

        {{-- Publish toggle --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Veröffentlichung') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">
                        {{ __('Wenn aktiviert, ist die Projekt-Website unter dem öffentlichen Link erreichbar.') }}
                    </flux:text>
                </div>
                <flux:switch wire:model.live="websitePublished" />
            </div>
        </flux:card>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" :href="route('projects.show', $project)" wire:navigate>
                {{ __('Abbrechen') }}
            </flux:button>
            <flux:button variant="primary" type="submit">
                {{ __('Speichern') }}
            </flux:button>
        </div>
    </form>

    {{-- Preview of events --}}
    @if ($this->previewEvents->isNotEmpty())
        <div class="mt-8">
            <flux:heading size="lg" class="mb-4">{{ __('Vorschau: Sichtbare Events') }}</flux:heading>
            <div class="grid gap-4">
                @foreach ($this->previewEvents as $event)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <div>
                            <flux:text class="font-medium">{{ $event->name }}</flux:text>
                            <flux:text size="sm">{{ $event->starts_at->format('d.m.Y H:i') }}</flux:text>
                        </div>
                        <flux:badge size="sm" :color="$event->status === \App\Enums\EventStatus::PublishedOpen ? 'emerald' : 'yellow'">
                            {{ $event->status->label() }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    </x-projects.layout>
</div>
