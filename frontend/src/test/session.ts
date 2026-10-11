import { http, HttpResponse } from 'msw'
import type { CurrentUser } from '../features/auth/api'

let sequence = 0

/** ULID válido e único por chamada, em minúsculas como o Laravel gera (HasUlids). */
export function ulid(): string {
  sequence++
  return `01jabcdefghjkmnpqrstv${String(sequence).padStart(5, '0')}`
}

export function makeUser(overrides: Partial<CurrentUser> = {}): CurrentUser {
  return {
    id: ulid(),
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    email_verified: true,
    roles: ['player'],
    permissions: [],
    created_at: '2026-10-01T12:00:00+00:00',
    ...overrides,
  }
}

export function makeAdmin(overrides: Partial<CurrentUser> = {}): CurrentUser {
  return makeUser({
    name: 'Rafael',
    email: 'rafael@example.com',
    roles: ['player', 'admin'],
    permissions: ['games.submit', 'users.manage-roles', 'audit.view'],
    ...overrides,
  })
}

/**
 * Sessão simulada: `/api/v1/me` devolve o usuário atual (ou 401) e o cookie CSRF é emitido sob demanda.
 * `setUser` muda a sessão no meio do teste (ex.: depois do login).
 */
export function sessionHandlers(initial: CurrentUser | null) {
  let current = initial
  return {
    setUser: (user: CurrentUser | null) => {
      current = user
    },
    handlers: [
      http.get('/api/v1/me', () =>
        current ? HttpResponse.json({ data: current }) : HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }),
      ),
      http.get('/sanctum/csrf-cookie', () => {
        document.cookie = 'XSRF-TOKEN=teste'
        return new HttpResponse(null, { status: 204 })
      }),
    ],
  }
}
