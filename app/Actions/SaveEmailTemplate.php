<?php

namespace App\Actions;

use App\Enums\EmailTemplateType;
use App\Events\Activity\EmailTemplateUpdated;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\User;

class SaveEmailTemplate
{
    public function execute(
        Event $event,
        EmailTemplateType $type,
        string $subject,
        string $body,
        User $causer,
    ): EmailTemplate {
        $template = EmailTemplate::updateOrCreate(
            ['event_id' => $event->id, 'type' => $type],
            ['subject' => $subject, 'body' => $body],
        );

        EmailTemplateUpdated::dispatch($event, $type, $causer);

        return $template;
    }
}
