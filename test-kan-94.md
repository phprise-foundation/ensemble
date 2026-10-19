# Teste da implementação KAN-94

Vamos validar os seguintes requisitos:

1. **Layout**: Os campos Summary, Description, Type, Status e Assignee devem ocupar 100% da largura útil do modal
2. **Status Inicial**: Apenas status com `type === 'initial'` devem ser exibidos no seletor de status
3. **Default Status**: Quando nenhum status é fornecido, deve usar o primeiro status do tipo 'initial' no workflow
4. **Backend**: O fake backend deve rejeitar com 422 se for fornecido um status que não seja 'initial'
5. **Backend**: O fake backend deve rejeitar com 422 se for fornecido status inexistente ou de workflow diferente
6. **Backend**: O fake backend deve aceitar se nenhum status for fornecido, mas usar o status 'initial' 

## Como testar manualmente:
1. Abrir o modal de criação de tarefa
2. Verificar que todos os campos tenham largura total (display: block e width: 100%)
3. Verificar que o seletor de status só mostra status com type='initial'
4. Verificar que o status padrão é o primeiro status inicial no workflow
5. Tentar criar uma issue com status 'final' ou 'transitional' - deve falhar com 422
6. Tentar criar uma issue com status inexistente - deve falhar com 422
7. Criar uma issue sem status - deve funcionar e usar o primeiro status inicial