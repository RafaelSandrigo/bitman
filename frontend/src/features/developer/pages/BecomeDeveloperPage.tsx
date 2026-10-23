import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { errorMessage, fieldErrors } from '../../../shared/api/http'
import { Alert } from '../../../shared/ui/Alert'
import { Button } from '../../../shared/ui/Button'
import { linkButton } from '../../../shared/ui/linkStyles'
import { TextArea } from '../../../shared/ui/TextArea'
import { TextField } from '../../../shared/ui/TextField'
import { Permission } from '../../auth/api'
import { hasPermission, useCurrentUser } from '../../auth/session'
import { formatDateTime } from '../../play/format'
import { getMyDeveloperRequest, githubProfilePattern, isGithubProfile, requestDeveloperRole, type DeveloperRequest } from '../api'

/** Espelha as regras do backend; a validação definitiva é a do servidor. */
const schema = z.object({
  name: z.string().trim().min(2, 'Informe seu nome.').max(60, 'Use no máximo 60 caracteres.'),
  github_url: z.string().trim().regex(githubProfilePattern, 'Use o endereço do seu perfil: https://github.com/seu-usuario.'),
  bio: z.string().trim().min(20, 'Conte um pouco mais (pelo menos 20 caracteres).').max(1000, 'Use no máximo 1000 caracteres.'),
})
type Form = z.infer<typeof schema>
const fields = ['name', 'github_url', 'bio'] as const

function RequestForm({ onSent }: { onSent: (request: DeveloperRequest) => void }) {
  const { register, handleSubmit, setError, formState } = useForm<Form>({ resolver: zodResolver(schema) })
  const mutation = useMutation({
    mutationFn: requestDeveloperRole,
    onSuccess: onSent,
    onError: (error) => {
      for (const [field, message] of Object.entries(fieldErrors(error))) {
        const known = fields.find((f) => f === field)
        if (known) setError(known, { message })
      }
    },
  })
  const hasFieldErrors = Object.keys(fieldErrors(mutation.error)).length > 0

  return (
    <form noValidate onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
      {mutation.isError && !hasFieldErrors && <Alert tone="error">{errorMessage(mutation.error)}</Alert>}
      <TextField label="Nome" autoComplete="name" error={formState.errors.name?.message} {...register('name')} />
      <TextField label="Perfil no GitHub" type="url" placeholder="https://github.com/seu-usuario" error={formState.errors.github_url?.message} {...register('github_url')} />
      <TextArea
        label="Apresentação"
        rows={5}
        maxLength={1000}
        hint="Quem é você, o que já fez e que jogo quer publicar."
        error={formState.errors.bio?.message}
        {...register('bio')}
      />
      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'Enviando…' : 'Enviar pedido'}
      </Button>
    </form>
  )
}

/** O pedido como foi enviado. Tudo é texto do usuário: renderizado só como texto (o link só se for um perfil do GitHub). */
function RequestSummary({ request }: { request: DeveloperRequest }) {
  return (
    <dl className="space-y-2 rounded-lg border border-slate-800 bg-slate-900/50 p-4 text-sm">
      <div>
        <dt className="text-slate-400">Nome</dt>
        <dd>{request.name}</dd>
      </div>
      <div>
        <dt className="text-slate-400">GitHub</dt>
        <dd>
          {isGithubProfile(request.github_url) ? (
            <a href={request.github_url} target="_blank" rel="noopener noreferrer" className="text-emerald-400 hover:underline">
              {request.github_url}
            </a>
          ) : (
            request.github_url
          )}
        </dd>
      </div>
      <div>
        <dt className="text-slate-400">Apresentação</dt>
        <dd className="whitespace-pre-line">{request.bio}</dd>
      </div>
      <div>
        <dt className="text-slate-400">Enviado em</dt>
        <dd>{formatDateTime(request.created_at)}</dd>
      </div>
    </dl>
  )
}

function RequestState({ request, onSent }: { request: DeveloperRequest | null; onSent: (request: DeveloperRequest) => void }) {
  if (request?.status === 'pending') {
    return (
      <div className="space-y-3">
        <Alert tone="info">Seu pedido está em análise.</Alert>
        <RequestSummary request={request} />
      </div>
    )
  }
  if (request?.status === 'approved') return <Alert tone="success">Pedido aprovado! Saia e entre de novo se o menu ainda não mostrar "Meus jogos".</Alert>

  return (
    <div className="space-y-4">
      {request?.status === 'rejected' && (
        <Alert tone="error">
          Seu último pedido não foi aprovado: {request.decision_reason} Você pode enviar outro.
        </Alert>
      )}
      <RequestForm onSent={onSent} />
    </div>
  )
}

function PageBody() {
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const isDeveloper = hasPermission(user, Permission.SubmitGames)
  const verified = user?.email_verified ?? false
  const current = useQuery({ queryKey: ['my-developer-request'], queryFn: getMyDeveloperRequest, enabled: verified && !isDeveloper })

  if (isDeveloper) {
    return (
      <div className="space-y-3">
        <p>Você já é developer.</p>
        <Link to="/desenvolvedor/enviar" className={linkButton.primary}>
          Enviar um jogo
        </Link>
      </div>
    )
  }
  if (!verified) {
    return (
      <Alert tone="info">
        Confirme seu e-mail para pedir acesso de developer.{' '}
        <Link to="/verificar-email" className="font-semibold underline">
          Confirmar e-mail
        </Link>
      </Alert>
    )
  }
  if (current.isPending) return <p className="text-slate-400">Carregando…</p>
  if (current.isError) return <Alert tone="error">{errorMessage(current.error)}</Alert>
  return <RequestState request={current.data} onSent={(request) => queryClient.setQueryData(['my-developer-request'], request)} />
}

/** M18: "Seja Desenvolvedor". O pedido vai para a fila do admin, que aprova ou rejeita com motivo. */
export function BecomeDeveloperPage() {
  return (
    <section className="max-w-2xl space-y-4">
      <h1 className="text-2xl font-bold">Seja Desenvolvedor</h1>
      <p className="text-slate-400">
        Developers publicam jogos no Bitman. Cada jogo passa pela revisão de uma pessoa antes de ir ao ar. Conte quem você é e onde
        está o seu trabalho; o pedido é analisado pela administração.
      </p>
      <PageBody />
    </section>
  )
}
