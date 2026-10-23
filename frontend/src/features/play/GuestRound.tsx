import { useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { linkButton } from '../../shared/ui/linkStyles'
import type { PublicGame } from '../catalog/game'
import { startGuestPlay, submitGuestScore, type GameSession, type GuestScore } from './api'
import { formatDateTime } from './format'
import { GameFrame } from './GameFrame'
import { savePendingScore } from './pendingScore'
import { ResultPanel } from './ResultPanel'
import { usePlayRound } from './usePlayRound'

/** Sem conta (`null`) ou com o e-mail ainda não confirmado (`false`). */
type Visitor = null | false

/**
 * Guarda o score pendente no servidor e, neste navegador, o que é preciso para salvá-lo depois
 * do login (pendingScore.ts). Quem salva é o PendingScoreSaver, em qualquer página.
 */
async function keepScore(game: PublicGame, play: GameSession, score: number): Promise<GuestScore> {
  const kept = await submitGuestScore(play, score)
  savePendingScore({ id: play.id, token: play.token, slug: game.slug, gameName: game.name, value: kept.value, claimExpiresAt: kept.claim_expires_at })
  return kept
}

/** CTA: entrar volta para o jogo; o cadastro passa pela confirmação do e-mail, e o score é salvo depois dela. */
function SaveCallToAction({ game, visitor }: { game: PublicGame; visitor: Visitor }) {
  if (visitor === false) {
    return (
      <Link to="/verificar-email" className={linkButton.primary}>
        Confirmar e-mail e salvar
      </Link>
    )
  }
  return (
    <>
      <Link to="/cadastro" className={linkButton.primary}>
        Criar conta e salvar
      </Link>
      <Link to={`/entrar?next=${encodeURIComponent(`/games/${game.slug}`)}`} className={linkButton.secondary}>
        Já tenho conta: entrar e salvar
      </Link>
    </>
  )
}

function KeptMessage({ visitor, kept }: { visitor: Visitor; kept: GuestScore }) {
  return (
    <>
      <p>
        {visitor === null
          ? 'Você não está logado. Crie sua conta ou entre para salvar este score no ranking.'
          : 'Confirme seu e-mail para salvar este score no ranking. Assim que confirmar, ele é salvo sozinho.'}
      </p>
      <p className="text-xs text-slate-400">Guardamos este score neste navegador até {formatDateTime(kept.claim_expires_at)}.</p>
    </>
  )
}

type Round = ReturnType<typeof usePlayRound<GuestScore>>

function GuestResult({ game, visitor, round, onRestart, onClose }: { game: PublicGame; visitor: Visitor; round: Pick<Round, 'submission' | 'invalidScore'>; onRestart: () => void; onClose: () => void }) {
  const { submission, invalidScore } = round
  const again = (
    <Button variant="secondary" onClick={onRestart}>
      Jogar de novo sem salvar
    </Button>
  )

  if (invalidScore) return <ResultPanel tone="error" title="O jogo enviou um score inválido; nada foi guardado." actions={again} onClose={onClose} />
  if (submission.isPending) return <ResultPanel tone="pending" title="Guardando seu score…" value={submission.variables.score} />
  if (submission.isError) {
    return (
      <ResultPanel tone="error" title="Score não guardado" value={submission.variables.score} actions={again} onClose={onClose}>
        <p>{errorMessage(submission.error)}</p>
      </ResultPanel>
    )
  }
  if (!submission.isSuccess) return null

  const actions = (
    <>
      <SaveCallToAction game={game} visitor={visitor} />
      {again}
    </>
  )
  return (
    <ResultPanel tone="warning" title="Fim de jogo!" value={submission.data.value} onClose={onClose} actions={actions}>
      <KeptMessage visitor={visitor} kept={submission.data} />
    </ResultPanel>
  )
}

function GuestHint({ game, visitor }: { game: PublicGame; visitor: Visitor }) {
  if (visitor === false) {
    return (
      <p className="text-sm text-slate-400">
        Confirme seu e-mail para registrar seus scores no ranking.{' '}
        <Link to="/verificar-email" className="font-semibold text-emerald-400 hover:underline">
          Saiba mais
        </Link>
      </p>
    )
  }
  return (
    <p className="text-sm text-slate-400">
      Você está jogando sem conta.{' '}
      <Link to={`/entrar?next=${encodeURIComponent(`/games/${game.slug}`)}`} className="font-semibold text-emerald-400 hover:underline">
        Entre
      </Link>{' '}
      ou{' '}
      <Link to="/cadastro" className="font-semibold text-emerald-400 hover:underline">
        crie sua conta
      </Link>{' '}
      para aparecer no ranking. Se jogar antes, o score fica guardado para você salvar.
    </p>
  )
}

/**
 * Partida de visitante: segue as regras da partida (sessão, token, duração mínima), mas o score
 * fica pendente até uma conta confirmada salvá-lo (A5).
 */
export function GuestRound({ game, visitor }: { game: PublicGame; visitor: Visitor }) {
  const [closed, setClosed] = useState(false)
  const round = usePlayRound({
    key: ['guest-play', game.slug],
    start: () => startGuestPlay(game.slug),
    submit: (play, score) => keepScore(game, play, score),
  })
  const restart = () => {
    setClosed(false)
    round.playAgain()
  }

  if (round.session.isPending) return <p className="text-slate-400">Preparando a partida…</p>
  if (round.session.isError) {
    return (
      <div className="space-y-3">
        <Alert tone="error">{errorMessage(round.session.error)}</Alert>
        <Button variant="secondary" onClick={restart}>
          Tentar de novo
        </Button>
      </div>
    )
  }

  const panel = <GuestResult game={game} visitor={visitor} round={round} onRestart={restart} onClose={() => setClosed(true)} />
  return (
    <div className="space-y-3">
      <GameFrame key={round.session.data.id} game={game} src={round.session.data.play_url} frameRef={round.frameRef} overlay={closed ? null : panel} />
      <GuestHint game={game} visitor={visitor} />
      <Button variant="secondary" onClick={restart}>
        Jogar de novo
      </Button>
    </div>
  )
}
