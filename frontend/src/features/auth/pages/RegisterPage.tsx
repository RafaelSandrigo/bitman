import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { errorMessage, fieldErrors } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { TextField } from '../../../shared/ui/TextField'
import { register as registerAccount } from '../api'
import { AuthCard } from '../AuthCard'
import { useRefreshSession } from '../session'

/** Espelha as regras do backend; a validação definitiva é sempre a do servidor. */
const schema = z
  .object({
    name: z.string().trim().min(2, 'Informe seu nome.').max(60, 'Use no máximo 60 caracteres.'),
    email: z.email('Informe um e-mail válido.'),
    password: z
      .string()
      .min(8, 'A senha precisa ter pelo menos 8 caracteres.')
      .regex(/[a-zA-Z]/, 'A senha precisa ter letras.')
      .regex(/\d/, 'A senha precisa ter números.'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'As senhas não conferem.',
  })
type Form = z.infer<typeof schema>
const fields = ['name', 'email', 'password', 'password_confirmation'] as const

export function RegisterPage() {
  const refreshSession = useRefreshSession()
  const { register, handleSubmit, setError, formState } = useForm<Form>({ resolver: zodResolver(schema) })

  const mutation = useMutation({
    mutationFn: registerAccount,
    // O GuestOnly leva a /verificar-email assim que a sessão é atualizada.
    onSuccess: () => refreshSession(),
    onError: (error) => {
      for (const [field, message] of Object.entries(fieldErrors(error))) {
        const known = fields.find((f) => f === field)
        if (known) setError(known, { message })
      }
    },
  })
  const hasFieldErrors = Object.keys(fieldErrors(mutation.error)).length > 0

  return (
    <AuthCard
      title="Criar conta"
      footer={
        <>
          Já tem conta?{' '}
          <Link to="/entrar" className="text-emerald-400 hover:underline">
            Entrar
          </Link>
        </>
      }
    >
      {mutation.isError && !hasFieldErrors && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
      <form noValidate onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
        <TextField label="Nome" autoComplete="name" error={formState.errors.name?.message} {...register('name')} />
        <TextField label="E-mail" type="email" autoComplete="email" error={formState.errors.email?.message} {...register('email')} />
        <TextField label="Senha" type="password" autoComplete="new-password" error={formState.errors.password?.message} {...register('password')} />
        <TextField
          label="Confirme a senha"
          type="password"
          autoComplete="new-password"
          error={formState.errors.password_confirmation?.message}
          {...register('password_confirmation')}
        />
        <Button type="submit" disabled={mutation.isPending} className="w-full">
          {mutation.isPending ? 'Criando…' : 'Criar conta'}
        </Button>
      </form>
    </AuthCard>
  )
}
