<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $preferredId = session('current_organization_id') ?? $user->current_organization_id;

        if ($preferredId) {
            $organization = $user->organizations()
                ->where('organization_id', $preferredId)
                ->first();
        }

        if (! isset($organization) || ! $organization) {
            $organization = $user->organizations()->first();
        }

        if (! $organization) {
            return $next($request);
        }

        session(['current_organization_id' => $organization->id]);

        if ($user->current_organization_id !== $organization->id) {
            $user->updateQuietly(['current_organization_id' => $organization->id]);
        }

        app()->instance(Organization::class, $organization);

        return $next($request);
    }
}
