import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import { makeVersionDetail } from '../../../test/versions'
import type { VersionDetail } from '../versions'

function serve(version: VersionDetail) {
  server.use(...sessionHandlers(makeAdmin()).handlers, http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })))
}

describe('VersionPage', () => {
  it('mostra o commit, os arquivos na quarentena e a ausência de violações', async () => {
    const version = makeVersionDetail({
      files: [
        { id: '1', path: 'game.js', sha256: 'b'.repeat(64), size: 2048, git_blob_sha: 'c'.repeat(40), change_type: 'added', review_status: 'pending' },
      ],
    })
    serve(version)
    renderAt(`/admin/versoes/${version.id}`)

    expect(await screen.findByRole('heading', { name: 'Snake · versão 1' })).toBeInTheDocument()
    expect(screen.getByText('a'.repeat(40))).toBeInTheDocument()
    expect(screen.getByText(/Autorizado por Rafael/)).toBeInTheDocument()
    expect(screen.getByText('Nenhuma violação.')).toBeInTheDocument()

    const files = screen.getByRole('region', { name: 'Arquivos da versão' })
    expect(within(files).getByText('✓ game.js')).toBeInTheDocument()
    expect(within(files).getByText('2 KiB')).toBeInTheDocument()
    expect(within(files).getByText('Novo')).toBeInTheDocument()
    expect(within(files).getByText('b'.repeat(64))).toBeInTheDocument()
  })

  it('versão bloqueada: lista as violações e os ignorados como texto, nunca como HTML (J5)', async () => {
    const hostile = '<img src=x onerror="alert(1)">.js'
    const version = makeVersionDetail({
      blocked: true,
      report: {
        blocked: true,
        violations: [
          { code: 'symlink', message: 'O repositório tem um symlink em game.js.', path: 'game.js' },
          { code: 'unsafe_path', message: `Nome de arquivo não permitido: ${hostile}.`, path: hostile },
        ],
        ignored: [{ path: hostile, mode: '100644', size: null }],
        ignored_total: 1,
      },
    })
    serve(version)
    const { container } = renderAt(`/admin/versoes/${version.id}`)

    const report = await screen.findByRole('region', { name: 'Política de repositório' })
    expect(within(report).getByText('Versão bloqueada: o commit não poderá ser aprovado.')).toBeInTheDocument()
    expect(within(report).getByText(/O repositório tem um symlink em game.js./)).toBeInTheDocument()
    expect(within(report).getByText(`✗ ${hostile} · 100644 · acima do limite`)).toBeInTheDocument()
    expect(container.querySelector('img')).toBeNull()
  })

  it('aguardando autorização: oferece a decisão do Portão 0', async () => {
    const version = makeVersionDetail({ status: 'import_requested', commit_sha: null, report: null, import_decision: null, fetched_at: null })
    serve(version)
    renderAt(`/admin/versoes/${version.id}`)

    expect(await screen.findByRole('button', { name: 'Autorizar importação' })).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Arquivos da versão' })).not.toBeInTheDocument()
  })

  it('recusa resposta com commit fora do formato (zero trust)', async () => {
    const version = makeVersionDetail({ commit_sha: 'HEAD' })
    serve(version)
    renderAt(`/admin/versoes/${version.id}`)

    expect(await screen.findByRole('alert', {}, { timeout: 3000 })).toHaveTextContent('Algo deu errado')
  })
})
