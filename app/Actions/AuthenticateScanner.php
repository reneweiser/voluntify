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

        // Support both plaintext (new) and bcrypt (legacy) auth codes
        if (strlen($scanner->auth_code) !== 6) {
            // Legacy bcrypt hash — validate and auto-migrate to plaintext
            if (! Hash::check($plainCode, $scanner->auth_code)) {
                return AuthenticationResult::InvalidCode;
            }

            $scanner->update(['auth_code' => $plainCode]);
        } elseif (! hash_equals($scanner->auth_code, $plainCode)) {
            return AuthenticationResult::InvalidCode;
        }

        return AuthenticationResult::Success;
    }
}
