<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectUser extends Pivot
{
    protected $table = 'project_user';

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
        ];
    }
}
