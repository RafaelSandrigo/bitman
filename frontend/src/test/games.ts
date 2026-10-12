import type { Game } from '../features/catalog/game'
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
    created_at: '2026-10-11T12:00:00+00:00',
    ...overrides,
  }
}
