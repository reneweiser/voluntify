<?php

namespace App\Actions;

use App\Enums\AuthenticationResult;
use App\Models\ProjectScanner;
use Illuminate\Support\Facades\Hash;

class AuthenticateScanner
{
    public function execute(ProjectScanner $scanner, string $plainCode): AuthenticationResult
    {
        if ($scanner->isExpired()) {
            return AuthenticationResult::Expired;
        }

        if ($scanner->isScheduled()) {
            return AuthenticationResult::NotYetActive;
        }

        if (! Hash::check($plainCode, $scanner->auth_code)) {
            return AuthenticationResult::InvalidCode;
        }

        return AuthenticationResult::Success;
    }
}
