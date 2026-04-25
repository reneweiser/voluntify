<?php

namespace App\Console\Commands;

use App\Models\EmailVerificationToken;
use App\Models\Volunteer;
use Illuminate\Console\Command;

class BackfillVolunteerEmailVerificationCommand extends Command
{
    protected $signature = 'app:backfill-volunteer-email-verification';

    protected $description = 'Backfill volunteer email verification timestamps from verified email tokens';

    public function handle(): int
    {
        $volunteers = Volunteer::query()
            ->whereNull('email_verified_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('email_verification_tokens')
                    ->whereColumn('email_verification_tokens.project_id', 'volunteers.project_id')
                    ->whereColumn('email_verification_tokens.email', 'volunteers.email')
                    ->whereNotNull('email_verification_tokens.verified_at');
            })
            ->get();

        if ($volunteers->isEmpty()) {
            $this->info('No volunteers needed backfilling.');

            return self::SUCCESS;
        }

        $backfilled = 0;

        $volunteers->each(function (Volunteer $volunteer) use (&$backfilled) {
            $verifiedAt = EmailVerificationToken::query()
                ->where('project_id', $volunteer->project_id)
                ->where('email', $volunteer->email)
                ->whereNotNull('verified_at')
                ->latest('verified_at')
                ->value('verified_at');

            if ($verifiedAt === null) {
                return;
            }

            $volunteer->update(['email_verified_at' => $verifiedAt]);
            $backfilled++;
        });

        $this->info("Backfilled {$backfilled} volunteer(s).");

        return self::SUCCESS;
    }
}
