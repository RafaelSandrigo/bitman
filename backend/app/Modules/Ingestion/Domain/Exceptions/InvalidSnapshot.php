<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Exceptions;

use DomainException;

/** O manifesto recebido da sandbox é incoerente (zero trust: a sandbox pode ter sido comprometida). */
final class InvalidSnapshot extends DomainException {}
