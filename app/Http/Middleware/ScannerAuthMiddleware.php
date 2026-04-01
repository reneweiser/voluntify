<?php

namespace App\Http\Middleware;

use App\Models\ProjectScanner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScannerAuthMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $scannerToken = $request->route('scannerToken');
        $scanner = ProjectScanner::where('scanner_token', $scannerToken)->first();

        if (! $scanner) {
            abort(404);
        }

        if (! $scanner->isActive()) {
            return redirect()->route('scanner.auth', $scannerToken)
                ->with('error', 'Scanner window is not active.');
        }

        $sessionScannerId = session('scanner_id');

        if (! $sessionScannerId || $sessionScannerId !== $scanner->id) {
            return redirect()->route('scanner.auth', $scannerToken);
        }

        $request->attributes->set('scanner', $scanner);

        return $next($request);
    }
}
