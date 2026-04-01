<?php

namespace App\Actions;

use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\Project;
use App\Models\User;

class CreateAnnouncement
{
    /**
     * @param  array{subject: string, body: string, event_id?: int|null, job_id?: int|null, shift_id?: int|null, send_at?: string|null}  $data
     */
    public function execute(Project $project, array $data, User $creator): Announcement
    {
        $announcement = Announcement::create([
            'project_id' => $project->id,
            'event_id' => $data['event_id'] ?? null,
            'job_id' => $data['job_id'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'send_at' => $data['send_at'] ?? null,
            'created_by' => $creator->id,
        ]);

        if ($announcement->send_at) {
            SendAnnouncementJob::dispatch($announcement)->delay($announcement->send_at);
        } else {
            SendAnnouncementJob::dispatch($announcement);
        }

        return $announcement;
    }
}
