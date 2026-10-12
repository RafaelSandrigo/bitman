<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Domain\ThumbnailProcessor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ThumbnailProcessor::class,
            fn (Application $app): ThumbnailProcessor => new GdThumbnailProcessor($app->make('filesystem')->disk('thumbnails')),
        );
    }

    public function boot(): void
    {
        // Cada submissão processa uma imagem: o limite protege a CPU e a fila de revisão.
        RateLimiter::for('game-submissions', fn (Request $request): Limit => Limit::perHour(10)->by('submissions|'.$request->user()?->getAuthIdentifier()));
    }
}
