<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\Volunteer;
use Illuminate\Support\LazyCollection;

class ExportVolunteersCsv
{
    /**
     * @return LazyCollection<int, array{name: string, email: string, phone: ?string, shifts: string, arrived: string, attendance: string, gear: string}>
     */
    public function execute(Event $event, ?string $search = null): LazyCollection
    {
        return Volunteer::forEvent($event->id)
            ->when($search, fn ($q) => $q->search($search))
            ->with([
                'shiftSignups' => fn ($q) => $q->whereHas('shift.volunteerJob', fn ($sq) => $sq->where('event_id', $event->id)),
                'shiftSignups.shift.volunteerJob',
                'shiftSignups.attendanceRecord',
                'eventArrivals' => fn ($q) => $q->where('event_id', $event->id),
                'volunteerGear' => fn ($q) => $q->whereHas('gearItem', fn ($sq) => $sq->where('event_id', $event->id)),
                'volunteerGear.gearItem',
            ])
            ->orderBy('name')
            ->cursor()
            ->map(fn (Volunteer $volunteer) => [
                'name' => $volunteer->name,
                'email' => $volunteer->email,
                'phone' => $volunteer->phone ?? '',
                'shifts' => $volunteer->shiftSignups
                    ->map(fn ($s) => $s->shift->volunteerJob->name.': '.$s->shift->starts_at->format('M d, g:i A'))
                    ->implode('; '),
                'arrived' => $volunteer->eventArrivals->isNotEmpty() ? 'Yes' : 'No',
                'attendance' => $this->attendanceStatus($volunteer),
                'gear' => $volunteer->volunteerGear
                    ->map(fn ($g) => $g->size ? "{$g->gearItem->name} ({$g->size})" : $g->gearItem->name)
                    ->implode('; '),
            ]);
    }

    private function attendanceStatus(Volunteer $volunteer): string
    {
        $total = $volunteer->shiftSignups->count();

        if ($total === 0) {
            return 'None';
        }

        $marked = $volunteer->shiftSignups->filter(fn ($s) => $s->attendanceRecord)->count();

        return "{$marked}/{$total}";
    }
}
