import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState, type RefObject } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../shared/api/http'
import { isPlayUrl } from '../../shared/config'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { useCurrentUser } from '../auth/session'
import type { PublicGame } from '../catalog/game'
import { startSession, submitScore, type GameSession } from './api'
import { listenToGame } from './bridge'
import { formatScore } from './format'

/**
 * O jogo roda num iframe da origem isolada (ADR 0004):
 * - `sandbox="allow-scripts"` sem `allow-same-origin` → origem opaca, sem cookies nem storage (J1);
 * - sem `allow-top-navigation`, `allow-popups`, `allow-forms` e `allow-modals` (J3);
 * - nenhum recurso liberado via `allow` e sem Referer.
 * A borda e o aviso deixam claro onde começa o conteúdo de terceiros (J3).
 */
function GameFrame({ game, src, frameRef }: { game: PublicGame; src: string; frameRef?: RefObject<HTMLIFrameElement | null> }) {
  return (
    <div className="rounded-lg border-2 border-dashed border-amber-700/70 p-2">
      {isPlayUrl(src) ? (
        <iframe
          ref={frameRef}
          title={`Jogo: ${game.name}`}
          src={src}
          sandbox="allow-scripts"
          referrerPolicy="no-referrer"
          className="aspect-video w-full rounded bg-black"
        />
      ) : (
        <Alert tone="error">Não foi possível carregar o jogo com segurança.</Alert>
      )}
      <p className="mt-2 text-xs text-amber-200/80">
        Conteúdo enviado por {game.author_name}. Ele roda isolado: não acessa a sua conta, a internet nem outras páginas.
      </p>
    </div>
  )
}

/** Sem conta confirmada, joga-se do mesmo jeito, só que sem sessão: nenhum score é registrado. */
function FreePlay({ game, verified }: { game: PublicGame; verified: boolean | null }) {
  return (
    <div className="space-y-3">
      <GameFrame game={game} src={game.play_url} />
      <p className="text-sm text-slate-400">
        {verified === null ? (
          <>
            Você está jogando sem registrar score.{' '}
            <Link to={`/entrar?next=${encodeURIComponent(`/games/${game.slug}`)}`} className="font-semibold text-emerald-400 hover:underline">
              Entre
            </Link>{' '}
            para aparecer no ranking.
          </>
        ) : (
          <>
            Confirme seu e-mail para registrar seus scores no ranking.{' '}
            <Link to="/verificar-email" className="font-semibold text-emerald-400 hover:underline">
              Saiba mais
            </Link>
          </>
        )}
      </p>
    </div>
  )
}

/**
 * Partida valendo score: cada partida é uma sessão nova (token de uso único, I10). O `game-over`
 * chega pela bridge (J4) e é enviado uma única vez; "Jogar de novo" cria outra sessão e recarrega o jogo.
 */
function ScoredPlay({ game }: { game: PublicGame }) {
  const queryClient = useQueryClient()
  const frameRef = useRef<HTMLIFrameElement>(null)
  const [attempt, setAttempt] = useState(0)
  const [invalidScore, setInvalidScore] = useState(false)
  // Uma query (e não um efeito) para que a montagem dupla do StrictMode não abra duas sessões.
  const session = useQuery({
    queryKey: ['game-session', game.slug, attempt],
    queryFn: () => startSession(game.slug),
    staleTime: Infinity,
    gcTime: 0,
    retry: false,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  })
  const submit = useMutation({
    mutationFn: ({ current, score }: { current: GameSession; score: number }) => submitScore(current, score),
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: ['leaderboard', game.slug] })
      void queryClient.invalidateQueries({ queryKey: ['my-scores'] })
    },
  })
  const { mutate, reset } = submit

  useEffect(() => {
    const current = session.data
    if (!current) return
    return listenToGame(
      () => frameRef.current,
      (event) => {
        if (event.kind === 'game-over') mutate({ current, score: event.score })
        if (event.kind === 'invalid-score') setInvalidScore(true)
      },
    )
  }, [session.data, mutate])

  function playAgain() {
    reset()
    setInvalidScore(false)
    setAttempt((value) => value + 1)
  }

  if (session.isPending) return <p className="text-slate-400">Preparando a partida…</p>
  if (session.isError) {
    return (
      <div className="space-y-3">
        <Alert tone="error">{errorMessage(session.error)}</Alert>
        <Button variant="secondary" onClick={playAgain}>
          Tentar de novo
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <GameFrame key={session.data.id} game={game} src={session.data.play_url} frameRef={frameRef} />
      <div aria-live="polite" className="space-y-2">
        {!submit.isIdle || invalidScore ? null : <p className="text-sm text-slate-400">Partida valendo score: ao terminar, o resultado vai para o ranking.</p>}
        {submit.isPending && <p className="text-sm text-slate-300">Enviando o score…</p>}
        {submit.isSuccess && (
          <Alert tone="success">
            Score {formatScore(submit.data.value)} registrado
            {submit.data.position !== null && <> · {submit.data.position}º lugar no ranking</>}.
            {submit.data.personal_best ? ' Novo recorde pessoal!' : submit.data.previous_best !== null && ` Seu recorde continua ${formatScore(submit.data.previous_best)}.`}
          </Alert>
        )}
        {submit.isError && <Alert tone="error">{errorMessage(submit.error)}</Alert>}
        {invalidScore && <Alert tone="error">O jogo enviou um score inválido; nada foi registrado.</Alert>}
      </div>
      <Button variant="secondary" onClick={playAgain}>
        Jogar de novo
      </Button>
    </div>
  )
}

export function GamePlayer({ game }: { game: PublicGame }) {
  const { data: user, isPending } = useCurrentUser()

  if (isPending) return <p className="text-slate-400">Carregando…</p>
  if (!user || !user.email_verified) return <FreePlay game={game} verified={user ? false : null} />
  return <ScoredPlay game={game} />
}
