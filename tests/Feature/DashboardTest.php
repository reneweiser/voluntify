<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Dashboard;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    ['user' => $user] = createUserWithOrganization();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('shows real metrics for upcoming events', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Event::factory()->for($org)->published()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);
    Event::factory()->for($org)->published()->create(['starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addHours(4)]);
    // Past event should not count
    Event::factory()->for($org)->published()->create(['starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHours(4)]);
    // Draft event should not count
    Event::factory()->for($org)->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('2'); // upcoming events count
});

test('shows total volunteers count', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create();
    $vol1 = Volunteer::factory()->create();
    $vol2 = Volunteer::factory()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $vol1->id, 'shift_id' => $shift->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $vol2->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('2'); // total volunteers
});

test('reminders show shifts needing volunteers', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);
    $fullShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 1]);
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);
    $reminders = $component->get('reminders');

    expect(collect($reminders)->contains(fn ($r) => str_contains($r['message'], 'Schicht(en)')))->toBeTrue();
});

test('reminders count excludes cancelled signups from shift capacity check', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 1]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);
    $reminders = $component->get('reminders');

    expect(collect($reminders)->contains(fn ($r) => str_contains($r['message'], 'Schicht(en)')))->toBeTrue();
});

test('lists upcoming events in table', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create([
        'name' => 'Spring Festival',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Spring Festival');
});

test('excludes past and archived events from upcoming list', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Event::factory()->for($org)->archived()->create([
        'name' => 'Archived Fest',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Archived Fest');
});

test('create event button visible for org organizer only', function () {
    ['user' => $organizer, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Livewire::actingAs($organizer)
        ->test(Dashboard::class)
        ->assertSee('Neues Event');

    ['user' => $projectOrganizer, 'organization' => $org2] = createUserWithProjectOrganization();
    app()->instance(Organization::class, $org2);

    Livewire::actingAs($projectOrganizer)
        ->test(Dashboard::class)
        ->assertDontSee('Neues Event');
});

test('empty state renders when no projects', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Keine Projekte');
});

test('no-show rate is 0 when no attendance records', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('0%');
});

test('computes no-show rate correctly', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $signup1 = ShiftSignup::factory()->create(['shift_id' => $shift->id]);
    $signup2 = ShiftSignup::factory()->create(['shift_id' => $shift->id]);

    AttendanceRecord::create([
        'shift_signup_id' => $signup1->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);
    AttendanceRecord::create([
        'shift_signup_id' => $signup2->id,
        'status' => AttendanceStatus::NoShow,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('50%');
});

test('attendance summary counts correctly', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $signups = ShiftSignup::factory()->count(3)->create(['shift_id' => $shift->id]);

    AttendanceRecord::create([
        'shift_signup_id' => $signups[0]->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);
    AttendanceRecord::create([
        'shift_signup_id' => $signups[1]->id,
        'status' => AttendanceStatus::Late,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);
    // signup 3 has no attendance record = unmarked

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Pünktlich')
        ->assertSee('Verspätet')
        ->assertSee('Offen');
});

test('recent past events only includes past events', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Event::factory()->for($org)->published()->create([
        'name' => 'Past Gala',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subMonth()->addHours(4),
    ]);
    Event::factory()->for($org)->published()->create([
        'name' => 'Future Fest',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addHours(4),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Past Gala')
        ->assertSee('Vergangene Events');
});

test('displays project tiles with counts', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $project = Project::factory()->for($org)->create(['name' => 'Summer Festival']);
    Event::factory()->for($org)->for($project)->published()->create([
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Summer Festival')
        ->assertSee('Projekte');
});

test('shows next upcoming event', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Event::factory()->for($org)->published()->create([
        'name' => 'Next Big Event',
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(4),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Next Big Event')
        ->assertSee('Nächstes Event');
});

test('shows smart reminders for shifts needing volunteers', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create([
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Schicht(en) brauchen noch Helfer:innen');
});

test('global volunteer search returns results', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    $project = Project::factory()->for($org)->create();
    app()->instance(Organization::class, $org);

    Volunteer::factory()->for($project)->create([
        'first_name' => 'Alice',
        'last_name' => 'Wonderland',
        'email' => 'alice@test.com',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('search', 'Alice')
        ->assertSee('Alice Wonderland')
        ->assertSee('alice@test.com');
});

test('global search shows no results for short queries', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('search', 'A')
        ->assertDontSee('Keine Ergebnisse');
});

test('project organizer only sees events from assigned project', function () {
    $org = Organization::factory()->create();
    $project1 = Project::factory()->for($org)->create();
    $project2 = Project::factory()->for($org)->create();

    $user = User::factory()->create();
    $project1->users()->attach($user, ['role' => StaffRole::Organizer]);
    $user->update(['current_organization_id' => $org->id]);

    app()->instance(Organization::class, $org);

    Event::factory()->for($org)->for($project1)->published()->create([
        'name' => 'My Project Event',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);
    Event::factory()->for($org)->for($project2)->published()->create([
        'name' => 'Other Project Event',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('My Project Event')
        ->assertDontSee('Other Project Event');
});

test('shows cross-organization overview with safe scoped previews', function () {
    ['user' => $user, 'organization' => $currentOrg] = createUserWithOrganization();
    $directAccessOrg = Organization::factory()->create(['name' => 'Direct Access Org']);
    $directAccessOrg->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);

    $projectAccessOrg = Organization::factory()->create(['name' => 'Project Access Org']);
    $visibleProject = Project::factory()->for($projectAccessOrg)->create(['name' => 'Visible Project']);
    $hiddenProject = Project::factory()->for($projectAccessOrg)->create(['name' => 'Hidden Project']);
    $visibleProject->users()->attach($user, ['role' => StaffRole::Organizer]);

    Event::factory()->for($projectAccessOrg)->for($visibleProject)->published()->create([
        'name' => 'Visible Cross-Org Event',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(4),
    ]);
    Event::factory()->for($projectAccessOrg)->for($hiddenProject)->published()->create([
        'name' => 'Hidden Cross-Org Event',
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(4),
    ]);

    app()->instance(Organization::class, $currentOrg);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Organisationen')
        ->assertSee($currentOrg->name)
        ->assertSee('Direct Access Org')
        ->assertSee('Project Access Org')
        ->assertSee('Projektzugang')
        ->assertSee('Visible Project')
        ->assertSee('Visible Cross-Org Event')
        ->assertDontSee('Hidden Project')
        ->assertDontSee('Hidden Cross-Org Event');
});

test('can switch organization from the dashboard overview', function () {
    ['user' => $user, 'organization' => $currentOrg] = createUserWithOrganization();
    $secondOrg = Organization::factory()->create();
    $secondOrg->users()->attach($user, ['role' => StaffRole::Organizer]);

    app()->instance(Organization::class, $currentOrg);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('switchOrganization', $secondOrg->id)
        ->assertSessionHas('current_organization_id', $secondOrg->id)
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_organization_id)->toBe($secondOrg->id);
});
