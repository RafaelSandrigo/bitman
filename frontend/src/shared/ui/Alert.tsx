import type { ReactNode } from 'react'

const tones = {
  error: 'border-red-800 bg-red-950/50 text-red-200',
  success: 'border-emerald-800 bg-emerald-950/50 text-emerald-200',
  info: 'border-slate-700 bg-slate-900 text-slate-200',
}

export function Alert({ tone, children }: { tone: keyof typeof tones; children: ReactNode }) {
  return (
    <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded-md border px-4 py-3 text-sm ${tones[tone]}`}>
      {children}
    </div>
  )
}
