#!/bin/bash
set -e

echo "=== E2E Test Setup ==="

# 1. Build frontend assets
echo "Building frontend assets..."
vendor/bin/sail npm run build

# 2. Fresh DB with seed data
echo "Running fresh migration with seeds..."
vendor/bin/sail artisan migrate:fresh --seed --no-interaction

# 3. Create EntranceStaff user (seeder only creates Organizer)
echo "Creating EntranceStaff user..."
vendor/bin/sail artisan tinker --execute="
  \$user = \App\Models\User::factory()->create([
    'name' => 'Entrance Staff',
    'email' => 'entrance@example.com',
    'password' => bcrypt('password'),
  ]);
  \$org = \App\Models\Organization::first();
  \$org->users()->attach(\$user, ['role' => \App\Enums\StaffRole::EntranceStaff]);
  echo 'EntranceStaff user created';
"

# 4. Create deterministic volunteer for organizer deletion E2E
echo "Creating organizer-delete E2E volunteer..."
vendor/bin/sail artisan tinker --execute="
  \$event = \App\Models\Event::where('name', 'Spring Community Fair')->firstOrFail();
  \$shift = \App\Models\Shift::whereHas('volunteerJob', fn (\$q) => \$q->where('event_id', \$event->id))->firstOrFail();
  \$volunteer = \App\Models\Volunteer::factory()->verified()->for(\$event->project)->create([
    'first_name' => 'E2E',
    'last_name' => 'Delete Volunteer',
    'email' => 'e2e-delete-volunteer@example.com',
    'phone' => '+15550001111',
  ]);
  \App\Models\ShiftSignup::factory()->create([
    'volunteer_id' => \$volunteer->id,
    'shift_id' => \$shift->id,
  ]);
  echo 'E2E volunteer created';
 "

# 5. Create deterministic multi-org dashboard user
echo "Creating dashboard discoverability E2E user..."
vendor/bin/sail artisan tinker --execute="
  \$user = \App\Models\User::factory()->create([
    'name' => 'Dashboard Explorer',
    'email' => 'dashboard@example.com',
    'password' => bcrypt('password'),
  ]);

  \$personalOrg = \App\Models\Organization::factory()->create([
    'name' => 'Dashboard Explorer Personal Org',
    'slug' => 'dashboard-explorer-personal',
  ]);
  \$personalOrg->users()->attach(\$user, ['role' => \App\Enums\StaffRole::Organizer]);

  \$sharedOrg = \App\Models\Organization::factory()->create([
    'name' => 'Shared Community Org',
    'slug' => 'shared-community-org',
  ]);
  \$sharedOrg->users()->attach(\$user, ['role' => \App\Enums\StaffRole::Organizer]);

  \$sharedProject = \App\Models\Project::factory()->for(\$sharedOrg)->create([
    'name' => 'Neighborhood Welcome Project',
  ]);

  \App\Models\Event::factory()->for(\$sharedOrg)->for(\$sharedProject)->published()->create([
    'name' => 'Community Onboarding Day',
    'slug' => 'community-onboarding-day',
    'starts_at' => now()->addDays(10),
    'ends_at' => now()->addDays(10)->addHours(4),
  ]);

  \$user->update([
    'personal_organization_id' => \$personalOrg->id,
    'current_organization_id' => \$personalOrg->id,
  ]);

  echo 'Dashboard discoverability user created';
"

# 6. Clear Mailpit inbox
echo "Clearing Mailpit inbox..."
curl -s -X DELETE http://localhost:8025/api/v1/messages

