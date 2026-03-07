<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password
            && ! $request->routeIs('change-password', 'logout', 'default-livewire.update')) {
            return redirect()->route('change-password');
        }

        return $next($request);
    }
}
