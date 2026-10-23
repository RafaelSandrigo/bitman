<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Play\Domain\Exceptions\InvalidSessionToken;
use App\Modules\Play\Domain\GuestPlay;
use App\Modules\Play\Domain\PlayRules;
use App\Modules\Play\Domain\ScoreValue;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/** Guarda o score de uma partida de visitante, pendente até uma conta salvá-lo (I10, S1). */
final readonly class SubmitGuestScore
{
    public function __construct(
        private PlayRules $rules,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(string $playId, string $token, ScoreValue $value): GuestPlay
    {
        return $this->db->transaction(function () use ($playId, $token, $value): GuestPlay {
            $play = GuestPlay::query()->lockForUpdate()->find($playId) ?? throw new InvalidSessionToken;
            $play->recordScore($token, $value, $this->rules, CarbonImmutable::now());

            return $play;
        });
    }
}
