<?php

namespace App\Livewire\Events;

use App\Actions\DeleteEmailTemplate;
use App\Actions\SaveEmailTemplate;
use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Services\EmailTemplateRenderer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Email Templates')]
class EmailTemplateEditor extends Component
{
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
            'body' => ['required', 'string'],
        ]);

        $type = EmailTemplateType::from($this->selectedType);

        $action = app(SaveEmailTemplate::class);
        $action->execute(
            event: $this->event,
            type: $type,
            subject: $this->subject,
            body: $this->body,
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

        $rendered = $renderer->render($type, $this->event, [
            'volunteer_name' => 'Jane Doe',
            'event_name' => $this->event->name,
            'job_name' => 'Setup Crew',
            'shift_date' => $this->event->starts_at->format('M d, Y'),
            'shift_time' => $this->event->starts_at->format('g:i A').' — '.$this->event->ends_at->format('g:i A'),
            'event_location' => $this->event->location ? "**Location:** {$this->event->location}" : '',
        ]);

        $this->previewSubject = $rendered['subject'];
        $this->previewBody = $rendered['body'];
        $this->showPreview = true;
    }
}
