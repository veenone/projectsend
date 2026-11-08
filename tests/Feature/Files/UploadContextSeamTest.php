<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Events\FileWasStored;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\UploadContextHandler;
use App\Modules\Files\Uploads\UploadContextHandlers;
use App\Modules\Files\Uploads\UploadSession;
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

        public function authorize(User $uploader, string $id): bool
        {
            return ($this->authorize)($uploader, $id);
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

    $this->actingAs($client);
    $id = beginUpload()->assertOk()->json('uploadId');

    $session = UploadSession::query()->findOrFail($id);
    expect($session->context_key)->toBeNull()
        ->and($session->context_id)->toBeNull()
        ->and($session->context())->toBeNull();

    uploadChunkedAsClient()->assertOk();

    expect(File::query()->latest('id')->firstOrFail()->disk)->toBe($bucket->disk);
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
