<?php

use App\Actions\PromoteVolunteer;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Exceptions\VolunteerAlreadyPromotedException;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;
use App\Notifications\VolunteerPromoted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->promoter = User::factory()->create();
    $this->org->users()->attach($this->promoter, ['role' => StaffRole::Organizer]);
});

// --- Promote to Organizer ---

it('promotes to organizer: creates user, attaches to org and project', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'new@example.com']);

    $action = new PromoteVolunteer;
    $promotion = $action->execute($volunteer, $this->org, StaffRole::Organizer, $this->promoter);

    expect($promotion)->toBeInstanceOf(VolunteerPromotion::class)
        ->and($promotion->role)->toBe(StaffRole::Organizer);

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($volunteer->fresh()->user_id)->toBe($user->id);

    Notification::assertSentTo($user, VolunteerPromoted::class);
});

it('promotes to organizer: links existing user without duplicate', function () {
    Notification::fake();

    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'existing@example.com']);

    $action = new PromoteVolunteer;
    $action->execute($volunteer, $this->org, StaffRole::Organizer, $this->promoter);

    expect($volunteer->fresh()->user_id)->toBe($existingUser->id)
        ->and($this->org->users()->where('user_id', $existingUser->id)->exists())->toBeTrue();

    Notification::assertNothingSent();
});

it('throws when volunteer already promoted to organizer', function () {
    $user = User::factory()->create();
    $volunteer = Volunteer::factory()->for($this->project)->create(['user_id' => $user->id]);

    $action = new PromoteVolunteer;
    $action->execute($volunteer, $this->org, StaffRole::Organizer, $this->promoter);
})->throws(VolunteerAlreadyPromotedException::class);

it('does not create duplicate org membership when promoting to organizer', function () {
    $existingUser = User::factory()->create(['email' => 'member@example.com']);
    $this->org->users()->attach($existingUser, ['role' => StaffRole::VolunteerAdmin]);
    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'member@example.com']);

    $action = new PromoteVolunteer;
    $promotion = $action->execute($volunteer, $this->org, StaffRole::Organizer, $this->promoter);

    expect($promotion->role)->toBe(StaffRole::Organizer)
        ->and($volunteer->fresh()->user_id)->toBe($existingUser->id);
});

// --- Promote to VA (scanner assignment) ---

it('promotes to VA: adds scanner assignee', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'va@example.com']);
    $scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::VolunteerAdmin]);

    $action = new PromoteVolunteer;
    $promotion = $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter, $scanner->id);

    expect($promotion->role)->toBe(StaffRole::VolunteerAdmin)
        ->and($scanner->assignees()->where('email', 'va@example.com')->exists())->toBeTrue();
});

it('promotes to VA: does not create user account', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'va@example.com']);
    $scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::VolunteerAdmin]);

    $action = new PromoteVolunteer;
    $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter, $scanner->id);

    expect(User::where('email', 'va@example.com')->exists())->toBeFalse()
        ->and($volunteer->fresh()->user_id)->toBeNull();
});

it('throws when no scanner selected for VA promotion', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $action = new PromoteVolunteer;

    expect(fn () => $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter))
        ->toThrow(DomainException::class, 'Bitte wähle einen VA-Scanner aus.');
});

it('throws when already assigned to scanner', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'assigned@example.com']);
    $scanner = ProjectScanner::factory()->for($this->project)->create(['type' => ScannerType::VolunteerAdmin]);
    $scanner->assignees()->create(['email' => 'assigned@example.com']);

    $action = new PromoteVolunteer;

    expect(fn () => $action->execute($volunteer, $this->org, StaffRole::VolunteerAdmin, $this->promoter, $scanner->id))
        ->toThrow(DomainException::class, 'Diese Person ist bereits diesem Scanner zugewiesen.');
});
