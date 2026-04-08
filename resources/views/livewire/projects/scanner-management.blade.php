<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
            {{ __('New Scanner') }}
        </flux:button>
    </div>

    <x-projects.layout :project="$project">

    @if (session('message'))
        <flux:callout variant="success" class="mb-4">{{ session('message') }}</flux:callout>
    @endif

    @error('scanner')
        <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
    @enderror

    {{-- Raw auth code (shown once after creation) --}}
    @if (session('rawAuthCode'))
        @php $authCodeData = session('rawAuthCode'); @endphp
        <flux:callout variant="warning" class="mb-4">
            <strong>{{ __('Auth code for scanner #:id:', ['id' => $authCodeData['id']]) }}</strong>
            <code class="ml-2 font-mono text-lg tracking-widest">{{ $authCodeData['code'] }}</code>
            <flux:text size="sm" class="mt-1">{{ __('This code is shown only once. Share it securely with scanner operators.') }}</flux:text>
        </flux:callout>
    @endif

    {{-- Scanner list --}}
    @if ($this->scanners->isEmpty())
        <flux:card>
            <flux:text class="text-center text-zinc-500 dark:text-zinc-400">
                {{ __('No scanners configured for this project yet.') }}
            </flux:text>
        </flux:card>
    @else
        <div class="space-y-4">
            @foreach ($this->scanners as $scanner)
                <flux:card wire:key="scanner-{{ $scanner->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <flux:heading size="lg">{{ $scanner->name }}</flux:heading>
                                @php $status = $scanner->status; @endphp
                                <flux:badge size="sm" :color="match($status) { 'active' => 'emerald', 'scheduled' => 'blue', 'expired' => 'zinc' }">
                                    {{ ucfirst($status) }}
                                </flux:badge>
                                <flux:badge size="sm" :color="$scanner->type === \App\Enums\ScannerType::EntryStaff ? 'blue' : 'purple'">
                                    {{ $scanner->type === \App\Enums\ScannerType::EntryStaff ? __('Entry Staff') : __('Volunteer Admin') }}
                                </flux:badge>
                            </div>
                            <flux:text size="sm" class="text-zinc-500">
                                @php $tz = $project->timezone ?? 'UTC'; @endphp
                                {{ $scanner->starts_at->setTimezone($tz)->format('M d, Y H:i') }} &mdash; {{ $scanner->ends_at->setTimezone($tz)->format('M d, Y H:i') }}
                            </flux:text>
                            @if ($scanner->hint_text)
                                <flux:text size="sm" class="mt-1">{{ $scanner->hint_text }}</flux:text>
                            @endif

                            {{-- Assignees --}}
                            <div class="mt-3">
                                <flux:text size="sm" class="font-medium mb-1">{{ __('Assignees') }}</flux:text>
                                @if ($scanner->assignees->isEmpty())
                                    <flux:text size="sm" class="text-zinc-400">{{ __('No assignees yet.') }}</flux:text>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($scanner->assignees as $assignee)
                                            <flux:badge size="sm" wire:key="assignee-{{ $assignee->id }}">
                                                {{ $assignee->email }}
                                                <button type="button" wire:click="removeAssignee({{ $assignee->id }})" class="ml-1 text-zinc-400 hover:text-red-500" aria-label="{{ __('Remove :email', ['email' => $assignee->email]) }}">
                                                    &times;
                                                </button>
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Add assignee inline --}}
                                <div x-data="{ email: '', adding: false }" class="mt-2">
                                    <template x-if="!adding">
                                        <flux:button variant="ghost" size="sm" icon="plus" x-on:click="adding = true">
                                            {{ __('Add assignee') }}
                                        </flux:button>
                                    </template>
                                    <template x-if="adding">
                                        <div class="flex gap-2 items-center">
                                            <input
                                                x-model="email"
                                                type="email"
                                                placeholder="email@example.com"
                                                aria-label="{{ __('Assignee email address') }}"
                                                class="rounded-md border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-1.5 text-sm"
                                                x-on:keydown.enter="$wire.addAssignee({{ $scanner->id }}, email); email = ''; adding = false"
                                            />
                                            <flux:button size="sm" variant="primary" x-on:click="$wire.addAssignee({{ $scanner->id }}, email); email = ''; adding = false">
                                                {{ __('Add') }}
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" x-on:click="adding = false">
                                                {{ __('Cancel') }}
                                            </flux:button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2">
                            <flux:button size="sm" icon="paper-airplane" wire:click="sendLinks({{ $scanner->id }})" title="{{ __('Send links') }}">
                                {{ __('Send Links') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-path"
                                wire:click="regenerateAuthCode({{ $scanner->id }})"
                                wire:confirm="{{ __('This will generate a new auth code and email it to all assignees. The old code will stop working. Continue?') }}"
                                title="{{ __('Regenerate Auth Code') }}"
                            />
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editScanner({{ $scanner->id }})" title="{{ __('Edit') }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $scanner->id }})" title="{{ __('Delete') }}" />
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    </x-projects.layout>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showCreateModal">
        <flux:heading>{{ $editingScannerId ? __('Edit Scanner') : __('New Scanner') }}</flux:heading>

        <div class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="form.name" placeholder="e.g. Eingang Nord" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model="form.type">
                    <flux:select.option value="entry_staff">{{ __('Entry Staff') }}</flux:select.option>
                    <flux:select.option value="volunteer_admin">{{ __('Volunteer Admin') }}</flux:select.option>
                </flux:select>
                <flux:error name="form.type" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Modes') }}</flux:label>
                <div class="flex gap-4">
                    <flux:checkbox wire:model="form.modes" value="checkin" label="{{ __('Check-in') }}" />
                    <flux:checkbox wire:model="form.modes" value="gear_pickup" label="{{ __('Gear Pickup') }}" />
                </div>
                <flux:error name="form.modes" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Scope to Event (optional)') }}</flux:label>
                <flux:select wire:model="form.eventId">
                    <flux:select.option value="">{{ __('Project-wide') }}</flux:select.option>
                    @foreach ($this->events as $event)
                        <flux:select.option :value="$event->id">{{ $event->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Starts at') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="form.startsAt" />
                    <flux:error name="form.startsAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Ends at') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="form.endsAt" />
                    <flux:error name="form.endsAt" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Hint Text (optional)') }}</flux:label>
                <flux:textarea wire:model="form.hintText" rows="2" placeholder="{{ __('Instructions for scanner operators...') }}" />
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
            @if ($editingScannerId)
                <flux:button variant="primary" wire:click="updateScanner">{{ __('Update') }}</flux:button>
            @else
                <flux:button variant="primary" wire:click="createScanner">{{ __('Create') }}</flux:button>
            @endif
        </div>
    </flux:modal>

    {{-- Delete confirmation --}}
    <flux:modal wire:model="showDeleteConfirm">
        <flux:heading>{{ __('Delete Scanner') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Are you sure you want to delete this scanner? All assignees will be removed.') }}</flux:text>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteConfirm', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteScanner">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
