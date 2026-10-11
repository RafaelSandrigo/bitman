import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { errorMessage, fieldErrors } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { TextField } from '../../../shared/ui/TextField'
import { login } from '../api'
import { AuthCard } from '../AuthCard'
import { useRefreshSession } from '../session'

const schema = z.object({
  email: z.email('Informe um e-mail válido.'),
  password: z.string().min(1, 'Informe a senha.'),
})
type Form = z.infer<typeof schema>

export function LoginPage() {
  const refreshSession = useRefreshSession()
  const { register, handleSubmit, setError, formState } = useForm<Form>({ resolver: zodResolver(schema) })

  const mutation = useMutation({
    mutationFn: login,
    // O GuestOnly leva ao destino (?next=) assim que a sessão é atualizada.
    onSuccess: () => refreshSession(),
    onError: (error) => {
      for (const [field, message] of Object.entries(fieldErrors(error))) {
        if (field === 'email' || field === 'password') setError(field, { message })
      }
    },
  })
  const hasFieldErrors = Object.keys(fieldErrors(mutation.error)).length > 0

  return (
    <AuthCard
      title="Entrar"
      footer={
        <div className="flex justify-between">
          <Link to="/cadastro" className="text-emerald-400 hover:underline">
            Criar conta
          </Link>
          <Link to="/esqueci-a-senha" className="text-emerald-400 hover:underline">
            Esqueci a senha
          </Link>
        </div>
      }
    >
      {mutation.isError && !hasFieldErrors && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
      <form noValidate onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
        <TextField label="E-mail" type="email" autoComplete="email" error={formState.errors.email?.message} {...register('email')} />
        <TextField label="Senha" type="password" autoComplete="current-password" error={formState.errors.password?.message} {...register('password')} />
        <Button type="submit" disabled={mutation.isPending} className="w-full">
          {mutation.isPending ? 'Entrando…' : 'Entrar'}
        </Button>
      </form>
    </AuthCard>
  )
}
