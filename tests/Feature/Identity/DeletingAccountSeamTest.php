<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Identity\Erasure\AccountEraser;
use App\Modules\Identity\Erasure\ErasureRefused;
use App\Modules\Identity\Events\DeletingAccount;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/*
 * Seam 5: a package may refuse to let an account be deleted or erased.
 * With nothing listening, every path deletes exactly as before.
 */

beforeEach(function () {
    Storage::fake('files');
    $this->admin = User::factory()->create();

    // Cached settings survive the per-test rollback; set what is leaned on.
    $settings = app(Settings::class);
    $settings->set(Setting::AccountSelfDeleteFiles, 'immediately');
    $settings->set(Setting::AccountSelfDeleteScope, 'any');
    $settings->set(Setting::AccountErasureGraceDays, 30);
    $settings->set(Setting::AccountErasureContentAction, 'cascade_delete');
});

dataset('deletable accounts', [
    'staff' => [fn () => User::factory()->create(), 'users'],
    'client' => [fn () => User::factory()->client()->create(), 'clients'],
]);

function refuseEveryDeletion(): void
{
    Event::listen(DeletingAccount::class, fn (DeletingAccount $event) => $event->refuse('Holds archived evidence.'));
}

/**
 * Runs each path once and asserts the outcome the existing deletion tests
 * assert: the admin delete cascades, self-deletion soft-deletes and removes
 * their files right away, the console command erases.
 */
function assertEveryPathDeletesAsBefore(User $admin): void
{
    foreach ([[User::factory()->create(), 'users'], [User::factory()->client()->create(), 'clients']] as [$target, $segment]) {
        $file = File::factory()->create(['uploaded_by' => $target->id]);

        test()->actingAs($admin)
            ->delete("/{$segment}/{$target->id}", ['content_action' => 'cascade_delete'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue()
            ->and(File::withTrashed()->find($file->id)->trashed())->toBeTrue();
    }

    $self = User::factory()->client()->create();
    $own = File::factory()->create(['uploaded_by' => $self->id]);

    test()->actingAs($self)->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');
    auth()->logout();

    expect(User::withTrashed()->find($self->id)->trashed())->toBeTrue()
        ->and(File::query()->find($own->id))->toBeNull();

    $erased = User::factory()->client()->create(['email' => 'erase-me@example.com']);

    test()->artisan('projectsend:erase-account', ['email' => 'erase-me@example.com', '--force' => true])
        ->assertSuccessful();

    expect(User::withTrashed()->find($erased->id))->toBeNull();
}

test('with nothing listening deletion, erasure and self-deletion behave exactly as before', function () {
    assertEveryPathDeletesAsBefore($this->admin);
});

test('a listener can refuse an administrator\'s delete, and nothing is touched', function (Closure $make, string $segment) {
    $target = $make();
    $file = File::factory()->create(['uploaded_by' => $target->id]);
    $folder = Folder::query()->create(['name' => 'Evidence', 'created_by' => $target->id]);
    $permissions = RolePermission::query()->orderBy('id')->get()->toArray();
    refuseEveryDeletion();

    $this->actingAs($this->admin)
        ->deleteJson("/{$segment}/{$target->id}", ['content_action' => 'cascade_delete'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account' => 'Holds archived evidence.']);

    expect(User::query()->find($target->id))->not->toBeNull()
        ->and(User::query()->find($target->id)->erase_after)->toBeNull()
        ->and(File::query()->find($file->id)?->uploaded_by)->toBe($target->id)
        ->and(Folder::query()->find($folder->id)?->created_by)->toBe($target->id)
        ->and(RolePermission::query()->orderBy('id')->get()->toArray())->toBe($permissions);
})->with('deletable accounts');

test('the delete dialogs get the refusal as an error on account, with or without content to decide on', function (Closure $make, string $segment) {
    $bare = $make();
    $owning = $make();
    File::factory()->create(['uploaded_by' => $owning->id]);
    refuseEveryDeletion();

    // An Inertia visit, the way both dialogs send it.
    $this->actingAs($this->admin)->from("/{$segment}")
        ->delete("/{$segment}/{$bare->id}")
        ->assertRedirect("/{$segment}")
        ->assertSessionHasErrors(['account' => 'Holds archived evidence.']);

    $this->from("/{$segment}")
        ->delete("/{$segment}/{$owning->id}", ['content_action' => 'cascade_delete'])
        ->assertRedirect("/{$segment}")
        ->assertSessionHasErrors(['account' => 'Holds archived evidence.']);

    expect(User::query()->whereKey([$bare->id, $owning->id])->count())->toBe(2);
})->with('deletable accounts');

test('the same refusal stops erasure, self-deletion and the console command', function () {
    $paths = [];
    Event::listen(DeletingAccount::class, function (DeletingAccount $event) use (&$paths) {
        $paths[] = $event->path;
    });
    refuseEveryDeletion();

    // Self-deletion: back to the form with the message, still signed in.
    $self = User::factory()->client()->create();
    $own = File::factory()->create(['uploaded_by' => $self->id]);

    $this->actingAs($self)
        ->from('/settings/profile')
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasErrors(['account' => 'Holds archived evidence.']);

    $this->assertAuthenticatedAs($self);
    expect(User::query()->find($self->id)?->erase_after)->toBeNull()
        ->and(File::query()->find($own->id))->not->toBeNull();
    auth()->logout();

    // The console command prints the message and fails.
    $erased = User::factory()->client()->create(['email' => 'keep-me@example.com']);
    $kept = File::factory()->create(['uploaded_by' => $erased->id]);

    $this->artisan('projectsend:erase-account', ['email' => 'keep-me@example.com', '--force' => true])
        ->expectsOutputToContain('Holds archived evidence.')
        ->assertExitCode(1);

    expect(User::query()->find($erased->id))->not->toBeNull()
        ->and(File::query()->find($kept->id))->not->toBeNull();

    // The eraser itself refuses, so nothing calling it can go around this.
    expect(fn () => app(AccountEraser::class)->erase($erased))
        ->toThrow(ErasureRefused::class, 'Holds archived evidence.');

    // The scheduled purge skips the refused account rather than failing.
    $due = User::factory()->client()->create();
    $due->forceFill(['erase_after' => now()->subDay()])->save();
    $due->delete();

    $this->artisan('projectsend:purge-erasures')->assertSuccessful();

    expect(User::withTrashed()->find($due->id))->not->toBeNull();

    expect($paths)->toBe(['self', 'erase', 'erase', 'erase']);
});

test('a listener that does not refuse changes nothing about the outcome', function () {
    $seen = 0;
    Event::listen(DeletingAccount::class, function (DeletingAccount $event) use (&$seen) {
        $seen++;
    });

    assertEveryPathDeletesAsBefore($this->admin);

    // Two administrator deletes, one self-deletion, one console erasure.
    expect($seen)->toBe(4);
});
