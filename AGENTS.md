# AGENTS.md

## Nome e caminho canônico (INVIOLÁVEL)

- O guarda-chuva se chama **PHPRise** — composição de **PHP + Rise** — e se
  escreve com **dois P**.
- O produto deste repositório se chama **Ensemble**: agentes organizados como
  uma equipe de desenvolvimento real, cada um com papel, perfil e etapa, num
  board que os orquestra do problema de negócio ao resultado.
- O único caminho válido é `/home/esdras/src/phprise/ensemble` (dois `p`:
  `phprise`).
- O caminho antigo `/home/esdras/src/phprise/multi-agent` foi aposentado no
  rename para Ensemble; **não** o recrie.
- `/home/esdras/src/phrise` (sem o segundo `p`) **não é um caminho deste
  projeto**. Nenhum documento humano o define; ele só existe por erro de
  agentes anteriores.
- Nunca crie, clone, mova, copie, gere worktree ou grave qualquer arquivo em
  `/home/esdras/src/phrise` ou em caminho que comece com ele.
- Antes de qualquer comando de shell, git, leitura ou escrita, confirme `pwd`
  (ou use caminho absoluto) e garanta que começa com
  `/home/esdras/src/phprise/`.
- Se o ambiente abrir em `phrise` ou em qualquer variante sem o segundo `p`,
  **pare** e reporte ao humano; não tente “consertar” permissões nem recriar
  diretórios.
- Referências antigas a `phrise` em docs/issues são dívida histórica: corrija
  para `phprise` quando o item pedir, nunca como justificativa para trabalhar no
  caminho errado.

> **Before you start:** read [`docs/STATE.md`](docs/STATE.md) to know where the
> project stands, what was decided and the next steps. For what hasn't started
> yet, see [`docs/ROADMAP.md`](docs/ROADMAP.md). The code layout is in
> [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Documents that drive development

| Document | When to consult |
|---|---|
| [`docs/PHILOSOPHY.md`](docs/PHILOSOPHY.md) | **Always.** It holds the unbreakable rules. All development is driven by it. |
| [`docs/GUIDE.md`](docs/GUIDE.md) | When in doubt about **how to apply** a rule from PHILOSOPHY. If there is no clear example, decide together and add the example to the GUIDE. |
| [`docs/GITFLOW.md`](docs/GITFLOW.md) | For **any git operation** (branch, commit, PR, tag, release). If a situation has no guidance, ask how to proceed and then add it to the document. |
| [`docs/STATE.md`](docs/STATE.md) | When resuming work. Tells where we stopped and what was decided. |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | To know what hasn't started yet. |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | To understand the layers, flow and contracts before touching the code. |

## What this project is

**Ensemble** orchestrates [agent-watcher](https://github.com/phprise-foundation/agent-watcher)
and provides a self-hosted kanban board with authentication. Agents are treated
as a real development team: each one has a role, a profile and a stage to own,
and the board orchestrates them from the business problem to the outcome. It is
not a full-stack framework: it is a lean application, built with DDD and Clean
Architecture following The OTAKU Manifesto.

Read `composer.json` for the exact PHP and dependency versions. The main
dependencies are `phprise/common` and `guzzlehttp/psr7`.

## Architecture (summary)

Dependencies point **inward**, towards the `Domain`:

```
Interface  →  Application  →  Domain
Infrastructure  ──────────────┘
```

- `Domain/` — the heart. Entities, value objects, contracts (interfaces) and
  exceptions. Does **not** know about HTTP, JSON or files.
- `Application/` — use cases. Orchestrates the domain. Depends only on `Domain` contracts.
- `Infrastructure/` — adapters. Implements the `Domain` contracts (SQLite
  persistence, password auth, HTTP chat client).
- `Interface/` — entry points. Controllers.

Details in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Code rules (non-negotiable)

The 9 Object Calisthenics rules of [`docs/PHILOSOPHY.md`](docs/PHILOSOPHY.md) are
mandatory. In short:

1. One level of indentation per method.
2. No `else`.
3. Wrap primitives and strings (value objects).
4. First-class collections.
5. One dot per line (Law of Demeter).
6. Don't abbreviate.
7. Small entities (≤50 lines per class, ≤10 files per package).
8. At most 2 instance variables per class.
9. No getters/setters/properties.

## Conventions

- **Strict types:** `declare(strict_types=1);` in every file.
- **No comments** in code. The code must explain itself; the "why" goes in the commit or the GUIDE.
- **Attributes** instead of annotations (`#[Override]`).
- **Interfaces** for public methods.
- **No `switch/case`.** Use `match` or polymorphism.
- **No generic types** (`mixed`, `object`, `array`) when there is an alternative.
  When unavoidable, annotate with generics in the docblock.
- **PHP tag:** only `<?php` on the first line, `declare(strict_types=1);` on the
  third, namespace, and **never** close with `?>`.
- **Cleanliness:** remove unused imports, comments, variables and private functions.

## Workflow

- Run tests with `vendor/bin/phpunit`.
- Run static analysis with `vendor/bin/psalm`.
- A feature is only done when it has a test that exercises it **as a caller would
  use it** (a service call or an HTTP request), not merely "it didn't throw".
- Before writing infrastructure by hand, check whether `phprise/common` or
  `guzzlehttp/psr7` already cover the case.

## Discover, don't guess

- Read the installed code under `vendor/` before assuming an API.
- Use grep/search to locate what already exists before creating something new.
- If the task doesn't specify something, **ask** instead of guessing. If there is
  no interactive channel, state the assumption and pick the simplest option.
