# 0006 — Aprovação vinculada ao hash, sem herança entre versões

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O requisito diz que a aprovação pertence ao conteúdo exato analisado. Arquivos
`unchanged` entre versões poderiam herdar a aprovação anterior.

## Decisão
Cada decisão registra o SHA-256 analisado. A publicação recalcula o hash a partir
da quarentena e o compara com o hash aprovado. **Toda versão exige aprovação
explícita de todos os seus arquivos**, inclusive os inalterados. A UI informa
"inalterado — aprovado anteriormente por X".

## Consequências
- Mais cliques para o admin, mas a regra "toda nova versão passa pelas duas etapas" é cumprida literalmente.
- A herança pode ser adotada no futuro por um novo ADR, porque o modelo já guarda o hash de cada aprovação.
