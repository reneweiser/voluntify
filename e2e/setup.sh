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

# 4. Clear Mailpit inbox
echo "Clearing Mailpit inbox..."
curl -s -X DELETE http://localhost:8025/api/v1/messages

echo ""
echo "=== Setup complete ==="
echo "Users available:"
echo "  Organizer:      test@example.com / password"
echo "  EntranceStaff:  entrance@example.com / password"
