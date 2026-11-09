<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Events\ConvertingAccount;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use Illuminate\Support\Facades\Event;

/*
 * A package may refuse to let an account change between staff and client.
 * With nothing listening, both directions convert exactly as before.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function convertBothWays(User $admin, User $staff, User $client)
{
    return [
        test()->actingAs($admin)->postJson("/users/convert/{$staff->id}", ['direction' => 'to_client']),
        test()->actingAs($admin)->postJson("/users/convert/{$client->id}", [
            'direction' => 'to_staff',
            'role_id' => app(EnsureSystemRoles::class)->materialize(SystemRole::Uploader)->id,
        ]),
    ];
}

test('with nothing listening both directions convert as before', function () {
    $staff = User::factory()->create();
    $client = User::factory()->client()->create();

    foreach (convertBothWays($this->admin, $staff, $client) as $response) {
        $response->assertRedirect()->assertSessionHasNoErrors();
    }

    expect($staff->refresh()->isClient())->toBeTrue()
        ->and($client->refresh()->isStaff())->toBeTrue();
});

test('a listener that refuses stops the conversion with its message, and the account is left as it was', function () {
    $asked = [];
    Event::listen(ConvertingAccount::class, function (ConvertingAccount $event) use (&$asked): void {
        $asked[] = [$event->account->id, $event->direction];
        $event->refuse('Holds archived evidence.');
    });
    $staff = User::factory()->create();
    $client = User::factory()->client()->create();

    foreach (convertBothWays($this->admin, $staff, $client) as $response) {
        $response->assertStatus(422)->assertJsonValidationErrors(['user' => 'Holds archived evidence.']);
    }

    expect($asked)->toBe([[$staff->id, 'to_client'], [$client->id, 'to_staff']])
        ->and($staff->refresh()->isStaff())->toBeTrue()
        ->and($client->refresh()->isClient())->toBeTrue();
});

test('the first refusal wins', function () {
    Event::listen(ConvertingAccount::class, fn (ConvertingAccount $event) => $event->refuse('First.'));
    Event::listen(ConvertingAccount::class, fn (ConvertingAccount $event) => $event->refuse('Second.'));
    $staff = User::factory()->create();

    $this->actingAs($this->admin)->postJson("/users/convert/{$staff->id}", ['direction' => 'to_client'])
        ->assertStatus(422)->assertJsonValidationErrors(['user' => 'First.']);
});
