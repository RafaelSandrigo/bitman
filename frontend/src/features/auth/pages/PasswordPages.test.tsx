import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { renderAt } from '../../../test/render'
import { server } from '../../../test/server'
import { sessionHandlers } from '../../../test/session'

describe('recuperação de senha', () => {
  it('mostra a mesma mensagem neutra do servidor, exista ou não a conta', async () => {
    const message = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.'
    server.use(
      ...sessionHandlers(null).handlers,
      http.post('/api/v1/auth/forgot-password', () => HttpResponse.json({ message })),
    )
    const { user } = renderAt('/esqueci-a-senha')

    await user.type(await screen.findByLabelText('E-mail'), 'ada@example.com')
    await user.click(screen.getByRole('button', { name: 'Enviar link' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
  })

  it('redefine a senha com o token e o e-mail do link', async () => {
    let body: unknown
    server.use(
      ...sessionHandlers(null).handlers,
      http.post('/api/v1/auth/reset-password', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ message: 'ok' })
      }),
    )
    const { user } = renderAt('/redefinir-senha?token=tok123&email=ada%40example.com')

    await user.type(await screen.findByLabelText('Nova senha'), 'nova-senha-2')
    await user.type(screen.getByLabelText('Confirme a nova senha'), 'nova-senha-2')
    await user.click(screen.getByRole('button', { name: 'Redefinir senha' }))

    expect(await screen.findByText(/senha redefinida/i)).toBeInTheDocument()
    expect(body).toEqual({ token: 'tok123', email: 'ada@example.com', password: 'nova-senha-2', password_confirmation: 'nova-senha-2' })
  })

  it('recusa link de redefinição incompleto', async () => {
    server.use(...sessionHandlers(null).handlers)
    renderAt('/redefinir-senha?token=tok123')

    expect(await screen.findByRole('alert')).toHaveTextContent(/link de redefinição inválido/i)
  })
})
