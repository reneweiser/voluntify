<?php

use App\Actions\ExportVolunteersCsv;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->recorder = User::factory()->create();
});

it('returns correct data for volunteers', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@test.com', 'phone' => '+1234567890']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Sound']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $signup = ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    AttendanceRecord::create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $this->recorder->id,
        'recorded_at' => now(),
    ]);

    $ticket = Ticket::where('volunteer_id', $volunteer->id)->where('event_id', $this->event->id)->first();
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Alice Smith')
        ->and($rows[0]['email'])->toBe('alice@test.com')
        ->and($rows[0]['phone'])->toBe('+1234567890')
        ->and($rows[0]['arrived'])->toBe('Yes')
        ->and($rows[0]['attendance'])->toBe('1/1');
});

it('respects search filter', function () {
    $vol1 = Volunteer::factory()->create(['name' => 'Alice Match']);
    $vol2 = Volunteer::factory()->create(['name' => 'Bob Nope']);
    Ticket::factory()->create(['volunteer_id' => $vol1->id, 'event_id' => $this->event->id]);
    Ticket::factory()->create(['volunteer_id' => $vol2->id, 'event_id' => $this->event->id]);

    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event, 'Alice')->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Alice Match');
});

it('includes gear column with item names and sizes', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Gear Volunteer']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    $tshirt = \App\Models\EventGearItem::factory()->sized()->for($this->event)->create(['name' => 'T-Shirt']);
    $badge = \App\Models\EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    \App\Models\VolunteerGear::factory()->create([
        'event_gear_item_id' => $tshirt->id,
        'volunteer_id' => $volunteer->id,
        'size' => 'L',
    ]);
    \App\Models\VolunteerGear::factory()->create([
        'event_gear_item_id' => $badge->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $action = new ExportVolunteersCsv;
    $rows = $action->execute($this->event)->toArray();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['gear'])->toContain('T-Shirt (L)')
        ->and($rows[0]['gear'])->toContain('Badge');
});

it('includes custom field columns in export', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Alice']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    $field = \App\Models\CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);
    \App\Models\CustomFieldResponse::factory()->create([
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
    $volunteer = Volunteer::factory()->create(['name' => 'Bob']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    $field = \App\Models\CustomRegistrationField::factory()->checkbox()->for($this->event)->create(['label' => 'Photo Release']);
    \App\Models\CustomFieldResponse::factory()->create([
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
    $volunteer = Volunteer::factory()->create(['name' => 'Carol']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    $field = \App\Models\CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Old Field']);
    \App\Models\CustomFieldResponse::factory()->create([
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
    $volunteer = Volunteer::factory()->create(['name' => 'Dan']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    $field = \App\Models\CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Notes']);

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
