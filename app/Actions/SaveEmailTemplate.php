<?php

namespace App\Actions;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;

class SaveEmailTemplate
{
    public function execute(
        Event $event,
        EmailTemplateType $type,
        string $subject,
        string $body,
    ): EmailTemplate {
        return EmailTemplate::updateOrCreate(
            ['event_id' => $event->id, 'type' => $type],
            ['subject' => $subject, 'body' => $body],
        );
    }
}
