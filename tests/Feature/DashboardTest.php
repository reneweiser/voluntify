<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Dashboard;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
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
    Ticket::factory()->create(['volunteer_id' => $vol1->id, 'event_id' => $event->id]);
    Ticket::factory()->create(['volunteer_id' => $vol2->id, 'event_id' => $event->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('2'); // total volunteers
});

test('shows shifts needing attention count', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);
    $job = VolunteerJob::factory()->for($event)->create();
    // Shift with capacity 5, 0 signups -> needs attention
    Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);
    // Shift with capacity 1, 1 signup -> does not need attention
    $fullShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 1]);
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('1'); // shifts needing attention
});

test('shiftsNeedingAttention excludes cancelled signups', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $event = Event::factory()->for($org)->published()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);
    $job = VolunteerJob::factory()->for($event)->create();
    // Shift with capacity 1, 1 cancelled signup -> should still need attention
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 1]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('1'); // shift still needs attention because signup is cancelled
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

test('create event button visible for organizer only', function () {
    ['user' => $organizer, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Livewire::actingAs($organizer)
        ->test(Dashboard::class)
        ->assertSee('Create Event');

    ['user' => $admin] = createUserWithOrganization(StaffRole::VolunteerAdmin);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertDontSee('Create Event');
});

test('empty state renders when no events', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('No upcoming events');
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
        ->assertSee('On Time')
        ->assertSee('Late')
        ->assertSee('Unmarked');
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
        ->assertSee('Recent Past Events');
});
