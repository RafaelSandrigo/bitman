import type { ReactNode } from 'react'

export function AuthCard({ title, children, footer }: { title: string; children: ReactNode; footer?: ReactNode }) {
  return (
    <section className="mx-auto max-w-md space-y-6 rounded-lg border border-slate-800 bg-slate-900/50 p-6">
      <h1 className="text-2xl font-bold">{title}</h1>
      {children}
      {footer && <div className="text-sm text-slate-400">{footer}</div>}
    </section>
  )
}
