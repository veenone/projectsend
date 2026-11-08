<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Comments\Models\FileComment;
use App\Modules\Files\Access\LibraryVisibilityRule;
use App\Modules\Files\Access\LibraryVisibilityRules;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\GetClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Tests\Support\McpWorld;

/**
 * A rule whose answers are closures over the viewer, so a test can hide
 * or grant per person the way a package's membership check would.
 */
function libraryVisibilityRule(?Closure $hidden = null, ?Closure $granted = null, ?Closure $log = null): LibraryVisibilityRule
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
function libraryPageNames($response, string $prop, string $key = 'name'): array
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

    // The policies ask once per row, so unrestricted staff must not cost a
    // query — asked of a fresh scope, so no memo answers for it. A rule
    // that hides nothing from this viewer costs nothing either.
    $admin->isClientScoped();
    $askFresh = function () use ($admin, $file, $folder): array {
        app()->forgetScopedInstances();
        $fresh = app(StaffLibraryScope::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $answers = [$fresh->allowsFile($admin, $file), $fresh->allowsFolder($admin, $folder)];
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return [$answers, $queries];
    };

    expect($askFresh())->toBe([[true, true], []]);

    app(LibraryVisibilityRules::class)->register(libraryVisibilityRule(hidden: fn (): array => []));
    expect($askFresh())->toBe([[true, true], []]);
    $scope = app(StaffLibraryScope::class);

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

    app(LibraryVisibilityRules::class)->register(libraryVisibilityRule(
        hidden: fn (User $viewer): array => $viewer->is($member) ? [] : [$hidden->id],
    ));

    // Outsider: an unrestricted admin, now kept out of the subtree.
    $flat = $this->actingAs($outsider)->get('/files?search=-')->assertOk();
    expect(libraryPageNames($flat, 'files'))->toEqualCanonicalizing(['visible-file', 'root-file']);

    $root = $this->actingAs($outsider)->get('/files')->assertOk();
    expect(libraryPageNames($root, 'folders'))->toBe(['Visible'])
        ->and(libraryPageNames($root, 'folder_options'))->toBe(['Visible']);

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
    expect(libraryPageNames($log, 'entries', 'replacements.subject'))
        ->toContain('visible-file', 'root-file')
        ->not->toContain('hidden-top', 'hidden-inner', 'Inner');

    // Member: the same kind of admin, answered differently by the rule.
    expect(libraryPageNames($this->actingAs($member)->get('/files?search=-'), 'files'))
        ->toEqualCanonicalizing(['hidden-top', 'hidden-inner', 'visible-file', 'root-file'])
        ->and($scope->allowsFile($member, $files['hidden-inner']))->toBeTrue();
    $this->actingAs($member)->get("/files/{$files['hidden-top']->id}/details")->assertOk();
    expect(libraryPageNames($this->actingAs($member)->get('/activity'), 'entries', 'replacements.subject'))
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
    // A grant opens a subtree, and the root belongs to none.
    $atRoot = File::factory()->create(['name' => 'root-file', 'uploaded_by' => $admin->id]);

    app(LibraryVisibilityRules::class)->register(libraryVisibilityRule(
        granted: fn (User $viewer): array => $viewer->is($manager) ? [$granted->id] : [],
    ));

    $scope = app(StaffLibraryScope::class);
    expect($scope->files($manager)->pluck('name')->all())
        ->toEqualCanonicalizing(['client-file', 'granted-top', 'granted-inner'])
        ->and($scope->folders($manager)->pluck('id')->all())->toEqualCanonicalizing([$granted->id, $grantedInner->id])
        ->and($scope->allowsFile($manager, $deep))->toBeTrue()
        ->and($scope->allowsFile($manager, $other))->toBeFalse()
        ->and($scope->allowsFile($manager, $atRoot))->toBeFalse()
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

    app(LibraryVisibilityRules::class)->register(libraryVisibilityRule(
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
    app(LibraryVisibilityRules::class)->register(libraryVisibilityRule(
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

/**
 * An unrestricted staff member the rule hides folder "Hidden" from, a client, and
 * one file inside that folder and one at the root, both shared with them.
 *
 * @return array{0: User, 1: User, 2: File, 3: File}
 */
function hiddenFileSharedWithClient(?User $admin = null): array
{
    $admin ??= McpWorld::staff();
    $client = McpWorld::clientAccount();
    $hidden = makeFolder('Hidden');

    $secret = File::factory()->create(['name' => 'hidden-secret', 'folder_id' => $hidden->id]);
    $plain = File::factory()->create(['name' => 'plain-file']);
    shareFileWith($secret, $client);
    shareFileWith($plain, $client);

    app(LibraryVisibilityRules::class)->register(libraryVisibilityRule(hidden: fn (): array => [$hidden->id]));

    return [$admin, $client, $secret, $plain];
}

test('a hidden file stays out of a client\'s files page', function () {
    [$admin, $client] = hiddenFileSharedWithClient();

    expect(libraryPageNames($this->actingAs($admin)->get("/clients/{$client->id}/files")->assertOk(), 'files'))
        ->toBe(['plain-file']);
});

test('a hidden file stays out of what the assistant says about a client', function () {
    McpContext::set(null);
    [$admin, $client] = hiddenFileSharedWithClient();

    $got = McpWorld::call(GetClient::class, ['client_id' => $client->id], $admin);

    expect(McpWorld::error($got))->toBeNull()
        ->and($got['files_shared']['count'])->toBe(1)
        ->and(array_column($got['files_shared']['latest'], 'name'))->toBe(['plain-file'])
        ->and(json_encode($got))->not->toContain('hidden-secret');
});

test('a comment on a hidden file cannot be moderated', function () {
    // A full administrator: the assistant's staff fixture holds no
    // moderate_comments.
    [$admin, , $secret, $plain] = hiddenFileSharedWithClient(User::factory()->create());
    $hiddenComment = FileComment::factory()->for($secret)->fromGuest()->pending()->create();
    $plainComment = FileComment::factory()->for($plain)->fromGuest()->pending()->create();

    $this->actingAs($admin)->post("/comments/{$hiddenComment->id}/approve")->assertForbidden();
    $this->actingAs($admin)->delete("/comments/{$hiddenComment->id}/moderate")->assertForbidden();
    $this->actingAs($admin)->post("/comments/{$plainComment->id}/approve")->assertRedirect();

    expect($hiddenComment->fresh()->isPending())->toBeTrue()
        ->and($plainComment->fresh()->isPending())->toBeFalse();
});

test('a hidden quarantined file stays off the quarantine screen and its badge, and cannot be released', function () {
    [$admin, , $secret, $plain] = hiddenFileSharedWithClient(User::factory()->create());
    foreach ([$secret, $plain] as $file) {
        $file->forceFill(['scan_status' => ScanStatus::Infected, 'scan_note' => 'Eicar-Test-Signature', 'scanned_at' => now()])->save();
    }

    $page = $this->actingAs($admin)->get('/files/quarantine')->assertOk();
    expect(libraryPageNames($page, 'files'))->toBe(['plain-file'])
        ->and(json_decode(json_encode($page->viewData('page')), true)['props']['pending']['quarantine'])->toBe(1);

    confirmPassword($admin);
    $this->actingAs($admin)->post("/files/{$secret->id}/release", ['reason' => 'looks fine'])->assertNotFound();
    expect($secret->fresh()->scan_status)->toBe(ScanStatus::Infected);
});

test('a hidden missing file stays off the missing-files tab', function () {
    [$admin, , $secret, $plain] = hiddenFileSharedWithClient(User::factory()->create());
    File::query()->whereKey([$secret->id, $plain->id])->update(['scan_status' => ScanStatus::Missing]);

    $page = $this->actingAs($admin)->get('/files/orphans?tab=missing')->assertOk();
    expect(libraryPageNames($page, 'missing'))->toBe(['plain-file'])
        ->and(json_decode(json_encode($page->viewData('page')), true)['props']['missing_count'])->toBe(1);
});
