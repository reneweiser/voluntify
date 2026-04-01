<?php

namespace App\Http\Middleware;

use App\Models\ProjectScanner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScannerApiMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Scanner-Token');

        if (! $token) {
            return response()->json(['error' => 'Missing scanner token.'], 401);
        }

        $scanner = ProjectScanner::where('scanner_token', $token)->first();

        if (! $scanner) {
            return response()->json(['error' => 'Invalid scanner token.'], 401);
        }

        if (! $scanner->isActive()) {
            return response()->json(['error' => 'Scanner window is not active.'], 401);
        }

        $request->attributes->set('scanner', $scanner);

        return $next($request);
    }
}
