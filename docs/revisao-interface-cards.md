# Revisão da organização das telas por cards

Revisão de 15/11/2026, autorizada para estender às demais telas o padrão aplicado ao cadastro de pessoas.

O inventário inclui as 61 telas que usam o layout administrativo, os respectivos formulários e listagens Livewire, e as páginas públicas de entrada e verificação documental. A organização foi avaliada por finalidade: consulta de assuntos independentes, edição de um único cadastro ou execução de uma tarefa sequencial.

| Área | Resultado da revisão |
| --- | --- |
| Cadastro e edição de pessoas | Cards de dados pessoais, contatos, endereço e ficha socioeconômica. No cadastro, card adicional para o vínculo inicial. |
| Meu cadastro | Mantida a divisão existente; validação nativa passa a usar o mesmo mecanismo dos demais formulários. |
| Cadastro e edição de escolas | Cards de dados e contatos, dados para documentos e endereço. O estado ativo continua disponível junto ao salvamento. |
| Conceitos e critérios | Cards para tabela, cadastro/edição e dependência/padrão sugerido. Links de editar e cancelar apontam explicitamente para a seção correspondente. |
| Documentos oficiais | Cards de emitidos e novo documento/cópia, com a largura disponível para a listagem e o editor. Para quem consulta documentos, a listagem permanece diretamente acessível. |
| Emissão de documentos | Mantido o fluxo sequencial de destinatário e opções de emissão. |
| Boletim | Resumo anual, um card para cada período e escola de origem conforme a permissão existente. |
| Recados | Cards de consulta e novo recado, mantendo a listagem Livewire. |
| Anos letivos, períodos, matrizes, turmas e componentes | Mantidos os cards existentes. A conferência de fechamento recebe o mesmo estilo de navegação. |
| Horários | Mantidos os cards. A seção de versões exibe uma mensagem e acesso à criação quando a turma ainda não possui versões. |
| Matrículas, vida escolar e diários do estudante | Mantidas as divisões existentes por contexto, consulta e documentos. |
| Históricos escolares | Mantidos os cards de etapas e os grupos de anos; os formulários de edição preservam a sequência de lançamento. |
| Diários docentes, frequência e conteúdo | Mantidos os cards de consulta e os editores por período/data. |
| Dashboard, horários pessoais e listagens administrativas | Mantidos os indicadores, filtros e cards adequados a cada consulta. |
| Conformidade, catálogo curricular e auditoria | Mantidos os agrupamentos existentes por ocorrência, registro e finalidade. |
| Entrada, verificação documental e erros | Mantida a apresentação específica de cada finalidade. |

## Comportamento compartilhado

O componente `section-tabs` reutiliza o estilo e a navegação por teclado existentes. Nos formulários, seleciona a seção do primeiro erro retornado pelo servidor. Os campos continuam no mesmo formulário e seus valores permanecem disponíveis ao alternar os cards.

A validação nativa revela a seção e os detalhes que contêm o primeiro campo inválido antes de o navegador focá-lo. Os links do resumo de erros priorizam o campo na seção visível quando vários formulários usam o mesmo nome de campo.

Os novos textos também possuem tradução italiana. As mudanças são de apresentação e navegação, sem migrations ou alterações nas regras de autorização e de cálculo.

## Validação

- PHP 8.4 em container, SQLite em memória para os testes e extensões do projeto habilitadas.
- Suíte completa: 1.160 testes e 8.371 verificações aprovados.
- Verificações adicionais de calendário/horários, conceitos, cadastro, idioma e mensagens após os ajustes da revisão.
- Navegação automatizada no Chromium com dados fictícios, nas larguras de 390 e 1.440 pixels, incluindo troca de seções, formulários, trajetória com guias internas e telas dos perfis de administração e estudante.
- Fluxos específicos de docência, validação nativa e de servidor, preservação de valores, edição de conceitos, estado vazio dos horários e editor de documentos com atualização Livewire.

A publicação segue o fluxo do `AGENTS.md`: commit, push, deploy automático e diagnóstico por SSH do commit ativo e das migrations, acompanhado da verificação HTTP.
