<?php

use App\Enums\StaffRole;
use App\Livewire\Settings\EmailSettings;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $this->org);
});

it('renders the email settings page for organizers', function () {
    $this->actingAs($this->organizer)
        ->get(route('settings.email'))
        ->assertOk()
        ->assertSeeLivewire(EmailSettings::class);
});

it('denies access to non-organizers', function (StaffRole $role) {
    $user = User::factory()->create();
    $this->org->users()->attach($user, ['role' => $role]);

    $this->actingAs($user)
        ->get(route('settings.email'))
        ->assertForbidden();
})->with([StaffRole::VolunteerAdmin, StaffRole::EntranceStaff]);

it('saves SMTP settings', function () {
    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('smtpHost', 'smtp.example.com')
        ->set('smtpPort', 587)
        ->set('smtpUsername', 'user@example.com')
        ->set('smtpPassword', 'secret123')
        ->set('smtpEncryption', 'tls')
        ->set('smtpFromAddress', 'noreply@example.com')
        ->set('smtpFromName', 'Test Org')
        ->call('saveSmtpSettings')
        ->assertHasNoErrors()
        ->assertDispatched('smtp-settings-saved');

    $this->org->refresh();
    expect($this->org->smtp_host)->toBe('smtp.example.com')
        ->and($this->org->smtp_port)->toBe(587)
        ->and($this->org->smtp_username)->toBe('user@example.com')
        ->and($this->org->smtp_password)->toBe('secret123')
        ->and($this->org->smtp_encryption->value)->toBe('tls')
        ->and($this->org->smtp_from_address)->toBe('noreply@example.com')
        ->and($this->org->smtp_from_name)->toBe('Test Org');
});

it('validates required fields when saving', function () {
    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('smtpHost', '')
        ->set('smtpPort', null)
        ->set('smtpFromAddress', '')
        ->call('saveSmtpSettings')
        ->assertHasErrors(['smtpHost', 'smtpPort', 'smtpFromAddress']);
});

it('validates port is numeric and within range', function () {
    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('smtpHost', 'smtp.example.com')
        ->set('smtpPort', 99999)
        ->set('smtpFromAddress', 'test@example.com')
        ->call('saveSmtpSettings')
        ->assertHasErrors(['smtpPort']);
});

it('validates encryption is a valid enum value', function () {
    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('smtpHost', 'smtp.example.com')
        ->set('smtpPort', 587)
        ->set('smtpEncryption', 'invalid')
        ->set('smtpFromAddress', 'test@example.com')
        ->call('saveSmtpSettings')
        ->assertHasErrors(['smtpEncryption']);
});

it('removes SMTP settings', function () {
    $this->org->update([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'secret',
        'smtp_encryption' => 'tls',
        'smtp_from_address' => 'noreply@example.com',
        'smtp_from_name' => 'Test',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->call('removeSmtpSettings')
        ->assertDispatched('smtp-settings-removed');

    $this->org->refresh();
    expect($this->org->smtp_host)->toBeNull()
        ->and($this->org->smtp_port)->toBeNull()
        ->and($this->org->smtp_username)->toBeNull()
        ->and($this->org->smtp_password)->toBeNull()
        ->and($this->org->smtp_encryption)->toBeNull()
        ->and($this->org->smtp_from_address)->toBeNull()
        ->and($this->org->smtp_from_name)->toBeNull();
});

it('sends a test email', function () {
    $this->org->update([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_from_address' => 'noreply@example.com',
    ]);

    $action = Mockery::mock(\App\Actions\SendTestEmail::class);
    $action->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(fn ($org) => $org->id === $this->org->id),
            'recipient@example.com',
        );
    app()->instance(\App\Actions\SendTestEmail::class, $action);

    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('testEmailAddress', 'recipient@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors()
        ->assertDispatched('test-email-sent');
});

it('shows error when test email fails', function () {
    $this->org->update([
        'smtp_host' => 'invalid.host',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_from_address' => 'noreply@example.com',
    ]);

    $action = Mockery::mock(\App\Actions\SendTestEmail::class);
    $action->shouldReceive('execute')
        ->once()
        ->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Connection refused'));
    app()->instance(\App\Actions\SendTestEmail::class, $action);

    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('testEmailAddress', 'recipient@example.com')
        ->call('sendTestEmail')
        ->assertHasErrors('testEmailAddress');
});

it('masks the SMTP password', function () {
    $this->org->update([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_password' => 'mysecretpassword',
        'smtp_encryption' => 'tls',
        'smtp_from_address' => 'noreply@example.com',
    ]);

    $component = Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class);

    expect($component->get('maskedSmtpPassword'))->toContain('myse')
        ->and($component->get('maskedSmtpPassword'))->toContain('*');
});

it('populates settings from existing org data on mount', function () {
    $this->org->update([
        'smtp_host' => 'smtp.existing.com',
        'smtp_port' => 465,
        'smtp_username' => 'existing@example.com',
        'smtp_encryption' => 'ssl',
        'smtp_from_address' => 'from@existing.com',
        'smtp_from_name' => 'Existing Org',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->assertSet('smtpHost', 'smtp.existing.com')
        ->assertSet('smtpPort', 465)
        ->assertSet('smtpUsername', 'existing@example.com')
        ->assertSet('smtpEncryption', 'ssl')
        ->assertSet('smtpFromAddress', 'from@existing.com')
        ->assertSet('smtpFromName', 'Existing Org');
});

it('preserves existing password when saving without new password', function () {
    $this->org->update([
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_password' => 'original-password',
        'smtp_encryption' => 'tls',
        'smtp_from_address' => 'noreply@example.com',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EmailSettings::class)
        ->set('smtpHost', 'smtp.updated.com')
        ->call('saveSmtpSettings')
        ->assertHasNoErrors();

    $this->org->refresh();
    expect($this->org->smtp_host)->toBe('smtp.updated.com')
        ->and($this->org->smtp_password)->toBe('original-password');
});
