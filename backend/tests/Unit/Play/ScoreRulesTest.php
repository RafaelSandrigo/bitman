<?php

declare(strict_types=1);

use App\Modules\Play\Domain\AnomalyPolicy;
use App\Modules\Play\Domain\Exceptions\InvalidScore;
use App\Modules\Play\Domain\ScoreStatistics;
use App\Modules\Play\Domain\ScoreValue;
use App\Modules\Play\Domain\SessionToken;

/*
| S2: faixa do score e sinalização de anomalias. S1: token de uso único guardado só como hash.
*/

it('aceita os extremos da faixa 0..Number.MAX_SAFE_INTEGER', function (int $value): void {
    expect(ScoreValue::fromInt($value)->value)->toBe($value);
})->with([0, 1, 9_007_199_254_740_991]);

it('rejeita fora da faixa, sem truncar', function (int $value): void {
    expect(fn () => ScoreValue::fromInt($value))->toThrow(InvalidScore::class);
})->with([-1, 9_007_199_254_740_992, PHP_INT_MAX]);

it('ScoreValue::MAX é o maior inteiro exato do JavaScript', function (): void {
    expect(ScoreValue::MAX)->toBe(2 ** 53 - 1);
});

it('sem amostra mínima, nada é sinalizado', function (): void {
    $policy = new AnomalyPolicy(factor: 3.0, minSample: 20);

    expect($policy->flagReason(ScoreValue::fromInt(1_000_000), new ScoreStatistics(19, 10)))->toBeNull()
        ->and($policy->flagReason(ScoreValue::fromInt(1_000_000), new ScoreStatistics(0, null)))->toBeNull();
});

it('sinaliza acima do p99 × fator, com o motivo', function (): void {
    $policy = new AnomalyPolicy(factor: 3.0, minSample: 20);
    $statistics = new ScoreStatistics(50, 100);

    expect($policy->flagReason(ScoreValue::fromInt(300), $statistics))->toBeNull()
        ->and($policy->flagReason(ScoreValue::fromInt(301), $statistics))->toBe('Acima de 3 × o p99 do jogo (100, com 50 scores).');
});

it('com p99 zero, o limite parte de 1 (não sinaliza todo score positivo)', function (): void {
    $policy = new AnomalyPolicy(factor: 3.0, minSample: 20);

    expect($policy->flagReason(ScoreValue::fromInt(3), new ScoreStatistics(20, 0)))->toBeNull()
        ->and($policy->flagReason(ScoreValue::fromInt(4), new ScoreStatistics(20, 0)))->not->toBeNull();
});

it('o token tem 256 bits aleatórios e só o SHA-256 dele é guardado', function (): void {
    $a = SessionToken::issue();
    $b = SessionToken::issue();

    expect($a->plain)->toMatch('/^[0-9a-f]{64}$/')
        ->and($a->hash)->toBe(hash('sha256', $a->plain))
        ->and($a->hash)->not->toBe($a->plain)
        ->and($a->plain)->not->toBe($b->plain);
});
