<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Email settings')" :subheading="__('Configure your organization\'s outgoing email server')">
        <form wire:submit="saveSmtpSettings" class="space-y-4">
            <flux:input wire:model="smtpHost" :label="__('SMTP host')" placeholder="smtp.example.com" />
            <flux:input wire:model="smtpPort" :label="__('SMTP port')" type="number" placeholder="587" />
            <flux:input wire:model="smtpUsername" :label="__('Username')" />

            @if ($this->hasSmtpSettings && $this->maskedSmtpPassword)
                <div>
                    <flux:input :value="$this->maskedSmtpPassword" :label="__('Current password')" disabled />
                    <flux:input wire:model="smtpPassword" :label="__('New password (leave blank to keep current)')" type="password" class="mt-2" />
                </div>
            @else
                <flux:input wire:model="smtpPassword" :label="__('Password')" type="password" />
            @endif

            <flux:select wire:model="smtpEncryption" :label="__('Encryption')">
                <flux:select.option value="tls">{{ __('TLS') }}</flux:select.option>
                <flux:select.option value="ssl">{{ __('SSL') }}</flux:select.option>
                <flux:select.option value="none">{{ __('None') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="smtpFromAddress" :label="__('From address')" type="email" placeholder="noreply@example.com" />
            <flux:input wire:model="smtpFromName" :label="__('From name')" placeholder="{{ $this->organization->name }}" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save settings') }}</flux:button>

                @if ($this->hasSmtpSettings)
                    <flux:button
                        variant="danger"
                        wire:click="removeSmtpSettings"
                        wire:confirm="{{ __('Are you sure you want to remove SMTP settings? Emails will use the default server.') }}"
                        type="button"
                    >
                        {{ __('Remove') }}
                    </flux:button>
                @endif
            </div>

            <x-action-message on="smtp-settings-saved">{{ __('SMTP settings saved.') }}</x-action-message>
            <x-action-message on="smtp-settings-removed">{{ __('SMTP settings removed.') }}</x-action-message>
        </form>

        @if ($this->hasSmtpSettings)
            <flux:separator class="my-8" />

            <div>
                <flux:heading size="sm">{{ __('Send test email') }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('Verify your SMTP configuration by sending a test email.') }}</flux:text>

                <div class="mt-4 space-y-4">
                    <flux:input wire:model="testEmailAddress" :label="__('Recipient email')" type="email" />

                    <flux:button variant="primary" wire:click="sendTestEmail">{{ __('Send test email') }}</flux:button>

                    <x-action-message on="test-email-sent">{{ __('Test email sent successfully.') }}</x-action-message>
                </div>
            </div>
        @endif
    </x-settings.layout>
</section>
