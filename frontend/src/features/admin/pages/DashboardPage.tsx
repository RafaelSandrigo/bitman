import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { errorMessage } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { getDeveloperRequestSummary } from '../developerRequests'
import { getScoreSummary } from '../scores'
import { getVersionSummary, type VersionSummary } from '../versions'

type Card = { title: string; hint: string; to: string; total: number; unseen: number }

function cards(queues: VersionSummary, readyToPublish: number): Card[] {
  const stopped = queues.fetch_failed.total + queues.validation_failed.total
  return [
    { title: 'Portão 0 — autorizar importação', hint: 'Nada é buscado antes da sua autorização.', to: '/admin/versoes', ...count(queues.import_requested) },
    { title: 'Portão 1 — revisar commit', hint: 'Repositório, commit e arquivos encontrados.', to: '/admin/versoes?estado=awaiting_commit_review', ...count(queues.awaiting_commit_review) },
    { title: 'Portão 2 — revisar arquivos', hint: 'Cada arquivo, com o hash exibido.', to: '/admin/versoes?estado=awaiting_file_review', ...count(queues.awaiting_file_review) },
    { title: 'Prontas para publicar', hint: 'Todos os arquivos aprovados.', to: '/admin/versoes?estado=awaiting_file_review&prontas=1', total: readyToPublish, unseen: 0 },
    {
      title: 'Paradas',
      hint: 'Busca falhou ou reprovada na validação: o dono precisa reenviar.',
      to: '/admin/versoes?estado=validation_failed',
      total: stopped,
      unseen: queues.fetch_failed.new + queues.validation_failed.new,
    },
  ]
}

function count(queue: VersionSummary[keyof VersionSummary]) {
  return { total: queue.total, unseen: queue.new }
}

/** M21: sinalizados esperando a decisão do admin; fora do ranking até lá (M20). */
function ScoresInReviewCard() {
  const summary = useQuery({ queryKey: ['admin', 'scores', 'summary'], queryFn: getScoreSummary, refetchInterval: 60_000 })
  if (!summary.data) return null

  return (
    <li>
      <Link to="/admin/scores" className="block h-full space-y-1 rounded-lg border border-slate-800 bg-slate-900/50 p-4 hover:border-emerald-500">
        <span className="block text-sm text-slate-300">Scores em revisão</span>
        <span className={`block text-3xl font-bold ${summary.data.in_review > 0 ? 'text-amber-300' : ''}`}>{summary.data.in_review}</span>
        <span className="block text-xs text-slate-400">Sinalizados, fora do ranking até você decidir.</span>
      </Link>
    </li>
  )
}

/** M18: pedidos "Seja Desenvolvedor" esperando decisão. */
function DeveloperRequestsCard() {
  const summary = useQuery({ queryKey: ['admin', 'developer-requests', 'summary'], queryFn: getDeveloperRequestSummary, refetchInterval: 60_000 })
  if (!summary.data) return null

  return (
    <li>
      <Link to="/admin/desenvolvedores" className="block h-full space-y-1 rounded-lg border border-slate-800 bg-slate-900/50 p-4 hover:border-emerald-500">
        <span className="block text-sm text-slate-300">Pedidos de developer</span>
        <span className="block text-3xl font-bold">{summary.data.pending}</span>
        <span className="block text-xs text-slate-400">Pessoas pedindo para publicar jogos.</span>
      </Link>
    </li>
  )
}

/** Visão geral do trabalho do admin: as filas por estado, com o que chegou e ninguém viu ainda. */
export function DashboardPage() {
  const summary = useQuery({ queryKey: ['admin', 'versions', 'summary'], queryFn: getVersionSummary, refetchInterval: 30_000 })

  if (summary.isError) return <Alert tone="error">{errorMessage(summary.error)}</Alert>
  if (summary.isPending) return <p className="text-slate-400">Carregando…</p>

  const items = cards(summary.data.queues, summary.data.readyToPublish)
  const pending = items.slice(0, 4).reduce((sum, card) => sum + card.total, 0)

  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-bold">Painel</h1>
      {pending === 0 && <p className="text-slate-400">Nenhuma versão espera por uma decisão.</p>}
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((card) => (
          <li key={card.title}>
            <Link to={card.to} className="block h-full space-y-1 rounded-lg border border-slate-800 bg-slate-900/50 p-4 hover:border-emerald-500">
              <span className="block text-sm text-slate-300">{card.title}</span>
              <span className="block text-3xl font-bold">{card.total}</span>
              {card.unseen > 0 && <span className="text-xs font-semibold text-emerald-400">{card.unseen} {card.unseen === 1 ? 'nova' : 'novas'}</span>}
              <span className="block text-xs text-slate-400">{card.hint}</span>
            </Link>
          </li>
        ))}
        <ScoresInReviewCard />
        <DeveloperRequestsCard />
      </ul>
    </section>
  )
}
