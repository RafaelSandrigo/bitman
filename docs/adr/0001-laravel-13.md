# 0001 — Laravel 13 em vez de 12

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O documento de produto cita Laravel 12, mas o projeto foi criado com Laravel 13.
Política oficial de suporte ([release notes](https://laravel.com/docs/13.x/releases)):

| Versão | Correção de bugs até | Correção de segurança até |
|---|---|---|
| 12 | 13/08/2026 (já encerrada) | 24/02/2027 |
| 13 | Q3 2027 | 17/03/2028 |

Os problemas relatados com o 13 vêm de **upgrades** de aplicações existentes:
pacotes de terceiros ainda incompatíveis e mudanças no formato de prefixos de
cache/Redis e no nome do cookie de sessão. Nenhum deles se aplica a um projeto novo.
Os pacotes de que precisamos já suportam o 13: `laravel/sanctum` ^4.3,
`spatie/laravel-permission` ^8, `pestphp/pest-plugin-laravel` ^5 e `larastan/larastan` ^3.

## Decisão
Usar **Laravel 13** com PHP 8.5.

## Consequências
- Começamos já fora do período de bug fixes no 12; no 13 temos cerca de um ano de correções e até 2028 de segurança.
- Ganhamos `PreventRequestForgery` (verificação de origem), atributos `#[Authorize]`/`#[Middleware]` e roteamento de filas por classe.
- Um pacote futuro sem suporte ao 13 exige avaliação antes de ser adotado.
