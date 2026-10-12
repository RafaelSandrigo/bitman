<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure;

use App\Modules\Catalog\Domain\Events\GameSubmitted;
use App\Modules\Versioning\Domain\Events\ImportAuthorized;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Infrastructure\Listeners\QueueRepositoryFetch;
use App\Modules\Versioning\Infrastructure\Listeners\RequestFirstImport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class VersioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            QuarantineStore::class,
            fn (Application $app): QuarantineStore => new FilesystemQuarantineStore($app->make('filesystem')->disk('quarantine')),
        );
    }

    public function boot(): void
    {
        Event::listen(GameSubmitted::class, RequestFirstImport::class);
        Event::listen(ImportAuthorized::class, QueueRepositoryFetch::class);
    }
}
