# Instruções operacionais do projeto

## Fluxo obrigatório para qualquer mudança de código

- Toda alteração de código deve ser feita com confirmação explícita do objetivo, do escopo e do impacto esperado.
- Após a implementação, o agente deve validar localmente o comportamento com testes relevantes e com o ambiente correto do projeto.
- Sempre que houver mudança de código, o agente deve registrar a mudança em português, com mensagem de commit em português.
- O commit deve ser feito imediatamente após a validação local, e em seguida o push deve ser executado para o repositório remoto.
- Após o push, o agente deve aguardar o deploy automático da Hostinger e verificar, via SSH, se o commit publicado chegou ao servidor e se as migrations foram executadas corretamente.
- Se o deploy automático não estiver disponível ou o commit não estiver visível em produção, a entrega não pode ser declarada como concluída.

## Regras de execução e testes

- Este projeto usa PHP 8.4.*. Não mudar para PHP 8.5 sem aprovação explícita e atualização de dependências compatíveis.
- O ambiente de teste deve ser executado com PHP 8.4 em container, usando um banco SQLite em memória para evitar depender da base de produção em validações locais.
- O ambiente seguro de teste deve ter estas configurações de base:
  - APP_ENV=testing
  - DB_CONNECTION=sqlite
  - DB_DATABASE=:memory:
  - CACHE_STORE=array
  - SESSION_DRIVER=array
  - QUEUE_CONNECTION=sync
  - MAIL_MAILER=array
- Para testes com geração de PDF, a extensão GD precisa estar instalada e habilitada. Não prosseguir como se a falha fosse de aplicação quando o erro for de extensão.
- A execução de testes deve usar memória elevada quando necessário, por exemplo `php -d memory_limit=4G artisan test`.
- Em caso de erro de memória, questão de GD, permissões de storage ou diretório de testes, localizar a causa do ambiente antes de assumir bug de lógica de negócio.

## Regras de publicação e verificação em produção

- Antes de publicar, sincronizar com o repositório remoto sem descartar alterações existentes do usuário.
- Fazer commit das alterações concluídas e enviar a branch correspondente ao GitHub.
- Este projeto usa deploy automático do GitHub na Hostinger. Após o push, aguardar o deploy e verificar no ambiente de produção se o commit publicado está ativo.
- Em toda entrega, acessar a produção via SSH e confirmar:
  - se o deploy foi concluído
  - se o código do commit esperado está no servidor
  - se a aplicação responde sem erros básicos
  - se as migrations pendentes já foram executadas
  - se houve falhas de deploy ou de execução que exijam correção
- Rode migrations pendentes apenas quando fizerem parte da alteração e o deploy automático não as tiver aplicado.
- Nunca gravar senhas, chaves privadas, tokens ou outros segredos neste repositório, nos commits ou em scripts versionados.
- Se autenticação, GitHub, Hostinger, SSH ou o deploy estiverem indisponíveis, não declarar a entrega concluída: informar claramente o bloqueio e a evidência obtida.

## Mensagem de commit exigida

- Toda mensagem de commit deve ser escrita em português.
- Preferir formato simples e objetivo, por exemplo:
  - "corrige ambiente de testes php 8.4 e valida suite"
  - "ajusta fluxo de deploy e documentação operacional"
  - "resolve falha de gd nos testes de pdf"
- A mensagem deve descrever o que foi feito e o motivo, sem misturar detalhes irrelevantes.

## Registro para IA e agentes

- Este arquivo é a fonte de verdade do fluxo operacional do projeto.
- Qualquer agente ou IA deve seguir estas instruções antes de declarar que uma alteração está pronta.
- Não repetir explicações de ambiente, deploy, execução de testes ou processo de produção a cada vez; a execução deve seguir este documento e a documentação complementar do projeto.
