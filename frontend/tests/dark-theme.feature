# language: pt-BR
Funcionalidade: Tema escuro alinhado à paleta do VSCode Dark+
  Origem: RFC KAN-8. O usuário que trabalha diariamente no VSCode compara o
  quadro, lado a lado, com o VSCode Dark+ e valida o resultado sem ajustes
  adicionais (critério de aceitação).

  Contratos derivados do item e dos comentários:
  - ADR (David): valores exatos do Dark+ nos quatro aspectos (fundo, bordas,
    syntax, seleção); tema é 100% frontend, tokens CSS em
    ":root[data-theme='dark']" em frontend/src/styles/main.css; nenhum contrato
    REST muda; o tema persiste em localStorage ("multi-agent-theme").
  - Guia de implementação (Lucy): tabela de tokens alvo e regra de ::selection.
  - Fases MVE (Jarvis): Fase 1 = fundo + bordas; Fase 2 = seleção + syntax.
    As fases podem aterrissar separadas; o contrato final cobre as duas.
  - Premissa: valores em hex minúsculo, literal.
  - Prova de não-regressão: tema claro e tokens de estado (danger/warn/ok)
    congelados.

  Desvios conscientes da fidelidade ao Dark+ (não são cenários bloqueantes; a
  decisão final é do usuário no "lado a lado"):
  - borda #454545 sobre #1e1e1e ~ 1.74:1 (WCAG 1.4.11 de não-texto pede 3:1);
  - muted #858585 sobre #252526 ~ 4.15:1 e sobre #2d2d2d ~ 3.73:1 em labels de
    11-13px (AA pede 4.5:1);
  - #ffffff sobre --accent #3794ff ~ 3.07:1 em botões (o VSCode usa #0E639C em
    botões, ~ 4.5:1 com branco; #3794ff é a cor de links/foco).

  Esquema do Cenário: Tokens base fiéis ao Dark+
    Dado que o tema escuro está aplicado a partir de 'data-theme="dark"'
    Então o token <token> vale <valor>

    Exemplos:
      | token             | valor   |
      | --bg              | #1e1e1e |
      | --surface         | #252526 |
      | --surface-2       | #2d2d2d |
      | --surface-3       | #333333 |
      | --border          | #454545 |
      | --text            | #d4d4d4 |
      | --muted           | #858585 |
      | --accent          | #3794ff |
      | --accent-contrast | #ffffff |
      | --selection       | #264f78 |

  Esquema do Cenário: Tokens de syntax prontos para blocos de código
    Dado que o tema escuro está aplicado
    Então o token <token> vale <valor>

    Exemplos:
      | token             | valor   |
      | --syntax-string   | #ce9178 |
      | --syntax-keyword  | #c586c0 |
      | --syntax-function | #dcdcaa |
      | --syntax-type     | #4ec9b0 |
      | --syntax-comment  | #6a9955 |
      | --syntax-number   | #b5cea8 |

  Cenário: Seleção de texto usa o destaque do Dark+
    Dado que o tema escuro está aplicado
    Quando o usuário seleciona um texto na interface
    Então o fundo da seleção é o token --selection
    E o texto da seleção é o token --text
    E o contraste entre --text e --selection é de pelo menos 4.5:1

  Cenário: Destaque de item do quadro usa a cor de seleção
    Dado que o tema escuro está aplicado
    Quando um item do quadro entra em um estado de seleção ou destaque
    Então a cor de destaque do estado vem do token --selection
    E nenhuma cor de seleção é definida ad hoc fora da paleta

  Esquema do Cenário: Contraste do texto atende WCAG AA no tema escuro
    Dado que o tema escuro está aplicado
    Então <frente> sobre <fundo> tem contraste de pelo menos 4.5:1

    Exemplos:
      | frente   | fundo       |
      | --text   | --bg        |
      | --text   | --surface   |
      | --text   | --surface-2 |
      | --text   | --surface-3 |
      | --accent | --bg        |
      | --accent | --surface   |
      | --muted  | --bg        |

  Cenário: Seleção de texto continua com cor definida no tema claro
    Dado que o tema claro está aplicado
    Quando a regra de seleção de texto é avaliada
    Então o conjunto de regras e tokens resolve cores nos dois temas
    E a ::selection é escopada ao tema escuro ou o --selection também está
    definido no bloco do tema claro

  Esquema do Cenário: Tema claro congelado (sem regressão)
    Dado que o tema claro está aplicado
    Então o token <token> mantém o valor vigente em 2026-10-15

    Exemplos:
      | token             | valor    |
      | --bg              | #f4f5f7  |
      | --surface         | #ffffff  |
      | --surface-2       | #f0f2f5  |
      | --surface-3       | #e9ecf1  |
      | --border          | #dfe1e6  |
      | --text            | #172b4d  |
      | --muted           | #6b778c  |
      | --accent          | #0052cc  |
      | --accent-contrast | #ffffff  |

  Cenário: Tokens de estado permanecem no tema escuro
    Dado que o tema escuro está aplicado
    Então os tokens --danger, --warn e --ok continuam declarados
