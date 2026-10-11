import type { z } from 'zod'

/** Erro de chamada à API: status HTTP (0 = resposta fora do contrato) e o corpo recebido. */
export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(message: string, status: number, body: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match?.[1] ? decodeURIComponent(match[1]) : null
}

async function request<T>(method: string, path: string, schema: z.ZodType<T>, body?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = xsrfToken()
  if (token) headers['X-XSRF-TOKEN'] = token
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  const response = await fetch(path, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  const payload: unknown = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(`HTTP ${response.status} em ${method} ${path}`, response.status, payload)
  }

  // Zero trust: toda resposta passa pelo schema antes de chegar à aplicação.
  const parsed = schema.safeParse(payload)
  if (!parsed.success) {
    throw new ApiError(`Resposta fora do contrato em ${method} ${path}`, 0, payload)
  }

  return parsed.data
}

export function apiGet<T>(path: string, schema: z.ZodType<T>): Promise<T> {
  return request('GET', path, schema)
}

export function apiSend<T>(method: 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, schema: z.ZodType<T>, body?: unknown): Promise<T> {
  return request(method, path, schema, body)
}
