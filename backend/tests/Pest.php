<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Testes de Feature sobem a aplicação Laravel no banco bitman_test (cada teste em transação);
| Unit e Arch são PHP puro.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
