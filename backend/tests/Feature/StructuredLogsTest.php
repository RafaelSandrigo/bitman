<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;

/* Logs em JSON estruturado no canal de produção (docs/convencoes.md). */

it('o canal stderr formata em JSON, com o contexto como campos', function (): void {
    /** @var Logger $logger */
    $logger = app('log')->channel('stderr')->getLogger();

    expect($logger->getHandlers()[0]->getFormatter())->toBeInstanceOf(JsonFormatter::class);
});
