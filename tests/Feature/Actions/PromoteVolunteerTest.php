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
        ->and($promotion->promoted_by)->toBe($this->promoter->id)
        ->and($promotion->volunteer_id)->toBe($volunteer->id)
        ->and($promotion->promoted_at)->not->toBeNull();

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
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
        ->and($this->org->users()->where('user_id', $existingUser->id)->exists())->toBeTrue()
        ->and($this->org->users()->where('user_id', $existingUser->id)->first()->pivot->role)
        ->toBe(StaffRole::EntranceStaff)
        ->and($promotion->role)->toBe(StaffRole::EntranceStaff);

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

it('does not create promotion record when user is already in organization', function () {
    $existingUser = User::factory()->create(['email' => 'member@example.com']);
    $this->org->users()->attach($existingUser, ['role' => StaffRole::VolunteerAdmin]);
    $volunteer = Volunteer::factory()->create(['email' => 'member@example.com']);

    $initialPromotionCount = VolunteerPromotion::count();

    try {
        (new PromoteVolunteer)->execute($volunteer, $this->org, StaffRole::EntranceStaff, $this->promoter);
    } catch (UserAlreadyInOrganizationException) {
        // expected
    }

    expect(VolunteerPromotion::count())->toBe($initialPromotionCount);
    $volunteer->refresh();
    expect($volunteer->user_id)->toBeNull();
});

it('does not modify existing user attributes when promoting', function () {
    Notification::fake();

    $existingUser = User::factory()->create([
        'name' => 'Original Name',
        'must_change_password' => false,
        'email' => 'staff@example.com',
    ]);
    $volunteer = Volunteer::factory()->create(['email' => 'staff@example.com']);

    (new PromoteVolunteer)->execute($volunteer, $this->org, StaffRole::EntranceStaff, $this->promoter);

    $existingUser->refresh();
    expect($existingUser->must_change_password)->toBeFalse()
        ->and($existingUser->name)->toBe('Original Name');
});
