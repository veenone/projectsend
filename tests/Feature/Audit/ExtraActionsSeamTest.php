<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Audit\ExtraActions;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Mcp\ProjectSendServer;
use App\Modules\Mcp\Resources\ActivityRecent;
use App\Modules\Mcp\Support\McpContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\McpWorld;

test('with nothing registered the activity log page and its filter list are exactly as before', function () {
    $staff = User::factory()->create();
    app(ActivityLogger::class)->log(Action::Login, $staff);

    $this->actingAs($staff)->get('/activity')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('actions', array_map(fn (Action $action): array => [
            'key' => $action->value,
            'description' => $action->description(),
        ], Action::cases()))
        ->where('entries.0.action', 'auth.login')
        ->where('entries.0.template', 'Logged in'));
});

test('a registered action is logged, rendered with its template, and offered in the filter', function () {
    app(ExtraActions::class)->register('evidence.recorded', 'Archived ":subject" for :release', 'Evidence was archived');
    $staff = User::factory()->create();
    $file = File::factory()->create(['name' => 'report.pdf']);

    $this->actingAs($staff);
    app(ActivityLogger::class)->log(app(ExtraActions::class)->find('evidence.recorded'), subject: $file, context: ['release' => '1.2']);

    $this->get('/activity')->assertOk()->assertInertia(function (AssertableInertia $page) {
        $props = $page->toArray()['props'];

        expect(end($props['actions']))->toBe(['key' => 'evidence.recorded', 'description' => 'Evidence was archived'])
            ->and($props['entries'][0]['action'])->toBe('evidence.recorded')
            ->and(__($props['entries'][0]['template'], $props['entries'][0]['replacements']))->toBe('Archived "report.pdf" for 1.2');
    });

    $this->get('/activity?action=evidence.recorded')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 1));

    $token = $staff->createToken('Zapier', [Permission::ViewActionsLog->value])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/activity')->assertOk()
        ->assertJsonPath('data.0.action', 'evidence.recorded');
});

test('a row left by an uninstalled package still renders', function () {
    $staff = User::factory()->create();
    DB::table('activity_log')->insert([
        'action' => 'gone.away',
        'origin' => 'ui',
        'actor_id' => $staff->id,
        'actor_name' => $staff->name,
        'created_at' => now(),
    ]);

    $this->actingAs($staff)->get('/activity')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('entries.0.action', 'gone.away')
        ->where('entries.0.template', 'Performed "gone.away"'));
});

test('a key that collides with a core action is refused', function () {
    app(ExtraActions::class)->register(Action::Login->value, 'Logged in again', 'Logged in again');
})->throws(InvalidArgumentException::class);

test('registering the same key twice is refused', function () {
    app(ExtraActions::class)->register('evidence.recorded', 'Archived ":subject"', 'Evidence was archived');
    app(ExtraActions::class)->register('evidence.recorded', 'Archived ":subject"', 'Evidence was archived');
})->throws(InvalidArgumentException::class);

test('the assistant reads registered and orphaned actions by their key', function () {
    McpContext::set(null);
    app(ExtraActions::class)->register('evidence.recorded', 'Archived ":subject"', 'Evidence was archived');
    $staff = McpWorld::staff();

    app(ActivityLogger::class)->log(app(ExtraActions::class)->find('evidence.recorded'), $staff);
    DB::table('activity_log')->insert(['action' => 'gone.away', 'origin' => 'ui', 'actor_id' => $staff->id, 'actor_name' => $staff->name, 'created_at' => now()]);

    ProjectSendServer::actingAs($staff)->resource(ActivityRecent::class)
        ->assertOk()
        ->assertSee('"action":"evidence.recorded"')
        ->assertSee('"action":"gone.away"');
});
