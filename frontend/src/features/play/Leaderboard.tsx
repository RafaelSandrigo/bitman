import { useQuery } from '@tanstack/react-query'
import { errorMessage } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { getLeaderboard } from './api'
import { formatDateTime, formatScore } from './format'

/** Limitação explícita dos scores (docs/seguranca.md#limitação-explícita-scores). */
export function ScoreDisclaimer() {
  return (
    <p className="text-xs text-slate-500">
      Os scores são calculados pelo próprio jogo, no navegador de quem joga, e podem ser manipulados. O Bitman dificulta e sinaliza
      abusos, e a administração pode ocultar scores suspeitos, mas não há como garantir que todo score é legítimo.
    </p>
  )
}

/** Melhor score de cada jogador, em todas as versões (a versão aparece ao lado). */
export function Leaderboard({ slug }: { slug: string }) {
  const board = useQuery({ queryKey: ['leaderboard', slug], queryFn: () => getLeaderboard(slug) })

  return (
    <section aria-labelledby="ranking" className="space-y-3">
      <h2 id="ranking" className="text-xl font-semibold">
        Ranking
      </h2>
      <ScoreDisclaimer />
      {board.isError && <Alert tone="error">{errorMessage(board.error)}</Alert>}
      {board.isPending && <p className="text-slate-400">Carregando…</p>}
      {board.data?.length === 0 && <p className="text-slate-400">Ninguém pontuou ainda. Seja o primeiro!</p>}
      {board.data && board.data.length > 0 && (
        <table className="w-full text-left text-sm">
          <thead className="text-xs uppercase text-slate-500">
            <tr>
              <th scope="col" className="py-2 pr-2">
                Posição
              </th>
              <th scope="col" className="py-2 pr-2">
                Jogador
              </th>
              <th scope="col" className="py-2 pr-2 text-right">
                Score
              </th>
              <th scope="col" className="py-2 pr-2">
                Versão
              </th>
              <th scope="col" className="py-2">
                Quando
              </th>
            </tr>
          </thead>
          <tbody>
            {board.data.map((entry) => (
              <tr key={`${entry.position}-${entry.player_name}-${entry.achieved_at}`} className={`border-t border-slate-800 ${entry.is_me ? 'bg-emerald-950/40 font-semibold' : ''}`}>
                <td className="py-2 pr-2">{entry.position}º</td>
                <td className="py-2 pr-2">
                  {entry.player_name}
                  {entry.is_me && <span className="ml-1 text-xs text-emerald-400">(você)</span>}
                </td>
                <td className="py-2 pr-2 text-right tabular-nums">{formatScore(entry.value)}</td>
                <td className="py-2 pr-2 text-slate-400">v{entry.version_number}</td>
                <td className="py-2 text-slate-400">{formatDateTime(entry.achieved_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
