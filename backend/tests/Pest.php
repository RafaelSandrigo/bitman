<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Testes de Feature sobem a aplicação Laravel; Unit e Arch são PHP puro.
*/
pest()->extend(TestCase::class)->in('Feature');
