<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Files\Access\LibraryVisibilityRule;
use App\Modules\Files\Access\LibraryVisibilityRules;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A rule whose answers are closures over the viewer, so a test can hide
 * or grant per person the way a package's membership check would.
 */
function visibilityRule(?Closure $hidden = null, ?Closure $granted = null, ?Closure $log = null): LibraryVisibilityRule
{
    return new class($hidden, $granted, $log) implements LibraryVisibilityRule
    {
        public function __construct(private ?Closure $hidden, private ?Closure $granted, private ?Closure $log) {}

        public function hiddenFolderIds(User $viewer): array
        {
            return $this->hidden ? ($this->hidden)($viewer) : [];
        }

        public function grantedFolderIds(User $viewer): array
        {
            return $this->granted ? ($this->granted)($viewer) : [];
        }

        public function scopeActivityLog(User $viewer, Builder $query): void
        {
            if ($this->log) {
                ($this->log)($viewer, $query);
            }
        }
    };
}

/** @return list<string> */
function pageNames($response, string $prop, string $key = 'name'): array
{
    return collect(json_decode(json_encode($response->viewData('page')), true)['props'][$prop])
        ->pluck($key)->all();
}

test('with nothing registered every listing, check and log query is exactly as before', function () {
    $scope = app(StaffLibraryScope::class);
    $admin = User::factory()->create();
    $file = File::factory()->create();
    $folder = makeFolder('Plain');

    expect($scope->files($admin)->toSql())->toBe(File::query()->toSql())
        ->and($scope->files($admin)->getBindings())->toBe(File::query()->getBindings())
        ->and($scope->folders($admin)->toSql())->toBe(Folder::query()->toSql())
        ->and($scope->folders($admin)->getBindings())->toBe(Folder::query()->getBindings())
        ->and(app(ActivityLogScope::class)->apply(ActivityLog::query(), $admin)->toSql())->toBe(ActivityLog::query()->toSql());

    // The policies ask once per row, so unrestricted staff must not cost a query.
    $admin->isClientScoped();
    DB::enableQueryLog();
    expect($scope->allowsFile($admin, $file))->toBeTrue()
        ->and($scope->allowsFolder($admin, $folder))->toBeTrue()
        ->and(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    // A client-scoped manager still sees their own uploads and their
    // client's files, and nothing else.
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $own = File::factory()->create(['uploaded_by' => $manager->id]);
    $shared = File::factory()->create();
    shareFileWith($shared, $client);
    $ownFolder = Folder::query()->create(['name' => 'Mine', 'created_by' => $manager->id]);

    expect($scope->files($manager)->pluck('id')->sort()->values()->all())->toBe([$own->id, $shared->id])
        ->and($scope->folders($manager)->pluck('id')->all())->toBe([$ownFolder->id])
        ->and($scope->allowsFile($manager, $file))->toBeFalse();
});

test('a hidden subtree disappears from listings, direct access and the activity log for staff who aren\'t granted it', function () {
    $outsider = User::factory()->create(['name' => 'Outsider']);
    $member = User::factory()->create(['name' => 'Member']);

    $hidden = makeFolder('Hidden');
    $inner = makeFolder('Inner', $hidden);
    $visible = makeFolder('Visible');

    $files = [
        'hidden-top' => File::factory()->create(['name' => 'hidden-top', 'folder_id' => $hidden->id, 'uploaded_by' => $member->id]),
        'hidden-inner' => File::factory()->create(['name' => 'hidden-inner', 'folder_id' => $inner->id, 'uploaded_by' => $member->id]),
        'visible-file' => File::factory()->create(['name' => 'visible-file', 'folder_id' => $visible->id, 'uploaded_by' => $member->id]),
        // Root-level files belong to no subtree and are never hidden.
        'root-file' => File::factory()->create(['name' => 'root-file', 'uploaded_by' => $member->id]),
    ];

    foreach ($files as $file) {
        app(ActivityLogger::class)->log(Action::FileUpdated, $member, $file);
    }
    app(ActivityLogger::class)->log(Action::FolderCreated, $member, $inner);

    app(LibraryVisibilityRules::class)->register(visibilityRule(
        hidden: fn (User $viewer): array => $viewer->is($member) ? [] : [$hidden->id],
    ));

    // Outsider: an unrestricted admin, now kept out of the subtree.
    $flat = $this->actingAs($outsider)->get('/files?search=-')->assertOk();
    expect(pageNames($flat, 'files'))->toEqualCanonicalizing(['visible-file', 'root-file']);

    $root = $this->actingAs($outsider)->get('/files')->assertOk();
    expect(pageNames($root, 'folders'))->toBe(['Visible'])
        ->and(pageNames($root, 'folder_options'))->toBe(['Visible']);

    $scope = app(StaffLibraryScope::class);
    expect($scope->allowsFile($outsider, $files['hidden-top']))->toBeFalse()
        ->and($scope->allowsFile($outsider, $files['hidden-inner']))->toBeFalse()
        ->and($scope->allowsFile($outsider, $files['visible-file']))->toBeTrue()
        ->and($scope->allowsFile($outsider, $files['root-file']))->toBeTrue()
        ->and($scope->allowsFolder($outsider, $hidden))->toBeFalse()
        ->and($scope->allowsFolder($outsider, $inner))->toBeFalse()
        ->and($scope->allowsFolder($outsider, $visible))->toBeTrue();

    foreach (['hidden-top', 'hidden-inner'] as $name) {
        $id = $files[$name]->id;
        expect($this->actingAs($outsider)->get("/files/{$id}/details")->status())->toBeIn([403, 404])
            ->and($this->actingAs($outsider)->get("/files/{$id}/download")->status())->toBeIn([403, 404]);
    }

    $log = $this->actingAs($outsider)->get('/activity')->assertOk();
    expect(pageNames($log, 'entries', 'replacements.subject'))
        ->toContain('visible-file', 'root-file')
        ->not->toContain('hidden-top', 'hidden-inner', 'Inner');

    // Member: the same kind of admin, answered differently by the rule.
    expect(pageNames($this->actingAs($member)->get('/files?search=-'), 'files'))
        ->toEqualCanonicalizing(['hidden-top', 'hidden-inner', 'visible-file', 'root-file'])
        ->and($scope->allowsFile($member, $files['hidden-inner']))->toBeTrue();
    $this->actingAs($member)->get("/files/{$files['hidden-top']->id}/details")->assertOk();
    expect(pageNames($this->actingAs($member)->get('/activity'), 'entries', 'replacements.subject'))
        ->toContain('hidden-top', 'hidden-inner', 'Inner');
});

test('a grant lets a client-scoped staff member into a subtree they otherwise couldn\'t see, alongside their own files', function () {
    $admin = User::factory()->create();
    $client = User::factory()->client()->create();
    $manager = User::factory()->role(SystemRole::ClientManager)->create();
    $manager->assignedClients()->sync([$client->id]);

    $granted = makeFolder('Granted');
    $grantedInner = makeFolder('Granted inner', $granted);
    $elsewhere = makeFolder('Elsewhere');

    $theirs = File::factory()->create(['name' => 'client-file', 'uploaded_by' => $admin->id]);
    shareFileWith($theirs, $client);
    $top = File::factory()->create(['name' => 'granted-top', 'folder_id' => $granted->id, 'uploaded_by' => $admin->id]);
    $deep = File::factory()->create(['name' => 'granted-inner', 'folder_id' => $grantedInner->id, 'uploaded_by' => $admin->id]);
    $other = File::factory()->create(['name' => 'elsewhere-file', 'folder_id' => $elsewhere->id, 'uploaded_by' => $admin->id]);

    app(LibraryVisibilityRules::class)->register(visibilityRule(
        granted: fn (User $viewer): array => $viewer->is($manager) ? [$granted->id] : [],
    ));

    $scope = app(StaffLibraryScope::class);
    expect($scope->files($manager)->pluck('name')->all())
        ->toEqualCanonicalizing(['client-file', 'granted-top', 'granted-inner'])
        ->and($scope->folders($manager)->pluck('id')->all())->toEqualCanonicalizing([$granted->id, $grantedInner->id])
        ->and($scope->allowsFile($manager, $deep))->toBeTrue()
        ->and($scope->allowsFile($manager, $other))->toBeFalse()
        ->and($scope->allowsFolder($manager, $grantedInner))->toBeTrue()
        ->and($scope->allowsFolder($manager, $elsewhere))->toBeFalse();

    $this->actingAs($manager)->get("/files/{$top->id}/details")->assertOk();
    expect($this->actingAs($manager)->get("/files/{$other->id}/details")->status())->toBeIn([403, 404]);
});

test('a rule can scope a package\'s own log subjects', function () {
    $admin = User::factory()->create();

    ActivityLog::query()->create([
        'action' => Action::FileUpdated,
        'subject_type' => 'secret',
        'subject_id' => 1,
        'subject_name' => 'classified',
        'created_at' => now(),
    ]);
    app(ActivityLogger::class)->log(Action::Login, $admin);

    $names = fn (): array => app(ActivityLogScope::class)
        ->apply(ActivityLog::query(), $admin)->pluck('action')->map(fn ($a) => $a->value)->all();

    expect($names())->toContain('file.updated');

    app(LibraryVisibilityRules::class)->register(visibilityRule(
        log: fn (User $viewer, Builder $query) => $query->where(fn (Builder $q) => $q
            ->whereNull('subject_type')->orWhere('subject_type', '!=', 'secret')),
    ));

    expect($names())->toBe(['auth.login']);
});

test('a rule registered once outlasts the request, while each viewer\'s answer is worked out again', function () {
    $viewer = User::factory()->create();
    $hidden = makeFolder('Hidden');
    $inner = makeFolder('Inner', $hidden);
    $file = File::factory()->create(['folder_id' => $inner->id]);

    $members = [];
    app(LibraryVisibilityRules::class)->register(visibilityRule(
        hidden: function (User $viewer) use (&$members, $hidden): array {
            return in_array($viewer->id, $members, true) ? [] : [$hidden->id];
        },
    ));

    expect(app(StaffLibraryScope::class)->allowsFile($viewer, $file))->toBeFalse();

    // What a queue worker does between jobs: the rule must survive it.
    app()->forgetScopedInstances();
    expect(app(StaffLibraryScope::class)->allowsFile($viewer, $file))->toBeFalse();

    // Membership changes mid-request: the request keeps its answer...
    $members[] = $viewer->id;
    expect(app(StaffLibraryScope::class)->allowsFolder($viewer, $inner))->toBeFalse();

    // ...and the next one asks again.
    app()->forgetScopedInstances();
    expect(app(StaffLibraryScope::class)->allowsFile($viewer, $file))->toBeTrue()
        ->and(app(StaffLibraryScope::class)->allowsFolder($viewer, $inner))->toBeTrue();
});
