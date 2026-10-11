# 0003 — Operações Git em serviço sandbox, sem checkout

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
Repositórios são entrada hostil: exploram CVEs do git (symlinks e submodules no
checkout), protocolos perigosos, SSRF e exaustão de recursos. Rodar `git` no
container da aplicação exporia DB, Redis, segredos e storage.

Lançar um container efêmero por job exigiria dar acesso ao socket do Docker a algum
componente, o que equivale a root no host e é um risco maior do que o que se quer evitar.

## Decisão
Um serviço `git-sandbox` dedicado e de vida longa, com cada requisição usando um
workspace `tmpfs` próprio e apagado ao final. O serviço:
- não tem acesso a DB, Redis, volumes da aplicação nem redes internas. Fica numa rede `internal: true`, cuja única saída é o `egress-proxy`: um proxy `CONNECT` com allowlist (`github.com`, `gitlab.com`, `codeberg.org`, porta 443). Isso impede, no nível da rede, o acesso à LAN do servidor e aos outros projetos do host, sem depender de firewall no host;
- roda como não-root, com rootfs read-only, `cap_drop: ALL`, `no-new-privileges` e limites de CPU, memória e pids;
- cria um repositório **bare**, faz `fetch --depth=1` e lê árvore e blobs com `ls-tree`/`cat-file`, **sem checkout**;
- devolve um manifesto. Toda a validação é refeita pelo Laravel (zero trust também na sandbox).

## Consequências
- Elimina a classe inteira de ataques de checkout e mantém o socket do Docker fora do sistema.
- Um comprometimento da sandbox persiste entre requisições até o restart. Mitigação: o container é reiniciado periodicamente e não tem nada de valor para roubar.
- Evolução possível: container efêmero por job com runtime isolado (gVisor/Firecracker) através de um orquestrador dedicado.
