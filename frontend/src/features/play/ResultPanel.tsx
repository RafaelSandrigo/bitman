import type { ReactNode } from 'react'
import { useId } from 'react'
import { formatScore } from './format'

/**
 * Resultado da partida, mostrado pelo hub por cima do jogo: é o Bitman que confirma (ou não) o
 * score, nunca o jogo. Fica no DOM do hub, fora do iframe, então o jogo não consegue imitá-lo
 * nem escondê-lo.
 */
export function ResultPanel({
  title,
  value,
  tone = 'success',
  children,
  actions,
  onClose,
}: {
  title: string
  value?: number
  tone?: 'success' | 'warning' | 'error' | 'pending'
  children?: ReactNode
  actions?: ReactNode
  onClose?: () => void
}) {
  const titleId = useId()
  const border = { success: 'border-emerald-500/50', warning: 'border-amber-500/60', error: 'border-red-500/60', pending: 'border-slate-600' }[tone]

  return (
    <div className="absolute inset-0 z-10 flex items-center justify-center rounded bg-slate-950/80 p-3 backdrop-blur-sm">
      <section
        role="dialog"
        aria-labelledby={titleId}
        className={`bitman-pop relative w-full max-w-sm space-y-3 rounded-xl border bg-slate-900 p-5 text-center shadow-2xl ${border}`}
      >
        {onClose && (
          <button type="button" onClick={onClose} aria-label="Fechar" className="absolute top-2 right-3 text-lg text-slate-400 hover:text-slate-100">
            ×
          </button>
        )}
        <p className="text-xs font-bold tracking-[0.3em] text-emerald-400 uppercase">Bitman</p>
        <h2 id={titleId} className="text-lg font-semibold">
          {title}
        </h2>
        {value !== undefined && (
          <p className="text-4xl font-black text-white tabular-nums" aria-label={`${formatScore(value)} pontos`}>
            {formatScore(value)}
          </p>
        )}
        {children && <div className="space-y-1 text-sm text-slate-300">{children}</div>}
        {actions && <div className="flex flex-col gap-2 pt-1">{actions}</div>}
      </section>
    </div>
  )
}

