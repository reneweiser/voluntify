<?php

namespace App\Events\Activity;

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EmailTemplateUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Event $event,
        public readonly EmailTemplateType $templateType,
        public readonly User $causer,
    ) {}
}
