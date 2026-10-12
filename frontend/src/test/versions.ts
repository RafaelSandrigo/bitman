import { http, HttpResponse } from 'msw'
import { versionStatuses, type Version, type VersionDetail, type VersionFile, type VersionSummary } from '../features/admin/versions'
import type { MyVersion } from '../features/submissions/api'
import { ulid } from './session'

export function makeVersion(overrides: Partial<Version> = {}): Version {
  return {
    id: ulid(),
    number: 1,
    status: 'import_requested',
    is_new: false,
    repository_url: 'https://github.com/ada/snake',
    requested_ref: 'main',
    commit_sha: null,
    blocked: null,
    fetch_failure: null,
    fetched_at: null,
    created_at: '2026-10-12T12:00:00+00:00',
    game: { id: ulid(), name: 'Snake', slug: 'snake' },
    requested_by: { id: ulid(), name: 'Ada' },
    ...overrides,
  }
}

export function makeVersionDetail(overrides: Partial<VersionDetail> = {}): VersionDetail {
  return {
    ...makeVersion({ status: 'awaiting_commit_review', commit_sha: 'a'.repeat(40), blocked: false, fetched_at: '2026-10-12T12:01:00+00:00' }),
    import_decision: { decided_by: { id: ulid(), name: 'Rafael' }, decided_at: '2026-10-12T12:00:30+00:00', rejection_reason: null },
    commit_decision: null,
    rejection: null,
    report: { blocked: false, violations: [], ignored: [], ignored_total: 0 },
    files: [],
    ...overrides,
  }
}

export function makeMyVersion(overrides: Partial<MyVersion> = {}): MyVersion {
  return {
    id: ulid(),
    game_id: ulid(),
    number: 1,
    status: 'import_requested',
    in_progress: true,
    requested_ref: 'main',
    commit_sha: null,
    rejection_reason: null,
    fetch_failure: null,
    violations: [],
    created_at: '2026-10-12T12:00:00+00:00',
    updated_at: '2026-10-12T12:00:00+00:00',
    ...overrides,
  }
}

export function makeVersionFile(overrides: Partial<VersionFile> = {}): VersionFile {
  return {
    id: ulid(),
    path: 'game.js',
    sha256: 'b'.repeat(64),
    size: 2048,
    git_blob_sha: 'c'.repeat(40),
    change_type: 'added',
    review_status: 'pending',
    reviews: [],
    previous_approval: null,
    ...overrides,
  }
}

/** Contadores das filas (todos zerados, salvo os informados) e o registro das marcações de "visto". */
export function queueHandlers(counts: Partial<VersionSummary> = {}) {
  const seen: unknown[] = []
  const summary = Object.fromEntries(versionStatuses.map((status) => [status, counts[status] ?? { total: 0, new: 0 }]))
  return {
    seen,
    handlers: [
      http.get('/api/v1/admin/versions/summary', () => HttpResponse.json({ data: summary })),
      http.post('/api/v1/admin/versions/seen', async ({ request }) => {
        seen.push(await request.json())
        return new HttpResponse(null, { status: 204 })
      }),
    ],
  }
}
