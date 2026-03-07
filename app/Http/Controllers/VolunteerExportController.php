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
        $rows = $action->execute($event, $search);

        $filename = str($event->name)->slug().'-volunteers.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Shifts', 'Arrived', 'Attendance']);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
