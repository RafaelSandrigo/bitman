<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

/** Uma regra da política de repositório (Specification). Nova regra = nova classe. */
interface RepositoryRule
{
    /** @return list<Violation> */
    public function check(RepositorySnapshot $snapshot): array;
}
