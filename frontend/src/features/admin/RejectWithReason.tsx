import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { errorMessage, fieldErrors } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { TextArea } from '../../shared/ui/TextArea'

const reasonHint = 'Obrigatório. O desenvolvedor vê este motivo, e ele fica registrado na auditoria.'

/** Rejeição em qualquer portão: sempre com motivo (I11), confirmada num segundo passo. */
export function RejectWithReason({
  label = 'Rejeitar',
  reject,
  onDone,
  disabled = false,
}: {
  label?: string
  reject: (reason: string) => Promise<unknown>
  onDone: () => unknown
  disabled?: boolean
}) {
  const [open, setOpen] = useState(false)
  const [reason, setReason] = useState('')
  const mutation = useMutation({ mutationFn: () => reject(reason), onSuccess: onDone })

  function submit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate()
  }

  if (!open) {
    return (
      <Button variant="danger" onClick={() => setOpen(true)} disabled={disabled}>
        {label}
      </Button>
    )
  }

  return (
    <form onSubmit={submit} className="w-full space-y-2">
      <TextArea
        label="Motivo da rejeição"
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        maxLength={1000}
        rows={3}
        error={fieldErrors(mutation.error).reason}
        hint={reasonHint}
      />
      <div className="flex gap-2">
        <Button type="submit" variant="danger" disabled={mutation.isPending}>
          Confirmar rejeição
        </Button>
        <Button variant="secondary" onClick={() => setOpen(false)} disabled={mutation.isPending}>
          Cancelar
        </Button>
      </div>
      {mutation.isError && !fieldErrors(mutation.error).reason && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
    </form>
  )
}
