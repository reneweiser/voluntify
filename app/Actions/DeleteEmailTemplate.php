<?php

namespace App\Actions;

use App\Enums\EmailTemplateType;
use App\Models\Event;

class DeleteEmailTemplate
{
    public function execute(Event $event, EmailTemplateType $type): void
    {
        $event->emailTemplates()
            ->where('type', $type)
            ->delete();
    }
}
