<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DomainException;

/**
 * Base das exceções de domínio: uma regra de negócio impediu a operação.
 * A mensagem é exibida ao usuário (pt-BR); o mapeamento para HTTP 422 fica em bootstrap/app.php.
 */
abstract class BusinessRuleViolation extends DomainException {}
