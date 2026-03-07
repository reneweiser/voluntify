<?php

namespace App\Livewire\Settings;

use App\Actions\SendTestEmail;
use App\Enums\SmtpEncryption;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportException;

#[Title('Email')]
class EmailSettings extends Component
{
    public string $smtpHost = '';

    public ?int $smtpPort = null;

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    public string $smtpEncryption = 'tls';

    public string $smtpFromAddress = '';

    public string $smtpFromName = '';

    public string $testEmailAddress = '';

    public function mount(): void
    {
        Gate::authorize('update', $this->organization());

        $org = $this->organization();

        $this->smtpHost = $org->smtp_host ?? '';
        $this->smtpPort = $org->smtp_port;
        $this->smtpUsername = $org->smtp_username ?? '';
        $this->smtpEncryption = $org->smtp_encryption?->value ?? 'tls';
        $this->smtpFromAddress = $org->smtp_from_address ?? '';
        $this->smtpFromName = $org->smtp_from_name ?? '';
        $this->testEmailAddress = Auth::user()->email;
    }

    #[Computed]
    public function organization(): Organization
    {
        return app(Organization::class);
    }

    #[Computed]
    public function hasSmtpSettings(): bool
    {
        return $this->organization()->smtp_host !== null;
    }

    #[Computed]
    public function maskedSmtpPassword(): ?string
    {
        $password = $this->organization()->smtp_password;

        if (! $password) {
            return null;
        }

        return Str::mask($password, '*', 4);
    }

    public function saveSmtpSettings(): void
    {
        Gate::authorize('update', $this->organization());

        $validated = $this->validate([
            'smtpHost' => ['required', 'string', 'max:255'],
            'smtpPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtpUsername' => ['nullable', 'string', 'max:255'],
            'smtpPassword' => ['nullable', 'string', 'max:500'],
            'smtpEncryption' => ['required', Rule::enum(SmtpEncryption::class)],
            'smtpFromAddress' => ['required', 'email', 'max:255'],
            'smtpFromName' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'smtp_host' => $validated['smtpHost'],
            'smtp_port' => $validated['smtpPort'],
            'smtp_username' => $validated['smtpUsername'] ?: null,
            'smtp_encryption' => $validated['smtpEncryption'],
            'smtp_from_address' => $validated['smtpFromAddress'],
            'smtp_from_name' => $validated['smtpFromName'] ?: null,
        ];

        if ($validated['smtpPassword'] !== '') {
            $data['smtp_password'] = $validated['smtpPassword'];
        }

        $this->organization()->update($data);

        $this->reset('smtpPassword');
        $this->dispatch('smtp-settings-saved');
    }

    public function removeSmtpSettings(): void
    {
        Gate::authorize('update', $this->organization());

        $this->organization()->update([
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_encryption' => null,
            'smtp_from_address' => null,
            'smtp_from_name' => null,
        ]);

        $this->reset('smtpHost', 'smtpPort', 'smtpUsername', 'smtpPassword', 'smtpEncryption', 'smtpFromAddress', 'smtpFromName');
        $this->smtpEncryption = 'tls';

        unset($this->hasSmtpSettings, $this->maskedSmtpPassword);

        $this->dispatch('smtp-settings-removed');
    }

    public function sendTestEmail(): void
    {
        Gate::authorize('update', $this->organization());

        $this->validate([
            'testEmailAddress' => ['required', 'email'],
        ]);

        try {
            app(SendTestEmail::class)->execute($this->organization(), $this->testEmailAddress);
            $this->dispatch('test-email-sent');
        } catch (TransportException $e) {
            $this->addError('testEmailAddress', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
