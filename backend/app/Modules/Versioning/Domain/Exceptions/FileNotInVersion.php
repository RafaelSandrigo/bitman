<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use LogicException;

/** Programação: o arquivo sempre é buscado pela versão (a rota não deixa misturar). */
final class FileNotInVersion extends LogicException {}
