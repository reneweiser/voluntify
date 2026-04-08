<?php

namespace App\Livewire\Public;

use App\Actions\RequestPortalAccessLink;
use App\Models\Project;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Project')]
class ProjectWebsite extends Component
{
    #[Locked]
    public Project $project;

    public string $requestEmail = '';

    public string $accessLinkMessage = '';

    public function mount(string $publicToken): void
    {
        $this->project = Project::where('public_token', $publicToken)
            ->firstOrFail();

        if (! $this->project->website_published) {
            abort(404);
        }
    }

    public function requestAccessLink(): void
    {
        $this->validate([
            'requestEmail' => ['required', 'email'],
        ]);

        $emailKey = 'access-link:'.strtolower(trim($this->requestEmail));
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $this->addError('requestEmail', 'Zu viele Anfragen. Bitte warte eine Stunde.');

            return;
        }

        $ipKey = 'access-link-ip:'.request()->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->addError('requestEmail', 'Zu viele Anfragen. Bitte versuche es später erneut.');

            return;
        }

        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 60);

        app(RequestPortalAccessLink::class)->execute($this->requestEmail, $this->project);

        $this->accessLinkMessage = 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Zugangslink versendet.';
        $this->requestEmail = '';
    }

    public function renderedDescription(): ?string
    {
        if (! $this->project->website_description) {
            return null;
        }

        return Str::markdown($this->project->website_description, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function render(): mixed
    {
        return view('livewire.public.project-website', [
            'events' => $this->project->publishedEvents()->publiclyVisible()->withVolunteerCount()->get(),
            'renderedDescription' => $this->renderedDescription(),
        ]);
    }
}
