<?php

namespace App\Actions;

use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

class ExportVolunteersCsv
{
    /**
     * @param  Collection<int, CustomRegistrationField>|null  $customFields
     * @return LazyCollection<int, array<string, string>>
     */
    public function execute(Event $event, ?string $search = null, ?Collection $customFields = null): LazyCollection
    {
        $query = Volunteer::forEvent($event->id)
            ->when($search, fn ($q) => $q->search($search))
            ->with([
                'shiftSignups' => fn ($q) => $q->whereHas('shift.volunteerJob', fn ($sq) => $sq->where('event_id', $event->id)),
                'shiftSignups.shift.volunteerJob',
                'shiftSignups.attendanceRecord',
                'eventArrivals' => fn ($q) => $q->where('event_id', $event->id),
                'volunteerGear' => fn ($q) => $q->whereHas('gearItem', fn ($sq) => $sq->where('project_id', $event->project_id)),
                'volunteerGear.gearItem',
                'volunteerGear.pickups',
            ]);

        if ($customFields !== null && $customFields->isNotEmpty()) {
            $query->withCustomFields($event->id, $event->project_id);
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->cursor()
            ->map(function (Volunteer $volunteer) use ($event, $customFields) {
                $tz = $event->project->timezone ?? 'UTC';
                $row = [
                    'first_name' => $volunteer->first_name,
                    'last_name' => $volunteer->last_name,
                    'email' => $volunteer->email,
                    'phone' => $volunteer->phone ?? '',
                    'shifts' => $volunteer->shiftSignups
                        ->map(fn ($s) => $s->shift->volunteerJob->name.': '.$s->shift->shift_date->setTimezone($tz)->format('d.m.Y').' '.$s->shift->displayTimeRange($tz))
                        ->implode('; '),
                    'arrived' => $volunteer->eventArrivals->isNotEmpty() ? 'Yes' : 'No',
                    'attendance' => $this->attendanceStatus($volunteer),
                    'gear' => $volunteer->volunteerGear
                        ->map(fn ($g) => ($g->size ? "{$g->gearItem->name} ({$g->size})" : $g->gearItem->name).($g->isPickedUp() ? ' [picked up]' : ''))
                        ->implode('; '),
                ];

                if ($customFields !== null) {
                    foreach ($customFields as $field) {
                        $columnLabel = $field->label.($field->trashed() ? ' (archived)' : '');
                        $response = $volunteer->customFieldResponses
                            ->firstWhere('custom_registration_field_id', $field->id);

                        $row['custom_field_'.$columnLabel] = $response
                            ? $field->type->displayValue($response->value)
                            : '';
                    }
                }

                return array_map([$this, 'sanitizeCsvValue'], $row);
            });
    }

    private function sanitizeCsvValue(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
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
