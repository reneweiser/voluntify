<?php

namespace App\Livewire\Public;

use App\Actions\VerifyMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Models\Ticket;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('layouts.public')]
#[Title('Your Ticket')]
class VolunteerTicket extends Component
{
    public ?Volunteer $volunteer = null;

    public ?Ticket $ticket = null;

    public bool $expired = false;

    public string $magicToken = '';

    public function mount(string $magicToken): void
    {
        $this->magicToken = $magicToken;
        try {
            $this->volunteer = app(VerifyMagicLink::class)->execute($magicToken);
        } catch (InvalidMagicLinkException $e) {
            if (str_contains($e->getMessage(), 'expired')) {
                $this->expired = true;

                return;
            }

            throw new NotFoundHttpException;
        }

        $this->ticket = Ticket::where('volunteer_id', $this->volunteer->id)->first();

        if (! $this->ticket) {
            throw new NotFoundHttpException;
        }
    }

    public function getShiftSignupsProperty(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        return $this->volunteer->shiftSignups()
            ->with('shift.volunteerJob')
            ->get();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.public.volunteer-ticket');
    }
}
