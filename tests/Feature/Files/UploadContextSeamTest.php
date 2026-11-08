<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Events\FileWasStored;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Files\Uploads\UploadContextHandler;
use App\Modules\Files\Uploads\UploadContextHandlers;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| An upload made on behalf of something else
|--------------------------------------------------------------------------
|
| A package can name a context on the chunked upload, decide who may use
| it, and say which disk its bytes go to. Core knows none of them: with
| nothing registered, an upload is exactly what it was.
*/

beforeEach(function () {
    // An installation with nobody in it is still being installed, and
    // sends every request to the installer.
    User::factory()->create();
});

function registerUploadContext(string $key, Closure $authorize, ?string $disk = null): void
{
    app(UploadContextHandlers::class)->register($key, new class($authorize, $disk) implements UploadContextHandler
    {
        public function __construct(private Closure $authorize, private ?string $disk) {}

        public function authorize(User $uploader, string $id, ?Folder $folder): bool
        {
            return ($this->authorize)($uploader, $id, $folder);
        }

        public function disk(User $uploader, string $id): ?string
        {
            return $this->disk;
        }
    });
}

function beginUpload(array $fields = []): TestResponse
{
    return test()->postJson('/uploads', [
        'filename' => 'statement.pdf',
        'size' => 11,
        'type' => 'application/pdf',
        ...$fields,
    ]);
}

test('with nothing registered a chunked upload is created exactly as before', function () {
    Storage::fake('files');
    $client = uploadingClient();
    $bucket = bucketLocation();
    makeDefault($bucket);
    Storage::fake($bucket->disk);

    $stored = [];
    Event::listen(FileWasStored::class, function (FileWasStored $event) use (&$stored): void {
        $stored[] = $event;
    });

    $this->actingAs($client);
    $id = beginUpload()->assertOk()->json('uploadId');

    $session = UploadSession::query()->findOrFail($id);
    expect($session->context_key)->toBeNull()
        ->and($session->context_id)->toBeNull()
        ->and($session->context())->toBeNull();

    uploadChunkedAsClient()->assertOk();

    expect(File::query()->latest('id')->firstOrFail()->disk)->toBe($bucket->disk)
        ->and($stored)->toHaveCount(1)
        ->and($stored[0]->context)->toBeNull();
});

/**
 * One upload through each path that takes one: the chunked session, the
 * web form and the API, each into $folder. Returns the disks they landed on.
 *
 * @return array{chunked: string, web: string, api: string}
 */
function uploadEveryWay(User $staff, ?Folder $folder): array
{
    test()->actingAs($staff, 'web');
    uploadChunkedAsClient(['folder_id' => $folder?->id])->assertOk();
    $chunked = File::query()->latest('id')->firstOrFail()->disk;

    test()->post('/files', [
        'file' => UploadedFile::fake()->create('web.pdf', 12, 'application/pdf'),
        'folder_id' => $folder?->id,
    ])->assertSessionHasNoErrors();
    $web = File::query()->latest('id')->firstOrFail()->disk;

    $token = $staff->createToken('t', [Permission::Upload->value])->plainTextToken;
    app('auth')->forgetGuards();
    test()->withToken($token)->post('/api/v1/files', [
        'file' => UploadedFile::fake()->create('api.pdf', 12, 'application/pdf'),
        'folder_id' => $folder?->id,
    ], ['Accept' => 'application/json'])->assertStatus(201);
    $api = File::query()->latest('id')->firstOrFail()->disk;
    test()->flushHeaders();

    return ['chunked' => $chunked, 'web' => $web, 'api' => $api];
}

test('with nothing claiming, every upload path resolves its disk exactly as before', function () {
    Storage::fake('files');
    $bucket = bucketLocation();
    makeDefault($bucket);
    Storage::fake($bucket->disk);

    expect(uploadEveryWay(User::factory()->create(), makeFolder('Anywhere')))
        ->toBe(['chunked' => $bucket->disk, 'web' => $bucket->disk, 'api' => $bucket->disk]);
});

test('a listener can claim a disk for a folder on every upload path, whichever order it runs in', function () {
    Storage::fake('files');
    $room = makeFolder('Room');
    $elsewhere = makeFolder('Elsewhere');
    $bucket = bucketLocation();
    applyStorage();
    Storage::fake($bucket->disk);

    // Ahead of core's own listener, which still runs after it and sets
    // $disk the usual way: the claim must survive that.
    Event::forget(ResolvingUploadDisk::class);
    Event::listen(ResolvingUploadDisk::class, function (ResolvingUploadDisk $event) use ($room, $bucket): void {
        if ($event->folderId === $room->id) {
            $event->claim($bucket->disk);
        }
    });
    Event::listen(ResolvingUploadDisk::class, [ExternalStorageConfigApplier::class, 'resolveDisk']);

    $staff = User::factory()->create();

    expect(uploadEveryWay($staff, $room))
        ->toBe(['chunked' => $bucket->disk, 'web' => $bucket->disk, 'api' => $bucket->disk])
        ->and(uploadEveryWay($staff, $elsewhere))
        ->toBe(['chunked' => 'files', 'web' => 'files', 'api' => 'files']);
});

