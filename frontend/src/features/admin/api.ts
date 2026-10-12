import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'
import { UserSchema } from '../auth/api'
import { GameSchema } from '../catalog/game'

const UserPageSchema = z.object({
  data: z.array(UserSchema),
  meta: z.object({ current_page: z.number(), last_page: z.number(), total: z.number() }),
})
export type UserPage = z.infer<typeof UserPageSchema>

export function listUsers(search: string, page: number): Promise<UserPage> {
  const query = new URLSearchParams({ page: String(page) })
  if (search) query.set('search', search)
  return apiGet(`/api/v1/admin/users?${query}`, UserPageSchema)
}

const UserEnvelope = z.object({ data: UserSchema })

export function promoteToDeveloper(userId: string) {
  return apiSend('POST', `/api/v1/admin/users/${encodeURIComponent(userId)}/developer`, UserEnvelope)
}

export function demoteDeveloper(userId: string) {
  return apiSend('DELETE', `/api/v1/admin/users/${encodeURIComponent(userId)}/developer`, UserEnvelope)
}

export const AuditEntrySchema = z.object({
  id: z.string(),
  occurred_at: z.string(),
  action: z.string(),
  subject_type: z.string(),
  subject_id: z.string(),
  context: z.record(z.string(), z.union([z.string(), z.number(), z.boolean(), z.null()])),
  reason: z.string().nullable(),
  actor: z.object({ id: z.string(), name: z.string() }).nullable(),
})
export type AuditEntry = z.infer<typeof AuditEntrySchema>

const AuditPageSchema = z.object({
  data: z.array(AuditEntrySchema),
  meta: z.object({ next_cursor: z.string().nullable() }),
})
export type AuditPage = z.infer<typeof AuditPageSchema>

export type AuditFilter = { subjectType: string; subjectId: string }

export function listAuditEntries(filter: AuditFilter, cursor: string | null): Promise<AuditPage> {
  const query = new URLSearchParams()
  if (filter.subjectType) query.set('subject_type', filter.subjectType)
  if (filter.subjectId) query.set('subject_id', filter.subjectId)
  if (cursor) query.set('cursor', cursor)
  return apiGet(`/api/v1/admin/audit-entries?${query}`, AuditPageSchema)
}

const SubmissionSchema = GameSchema.extend({ owner: z.object({ id: z.string(), name: z.string(), email: z.string() }) })
export type Submission = z.infer<typeof SubmissionSchema>

const SubmissionPageSchema = z.object({
  data: z.array(SubmissionSchema),
  meta: z.object({ current_page: z.number(), last_page: z.number(), total: z.number() }),
})

export function listSubmissions(page: number) {
  return apiGet(`/api/v1/admin/submissions?page=${page}`, SubmissionPageSchema)
}

export function changeGameSlug(gameId: string, slug: string) {
  return apiSend('PATCH', `/api/v1/admin/games/${encodeURIComponent(gameId)}/slug`, z.object({ data: GameSchema }), { slug })
}
