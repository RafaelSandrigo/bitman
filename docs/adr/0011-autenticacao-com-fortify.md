# 0011 — Autenticação com Fortify headless

- **Status:** Aceito
- **Data:** 2026-10-11

## Contexto
A Fase 1 precisa de cadastro, login, logout, verificação de e-mail e recuperação de senha para uma SPA
(Sanctum em modo SPA, [ADR 0005](0005-autenticacao-e-autorizacao.md)). Escrever esses fluxos à mão
significa reimplementar tokens de redefinição, URLs assinadas e limitação de tentativas, que é código
sensível e fácil de errar.

## Decisão
Usar o **Laravel Fortify** (first-party) sem views, com as rotas em `/api/v1/auth` e só os recursos
`registration`, `resetPasswords` e `emailVerification`. O Fortify fica restrito à camada `Infrastructure`
do módulo Identity:

- `CreateNewUser` valida a entrada e chama a Action `RegisterPlayer` (a conta nasce `player`).
- `ResetUserPassword` aplica a nova senha.
- Uma resposta única para "esqueci a senha" (`PasswordResetLinkRequestedResponse`) não revela se a conta existe.
- Limitadores próprios: `login` (5/min por e-mail + IP e 20/min por IP, A2) e `auth` (30/min por IP em
  todas as rotas do Fortify). O reenvio de verificação fica em 6/min (A5).
- Os e-mails saem pela fila (`ShouldQueue`), com texto em pt-BR, e apontam para a SPA (`/verificar-email`,
  `/redefinir-senha`), que conclui a operação pela API na mesma origem.

## Consequências
- Menos código próprio nos fluxos mais sensíveis, mantidos e testados pelo Laravel.
- O Fortify recebe `$request->all()` nos adaptadores. A convenção "sem `$request->all()`" vale para o
  nosso código, e os adaptadores repassam só os campos validados.
- O link de verificação exige estar logado com a mesma conta. A SPA pede o login e volta ao link.
- 2FA e passkeys ficam disponíveis no pacote, mas desligados. Se forem ativados, isso pede um novo ADR.
