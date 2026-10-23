import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { errorMessage, fieldErrors } from '../../shared/api/http'
import { Alert } from '../../shared/ui/Alert'
import { Button } from '../../shared/ui/Button'
import { TextField } from '../../shared/ui/TextField'
import { formatScore } from '../play/format'
import { changeScoreCap } from './games'

/** Só dígitos; o vazio remove o teto. O servidor confere a faixa (1..Number.MAX_SAFE_INTEGER). */
function parseCap(input: string): number | null | 'invalid' {
  const trimmed = input.trim()
  if (trimmed === '') return null
  if (!/^\d+$/.test(trimmed)) return 'invalid'
  const value = Number(trimmed)
  return Number.isSafeInteger(value) && value >= 1 ? value : 'invalid'
}

/** M15: teto opcional de score; acima dele, o score é sinalizado e fica fora do ranking até a revisão. */
export function ScoreCapForm({ gameId, current, onSaved }: { gameId: string; current: number | null; onSaved: () => unknown }) {
  const [input, setInput] = useState(current === null ? '' : String(current))
  const [invalid, setInvalid] = useState(false)
  const save = useMutation({ mutationFn: (cap: number | null) => changeScoreCap(gameId, cap), onSuccess: onSaved })

  function submit(event: FormEvent) {
    event.preventDefault()
    const cap = parseCap(input)
    setInvalid(cap === 'invalid')
    if (cap !== 'invalid') save.mutate(cap)
  }

  return (
    <section aria-label="Teto de score" className="space-y-2">
      <h2 className="text-lg font-semibold">Teto de score</h2>
      <p className="text-sm text-slate-400">
        {current === null ? 'Sem teto: só o padrão do jogo sinaliza scores.' : `Scores acima de ${formatScore(current)} são sinalizados.`}
      </p>
      <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
        <TextField
          label="Teto (vazio para remover)"
          inputMode="numeric"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          error={invalid ? 'Use um número inteiro maior que zero.' : fieldErrors(save.error).score_cap}
        />
        <Button type="submit" variant="secondary" disabled={save.isPending}>
          Salvar teto
        </Button>
      </form>
      {save.isError && !fieldErrors(save.error).score_cap && <Alert tone="error">{errorMessage(save.error)}</Alert>}
    </section>
  )
}
