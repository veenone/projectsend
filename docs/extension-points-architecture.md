# Extension points

A package — `cloud-modules`, `community-modules`, or one of an installation's own — changes what
the application does only through the seams listed here. Core never names a package: it dispatches
an event or reads a registry, and whatever is installed answers. Every seam has a default, and the
default is what an installation running no packages has always done. That is the one rule all of
them share, and each section below says what it means for that seam.

Everything is registered from a package service provider's `boot()`. An installation's own
providers go in `bootstrap/providers.local.php`, which no release ships, so an update leaves them
alone.

---

## How the seams are shaped

There are two shapes, and the choice between them is not a matter of taste.

**Events with a mutable payload.** Core builds an object holding the default answer, dispatches
it, and reads it back. A listener changes the answer or leaves it alone. With no listener, the
default stands and nothing else happens. A package listens by *string* class name rather than by
importing the class:

```php
Event::listen('App\Modules\Files\Events\ResolvingUploadNotice', function ($event): void {
    $event->add(__('Files on this plan are kept for 30 days.'));
});
```

That indirection keeps a package buildable and testable with no host application present, which
is also why these are events rather than interfaces a package implements or container bindings it
replaces. A binding has exactly one owner, so two packages wanting the same seam would have to know
about each other; an event takes any number of listeners, and none of them needs the others.

**Registries.** Where a package contributes something with an identity of its own — a permission
key, an activity-log action, an upload context, a visibility rule — core keeps a registry bound as
a singleton, and the package registers into it. A registry refuses a key that collides with core's
own vocabulary or with another registration, and it refuses it at boot, so a clash fails on the
first request in development rather than leaving two owners decided by boot order. A package using
a registry types against the host's classes; one that must also load on an installation predating
the registry can guard the call with `class_exists()`.

Some events only move one way. Where a listener can make something stricter but not looser (hiding
attribution, deleting sooner, requiring a rendering), the payload has a method or a flag that only
moves in that direction, so one listener declining is never taken as the others' answer.

---

## Sidebar links — `ResolvingNavigationLinks`

`App\Modules\Platform\Navigation\Events\ResolvingNavigationLinks`. Asked on every Inertia page;
the links are shown in the sidebar beside its own. Label, URL and icon all come from the listener; core
learns nothing about what is added.

```php
Event::listen('App\Modules\Platform\Navigation\Events\ResolvingNavigationLinks', function ($event): void {
    if ($event->isStaff) {
        $event->add(__('Billing'), 'https://portal.example.com', external: true, icon: 'credit-card');
    }
});
```

`external: true` opens the link in a new tab and marks it as leaving the installation. Links are
only ever rendered in the administration area, and core decides that rather than each listener;
`isStaff` is there for listeners whose link makes no sense for some staff.

**Nothing registered:** the sidebar is the hardcoded one.

## Announcements — `ResolvingAnnouncement`

`App\Modules\Platform\Announcements\Events\ResolvingAnnouncement`. One message, shown as a band
across the dashboard and behind an icon beside the notification bell on every other page.

```php
Event::listen('App\Modules\Platform\Announcements\Events\ResolvingAnnouncement', function ($event): void {
    $event->show(
        title: __('Your trial ends on Friday'),
        body: __('Choose a plan to keep your files.'),
        audience: 'staff',
        actionLabel: __('See plans'),
        actionUrl: 'https://portal.example.com/plans',
        tone: 'warning',
    );
});
```

`audience` is required: `'staff'` or `'clients'`. A message aimed at the wrong audience, or at an
unknown one, reaches nobody. The first `show()` wins; later calls are ignored. `tone` is `info` or
`warning`, and anything else falls back to `info`.

**Nothing registered:** no band and no icon.

## Upload notice — `ResolvingUploadNotice`

`App\Modules\Files\Events\ResolvingUploadNotice`. Lines shown above the uploader on a client's
upload page, in every theme. Each listener adds whole, already-translated sentences; two unrelated
rules can both apply, so it is a list rather than one message. `$event->uploader` is the client.

