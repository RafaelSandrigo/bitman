import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { errorMessage } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import type { PublicGame } from '../catalog/game'
import { startSession, submitScore, type ScoreResult } from './api'
import { formatScore } from './format'
import { GameFrame } from './GameFrame'
import { ResultPanel } from './ResultPanel'
import { usePlayRound } from './usePlayRound'

function scrollToRanking() {
  document.getElementById('ranking')?.scrollIntoView({ behavior: 'smooth' })
}

function RecordSummary({ result }: { result: ScoreResult }) {
  return (
    <>
      {result.position !== null && <p>{result.position}º lugar no ranking</p>}
      {result.personal_best ? (
        <p className="font-semibold text-amber-300">Novo recorde pessoal!</p>
      ) : (
        result.previous_best !== null && <p>Seu recorde continua {formatScore(result.previous_best)}.</p>
      )}
    </>
  )
}

type Round = { submission: ReturnType<typeof usePlayRound<ScoreResult>>['submission']; invalidScore: boolean }

/** O painel do Bitman para cada estado do envio; nada antes do `game-over`. */
function ScoredResult({ round, onRestart, onClose }: { round: Round; onRestart: () => void; onClose: () => void }) {
  const { submission, invalidScore } = round
  const again = <Button onClick={onRestart}>Jogar de novo</Button>

  if (invalidScore) return <ResultPanel tone="error" title="O jogo enviou um score inválido; nada foi registrado." actions={again} onClose={onClose} />
  if (submission.isPending) return <ResultPanel tone="pending" title="Salvando seu score…" value={submission.variables.score} />
  if (submission.isError) {
    return (
      <ResultPanel tone="error" title="Score não registrado" value={submission.variables.score} actions={again} onClose={onClose}>
        <p>{errorMessage(submission.error)}</p>
      </ResultPanel>
    )
  }
  if (!submission.isSuccess) return null

  const actions = (
    <>
      {again}
      <Button variant="secondary" onClick={scrollToRanking}>
        Ver ranking
      </Button>
    </>
  )
  return (
    <ResultPanel title="Score registrado!" value={submission.data.value} onClose={onClose} actions={actions}>
      <RecordSummary result={submission.data} />
    </ResultPanel>
  )
}

/** Partida valendo score (conta confirmada): o painel do Bitman confirma o registro no ranking. */
export function ScoredPlay({ game }: { game: PublicGame }) {
  const queryClient = useQueryClient()
  const [closed, setClosed] = useState(false)
  const { session, submission, invalidScore, frameRef, playAgain } = usePlayRound({
    key: ['game-session', game.slug],
    start: () => startSession(game.slug),
    submit: submitScore,
    onSubmitted: () => {
      void queryClient.invalidateQueries({ queryKey: ['leaderboard', game.slug] })
      void queryClient.invalidateQueries({ queryKey: ['my-scores'] })
    },
  })

  function restart() {
    setClosed(false)
    playAgain()
  }

  if (session.isPending) return <p className="text-slate-400">Preparando a partida…</p>
  if (session.isError) {
    return (
      <div className="space-y-3">
        <Alert tone="error">{errorMessage(session.error)}</Alert>
        <Button variant="secondary" onClick={restart}>
          Tentar de novo
        </Button>
      </div>
    )
  }

  const panel = <ScoredResult round={{ submission, invalidScore }} onRestart={restart} onClose={() => setClosed(true)} />

  return (
    <div className="space-y-3">
      <GameFrame key={session.data.id} game={game} src={session.data.play_url} frameRef={frameRef} overlay={closed ? null : panel} />
      <p className="text-sm text-slate-400">Partida valendo score: ao terminar, o resultado vai para o ranking.</p>
      <Button variant="secondary" onClick={restart}>
        Jogar de novo
      </Button>
    </div>
  )
}
