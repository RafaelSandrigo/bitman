import { z } from 'zod'
import { apiGet, apiSend } from '../../shared/api/http'
import { DeveloperRequestSchema } from '../developer/api'

export const AdminDeveloperRequestSchema = DeveloperRequestSchema.extend({
  user: z.object({ id: z.string(), name: z.string(), email: z.string() }),
})
export type AdminDeveloperRequest = z.infer<typeof AdminDeveloperRequestSchema>

export const requestStatuses = ['pending', 'approved', 'rejected'] as const
export type RequestStatus = (typeof requestStatuses)[number]

const PageSchema = z.object({
  data: z.array(AdminDeveloperRequestSchema),
  meta: z.object({ current_page: z.number(), last_page: z.number(), total: z.number() }),
})
const Envelope = z.object({ data: AdminDeveloperRequestSchema })

export function listDeveloperRequests(status: RequestStatus, page: number) {
  return apiGet(`/api/v1/admin/developer-requests?status=${status}&page=${page}`, PageSchema)
}

export async function getDeveloperRequestSummary() {
  return (await apiGet('/api/v1/admin/developer-requests/summary', z.object({ data: z.object({ pending: z.number().int().nonnegative() }) }))).data
}

/** M18: concede a role developer (auditado). */
export function approveDeveloperRequest(id: string) {
  return apiSend('POST', `/api/v1/admin/developer-requests/${encodeURIComponent(id)}/approve`, Envelope)
}

/** M18: rejeita com motivo (I11), que o usuário vê. */
export function rejectDeveloperRequest(id: string, reason: string) {
  return apiSend('POST', `/api/v1/admin/developer-requests/${encodeURIComponent(id)}/reject`, Envelope, { reason })
}
