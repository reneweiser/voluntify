<?php

namespace App\Actions;

use App\Models\ProjectScanner;

class RegenerateAuthCode
{
    public function execute(ProjectScanner $scanner): string
    {
        $rawCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $scanner->update(['auth_code' => $rawCode]);

        return $rawCode;
    }
}
