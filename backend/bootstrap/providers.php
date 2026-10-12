<?php

declare(strict_types=1);

use App\Modules\Audit\Infrastructure\AuditServiceProvider;
use App\Modules\Catalog\Infrastructure\CatalogServiceProvider;
use App\Modules\Identity\Infrastructure\IdentityServiceProvider;
use App\Modules\Versioning\Infrastructure\VersioningServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    CatalogServiceProvider::class,
    IdentityServiceProvider::class,
    VersioningServiceProvider::class,
];
