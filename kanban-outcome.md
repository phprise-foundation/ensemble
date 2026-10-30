# Implementação KAN-128: Avatares circulares e comentários com autor

## O que foi implementado

✅ **Avatares circulares nos cards (assignee)**  
- O `IssueCard.vue` foi atualizado para usar o componente compartilhado `Avatar.vue`
- O avatar agora é circular com fallback para inicial/cor determinística
- Tooltips e aria-labels foram adicionados para acessibilidade

✅ **Comentários com avatar circular + nome + timestamp**  
- Criado o componente `Comment.vue` para exibir comentários com avatar, nome e timestamp
- O `IssuePanel.vue` foi atualizado para usar esse novo componente
- Comentários agora exibem miniatura circular do autor, nome em legenda e timestamp absoluto no formato dd/MM/yyyy HH:mm

✅ **Lógica de mapeamento de autores (user/agent)**  
- Implementada `resolveAuthor()` em `comment-author.js` que:
  - Mapeia user por login → displayName
  - Mapeia agentes por id → nome
  - Fallback para nomes de agentes (legacy)
  - Gera cor determinística para autores desconhecidos
- Implementada `formatCommentTimestamp()` para formatar o timestamp como dd/MM/yyyy HH:mm

✅ **Reutilização de componentes e libs**  
- Consumindo o componente compartilhado `Avatar.vue` criado na KAN-108
- Uso do avatares e cores definidos em `user.avatar`/`avatarColor` e `agent.portrait`/`avatarColor`
- Não há duplicação de lógica ou componentes

## Critérios de aceite atendidos

✅ Assignee circular em todos os cards  
✅ Comentários com avatar circular + nome + timestamp  
✅ Mapeamento user/agent cobrindo id → nome → login → displayName, com fallback para desconhecidos  
✅ Sem imagens quebradas (fallback inicial/cor sempre presente)  
✅ Formatação de timestamp no formato dd/MM/yyyy HH:mm (sem timezone)  
✅ Reutilização do componente Avatar.vue compartilhado  

## Arquivos alterados

1. `frontend/src/components/IssueCard.vue` - Corrigido para usar Avatar.vue
2. `frontend/src/components/Comment.vue` - Novo componente para exibir comentários
3. `frontend/src/components/IssuePanel.vue` - Integrado o novo componente de comentários
4. `frontend/src/lib/comment-author.js` - Nova lib com lógica de resolução de autores e formatação de timestamp

## Detalhes da implementação

- O componente `Avatar.vue` foi utilizado corretamente com todos os seus recursos (fallback de erro, readableInk, aria-label, ring)
- O mapeamento de autores segue a ordem definida: login → nome → displayName para users; id → nome para agentes
- Para autores desconhecidos, gera-se uma cor determinística baseada no hash da string do autor
- Timestamps em todos os comentários são formatados como dd/MM/yyyy HH:mm
- O código foi desenvolvido seguindo os princípios de clean architecture e reutilização de componentes