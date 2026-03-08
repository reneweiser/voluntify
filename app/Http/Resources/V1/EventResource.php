<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'title_image_url' => $this->titleImageUrl(),
            'organization' => $this->organization->name,
            'signup_url' => route('events.public', $this->public_token),
            'volunteer_jobs' => VolunteerJobResource::collection($this->volunteerJobs),
        ];
    }
}
