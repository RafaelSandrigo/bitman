import { Alert } from '../../shared/ui/Alert'
import type { MyVersion } from './api'

/** Por que a versão parou: violações da política, falha da busca ou o motivo do admin. */
export function StopReason({ version }: { version: MyVersion }) {
  if (version.status === 'validation_failed') {
    return (
      <Alert tone="error">
        <p>O repositório não passou na validação:</p>
        <ul aria-label="Violações" className="mt-1 list-disc space-y-1 pl-5">
          {version.violations.map((violation, index) => (
            <li key={`${violation.code}-${index}`}>{violation.message}</li>
          ))}
        </ul>
      </Alert>
    )
  }
  if (version.status === 'fetch_failed' && version.fetch_failure) {
    return <Alert tone="error">{version.fetch_failure.message}</Alert>
  }
  if (version.rejection_reason) {
    return <Alert tone="error">Motivo: {version.rejection_reason}</Alert>
  }
  return null
}