# 7. Create deterministic Volunteer Admin scanner fixture for shift-list E2E
echo "Creating volunteer-admin shift-list E2E fixture..."
vendor/bin/sail artisan tinker --execute="
  use App\Enums\GearItemType;
  use App\Enums\ScannerMode;
  use App\Enums\ScannerType;
  use App\Actions\GenerateTicket;
  use App\Actions\RefreshTicketJwt;
  use App\Models\Event;
  use App\Models\Organization;
  use App\Models\Project;
  use App\Models\ProjectGearItem;
  use App\Models\ProjectScanner;
  use App\Models\Shift;
  use App\Models\ShiftSignup;
  use App\Models\Ticket;
  use App\Models\Volunteer;
  use App\Models\VolunteerGear;
  use App\Models\VolunteerJob;

  \Carbon\Carbon::setTestNow(now());

  \DB::transaction(function () {
    \App\Models\ProjectScanner::where('scanner_token', 'e2e-va-shift-list-token')->delete();

    \App\Models\Event::where('name', 'E2E Volunteer Admin Shift Event')->delete();

    \App\Models\Project::where('name', 'E2E Volunteer Admin Scanner Project')->delete();

    \App\Models\Volunteer::whereIn('email', [
      'lisa-shifts@example.com',
      'tom-shifts@example.com',
      'active-1@example.com',
      'active-2@example.com',
      'active-3@example.com',
      'active-4@example.com',
      'active-5@example.com',
      'active-6@example.com',
      'active-7@example.com',
    ])->delete();

    \$organization = Organization::firstOrFail();

    \$project = Project::factory()->for(\$organization)->create([
      'name' => 'E2E Volunteer Admin Scanner Project',
    ]);

    \$event = Event::factory()->for(\$organization)->for(\$project)->published()->create([
      'name' => 'E2E Volunteer Admin Shift Event',
      'slug' => 'e2e-volunteer-admin-shift-event',
      'starts_at' => now()->subHours(2),
      'ends_at' => now()->addHours(8),
      'attendance_grace_minutes' => 15,
    ]);

    \$activeJob = VolunteerJob::factory()->create([
      'event_id' => \$event->id,
      'name' => 'Welcome Desk',
    ]);

    \$nextJob = VolunteerJob::factory()->create([
      'event_id' => \$event->id,
      'name' => 'Badge Check',
    ]);

    \$laterJob = VolunteerJob::factory()->create([
      'event_id' => \$event->id,
      'name' => 'Cleanup Crew',
    ]);

    \$activeShift = Shift::factory()->create([
      'volunteer_job_id' => \$activeJob->id,
      'shift_date' => now()->toDateString(),
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHour(),
      'display_text' => now()->subHour()->format('M d, H:i').' - '.now()->addHour()->format('H:i'),
    ]);

    \$nextShift = Shift::factory()->create([
      'volunteer_job_id' => \$nextJob->id,
      'shift_date' => now()->toDateString(),
      'starts_at' => now()->addHours(2),
      'ends_at' => now()->addHours(4),
      'display_text' => now()->addHours(2)->format('M d, H:i').' - '.now()->addHours(4)->format('H:i'),
    ]);

    \$laterShift = Shift::factory()->create([
      'volunteer_job_id' => \$laterJob->id,
      'shift_date' => now()->toDateString(),
      'starts_at' => now()->addHours(5),
      'ends_at' => now()->addHours(7),
      'display_text' => now()->addHours(5)->format('M d, H:i').' - '.now()->addHours(7)->format('H:i'),
    ]);

    \$lisa = Volunteer::factory()->verified()->for(\$project)->create([
      'first_name' => 'Lisa',
      'last_name' => 'Mueller',
      'email' => 'lisa-shifts@example.com',
      'phone' => '+491701234567',
    ]);

    Ticket::factory()->create([
      'project_id' => \$project->id,
      'volunteer_id' => \$lisa->id,
    ]);

    ShiftSignup::factory()->create([
      'volunteer_id' => \$lisa->id,
      'shift_id' => \$activeShift->id,
    ]);

    ShiftSignup::factory()->create([
      'volunteer_id' => \$lisa->id,
      'shift_id' => \$laterShift->id,
    ]);

    \$tom = Volunteer::factory()->verified()->for(\$project)->create([
      'first_name' => 'Tom',
      'last_name' => 'Weber',
      'email' => 'tom-shifts@example.com',
      'phone' => '+491701234568',
    ]);

    Ticket::factory()->create([
      'project_id' => \$project->id,
      'volunteer_id' => \$tom->id,
    ]);

    ShiftSignup::factory()->create([
      'volunteer_id' => \$tom->id,
      'shift_id' => \$nextShift->id,
    ]);

    foreach (range(1, 14) as \$index) {
      \$volunteer = Volunteer::factory()->verified()->for(\$project)->create([
        'first_name' => 'Active',
        'last_name' => 'Helper '.\$index,
        'email' => 'active-'.\$index.'@example.com',
        'phone' => '+4917000000'.\$index,
      ]);

      Ticket::factory()->create([
        'project_id' => \$project->id,
        'volunteer_id' => \$volunteer->id,
      ]);

      ShiftSignup::factory()->create([
        'volunteer_id' => \$volunteer->id,
        'shift_id' => \$activeShift->id,
      ]);
    }

    \$gearItem = ProjectGearItem::factory()->create([
      'project_id' => \$project->id,
      'name' => 'E2E Vest',
      'type' => GearItemType::SizeSelection,
      'requires_size' => true,
      'available_sizes' => ['S', 'M', 'L'],
      'sort_order' => 1,
    ]);

    VolunteerGear::factory()->create([
      'volunteer_id' => \$lisa->id,
      'project_gear_item_id' => \$gearItem->id,
      'size' => 'M',
      'quantity_entitled' => null,
    ]);

    ProjectScanner::factory()->active()->create([
      'project_id' => \$project->id,
      'entry_event_id' => \$event->id,
      'pool_event_ids' => [\$event->id],
      'name' => 'E2E Volunteer Admin Scanner',
      'type' => ScannerType::VolunteerAdmin,
      'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
      'scanner_token' => 'e2e-va-shift-list-token',
      'auth_code' => '222222',
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHours(4),
    ]);

    \$pastProject = Project::factory()->for(\$organization)->create([
      'name' => 'E2E Volunteer Admin Past Scanner Project',
    ]);

    \$pastEvent = Event::factory()->for(\$organization)->for(\$pastProject)->published()->create([
      'name' => 'E2E Volunteer Admin Past Shift Event',
      'slug' => 'e2e-volunteer-admin-past-shift-event',
      'starts_at' => now()->subHours(10),
      'ends_at' => now()->subHours(3),
      'attendance_grace_minutes' => 15,
    ]);

    \$pastJob = VolunteerJob::factory()->create([
      'event_id' => \$pastEvent->id,
      'name' => 'Past Shift Team',
    ]);

    \$pastShift = Shift::factory()->create([
      'volunteer_job_id' => \$pastJob->id,
      'shift_date' => now()->subDay()->toDateString(),
      'starts_at' => now()->subHours(8),
      'ends_at' => now()->subHours(6),
      'display_text' => now()->subHours(8)->format('M d, H:i').' - '.now()->subHours(6)->format('H:i'),
    ]);

    \$pastVolunteer = Volunteer::factory()->verified()->for(\$pastProject)->create([
      'first_name' => 'Past',
      'last_name' => 'Volunteer',
      'email' => 'past-volunteer@example.com',
      'phone' => '+491709999999',
    ]);

    Ticket::factory()->create([
      'project_id' => \$pastProject->id,
      'volunteer_id' => \$pastVolunteer->id,
    ]);

    ShiftSignup::factory()->create([
      'volunteer_id' => \$pastVolunteer->id,
      'shift_id' => \$pastShift->id,
    ]);

    ProjectScanner::factory()->active()->create([
      'project_id' => \$pastProject->id,
      'entry_event_id' => \$pastEvent->id,
      'pool_event_ids' => [\$pastEvent->id],
      'name' => 'E2E Volunteer Admin Past Scanner',
      'type' => ScannerType::VolunteerAdmin,
      'modes' => [ScannerMode::Checkin->value, ScannerMode::GearPickup->value],
      'scanner_token' => 'e2e-va-all-past-token',
      'auth_code' => '444444',
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHours(4),
    ]);

  \App\Models\ProjectScanner::where('scanner_token', 'e2e-entry-pool-scanner-token')->delete();
  \App\Models\Event::whereIn('name', [
    'E2E Entry Event',
      'E2E Pool Event',
    ])->delete();
    \App\Models\Project::where('name', 'E2E Entry Pool Scanner Project')->delete();
    \App\Models\Volunteer::where('email', 'entry-pool@example.com')->delete();

    \$entryPoolProject = Project::factory()->for(\$organization)->create([
      'name' => 'E2E Entry Pool Scanner Project',
    ]);

    \$entryEvent = Event::factory()->for(\$organization)->for(\$entryPoolProject)->published()->create([
      'name' => 'E2E Entry Event',
      'slug' => 'e2e-entry-event',
      'starts_at' => now()->subHours(2),
      'ends_at' => now()->addHours(8),
      'attendance_grace_minutes' => 10,
    ]);

    \$poolEvent = Event::factory()->for(\$organization)->for(\$entryPoolProject)->published()->create([
      'name' => 'E2E Pool Event',
      'slug' => 'e2e-pool-event',
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHours(6),
      'attendance_grace_minutes' => 10,
    ]);

    \$poolJob = VolunteerJob::factory()->create([
      'event_id' => \$poolEvent->id,
      'name' => 'Pool Event Team',
    ]);

    \$poolShift = Shift::factory()->create([
      'volunteer_job_id' => \$poolJob->id,
      'shift_date' => now()->toDateString(),
      'starts_at' => now()->subMinutes(30),
      'ends_at' => now()->addHours(2),
      'display_text' => now()->subMinutes(30)->format('M d, H:i').' - '.now()->addHours(2)->format('H:i'),
    ]);

    \$poolVolunteer = Volunteer::factory()->verified()->for(\$entryPoolProject)->create([
      'first_name' => 'Entry',
      'last_name' => 'Pool',
      'email' => 'entry-pool@example.com',
      'phone' => '+491701112233',
    ]);

    ShiftSignup::factory()->create([
      'volunteer_id' => \$poolVolunteer->id,
      'shift_id' => \$poolShift->id,
    ]);

    \$ticket = app(GenerateTicket::class)->execute(\$poolVolunteer, \$entryEvent);
    app(RefreshTicketJwt::class)->execute(\$ticket);

    \$entryPoolScanner = ProjectScanner::factory()->active()->create([
      'project_id' => \$entryPoolProject->id,
      'entry_event_id' => \$entryEvent->id,
      'pool_event_ids' => [\$entryEvent->id, \$poolEvent->id],
      'name' => 'E2E Entry Pool Scanner',
      'type' => ScannerType::EntryStaff,
      'modes' => [ScannerMode::Checkin->value],
      'scanner_token' => 'e2e-entry-pool-scanner-token',
      'auth_code' => '333333',
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHours(4),
    ]);

    file_put_contents(base_path('public/e2e-fixtures.json'), json_encode([
      'entryPoolProjectId' => \$entryPoolProject->id,
      'entryPoolEntryEventId' => \$entryEvent->id,
      'entryPoolPoolEventId' => \$poolEvent->id,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    \App\Models\ProjectScanner::where('scanner_token', 'e2e-entry-manual-lookup-token')->delete();
    \App\Models\Event::whereIn('name', [
      'E2E Entry Manual Event',
      'E2E Entry Manual Past Event',
    ])->delete();
    \App\Models\Project::where('name', 'E2E Entry Manual Lookup Project')->delete();
    \App\Models\Volunteer::where('email', 'past-manual-lookup@example.com')->delete();

    \$manualLookupProject = Project::factory()->for(\$organization)->create([
      'name' => 'E2E Entry Manual Lookup Project',
    ]);

    \$manualEntryEvent = Event::factory()->for(\$organization)->for(\$manualLookupProject)->published()->create([
      'name' => 'E2E Entry Manual Event',
      'slug' => 'e2e-entry-manual-event',
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHours(6),
      'attendance_grace_minutes' => 10,
    ]);

    \$manualPastEvent = Event::factory()->for(\$organization)->for(\$manualLookupProject)->published()->create([
      'name' => 'E2E Entry Manual Past Event',
      'slug' => 'e2e-entry-manual-past-event',
      'starts_at' => now()->subDays(30),
      'ends_at' => now()->subDays(30)->addHours(6),
      'attendance_grace_minutes' => 10,
    ]);

    \$manualPastJob = VolunteerJob::factory()->create([
      'event_id' => \$manualPastEvent->id,
      'name' => 'Past Event Team',
    ]);

    \$manualPastShift = Shift::factory()->create([
      'volunteer_job_id' => \$manualPastJob->id,
      'shift_date' => now()->subDays(30)->toDateString(),
      'starts_at' => now()->subDays(30)->setTime(10, 0),
      'ends_at' => now()->subDays(30)->setTime(14, 0),
      'display_text' => now()->subDays(30)->setTime(10, 0)->format('M d, H:i').' - '.now()->subDays(30)->setTime(14, 0)->format('H:i'),
    ]);

    \$manualVolunteer = Volunteer::factory()->verified()->for(\$manualLookupProject)->create([
      'first_name' => 'Past',
      'last_name' => 'Lookup',
      'email' => 'past-manual-lookup@example.com',
      'phone' => '+491701234999',
    ]);

    Ticket::factory()->create([
      'project_id' => \$manualLookupProject->id,
      'volunteer_id' => \$manualVolunteer->id,
    ]);

    ShiftSignup::factory()->create([
      'volunteer_id' => \$manualVolunteer->id,
      'shift_id' => \$manualPastShift->id,
    ]);

    ProjectScanner::factory()->active()->create([
      'project_id' => \$manualLookupProject->id,
      'entry_event_id' => \$manualEntryEvent->id,
      'pool_event_ids' => [\$manualEntryEvent->id, \$manualPastEvent->id],
      'name' => 'E2E Entry Manual Lookup Scanner',
      'type' => ScannerType::EntryStaff,
      'modes' => [ScannerMode::Checkin->value],
      'scanner_token' => 'e2e-entry-manual-lookup-token',
      'auth_code' => '555555',
      'starts_at' => now()->subHour(),
      'ends_at' => now()->addHours(4),
    ]);
  });

  echo 'Volunteer admin shift-list fixture created';
"

echo ""
echo "=== Setup complete ==="
echo "Users available:"
echo "  Organizer:      test@example.com / password"
echo "  EntranceStaff:  entrance@example.com / password"
echo "  Dashboard user: dashboard@example.com / password"
echo "  VA scanner:     /s/e2e-va-shift-list-token with code 222222"
echo "  VA all past:    /s/e2e-va-all-past-token with code 444444"
echo "  Entry/pool:     /s/e2e-entry-pool-scanner-token with code 333333"
echo "  Entry lookup:   /s/e2e-entry-manual-lookup-token with code 555555"
