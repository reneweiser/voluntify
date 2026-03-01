<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizationUser extends Pivot
{
    protected $table = 'organization_user';

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
        ];
    }
}
