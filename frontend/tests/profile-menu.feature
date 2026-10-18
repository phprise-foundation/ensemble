# language: pt-BR
Funcionalidade: Menu de perfil do usuário no topo direito
  Origem: KAN-15 (RFC aprovada) e guia docs/KAN-15-guide.md.

  Contratos derivados do item e dos comentários:
  - Foto: placeholder fixo e determinístico (inicial do usuário em círculo de
    38px, contorno neutro). Sem upload, sem presets e sem troca de foto.
  - Perfil: edita apenas o nome de exibição; teto de 40 caracteres na entrada.
    O nome é rótulo e jamais altera a credencial de login.
  - Exibição: linha única com reticências (…) e largura máxima fixa (≈14
    caracteres no desktop, ≈11 em tela pequena). O nome completo permanece no
    dado, no rótulo acessível (title/aria-label) e por inteiro na tela de profile.
  - Sem nome definido: mostra o identificador de login — nunca um estado vazio.
  - Logout: direto, sem confirmação. Leitura de produto (ii), congelada pela PO
    em 15:06: encerra a sessão e a área protegida fica inacessível; o destino
    literal "tela de login" torna-se pleno na Fase 2, com o login por usuário.
  - Persistência do nome: vive apenas no protótipo (backend fake); o backend PHP
    real permanece intocado nesta rodada.
  - "profile" (menu do usuário) ≠ "Profiles" (papéis do Time): rota /profile
    distinta de /teams/:teamId/profiles.

  Cenário: Menu de perfil visível no topo direito
    Dado que a aplicação autenticada está carregada
    Então um avatar circular de 38px aparece no topo direito
    E o nome de exibição aparece abaixo do avatar
    E um dropdown oferece as opções "profile" e "logout"

  Cenário: Nome de exibição longo é truncado em linha única
    Dado que o usuário tem um nome de exibição acima da largura máxima da tela
    Então o nome no topo é exibido em linha única com reticências (…)
    E o nome completo permanece no rótulo acessível (title/aria-label)
    E o topo não quebra em duas linhas nem empurra o layout

  Cenário: Sem nome de exibição, o menu mostra o login
    Dado que o usuário não definiu um nome de exibição
    Então o menu exibe o identificador de login no lugar do nome
    E o avatar mostra a inicial do identificador de login
    E o topo não apresenta um estado vazio quebrado

  Cenário: Perfil edita o nome de exibição
    Dado que o usuário abre a opção "profile"
    Quando informa um nome de exibição com até 40 caracteres e salva
    Então o novo nome passa a ser exibido no menu do topo
    E o nome completo aparece por inteiro na tela de profile

  Cenário: Recarregar a tela de profile mostra o nome persistido no campo
    Dado que o usuário salvou um novo nome de exibição
    Quando a tela de profile é recarregada
    Então o campo do nome de exibição volta com o nome persistido
    E o campo de login, somente leitura, mantém a credencial inalterada

  Cenário: Nome acima de 40 caracteres é recusado
    Dado que o usuário está na tela de profile
    Quando informa um nome de exibição com mais de 40 caracteres e salva
    Então a alteração é recusada com uma mensagem clara
    E o nome de exibição anterior permanece

  Cenário: Editar o nome nunca altera a credencial de login
    Dado que o usuário altera o nome de exibição
    Então o identificador de login permanece o mesmo
    E a credencial de login nunca é enviada nem alterada
    E na tela de profile o campo de login, somente leitura, mantém o valor de
    antes de salvar

  Cenário: Logout encerra a sessão e torna a área protegida inacessível
    Dado que o menu de perfil está aberto
    Quando o usuário seleciona "logout"
    Então a sessão é encerrada de forma direta, sem confirmação
    E nenhum conteúdo protegido permanece renderizado
    E a vista de fim de sessão oferece "Sign in again" (protótipo até a tela de
    login real da Fase 2)

  Cenário: O menu de perfil convive com o acionador do drawer no topo
    Dado que a tela está em desktop ou em tela pequena
    Então o acionador do drawer permanece à esquerda e o menu de perfil à direita
    E o nome truncado não sobrepõe nem empurra os controles do topo
