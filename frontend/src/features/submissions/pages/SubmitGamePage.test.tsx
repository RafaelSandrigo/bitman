import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { makeGame } from '../../../test/games'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { makeDeveloper, makeUser, sessionHandlers } from '../../../test/session'

const png = () => new File([new Uint8Array([0x89, 0x50, 0x4e, 0x47])], 'capa.png', { type: 'image/png' })

async function fillForm(user: ReturnType<typeof renderAt>['user'], file: File = png()) {
  await user.type(await screen.findByLabelText('Nome do jogo'), 'Snake')
  await user.type(screen.getByLabelText('Descrição'), 'O clássico da cobrinha.')
  await user.type(screen.getByLabelText('Autor'), 'Ada Lovelace')
  await user.type(screen.getByLabelText('Repositório Git'), 'https://github.com/ada/snake')
  await user.upload(screen.getByLabelText(/thumbnail/i), file)
}

describe('SubmitGamePage', () => {
  it('envia a submissão como multipart e leva a "Meus jogos" com a confirmação', async () => {
    let sent: Record<string, FormDataEntryValue> = {}
    const game = makeGame()
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.post('/api/v1/games', async ({ request }) => {
        sent = Object.fromEntries((await request.formData()).entries())
        return HttpResponse.json({ data: game }, { status: 201 })
      }),
      http.get('/api/v1/me/games', () => HttpResponse.json({ data: [game] })),
      http.get('/api/v1/me/versions', () => HttpResponse.json({ data: [] })),
    )
    const { user } = renderAt('/desenvolvedor/enviar')

    await fillForm(user)
    expect(screen.getByLabelText('Branch, tag ou commit')).toHaveValue('main')
    await user.click(screen.getByRole('button', { name: 'Enviar para análise' }))

    expect(await screen.findByText('Snake foi enviado para análise.')).toBeInTheDocument()
    expect(sent).toMatchObject({
      name: 'Snake',
      description: 'O clássico da cobrinha.',
      author_name: 'Ada Lovelace',
      repository_url: 'https://github.com/ada/snake',
      ref: 'main',
    })
    // O File do jsdom e o do undici (MSW) são classes diferentes: confere tipo e tamanho.
    expect(sent.thumbnail).toMatchObject({ type: 'image/png', size: 4 })
  })

  it('mostra no campo o erro de validação do servidor (G1–G3)', async () => {
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.post('/api/v1/games', () =>
        HttpResponse.json(
          { message: 'x', errors: { repository_url: ['Use o endereço HTTPS de um repositório público no GitHub, GitLab ou Codeberg.'] } },
          { status: 422 },
        ),
      ),
    )
    const { user } = renderAt('/desenvolvedor/enviar')

    await fillForm(user)
    await user.click(screen.getByRole('button', { name: 'Enviar para análise' }))

    expect(await screen.findByText('Use o endereço HTTPS de um repositório público no GitHub, GitLab ou Codeberg.')).toBeInTheDocument()
    expect(screen.getByLabelText('Repositório Git')).toHaveAttribute('aria-invalid', 'true')
  })

  it('mostra a recusa da thumbnail pelo servidor (A4)', async () => {
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.post('/api/v1/games', () =>
        HttpResponse.json({ message: 'A thumbnail deve ser uma imagem PNG, JPEG ou WebP válida.' }, { status: 422 }),
      ),
    )
    const { user } = renderAt('/desenvolvedor/enviar')

    await fillForm(user)
    await user.click(screen.getByRole('button', { name: 'Enviar para análise' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('A thumbnail deve ser uma imagem PNG, JPEG ou WebP válida.')
  })

  it('confere host, tipo da imagem e campos obrigatórios antes de enviar', async () => {
    let posted = false
    server.use(
      ...sessionHandlers(makeDeveloper()).handlers,
      http.post('/api/v1/games', () => {
        posted = true
        return HttpResponse.json({}, { status: 500 })
      }),
    )
    const { user } = renderAt('/desenvolvedor/enviar')

    await user.type(await screen.findByLabelText('Repositório Git'), 'https://bitbucket.org/ada/snake')
    await user.click(screen.getByRole('button', { name: 'Enviar para análise' }))

    expect(await screen.findByText('Use o endereço HTTPS do repositório no GitHub, GitLab ou Codeberg.')).toBeInTheDocument()
    expect(screen.getByText('Informe o nome do jogo.')).toBeInTheDocument()
    expect(screen.getByText('Escolha uma imagem.')).toBeInTheDocument()
    expect(posted).toBe(false)
  })

  it('nega a área a quem não é developer', async () => {
    server.use(...sessionHandlers(makeUser()).handlers)
    renderAt('/desenvolvedor/enviar')

    expect(await screen.findByRole('heading', { name: 'Acesso negado' })).toBeInTheDocument()
    expect(screen.queryByLabelText('Nome do jogo')).not.toBeInTheDocument()
  })

  it('manda o visitante entrar e voltar para cá', async () => {
    server.use(...sessionHandlers(null).handlers)
    const { router } = renderAt('/desenvolvedor/enviar')

    await screen.findByRole('heading', { name: 'Entrar' })
    expect(router.state.location.search).toBe('?next=%2Fdesenvolvedor%2Fenviar')
  })
})
