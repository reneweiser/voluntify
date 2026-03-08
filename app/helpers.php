<?php

use App\Models\Organization;

function currentOrganization(): Organization
{
    return app(Organization::class);
}
