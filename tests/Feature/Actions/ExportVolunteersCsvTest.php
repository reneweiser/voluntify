<?php

use App\Actions\ExportVolunteersCsv;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->recorder = User::factory()->create();
});

it('returns correct data for volunteers', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@test.com', 'phone' => '+1234567890']);

    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Sound']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $signup = ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    AttendanceRecord::create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $this->recorder->id,
        'recorded_at' => now(),
    ]);

    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['first_name'])->toBe('Alice')
        ->and($rows[0]['last_name'])->toBe('Smith')
        ->and($rows[0]['email'])->toBe('alice@test.com')
        ->and($rows[0]['phone'])->toBe("'+1234567890")
        ->and($rows[0]['arrived'])->toBe('Yes')
        ->and($rows[0]['attendance'])->toBe('1/1');
});

it('respects search filter', function () {
    $vol1 = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Match']);
    $vol2 = Volunteer::factory()->for($this->project)->create(['first_name' => 'Bob', 'last_name' => 'Nope']);

    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $vol1->id, 'shift_id' => $shift->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $vol2->id, 'shift_id' => $shift->id]);

    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event, 'Alice')->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['first_name'])->toBe('Alice');
});

it('includes gear column with item names and sizes', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Gear', 'last_name' => 'Volunteer']);

    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $badge = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    VolunteerGear::factory()->create([
        'project_gear_item_id' => $tshirt->id,
        'volunteer_id' => $volunteer->id,
        'size' => 'L',
    ]);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $badge->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['gear'])->toContain('T-Shirt (L)')
        ->and($rows[0]['gear'])->toContain('Badge');
});

it('includes custom field columns in export', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Test']);

    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
        'value' => 'Vegan',
    ]);

    $fields = $this->event->customRegistrationFields()->withTrashed()->get();
    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event, null, $fields)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['custom_field_Diet'])->toBe('Vegan');
});

it('shows Yes/No for checkbox fields', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Bob', 'last_name' => 'Test']);

    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $field = CustomRegistrationField::factory()->checkbox()->for($this->event)->create(['label' => 'Photo Release']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
        'value' => '1',
    ]);

    $fields = $this->event->customRegistrationFields()->withTrashed()->get();
    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event, null, $fields)->toArray();

    expect($rows[0]['custom_field_Photo Release'])->toBe('Yes');
});

it('marks archived field columns with suffix', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Carol', 'last_name' => 'Test']);

    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Old Field']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
        'value' => 'some value',
    ]);
    $field->delete();

    $fields = $this->event->customRegistrationFields()->withTrashed()->get();
    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event, null, $fields)->toArray();

    expect($rows[0])->toHaveKey('custom_field_Old Field (archived)');
});

it('handles volunteers without custom field responses', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Dan', 'last_name' => 'Test']);

    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Notes']);

    $fields = $this->event->customRegistrationFields()->withTrashed()->get();
    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event, null, $fields)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['custom_field_Notes'])->toBe('');
});

it('handles empty list', function () {
    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event)->toArray();

    expect($rows)->toHaveCount(0);
});
