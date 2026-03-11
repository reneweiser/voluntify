<?php

namespace App\Http\Controllers;

use App\Actions\ExportVolunteersCsv;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerExportController extends Controller
{
    public function export(int $eventId, Request $request, ExportVolunteersCsv $action): StreamedResponse
    {
        $organization = currentOrganization();
        $event = Event::where('id', $eventId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        Gate::authorize('view', $event);

        $search = $request->query('search');
        $customFields = $event->customRegistrationFields()->withTrashed()->get();
        $rows = $action->execute($event, $search, $customFields->isNotEmpty() ? $customFields : null);

        $filename = str($event->name)->slug().'-volunteers.csv';

        $headers = ['Name', 'Email', 'Phone', 'Shifts', 'Arrived', 'Attendance', 'Gear'];
        foreach ($customFields as $field) {
            $headers[] = $field->label.($field->trashed() ? ' (archived)' : '');
        }

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
