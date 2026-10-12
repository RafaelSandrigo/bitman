<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure;

use App\Modules\Catalog\Domain\Events\GameSubmitted;
use App\Modules\Versioning\Infrastructure\Listeners\RequestFirstImport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class VersioningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(GameSubmitted::class, RequestFirstImport::class);
    }
}
