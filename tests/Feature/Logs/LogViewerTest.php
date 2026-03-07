<?php

use App\Enums\StaffRole;
use App\Livewire\Logs\LogViewer;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    app()->instance(\App\Models\Organization::class, $this->org);

    $this->testLogFile = 'test-log-'.uniqid().'.log';
    $this->testLogPath = storage_path('logs/'.$this->testLogFile);
    $this->testLogContent = implode("\n", [
        '[2026-03-07 10:00:00] local.INFO: Application started',
        '[2026-03-07 10:01:00] local.ERROR: Something went wrong',
        '#0 /app/Http/Controller.php(42): handleError()',
        '#1 /vendor/laravel/framework/src/Router.php(100): dispatch()',
        '[2026-03-07 10:02:00] local.WARNING: Disk space low',
        '',
    ]);
    file_put_contents($this->testLogPath, $this->testLogContent);
});

afterEach(function () {
    if (isset($this->testLogPath) && file_exists($this->testLogPath)) {
        unlink($this->testLogPath);
    }
});

it('allows organizer to access the page', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('logs.index'))
        ->assertOk()
        ->assertSeeLivewire(LogViewer::class);
});

it('denies access to non-organizer roles', function (StaffRole $role, string $userKey) {
    $this->actingAs($this->{$userKey})
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('logs.index'))
        ->assertForbidden();
})->with([
    'volunteer admin' => [StaffRole::VolunteerAdmin, 'volunteerAdmin'],
    'entrance staff' => [StaffRole::EntranceStaff, 'entranceStaff'],
]);

it('redirects unauthenticated users to login', function () {
    $this->get(route('logs.index'))
        ->assertRedirect(route('login'));
});

it('lists available log files', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->assertSee($this->testLogFile);
});

it('defaults to latest log file', function () {
    $secondFile = 'test-log-zzz-'.uniqid().'.log';
    $secondPath = storage_path('logs/'.$secondFile);
    file_put_contents($secondPath, "[2026-03-07 12:00:00] local.INFO: Second file\n");

    $logFiles = Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->get('logFiles');

    expect($logFiles[0])->not->toBeEmpty();

    unlink($secondPath);
});

it('shows log content for selected file', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->assertSee('Application started')
        ->assertSee('Something went wrong');
});

it('filters entries by search term', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->set('search', 'ERROR')
        ->assertSee('Something went wrong')
        ->assertDontSee('Application started');
});

it('respects tail line count', function () {
    $manyLinesFile = 'test-many-lines-'.uniqid().'.log';
    $manyLinesPath = storage_path('logs/'.$manyLinesFile);
    $lines = [];
    for ($i = 1; $i <= 100; $i++) {
        $lines[] = "[2026-03-07 10:00:{$i}] local.INFO: Log line number {$i}";
    }
    file_put_contents($manyLinesPath, implode("\n", $lines)."\n");

    $content = Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $manyLinesFile)
        ->set('tail', 50)
        ->get('logContent');

    expect(count($content))->toBeLessThanOrEqual(50);

    unlink($manyLinesPath);
});

it('rejects path traversal in selectedFile', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', '../../.env')
        ->assertDontSee('APP_KEY')
        ->assertDontSee('DB_PASSWORD');
});

it('downloads a log file', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->call('downloadLog')
        ->assertFileDownloaded($this->testLogFile);
});

it('handles empty log directory gracefully', function () {
    $logsDir = storage_path('logs');
    $renamed = [];
    foreach (glob($logsDir.'/*.log') as $file) {
        $backup = $file.'.bak';
        rename($file, $backup);
        $renamed[$backup] = $file;
    }

    try {
        Livewire::actingAs($this->organizer)
            ->test(LogViewer::class)
            ->assertSee('No log files found');
    } finally {
        foreach ($renamed as $backup => $original) {
            rename($backup, $original);
        }
    }
});

it('parses entries into structured format', function () {
    $content = Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->get('logContent');

    expect($content)->toHaveCount(3);

    expect($content[0])->toHaveKeys(['level', 'timestamp', 'message', 'trace']);
    expect($content[0]['level'])->toBe('INFO');
    expect($content[0]['timestamp'])->toBe('2026-03-07 10:00:00');
    expect($content[0]['message'])->toBe('Application started');
    expect($content[0]['trace'])->toBe('');

    expect($content[1]['level'])->toBe('ERROR');
    expect($content[1]['message'])->toBe('Something went wrong');

    expect($content[2]['level'])->toBe('WARNING');
    expect($content[2]['message'])->toBe('Disk space low');
});

it('includes stack trace in error entry', function () {
    $content = Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->get('logContent');

    expect($content[1]['trace'])->toContain('handleError');
    expect($content[1]['trace'])->toContain('dispatch()');
});

it('search matches stack trace content', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->set('search', 'handleError')
        ->assertSee('Something went wrong')
        ->assertDontSee('Application started');
});

it('renders level badges', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->assertSee('ERROR')
        ->assertSee('INFO')
        ->assertSee('WARNING');
});

it('renders Alpine expand directives', function () {
    Livewire::actingAs($this->organizer)
        ->test(LogViewer::class)
        ->set('selectedFile', $this->testLogFile)
        ->assertSeeHtml('x-data=')
        ->assertSeeHtml('x-show=')
        ->assertSeeHtml('x-collapse');
});
