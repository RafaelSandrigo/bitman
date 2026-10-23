import { useCurrentUser } from '../auth/session'
import type { PublicGame } from '../catalog/game'
import { GuestRound } from './GuestRound'
import { ScoredPlay } from './ScoredPlay'

/** Conta confirmada joga valendo score; os demais jogam como visitante e podem salvar o score depois (A5). */
export function GamePlayer({ game }: { game: PublicGame }) {
  const { data: user, isPending } = useCurrentUser()

  if (isPending) return <p className="text-slate-400">Carregando…</p>
  if (!user || !user.email_verified) return <GuestRound game={game} visitor={user ? false : null} />
  return <ScoredPlay game={game} />
}
