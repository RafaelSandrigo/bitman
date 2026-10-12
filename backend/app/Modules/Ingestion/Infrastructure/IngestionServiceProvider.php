<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Infrastructure;

use App\Modules\Ingestion\Domain\GitSourceFetcher;
use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryPolicy;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class IngestionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RepositoryPolicy::class, function (Application $app): RepositoryPolicy {
            $config = $app->make(Config::class);

            /** @var list<string> $paths */
            $paths = (array) $config->get('ingestion.required_paths');

            return RepositoryPolicy::standard(new PolicyLimits(
                $paths,
                (int) $config->get('ingestion.max_file_bytes'),
                (int) $config->get('ingestion.max_tree_entries'),
            ));
        });

        $this->app->bind(GitSourceFetcher::class, function (Application $app): GitSourceFetcher {
            $config = $app->make(Config::class);

            return new HttpGitSourceFetcher(
                $app->make(Http::class),
                $app->make(LoggerInterface::class),
                (string) $config->get('ingestion.sandbox.url'),
                (string) $config->get('ingestion.sandbox.token'),
                (int) $config->get('ingestion.sandbox.timeout'),
                (int) $config->get('ingestion.sandbox.max_response_bytes'),
            );
        });
    }
}
