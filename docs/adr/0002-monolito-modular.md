# 0002 — Monólito modular com DDD pragmático

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O domínio tem um núcleo rico (ciclo de vida da versão, com três portões e
integridade por hash) e partes simples (catálogo, ranking). A equipe é pequena.
Microsserviços trariam custo operacional sem benefício.

## Decisão
Um único Laravel organizado em módulos (`app/Modules/*`), cada um com as camadas
Domain / Application / Infrastructure / Http. Os modelos Eloquent são os agregados,
e as transições ocorrem apenas por métodos de intenção. Não há Repository Pattern
sobre o Eloquent. Portas e adaptadores existem só para I/O externo. As regras de
dependência são verificadas por testes de arquitetura do Pest.

## Consequências
- Rigor do DDD onde há complexidade (Versioning, Ingestion) e simplicidade no restante.
- O domínio fica acoplado ao Eloquent. Aceitamos esse custo em troca de produtividade.
- Os módulos podem ser extraídos no futuro, porque a comunicação já ocorre por eventos e IDs.
