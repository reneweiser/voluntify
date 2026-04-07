<?php

namespace App\Livewire\Events;

use App\Actions\DeleteEmailTemplate;
use App\Actions\SaveEmailTemplate;
use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Services\EmailTemplateRenderer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Email Templates')]
class EmailTemplateEditor extends Component
{
    #[Locked]
    public Event $event;

    public string $selectedType = '';

    public string $subject = '';

    public string $body = '';

    public string $previewSubject = '';

    public string $previewBody = '';

    public bool $showPreview = false;

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);
        Gate::authorize('update', $this->event);

        $this->selectedType = EmailTemplateType::SignupConfirmation->value;
        $this->loadTemplate();
    }

    /**
     * @return array<string>
     */
    #[Computed]
    public function availablePlaceholders(): array
    {
        $renderer = app(EmailTemplateRenderer::class);
        $type = EmailTemplateType::from($this->selectedType);

        return $renderer->availablePlaceholders($type);
    }

    #[Computed]
    public function isCustomized(): bool
    {
        $type = EmailTemplateType::from($this->selectedType);

        return $this->event->emailTemplates()
            ->where('type', $type)
            ->exists();
    }

    public function loadTemplate(): void
    {
        $type = EmailTemplateType::from($this->selectedType);

        $template = $this->event->emailTemplates()
            ->where('type', $type)
            ->first();

        if ($template) {
            $this->subject = $template->subject;
            $this->body = $template->body;
        } else {
            $renderer = app(EmailTemplateRenderer::class);
            $defaults = $renderer->getDefaults($type);
            $this->subject = $defaults['subject'];
            $this->body = $defaults['body'];
        }

        $this->showPreview = false;
        unset($this->isCustomized);
    }

    public function updatedSelectedType(): void
    {
        $this->loadTemplate();
        unset($this->availablePlaceholders);
    }

    public function saveTemplate(): void
    {
        Gate::authorize('update', $this->event);

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $type = EmailTemplateType::from($this->selectedType);

        $action = app(SaveEmailTemplate::class);
        $action->execute(
            event: $this->event,
            type: $type,
            subject: $this->subject,
            body: $this->body,
            causer: auth()->user(),
        );

        unset($this->isCustomized);
        $this->dispatch('template-saved');
    }

    public function resetToDefault(): void
    {
        Gate::authorize('update', $this->event);

        $type = EmailTemplateType::from($this->selectedType);

        $action = app(DeleteEmailTemplate::class);
        $action->execute($this->event, $type);

        $this->loadTemplate();
        $this->dispatch('template-reset');
    }

    public function previewTemplate(): void
    {
        $type = EmailTemplateType::from($this->selectedType);
        $renderer = app(EmailTemplateRenderer::class);

        $rendered = $renderer->render($type, $this->event, $this->getSampleVariables($type));

        $this->previewSubject = $rendered['subject'];
        $this->previewBody = $rendered['body'];
        $this->showPreview = true;
    }

    /**
     * Returns sample variables for template preview based on type.
     *
     * @return array<string, string>
     */
    private function getSampleVariables(EmailTemplateType $type): array
    {
        $common = [
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'telefon' => '+49 170 1234567',
            'volunteer_name' => 'Anna Schmidt',
            'event_name' => $this->event->name,
            'project_name' => $this->event->project?->name ?? 'Beispielprojekt',
            'kontakt_email' => $this->event->project?->contact_email ?? 'kontakt@example.com',
            'portal_link' => 'https://example.com/portal/vorschau',
        ];

        return match ($type) {
            EmailTemplateType::SignupConfirmation => array_merge($common, [
                'job_name' => 'Aufbau-Team',
                'shift_date' => $this->event->starts_at->format('d.m.Y'),
                'shift_time' => $this->event->starts_at->format('H:i').' — '.$this->event->ends_at->format('H:i'),
                'shifts_summary' => "- Aufbau-Team: {$this->event->starts_at->format('d.m.Y H:i')} — {$this->event->ends_at->format('H:i')}",
                'event_location' => $this->event->location ? "**Ort:** {$this->event->location}" : '',
                'gear_zusammenfassung' => 'T-Shirt (Größe M), Funkgerät',
            ]),
            EmailTemplateType::PreShiftReminder24h,
            EmailTemplateType::PreShiftReminder4h => array_merge($common, [
                'job_name' => 'Aufbau-Team',
                'shift_date' => $this->event->starts_at->format('d.m.Y'),
                'shift_time' => $this->event->starts_at->format('H:i').' — '.$this->event->ends_at->format('H:i'),
                'event_location' => $this->event->location ? "**Ort:** {$this->event->location}" : '',
                'cheat_sheet_url' => '[Aufgaben-Infos anzeigen](https://example.com/cheat-sheet)',
            ]),
            EmailTemplateType::EmailVerification => $common,
            EmailTemplateType::StaffInvitation => [
                'name' => 'Max Mustermann',
                'organization_name' => currentOrganization()->name,
                'temporary_password' => 'Beispiel-Passwort-123',
                'login_url' => route('login'),
            ],
            EmailTemplateType::VolunteerPromoted => [
                'name' => 'Anna Schmidt',
                'organization_name' => currentOrganization()->name,
                'role_name' => 'Helfer-Admin',
                'temporary_password' => 'Beispiel-Passwort-123',
                'login_url' => route('login'),
            ],
            EmailTemplateType::AddedToOrganization => [
                'name' => 'Max Mustermann',
                'organization_name' => currentOrganization()->name,
                'role_name' => 'Organisator',
                'login_url' => route('login'),
            ],
            EmailTemplateType::EventAnnouncement => [
                'subject' => 'Wichtige Info zu '.$this->event->name,
                'body' => 'Dies ist eine Beispiel-Ankündigung für die Vorschau.',
            ],
            EmailTemplateType::EventUpdated => array_merge($common, [
                'organizer_note' => 'Die Startzeit hat sich um 30 Minuten nach hinten verschoben.',
            ]),
            EmailTemplateType::CancellationConfirmation => array_merge($common, [
                'cancelled_shift_summary' => "- Aufbau-Team: {$this->event->starts_at->format('d.m.Y')} {$this->event->starts_at->format('H:i')} — {$this->event->ends_at->format('H:i')}",
                'remaining_shifts_section' => "**Deine verbleibenden Schichten:**\n- Abbau-Team: {$this->event->starts_at->format('d.m.Y')} 18:00 — 20:00",
            ]),
        };
    }
}
