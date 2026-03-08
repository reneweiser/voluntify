<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventResource;
use App\Models\Event;

class PublicEventController extends Controller
{
    public function show(string $publicToken): EventResource
    {
        $event = Event::query()
            ->published()
            ->where('public_token', $publicToken)
            ->with(['organization', 'volunteerJobs.shifts' => fn ($q) => $q->withCount('signups')->orderBy('starts_at')])
            ->firstOrFail();

        return new EventResource($event);
    }
}
