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

echo ""
echo "=== Setup complete ==="
echo "Users available:"
echo "  Organizer:      test@example.com / password"
echo "  EntranceStaff:  entrance@example.com / password"
echo "  Dashboard user: dashboard@example.com / password"
