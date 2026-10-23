<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure;

use App\Modules\Catalog\Domain\Events\GameBlocked;
use App\Modules\Catalog\Domain\Events\GameSubmitted;
use App\Modules\Catalog\Domain\Events\GameUnblocked;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\Events\ImportAuthorized;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Infrastructure\Console\ReconcileServedCommand;
use App\Modules\Versioning\Infrastructure\Listeners\PutUnblockedGameBackOnline;
use App\Modules\Versioning\Infrastructure\Listeners\QueueRepositoryFetch;
use App\Modules\Versioning\Infrastructure\Listeners\RequestFirstImport;
use App\Modules\Versioning\Infrastructure\Listeners\TakeBlockedGameOffline;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class VersioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ArtifactStore::class,
            fn (Application $app): ArtifactStore => new FilesystemArtifactStore(
                (string) config('publication.artifacts_path'),
                array_values(array_map('strval', (array) config('ingestion.required_paths'))),
            ),
        );
        $this->app->bind(
            QuarantineStore::class,
            fn (Application $app): QuarantineStore => new FilesystemQuarantineStore($app->make('filesystem')->disk('quarantine')),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileServedCommand::class]);
        }
        Event::listen(GameSubmitted::class, RequestFirstImport::class);
        Event::listen(ImportAuthorized::class, QueueRepositoryFetch::class);
        Event::listen(GameBlocked::class, TakeBlockedGameOffline::class);
        Event::listen(GameUnblocked::class, PutUnblockedGameBackOnline::class);
    }
}
