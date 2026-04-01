<?php

namespace App\Actions;

use App\Models\ProjectScanner;

class DeleteProjectScanner
{
    public function execute(ProjectScanner $scanner): void
    {
        $scanner->delete();
    }
}
