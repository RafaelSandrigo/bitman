import type { Version, VersionDetail } from '../features/admin/versions'
import { ulid } from './session'

export function makeVersion(overrides: Partial<Version> = {}): Version {
  return {
    id: ulid(),
    number: 1,
    status: 'import_requested',
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
    report: { blocked: false, violations: [], ignored: [], ignored_total: 0 },
    files: [],
    ...overrides,
  }
}