test('the first claim wins', function () {
    $event = new ResolvingUploadDisk(User::factory()->create());
    $event->claim('first');
    $event->claim('second');

    expect($event->claimedDisk)->toBe('first');
});

test('a handler sees the folder an upload is aimed at, and can refuse the wrong one', function () {
    $staff = User::factory()->create();
    $room = makeFolder('Room');
    $elsewhere = makeFolder('Elsewhere');
    $seen = [];
    registerUploadContext('test.room', function (User $uploader, string $id, ?Folder $folder) use (&$seen, $room): bool {
        $seen[] = $folder?->id;

        return $folder?->is($room) ?? false;
    });

    $this->actingAs($staff);
    beginUpload(['context_key' => 'test.room', 'context_id' => 'r', 'folder_id' => $elsewhere->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['context_key']);
    beginUpload(['context_key' => 'test.room', 'context_id' => 'r'])->assertStatus(422);
    beginUpload(['context_key' => 'test.room', 'context_id' => 'r', 'folder_id' => $room->id])->assertOk();

    expect($seen)->toBe([$elsewhere->id, null, $room->id]);
});

test('an unknown context key is refused before any byte is sent', function () {
    $this->actingAs(uploadingClient());

    beginUpload(['context_key' => 'nobody.knows', 'context_id' => '7'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['context_key' => 'This upload context is not available to you.']);

    expect(UploadSession::query()->count())->toBe(0);
});

test('a context id is required with a context key', function () {
    registerUploadContext('test.room', fn (): bool => true);
    $this->actingAs(uploadingClient());

    beginUpload(['context_key' => 'test.room'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['context_id']);
});

test('a handler decides who may use its context', function () {
    $allowed = uploadingClient();
    $refused = uploadingClient();
    registerUploadContext('test.room', fn (User $uploader, string $id): bool => $uploader->is($allowed) && $id === 'room-1');

    $this->actingAs($refused);
    beginUpload(['context_key' => 'test.room', 'context_id' => 'room-1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['context_key']);
    expect(UploadSession::query()->count())->toBe(0);

    $this->actingAs($allowed);
    $id = beginUpload(['context_key' => 'test.room', 'context_id' => 'room-1'])->assertOk()->json('uploadId');

    $context = UploadSession::query()->findOrFail($id)->context();
    expect($context?->key)->toBe('test.room')
        ->and($context?->id)->toBe('room-1');
});

test('a handler routes the upload to its disk and the stored event carries the context', function () {
    Storage::fake('files');
    $client = uploadingClient();
    // Not one of the client's locations: the handler, not the client's
    // list, is what sends the bytes here.
    $bucket = bucketLocation();
    applyStorage();
    Storage::fake($bucket->disk);
    registerUploadContext('test.room', fn (): bool => true, $bucket->disk);
    Event::fake([FileWasStored::class]);

    $this->actingAs($client);
    uploadChunkedAsClient(['context_key' => 'test.room', 'context_id' => 'room-1'])->assertOk();

    $file = File::query()->latest('id')->firstOrFail();
    expect($file->disk)->toBe($bucket->disk);
    Storage::disk($bucket->disk)->assertExists($file->path);

    Event::assertDispatched(FileWasStored::class, fn (FileWasStored $event): bool => $event->context?->key === 'test.room'
        && $event->context->id === 'room-1'
        && $event->file->is($file));
});

test('a handler that returns no disk leaves the normal resolution alone', function () {
    Storage::fake('files');
    $client = uploadingClient();
    $bucket = bucketLocation();
    assignLocations($client, [$bucket]);
    applyStorage();
    Storage::fake($bucket->disk);
    registerUploadContext('test.room', fn (): bool => true);

    $this->actingAs($client);
    uploadChunkedAsClient(['context_key' => 'test.room', 'context_id' => 'room-1'])->assertOk();

    expect(File::query()->latest('id')->firstOrFail()->disk)->toBe($bucket->disk);
});

test('a context key can only be claimed once', function () {
    registerUploadContext('test.room', fn (): bool => true);

    expect(fn () => registerUploadContext('test.room', fn (): bool => true))
        ->toThrow(InvalidArgumentException::class);
});
