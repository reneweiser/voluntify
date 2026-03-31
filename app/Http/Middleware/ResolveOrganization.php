<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Short-circuit (D12): if we have a preferred org ID, verify access
        // via org membership OR project membership in a single check.
        if ($preferredId) {
            $hasAccess = $user->organizations()
                ->where('organization_id', $preferredId)
                ->exists()
                || DB::table('project_user')
                    ->join('projects', 'project_user.project_id', '=', 'projects.id')
                    ->where('project_user.user_id', $user->id)
                    ->where('projects.organization_id', $preferredId)
                    ->exists();

            if ($hasAccess) {
                $organization = Organization::find($preferredId);
            }
        }

        // Fall through: first login or preferred org no longer accessible
        if (! isset($organization) || ! $organization) {
            $organization = $user->organizations()->first();

            if (! $organization) {
                // Check if user has access via project membership
                $projectOrgId = DB::table('project_user')
                    ->join('projects', 'project_user.project_id', '=', 'projects.id')
                    ->where('project_user.user_id', $user->id)
                    ->value('projects.organization_id');

                if ($projectOrgId) {
                    $organization = Organization::find($projectOrgId);
                }
            }
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
