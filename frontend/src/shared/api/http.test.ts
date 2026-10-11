import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { z } from 'zod'
import { server } from '../../test/server'
import { ApiError, apiGet } from './http'

const Health = z.object({ status: z.literal('ok') })

describe('apiGet', () => {
  it('retorna o corpo validado pelo schema', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ status: 'ok' })))

    await expect(apiGet('/api/health', Health)).resolves.toEqual({ status: 'ok' })
  })

  it('rejeita resposta que não respeita o schema (zero trust na fronteira)', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ status: 'hacked', extra: 1 })))

    await expect(apiGet('/api/health', Health)).rejects.toBeInstanceOf(ApiError)
  })

  it('lança ApiError com o status HTTP em respostas de erro', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ message: 'x' }, { status: 403 })))

    await expect(apiGet('/api/health', Health)).rejects.toMatchObject({ status: 403 })
  })

  it('envia o token XSRF do cookie e mantém as credenciais na mesma origem', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D%3D'
    let seen: { xsrf: string | null; accept: string | null } | undefined
    server.use(
      http.get('/api/health', ({ request }) => {
        seen = { xsrf: request.headers.get('X-XSRF-TOKEN'), accept: request.headers.get('Accept') }
        return HttpResponse.json({ status: 'ok' })
      }),
    )

    await apiGet('/api/health', Health)

    expect(seen).toEqual({ xsrf: 'abc==', accept: 'application/json' })
  })
})
