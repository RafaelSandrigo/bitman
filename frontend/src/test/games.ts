import { http, HttpResponse } from 'msw'
import type { Game, PublicGame } from '../features/catalog/game'
import { ulid } from './session'

export function makeGame(overrides: Partial<Game> = {}): Game {
  return {
    id: ulid(),
    slug: 'snake',
    name: 'Snake',
    description: 'O clássico da cobrinha.',
    author_name: 'Ada Lovelace',
    thumbnail_url: '/media/thumbnails/0123456789abcdef0123456789abcdef.webp',
    repository_url: 'https://github.com/ada/snake',
    default_ref: 'main',
    status: 'submitted',
    published_version_id: null,
    first_published_at: null,
    block_reason: null,
    score_cap: null,
    created_at: '2026-10-11T12:00:00+00:00',
    ...overrides,
  }
}

export function makePublicGame(overrides: Partial<PublicGame> = {}): PublicGame {
  return {
    slug: 'snake',
    name: 'Snake',
    description: 'O clássico da cobrinha.',
    author_name: 'Ada Lovelace',
    thumbnail_url: '/media/thumbnails/0123456789abcdef0123456789abcdef.webp',
    version_number: 1,
    published_at: '2026-10-12T12:00:00+00:00',
    play_url: 'https://games.test/0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f/index.html',
    ...overrides,
  }
}

/** Catálogo público da página inicial. */
export function catalogHandler(games: PublicGame[] = []) {
  return http.get('/api/v1/games', () => HttpResponse.json({ data: games }))
}
