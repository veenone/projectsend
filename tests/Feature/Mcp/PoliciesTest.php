<?php

declare(strict_types=1);

use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Tools\DeleteFile;
use App\Modules\Mcp\Tools\MoveFile;
use App\Modules\Mcp\Tools\RevokeLink;
use App\Modules\Mcp\Tools\SendFileToClient;
use App\Modules\Mcp\Tools\ShareByLink;
use App\Modules\Mcp\Tools\ShareFolderWithClient;
use Illuminate\Support\Facades\Gate;
use Tests\Support\McpWorld;

/*
 * The tools that delete, move or share ask FilePolicy and FolderPolicy
 * through the Gate, as the web and the API do, on top of the role's
 * permission keys. A refusal the role alone would not give (here a
 * Gate::before, as a package registers) reaches the assistant too.
 */

beforeEach(function (): void {
    McpContext::set(null);
});

/** Refuse $ability on $subject for everybody, as a package's Gate::before would. */
function mcpRefuse(string $ability, File|Folder $subject): void
{
    Gate::before(fn ($user, string $asked, array $arguments): ?bool => $asked === $ability && ($arguments[0] ?? null)?->is($subject) ? false : null);
}

it('asks the policy before each file tool deletes, moves or shares', function (string $tool, string $ability, Closure $arguments): void {
    $staff = McpWorld::staff();
    $file = McpWorld::file($staff);
    mcpRefuse($ability, $file);

    $error = McpWorld::error(McpWorld::call($tool, $arguments($file), $staff));

    expect($error['code'] ?? null)->toBe('permission_missing')
        ->and($error['permission'])->toBe('policy')
        ->and(File::query()->find($file->id)?->folder_id)->toBeNull()
        ->and(ShareLink::query()->count())->toBe($tool === RevokeLink::class ? 1 : 0)
        ->and($file->fresh()->assignments()->count())->toBe(0);
})->with([
    'delete_file' => [DeleteFile::class, 'delete', fn (File $file): array => ['file_id' => $file->id]],
    'move_file' => [MoveFile::class, 'update', fn (File $file): array => ['file_id' => $file->id, 'folder_id' => McpWorld::folder('Elsewhere')->id]],
    'share_by_link' => [ShareByLink::class, 'update', fn (File $file): array => ['file_id' => $file->id]],
    'send_file_to_client' => [SendFileToClient::class, 'update', fn (File $file): array => ['file_id' => $file->id, 'client_id' => McpWorld::clientAccount()->id]],
    'revoke_link' => [RevokeLink::class, 'update', fn (File $file): array => ['link_id' => McpWorld::link($file)->id]],
]);

it('asks the policy before sharing a folder', function (): void {
    $staff = McpWorld::staff();
    $folder = McpWorld::folder('Contracts', creator: $staff);
    mcpRefuse('update', $folder);

    $error = McpWorld::error(McpWorld::call(ShareFolderWithClient::class, ['folder_id' => $folder->id, 'client_id' => McpWorld::clientAccount()->id], $staff));

    expect($error['code'] ?? null)->toBe('permission_missing')
        ->and($error['permission'])->toBe('policy')
        ->and($folder->assignments()->count())->toBe(0);
});
