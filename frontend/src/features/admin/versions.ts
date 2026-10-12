import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'

export const versionStatuses = [
  'import_requested',
  'import_rejected',
  'fetching',
  'fetch_failed',
  'validation_failed',
  'awaiting_commit_review',
  'commit_rejected',
  'awaiting_file_review',
  'rejected',
  'published',
  'superseded',
  'withdrawn',
] as const
export type VersionStatus = (typeof versionStatuses)[number]

export const versionStatusLabels: Record<VersionStatus, string> = {
  import_requested: 'Aguardando autorização',
  import_rejected: 'Importação rejeitada',
  fetching: 'Buscando o repositório',
  fetch_failed: 'Busca falhou',
  validation_failed: 'Reprovada na validação',
  awaiting_commit_review: 'Aguardando revisão do commit',
  commit_rejected: 'Commit rejeitado',
  awaiting_file_review: 'Aguardando revisão dos arquivos',
  rejected: 'Rejeitada',
  published: 'Publicada',
  superseded: 'Substituída',
  withdrawn: 'Retirada',
}

const Sha = z.string().regex(/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/)

export const VersionSchema = z.object({
  id: z.string(),
  number: z.number().int().positive(),
  status: z.enum(versionStatuses),
  repository_url: z.string().startsWith('https://'),
  requested_ref: z.string(),
  commit_sha: Sha.nullable(),
  blocked: z.boolean().nullable(),
  fetch_failure: z.object({ code: z.string(), message: z.string() }).nullable(),
  fetched_at: z.string().nullable(),
  created_at: z.string(),
  game: z.object({ id: z.string(), name: z.string(), slug: z.string() }),
  requested_by: z.object({ id: z.string(), name: z.string() }),
})
export type Version = z.infer<typeof VersionSchema>

const ViolationSchema = z.object({ code: z.string(), message: z.string(), path: z.string().nullable() })

export const ReportSchema = z.object({
  blocked: z.boolean(),
  violations: z.array(ViolationSchema),
  ignored: z.array(z.object({ path: z.string(), mode: z.string(), size: z.number().nullable() })),
  ignored_total: z.number().int(),
})
export type Report = z.infer<typeof ReportSchema>

export const VersionFileSchema = z.object({
  id: z.string(),
  path: z.string(),
  sha256: z.string().regex(/^[0-9a-f]{64}$/),
  size: z.number().int().nonnegative(),
  git_blob_sha: Sha,
  change_type: z.enum(['added', 'modified', 'unchanged']),
  review_status: z.enum(['pending', 'approved', 'rejected']),
})
export type VersionFile = z.infer<typeof VersionFileSchema>

export const VersionDetailSchema = VersionSchema.extend({
  import_decision: z
    .object({
      decided_by: z.object({ id: z.string(), name: z.string() }).nullable(),
      decided_at: z.string(),
      rejection_reason: z.string().nullable(),
    })
    .nullable(),
  report: ReportSchema.nullable(),
  files: z.array(VersionFileSchema),
})
export type VersionDetail = z.infer<typeof VersionDetailSchema>

const VersionPageSchema = z.object({
  data: z.array(VersionSchema),
  meta: z.object({ current_page: z.number(), last_page: z.number(), total: z.number() }),
})

export function listVersions(status: VersionStatus, page: number) {
  const query = new URLSearchParams({ status, page: String(page) })
  return apiGet(`/api/v1/admin/versions?${query}`, VersionPageSchema)
}

export function getVersion(id: string) {
  return apiGet(`/api/v1/admin/versions/${encodeURIComponent(id)}`, z.object({ data: VersionDetailSchema }))
}

const VersionEnvelope = z.object({ data: VersionSchema.omit({ game: true, requested_by: true }) })

export function authorizeImport(id: string) {
  return apiSend('POST', `/api/v1/admin/versions/${encodeURIComponent(id)}/authorize-import`, VersionEnvelope)
}

export function rejectImport(id: string, reason: string) {
  return apiSend('POST', `/api/v1/admin/versions/${encodeURIComponent(id)}/reject-import`, VersionEnvelope, { reason })
}

export function requestNewImport(gameId: string) {
  return apiSend('POST', `/api/v1/admin/games/${encodeURIComponent(gameId)}/versions`, VersionEnvelope)
}
