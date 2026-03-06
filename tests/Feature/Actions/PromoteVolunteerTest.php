<?php

use App\Actions\PromoteVolunteer;
use App\Enums\StaffRole;
use App\Exceptions\UserAlreadyInOrganizationException;
use App\Exceptions\VolunteerAlreadyPromotedException;
use App\Models\Organization;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;
use App\Notifications\VolunteerPromoted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->promoter = User::factory()->create();
    $this->org->users()->attach($this->promoter, ['role' => StaffRole::Organizer]);
});

it('creates user, pivot, and promotion record for new volunteer', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->create(['email' => 'new@example.com']);

    $action = new PromoteVolunteer;
    $promotion = $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter);

    expect($promotion)->toBeInstanceOf(VolunteerPromotion::class)
        ->and($promotion->role)->toBe(StaffRole::VolunteerAdmin)
        ->and($promotion->promoted_by)->toBe($this->promoter->id);

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and($volunteer->fresh()->user_id)->toBe($user->id);

    expect($this->org->users()->where('user_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, VolunteerPromoted::class);
});

it('links existing user without creating a new one', function () {
    Notification::fake();

    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $volunteer = Volunteer::factory()->create(['email' => 'existing@example.com']);

    $action = new PromoteVolunteer;
    $promotion = $action->execute($volunteer, $this->org, StaffRole::EntranceStaff, $this->promoter);

    expect($volunteer->fresh()->user_id)->toBe($existingUser->id)
        ->and($this->org->users()->where('user_id', $existingUser->id)->exists())->toBeTrue();

    Notification::assertNothingSent();
});

it('throws when volunteer already promoted', function () {
    $user = User::factory()->create();
    $volunteer = Volunteer::factory()->create(['user_id' => $user->id]);

    $action = new PromoteVolunteer;
    $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter);
})->throws(VolunteerAlreadyPromotedException::class);

it('throws when user already in organization', function () {
    $existingUser = User::factory()->create(['email' => 'member@example.com']);
    $this->org->users()->attach($existingUser, ['role' => StaffRole::VolunteerAdmin]);
    $volunteer = Volunteer::factory()->create(['email' => 'member@example.com']);

    $action = new PromoteVolunteer;
    $action->execute($volunteer, $this->org, StaffRole::EntranceStaff, $this->promoter);
})->throws(UserAlreadyInOrganizationException::class);

it('sets must_change_password on new user', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->create();

    $action = new PromoteVolunteer;
    $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter);

    $user = User::where('email', $volunteer->email)->first();
    expect($user->must_change_password)->toBeTrue();
});
