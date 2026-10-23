import { useMutation, useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import type { GameSession } from './api'
import { listenToGame } from './bridge'

type RoundOptions<Result> = {
  /** Identifica a rodada no cache (ex.: `['game-session', slug]`); a tentativa é acrescentada. */
  key: readonly unknown[]
  start: () => Promise<GameSession>
  submit: (session: GameSession, score: number) => Promise<Result>
  onSubmitted?: () => void
}

/**
 * Uma rodada = uma sessão nova (token de uso único, I10). O `game-over` chega pela bridge (J4) e é
 * enviado uma única vez; `playAgain` abre outra sessão e recarrega o jogo. Vale para a partida
 * valendo score e para a de visitante, que só mudam o `start` e o `submit`.
 */
export function usePlayRound<Result>({ key, start, submit, onSubmitted }: RoundOptions<Result>) {
  const frameRef = useRef<HTMLIFrameElement>(null)
  const [attempt, setAttempt] = useState(0)
  const [invalidScore, setInvalidScore] = useState(false)
  // Uma query (e não um efeito) para que a montagem dupla do StrictMode não abra duas sessões.
  const session = useQuery({
    queryKey: [...key, attempt],
    queryFn: start,
    staleTime: Infinity,
    gcTime: 0,
    retry: false,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  })
  const submission = useMutation({
    mutationFn: ({ current, score }: { current: GameSession; score: number }) => submit(current, score),
    onSettled: onSubmitted,
  })
  const { mutate, reset } = submission

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

  return { session, submission, invalidScore, frameRef, playAgain }
}
