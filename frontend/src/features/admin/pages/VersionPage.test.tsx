import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeAdmin, sessionHandlers } from '../../../test/session'
import { makeVersionDetail, makeVersionFile, queueHandlers } from '../../../test/versions'
import type { VersionDetail } from '../versions'

function serve(version: VersionDetail) {
  server.use(...sessionHandlers(makeAdmin()).handlers, http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })))
}

describe('VersionPage', () => {
  it('mostra o commit, os arquivos na quarentena e a ausência de violações', async () => {
    const version = makeVersionDetail({
      files: [makeVersionFile()],
    })
    serve(version)
    renderAt(`/admin/versoes/${version.id}`)

    expect(await screen.findByRole('heading', { name: 'Snake · versão 1' })).toBeInTheDocument()
    expect(screen.getByText('a'.repeat(40))).toBeInTheDocument()
    expect(screen.getByText(/Autorizado por Rafael/)).toBeInTheDocument()
    expect(screen.getByText('Nenhuma violação.')).toBeInTheDocument()

    const files = screen.getByRole('region', { name: 'Arquivos da versão' })
    expect(within(files).getByText('✓ game.js')).toBeInTheDocument()
    expect(within(files).getByText(/2 KiB · Novo/)).toBeInTheDocument()
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

  describe('Portão 1', () => {
    it('aprova o commit', async () => {
      let version = makeVersionDetail()
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.post(`/api/v1/admin/versions/${version.id}/approve-commit`, () => {
          version = { ...version, status: 'awaiting_file_review', commit_decision: { decided_by: { id: '1', name: 'Rafael' }, decided_at: '2026-10-12T13:00:00+00:00', rejection_reason: null } }
          const { game: _g, requested_by: _r, import_decision: _i, commit_decision: _c, rejection: _x, report: _p, files: _f, ...data } = version
          return HttpResponse.json({ data })
        }),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      const gate = await screen.findByRole('region', { name: 'Portão 1' })
      await user.click(within(gate).getByRole('button', { name: 'Aprovar commit' }))

      expect(await screen.findByText(/Commit aprovado por Rafael/)).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: 'Portão 2 — revisão dos arquivos' })).toBeInTheDocument()
    })

    it('rejeita o commit com motivo', async () => {
      const version = makeVersionDetail()
      const bodies: unknown[] = []
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.post(`/api/v1/admin/versions/${version.id}/reject-commit`, async ({ request }) => {
          bodies.push(await request.json())
          return HttpResponse.json({ message: 'Dados inválidos.', errors: { reason: ['Informe o motivo, de 3 a 1000 caracteres.'] } }, { status: 422 })
        }),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Rejeitar commit' }))
      await user.type(screen.getByLabelText('Motivo da rejeição'), 'ok')
      await user.click(screen.getByRole('button', { name: 'Confirmar rejeição' }))

      expect(await screen.findByText('Informe o motivo, de 3 a 1000 caracteres.')).toBeInTheDocument()
      expect(bodies).toEqual([{ reason: 'ok' }])
    })
  })

  describe('Portão 2', () => {
    function reviewing(file = makeVersionFile()) {
      return makeVersionDetail({
        status: 'awaiting_file_review',
        commit_decision: { decided_by: { id: '1', name: 'Rafael' }, decided_at: '2026-10-12T13:00:00+00:00', rejection_reason: null },
        files: [file],
      })
    }

    it('mostra o conteúdo hostil como texto e aprova o hash exibido (J5, I5)', async () => {
      const file = makeVersionFile()
      const version = reviewing(file)
      const hostile = '<script>window.pwned = true</script>\n<img src=x onerror="window.pwned = true">'
      const bodies: unknown[] = []
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.get(`/api/v1/admin/versions/${version.id}/files/${file.id}/content`, () =>
          HttpResponse.json({ data: { id: file.id, path: 'game.js', sha256: file.sha256, size: hostile.length, content: hostile } }),
        ),
        http.post(`/api/v1/admin/versions/${version.id}/files/${file.id}/review`, async ({ request }) => {
          bodies.push(await request.json())
          const { game: _g, requested_by: _r, import_decision: _i, commit_decision: _c, rejection: _x, report: _p, files: _f, ...data } = version
          return HttpResponse.json({ data })
        }),
      )
      const { user, container } = renderAt(`/admin/versoes/${version.id}`)

      expect(screen.queryByRole('button', { name: 'Aprovar game.js' })).not.toBeInTheDocument()
      await user.click(await screen.findByRole('button', { name: 'Ver conteúdo' }))

      const viewer = await screen.findByLabelText('Conteúdo de game.js')
      expect(viewer).toHaveTextContent('<script>window.pwned = true</script>')
      expect(container.querySelector('script, img')).toBeNull()
      expect((window as { pwned?: boolean }).pwned).toBeUndefined()

      await user.click(screen.getByRole('button', { name: 'Aprovar game.js' }))
      await waitFor(() => expect(bodies).toEqual([{ decision: 'approved', sha256: file.sha256 }]))
    })

    it('rejeita o arquivo com motivo, avisando que a versão inteira é rejeitada', async () => {
      const file = makeVersionFile()
      const version = reviewing(file)
      const bodies: unknown[] = []
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.get(`/api/v1/admin/versions/${version.id}/files/${file.id}/content`, () =>
          HttpResponse.json({ data: { id: file.id, path: 'game.js', sha256: file.sha256, size: 3, content: 'x()' } }),
        ),
        http.post(`/api/v1/admin/versions/${version.id}/files/${file.id}/review`, async ({ request }) => {
          bodies.push(await request.json())
          const { game: _g, requested_by: _r, import_decision: _i, commit_decision: _c, rejection: _x, report: _p, files: _f, ...data } = version
          return HttpResponse.json({ data: { ...data, status: 'rejected' } })
        }),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Ver conteúdo' }))
      expect(await screen.findByText('Rejeitar um arquivo rejeita a versão inteira: todos são obrigatórios.')).toBeInTheDocument()
      await user.click(screen.getByRole('button', { name: 'Rejeitar game.js' }))
      await user.type(screen.getByLabelText('Motivo da rejeição'), 'Minerador de cripto.')
      await user.click(screen.getByRole('button', { name: 'Confirmar rejeição' }))

      await waitFor(() => expect(bodies).toEqual([{ decision: 'rejected', sha256: file.sha256, reason: 'Minerador de cripto.' }]))
    })

    it('M7: arquivo modificado abre no diff contra a versão no ar; o arquivo completo fica a um clique', async () => {
      const file = makeVersionFile({ change_type: 'modified' })
      const version = reviewing(file)
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.get(`/api/v1/admin/versions/${version.id}/files/${file.id}/content`, () =>
          HttpResponse.json({
            data: {
              id: file.id,
              path: 'game.js',
              sha256: file.sha256,
              size: 22,
              content: 'let a = 1;\nlet b = 3;\n',
              previous: { version_number: 1, content: 'let a = 1;\nlet b = 2;\n' },
              previous_unavailable: false,
            },
          }),
        ),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Ver conteúdo' }))
      const diff = await screen.findByLabelText('Mudanças em game.js')
      expect(diff.querySelector('[data-kind="removed"]')).toHaveTextContent('let b = 2;')
      expect(diff.querySelector('[data-kind="added"]')).toHaveTextContent('let b = 3;')
      expect(screen.getByText('2 linhas mudaram desde a versão 1 (no ar).')).toBeInTheDocument()

      await user.click(screen.getByRole('tab', { name: 'Arquivo completo' }))
      expect(screen.getByLabelText('Conteúdo de game.js')).toBeInTheDocument()
    })

    it('marca caracteres invisíveis e bidirecionais (Trojan Source)', async () => {
      const file = makeVersionFile()
      const version = reviewing(file)
      const content = 'if (isAdmin) { /* ‮ } ⁦ if (true) { ⁩ */ }​'
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.get(`/api/v1/admin/versions/${version.id}/files/${file.id}/content`, () =>
          HttpResponse.json({ data: { id: file.id, path: 'game.js', sha256: file.sha256, size: content.length, content } }),
        ),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Ver conteúdo' }))

      expect(await screen.findByText(/caracteres invisíveis ou bidirecionais/)).toBeInTheDocument()
      const viewer = screen.getByLabelText('Conteúdo de game.js')
      expect(viewer).toHaveTextContent('⟦U+202E⟧')
      expect(viewer).toHaveTextContent('⟦U+2066⟧')
      expect(viewer).toHaveTextContent('⟦U+200B⟧')
      expect(viewer.textContent).not.toMatch(/[‮⁦⁩​]/)
    })

    it('conteúdo com hash diferente do arquivo não pode ser decidido', async () => {
      const file = makeVersionFile()
      const version = reviewing(file)
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.get(`/api/v1/admin/versions/${version.id}/files/${file.id}/content`, () =>
          HttpResponse.json({ data: { id: file.id, path: 'game.js', sha256: 'f'.repeat(64), size: 3, content: 'x()' } }),
        ),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Ver conteúdo' }))

      expect(await screen.findByText('O conteúdo recebido não corresponde ao hash do arquivo. Não decida sobre ele.')).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Aprovar game.js' })).not.toBeInTheDocument()
    })

    it('mostra o histórico, a aprovação anterior (sem herdar) e o progresso', async () => {
      const approved = makeVersionFile({
        path: 'index.html',
        review_status: 'approved',
        reviews: [
          { id: 'r1', decision: 'approved', sha256: 'b'.repeat(64), reason: null, reviewer: { id: '1', name: 'Rafael' }, decided_at: '2026-10-12T13:00:00+00:00' },
        ],
      })
      const unchanged = makeVersionFile({
        path: 'style.css',
        change_type: 'unchanged',
        previous_approval: {
          id: 'r0',
          decision: 'approved',
          sha256: 'b'.repeat(64),
          reason: null,
          reviewer: { id: '1', name: 'Ada' },
          decided_at: '2026-10-01T13:00:00+00:00',
          version_number: 1,
        },
      })
      const version = { ...reviewing(), files: [approved, unchanged] }
      serve(version)
      renderAt(`/admin/versoes/${version.id}`)

      expect(await screen.findByText(/1 de 2 aprovados/)).toBeInTheDocument()
      const history = screen.getByRole('list', { name: 'Decisões sobre index.html' })
      expect(within(history).getByText(/Aprovado por Rafael/)).toBeInTheDocument()
      const style = screen.getByRole('listitem', { name: 'style.css' })
      expect(within(style).getByText(/aprovado anteriormente por Ada .* \(versão 1\)\. Mesmo assim, precisa de nova aprovação\./)).toBeInTheDocument()
      expect(within(style).getByText('Pendente')).toBeInTheDocument()
    })

    it('todos aprovados: avisa que a versão está pronta para a publicação', async () => {
      const version = reviewing(makeVersionFile({ review_status: 'approved' }))
      serve(version)
      renderAt(`/admin/versoes/${version.id}`)

      expect(await screen.findByText(/Todos os arquivos aprovados/)).toBeInTheDocument()
    })

    it('versão rejeitada mostra o motivo e permite pedir nova importação', async () => {
      const version = makeVersionDetail({
        status: 'rejected',
        rejection: { decided_by: { id: '1', name: 'Rafael' }, decided_at: '2026-10-12T14:00:00+00:00', rejection_reason: 'Arquivo game.js rejeitado: minerador.' },
        files: [makeVersionFile({ review_status: 'rejected' })],
      })
      serve(version)
      renderAt(`/admin/versoes/${version.id}`)

      expect(await screen.findByText('Motivo: Arquivo game.js rejeitado: minerador.')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Pedir nova importação' })).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Ver conteúdo' })).not.toBeInTheDocument()
    })
  })

  it('abrir uma versão nova a marca como vista', async () => {
    const version = makeVersionDetail({ is_new: true, status: 'validation_failed' })
    const queues = queueHandlers()
    server.use(...queues.handlers)
    serve(version)
    renderAt(`/admin/versoes/${version.id}`)

    await waitFor(() => expect(queues.seen).toEqual([{ versions: [{ id: version.id, status: 'validation_failed' }] }]))
  })

  describe('publicação (Fase 5)', () => {
    const approvedFile = () => makeVersionFile({ review_status: 'approved' })
    function strip(version: ReturnType<typeof makeVersionDetail>) {
      const { game: _g, requested_by: _r, import_decision: _i, commit_decision: _c, rejection: _x, publication: _p, withdrawal: _w, play_url: _u, report: _rp, files: _f, ...data } = version
      return data
    }

    it('com todos os arquivos aprovados, publica a versão', async () => {
      let version = makeVersionDetail({ status: 'awaiting_file_review', files: [approvedFile()] })
      let published = 0
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.post(`/api/v1/admin/versions/${version.id}/publish`, () => {
          published++
          version = {
            ...version,
            status: 'published',
            public_id: '0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f',
            play_url: 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html',
            publication: { decided_by: { id: '1', name: 'Rafael' }, decided_at: '2026-10-12T15:00:00+00:00', rejection_reason: null },
          }
          return HttpResponse.json({ data: strip(version) })
        }),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Publicar versão' }))

      expect(await screen.findByText(/Publicada por Rafael/)).toBeInTheDocument()
      expect(screen.getByText('https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html')).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Abrir a página do jogo' })).toHaveAttribute('href', '/games/snake')
      expect(published).toBe(1)
    })

    it('mostra o erro de integridade do servidor (I6) sem publicar', async () => {
      const version = makeVersionDetail({ status: 'awaiting_file_review', files: [approvedFile()] })
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.post(`/api/v1/admin/versions/${version.id}/publish`, () =>
          HttpResponse.json({ message: 'O conteúdo de game.js não confere com o hash aprovado. Nada foi publicado.' }, { status: 409 }),
        ),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Publicar versão' }))

      expect(await screen.findByText('O conteúdo de game.js não confere com o hash aprovado. Nada foi publicado.')).toBeInTheDocument()
    })

    it('sem todos aprovados, não há botão de publicar', async () => {
      const version = makeVersionDetail({ status: 'awaiting_file_review', files: [approvedFile(), makeVersionFile({ path: 'style.css' })] })
      serve(version)
      renderAt(`/admin/versoes/${version.id}`)

      expect(await screen.findByText(/1 de 2 aprovados/)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Publicar versão' })).not.toBeInTheDocument()
    })

    it('retira a versão publicada com motivo', async () => {
      const version = makeVersionDetail({
        status: 'published',
        public_id: '0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f',
        play_url: 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html',
        files: [approvedFile()],
      })
      const bodies: unknown[] = []
      server.use(
        ...sessionHandlers(makeAdmin()).handlers,
        http.get(`/api/v1/admin/versions/${version.id}`, () => HttpResponse.json({ data: version })),
        http.post(`/api/v1/admin/versions/${version.id}/withdraw`, async ({ request }) => {
          bodies.push(await request.json())
          return HttpResponse.json({ data: { ...strip(version), status: 'withdrawn' } })
        }),
      )
      const { user } = renderAt(`/admin/versoes/${version.id}`)

      await user.click(await screen.findByRole('button', { name: 'Retirar versão' }))
      await user.type(screen.getByLabelText('Motivo da rejeição'), 'Trava o navegador.')
      await user.click(screen.getByRole('button', { name: 'Confirmar rejeição' }))

      await waitFor(() => expect(bodies).toEqual([{ reason: 'Trava o navegador.' }]))
    })

    it('uma play_url fora do formato nunca aparece', async () => {
      const version = makeVersionDetail({ status: 'published', play_url: 'javascript:alert(1)', files: [approvedFile()] })
      serve(version)
      renderAt(`/admin/versoes/${version.id}`)

      expect(await screen.findByRole('region', { name: 'Publicação' })).toBeInTheDocument()
      expect(screen.queryByText('javascript:alert(1)')).not.toBeInTheDocument()
    })
  })
})