The sample under [How the seams are shaped](#how-the-seams-are-shaped) is a complete listener.

**Nothing registered:** no notice.

## API modules — `RegisteringApiModules`

`App\Modules\Api\Events\RegisteringApiModules`. Dispatched once while `routes/api.php` loads. A
package supplies a slug, its routes and the capability that gates them; core mounts them under
`/api/v1/modules/{slug}` inside its own authentication, rate limiting and error handling.

```php
Event::listen('App\Modules\Api\Events\RegisteringApiModules', function ($event): void {
    $event->register(
        slug: 'branding',
        routes: __DIR__.'/../routes/api.php',
        capability: 'branding.customize',
    );
});
```

`capability` has no default: pass `null` explicitly for a module available in every edition. A
malformed or duplicate slug, a missing route file or an unknown capability throws at route-load
time. `GET /api/v1/me` lists the mounted slugs. [`api-modules.md`](api-modules.md) covers why
module paths stay out of the committed OpenAPI document.

**Nothing registered:** no module routes, and `/api/v1/me` lists none.

## Rendered images — `RenderingImage` and `ResolvingImageRendering`

`App\Modules\Files\Thumbnails\Events\RenderingImage` is dispatched once per rendered thumbnail or
preview, after the image is oriented and scaled and before it is encoded. The listener changes
`$event->image` (a `SimpleImage`) in place, so a watermarked JPEG is encoded once, not twice.
`audience` and `rendition` say who the image is for and which size it is; core caches them apart,
so a listener may draw them differently.

A preview is normally the original file, served without decoding it. A listener that intends to
change a preview says so first through `ResolvingImageRendering`, and only then does core render
one:

```php
Event::listen('App\Modules\Files\Thumbnails\Events\ResolvingImageRendering', function ($event): void {
    if ($event->audience->value !== 'staff') {
        $event->required = true;
    }
});

Event::listen('App\Modules\Files\Thumbnails\Events\RenderingImage', function ($event): void {
    if ($event->audience->value !== 'staff') {
        app(Watermarker::class)->stamp($event->image);
    }
});
```

A listener that throws fails the request for that image. Core does not swallow the error, because a
silently unmarked image is worse than a visible failure.

**Nothing registered:** thumbnails are written as core produces them, and previews serve the
original bytes.

## Package pages, stylesheets and translations

No event is involved; the build and the shared props find these.

- **Pages.** `resources/js/app.tsx` globs `vendor/*/*/resources/js/pages/**/*.tsx`. A package
  controller returns `Inertia::render('reports/index')` and the page at
  `resources/js/pages/reports/index.tsx` inside the package resolves exactly like a core page. Only
  `vendor/` is globbed; a path repository symlinks a local clone there, so a dev checkout works the
  same way.
- **Stylesheets.** `vendor/*/*/resources/css/*.css` is imported after `app.css`, so a package can
  restyle what core draws.
- **Translations.** The shared `translations` prop is read through the framework's translation
  loader, so a catalogue registered with `loadJsonTranslationsFrom()` reaches the frontend. An
  installation's own `lang/{locale}.json` is merged last and wins.

Pages and stylesheets are picked up at build time: after installing a package, run `npm run build`.

**Nothing installed:** the globs match nothing and the bundle is core's alone.

---

## Upload contexts — `UploadContextHandlers`

For a package whose screens take uploads that belong to something of its own: a request for
evidence, a data room, a ticket. The package claims a context key and decides who may upload into
it and, optionally, which disk the bytes go to. Core stores the context on the upload and hands it
back when the file is stored, without reading it.

The handler implements `App\Modules\Files\Uploads\UploadContextHandler`:

```php
final class DataRoomUploads implements UploadContextHandler
{
    public function authorize(User $uploader, string $id): bool
    {
        return DataRoom::query()->whereKey($id)->whereMember($uploader)->exists();
    }

    public function disk(User $uploader, string $id): ?string
    {
        return DataRoom::query()->find($id)?->disk;
    }
}
```

and is registered under its key:

```php
public function boot(): void
{
    $this->app->make(UploadContextHandlers::class)->register('dataroom', new DataRoomUploads);

    Event::listen(FileWasStored::class, function (FileWasStored $event): void {
        if ($event->context?->key === 'dataroom') {
            DataRoom::query()->find($event->context->id)?->attach($event->file);
        }
    });
}
```

The package's page starts a chunked upload (`POST /uploads`, or `POST /api/v1/uploads`) with
`context_key` and `context_id` beside the usual fields. `context_id` is required whenever
`context_key` is sent and means whatever the handler says it means. `authorize()` is asked when the
session is created, before any byte is sent; `false` is a 422 on `context_key`. A key nobody
registered gets the same 422, so the answer does not reveal which contexts exist.

`disk()` returning a disk name sends the bytes there. Returning `null` leaves the choice to the
uploader's storage locations, as for any other upload. Either way the disk is resolved through
`ResolvingUploadDisk`, which now carries `context` and `contextDisk` too.

`FileWasStored` carries the `UploadContext` (`key`, `id`) as `$event->context`, so the handler's
package can tell its own files from everyone else's. It is `null` for every upload that named no
context. A key can be registered once; a second registration throws.

**Nothing registered:** any request naming a context is refused, and every other upload is created,
stored and announced exactly as before, with `context_key`, `context_id` and `FileWasStored::$context`
all null.

## Permissions — `ExtraPermissions`

For a package that gates its own screens or actions on a permission an administrator grants per
role. A registered permission appears in the roles editor under the category given, can be granted
to a role like a core one, and is checked through the Gate under its key.

```php
public function boot(): void
{
    $this->app->make(ExtraPermissions::class)->register(
        'evidence.manage',
        'Manage evidence',
        PermissionCategory::System,
    );
}
```

```php
Route::middleware('can:evidence.manage')->group(...);
// or
Gate::allows('evidence.manage');
```

The label is English and is also the translation key, so the package translates it in its own
catalogue. Administrators hold every registered permission without a stored row; anyone else needs
the role to have been granted it. The usual grading applies: a staff member cannot grant a
registered permission their own role lacks, cannot give themselves a role that carries one, and
cannot edit a role, or its holders, carrying one they lack.

A key that is already a core permission, or already registered, throws.

**Nothing registered:** the roles editor lists exactly the core permissions, and every permission
check is unchanged.

## Activity-log actions — `ExtraActions`, `LogAction`, `UnknownAction`

For a package that records its own events in the activity log, beside core's.

```php
public function boot(): void
{
    $this->app->make(ExtraActions::class)->register(
        'evidence.recorded',
        'Archived ":subject" for :release',
        'Evidence was archived',
    );
}
```

```php
app(ActivityLogger::class)->log(
    app(ExtraActions::class)->find('evidence.recorded'),
    subject: $file,
    context: ['release' => '1.2'],
);
```

The second argument is the sentence for a log row, with the same placeholders core's own templates
use; the third is the description shown in the action filter. Both are English and double as
translation keys. A registered action is listed in the activity log's filter, filters like a core
one, and comes back from `GET /api/v1/activity` under its key.

`ActivityLogger::log()` and `logSystem()` take any `LogAction`: a core `Action` case, a registered
`ExtraAction`, or an `UnknownAction`. The last is what a stored key that is neither core nor
registered reads back as, typically a row left by a package that has since been removed. Such a row
still renders, as `Performed "evidence.recorded"`, rather than breaking the page.

A key that is already a core action, or already registered, throws.

**Nothing registered:** the activity log, its filter list and the API are exactly as before.

## Library visibility — `LibraryVisibilityRules`

For a package that must keep part of the shared library away from some staff, or open part of it
to client-scoped staff who would not otherwise see it. A rule implements
`App\Modules\Files\Access\LibraryVisibilityRule`:

```php
final class RestrictedRooms implements LibraryVisibilityRule
{
    public function hiddenFolderIds(User $viewer): array
    {
        return DataRoom::query()->whereNotMember($viewer)->pluck('folder_id')->all();
    }

    public function grantedFolderIds(User $viewer): array
    {
        return DataRoom::query()->whereMember($viewer)->pluck('folder_id')->all();
    }

    public function scopeActivityLog(User $viewer, Builder $query): void
    {
        // Narrow entries whose subject is one of this package's own models.
    }
}
```

```php
public function boot(): void
{
    $this->app->make(LibraryVisibilityRules::class)->register(new RestrictedRooms);
}
```

Folder ids are subtree roots: each stands for that folder and everything below it, trashed folders
included. `hiddenFolderIds()` applies to every staff member, unrestricted ones too.
`grantedFolderIds()` is only asked for client-scoped staff, since everyone else already sees the
whole library. `scopeActivityLog()` narrows the log for the package's own subject types; entries
about files and folders are already handled by core.

`StaffLibraryScope` folds the rules into every staff listing (files, folders, a client's files
page), into `allowsFile()` and `allowsFolder()` and so into every policy that consults them
(download, preview, details, comment moderation), into the activity log, and into what the MCP
connector reports. Files at the library root belong to no folder, and no rule can hide them.

Two methods on `StaffLibraryScope` are for code that needs the answer directly:
`hiddenSubtreeIds($user)` returns every folder id hidden from that person, remembered for the
request; `unrestricted($user)` is true when they are not client-scoped and nothing is hidden from
them, which is what lets a per-row check skip its query.

The registry is a singleton and outlasts queue jobs; nothing in it is remembered per viewer, so each
request works out each viewer's answer afresh.

**A constraint to design around.** Hiding is enforced where a file or folder is *reached*, not on
operations that act on a whole subtree. Deleting or sharing a folder the viewer can see still
reaches everything inside it, including a hidden subtree nested below it. Hide subtrees rooted at
the library's top level, where no visible parent sits above them.

**Nothing registered:** every listing, every per-row check and every activity-log query is exactly
the one it was before.

## Account deletion — `DeletingAccount`

`App\Modules\Identity\Events\DeletingAccount`. "May this account be deleted?" It is asked once on
every path that deletes or erases an account, before anything is deleted or reassigned and before
the transaction starts. `$event->path` says which: `'delete'` for an administrator deleting a staff
member or a client (web or API), `'self'` for somebody deleting their own account, `'erase'` for
permanent erasure by the console command or the scheduled purge.

```php
Event::listen('App\Modules\Identity\Events\DeletingAccount', function ($event): void {
    if (Evidence::query()->heldBy($event->account)->exists()) {
        $event->refuse(__('This account holds archived evidence and cannot be deleted yet.'));
    }
});
```

The first refusal wins, so the message shown is the one from the listener that stopped it. Where it
is shown depends on the path:

| Path | What the person sees |
|---|---|
| `delete`, browser | The delete dialog on the users or clients page stays open, keeps the choice made in it, and shows the message. |
| `delete`, API | A 422 with the message as the error on `account`. |
| `self` | The profile form, still signed in, with the message under the password field. |
| `erase`, `projectsend:erase-account` | The command prints the message and exits with failure. |
| `erase`, scheduled purge | The account is skipped, a warning is logged, and it is asked again on the next run. |

Code that calls `AccountEraser::erase()` directly gets an
`App\Modules\Identity\Erasure\ErasureRefused` exception carrying the message.

**Nothing registered:** every path deletes and erases exactly as before.

---

## Other hooks

Smaller seams, each documented in full on its class.

| Event | Asked | Default |
|---|---|---|
| `Files\Storage\ResolvingUploadDisk` | Once per upload, before bytes are written: which disk? | `files`. Core's own listener applies storage locations. |
| `Platform\Attribution\Events\ResolvingAttribution` | Per page render and per outgoing email: name ProjectSend to clients? | Yes. Can only be turned off. Staff surfaces are never affected. |
| `Platform\Installation\Events\ResolvingInstallationStatus` | By `projectsend:status`: anything else worth reporting? | No extra facts. Scalars and null only. |
| `Identity\Erasure\Events\ResolvingSelfDeletion` | When somebody deletes their own account: do their files go at once? | The installation's setting. Can only be made sooner. |
| `Audit\Events\ResolvingActivityOrigin` | Signed-in request with no personal access token: was it really a browser? | A browser session. Set `origin` only for your own credential. |
| `Mcp\Events\ResolvingConnectorPlan` | First time the MCP connector needs plan details. | No plan; only staff may connect. Grants no permission. |

All of these are in the `App\Modules\` namespace.
