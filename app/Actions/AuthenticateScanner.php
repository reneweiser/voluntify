<?php

namespace App\Actions;

use App\Models\ProjectScanner;
use Illuminate\Support\Facades\Hash;

class AuthenticateScanner
{
    public function execute(ProjectScanner $scanner, string $plainCode): bool
    {
        if (! $scanner->isActive()) {
            return false;
        }

        return Hash::check($plainCode, $scanner->auth_code);
    }
}
