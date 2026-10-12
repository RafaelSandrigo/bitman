<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Exceptions;

use App\Modules\Ingestion\Domain\FetchFailure;
use RuntimeException;
use Throwable;

final class FetchFailed extends RuntimeException
{
    public function __construct(public readonly FetchFailure $failure, ?Throwable $previous = null)
    {
        parent::__construct($failure->message(), 0, $previous);
    }
}
