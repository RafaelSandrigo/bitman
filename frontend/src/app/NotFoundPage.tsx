import { Link } from 'react-router'

export function NotFoundPage() {
  return (
    <section className="space-y-4">
      <h1 className="text-2xl font-semibold">Página não encontrada</h1>
      <Link to="/" className="text-emerald-400 underline">
        Voltar para o início
      </Link>
    </section>
  )
}
