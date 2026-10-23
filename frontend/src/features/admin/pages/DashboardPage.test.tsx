import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../../../test/server'
import { renderAt } from '../../../test/render'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import { queueHandlers } from '../../../test/versions'

const scoreSummary = (inReview = 0) => http.get('/api/v1/admin/scores/summary', () => HttpResponse.json({ data: { in_review: inReview } }))

describe('DashboardPage', () => {
  it('é a página inicial da administração e leva a cada fila de trabalho com o total e os novos', async () => {
    server.use(
      ...sessionHandlers(makeAdmin()).handlers,
      ...queueHandlers(
        {
          import_requested: { total: 3, new: 2 },
          awaiting_commit_review: { total: 1, new: 0 },
          awaiting_file_review: { total: 4, new: 1 },
          fetch_failed: { total: 1, new: 1 },
          validation_failed: { total: 2, new: 0 },
        },
        2,
      ).handlers,
      scoreSummary(),
    )
    const { router } = renderAt('/admin')

    const gate0 = await screen.findByRole('link', { name: /Portão 0/ })
    expect(router.state.location.pathname).toBe('/admin')
    expect(within(gate0).getByText('3')).toBeInTheDocument()
    expect(within(gate0).getByText('2 novas')).toBeInTheDocument()
    expect(gate0).toHaveAttribute('href', '/admin/versoes')
    expect(screen.getByRole('link', { name: /Portão 1/ })).toHaveAttribute('href', '/admin/versoes?estado=awaiting_commit_review')
    expect(screen.getByRole('link', { name: /Portão 2/ })).toHaveAttribute('href', '/admin/versoes?estado=awaiting_file_review')
    const ready = screen.getByRole('link', { name: /Prontas para publicar/ })
    expect(within(ready).getByText('2')).toBeInTheDocument()
    expect(ready).toHaveAttribute('href', '/admin/versoes?estado=awaiting_file_review&prontas=1')
    // Busca falha e reprovadas na validação juntas: o que parou e espera o dono ou o admin.
    expect(within(screen.getByRole('link', { name: /Paradas/ })).getByText('3')).toBeInTheDocument()
  })

  it('avisa quando não há nada pendente', async () => {
    server.use(...sessionHandlers(makeAdmin()).handlers, ...queueHandlers().handlers, scoreSummary())
    renderAt('/admin')

    expect(await screen.findByText('Nenhuma versão espera por uma decisão.')).toBeInTheDocument()
  })

  it('M21: mostra os scores sinalizados em revisão e leva à moderação', async () => {
    server.use(...sessionHandlers(makeAdmin()).handlers, ...queueHandlers().handlers, scoreSummary(4))
    renderAt('/admin')

    const card = await screen.findByRole('link', { name: /Scores em revisão/ }, { timeout: 3000 })
    expect(within(card).getByText('4')).toBeInTheDocument()
    expect(card).toHaveAttribute('href', '/admin/scores')
  })
})
