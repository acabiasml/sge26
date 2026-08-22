# Instruções operacionais do projeto

## Entrega e verificação em produção

- Para toda modificação solicitada, conclua a implementação e as verificações locais proporcionais ao risco.
- Antes de publicar, sincronize com o repositório remoto sem descartar alterações existentes do usuário.
- Faça commit das alterações concluídas e envie a branch correspondente ao GitHub.
- Este projeto usa deploy automático do GitHub na Hostinger. Após o push, aguarde o deploy e verifique no ambiente de produção se o commit publicado está ativo.
- Em toda entrega, acesse a produção via SSH e confirme o estado do deploy, a saúde básica da aplicação e o status das migrations. Rode migrations pendentes apenas quando fizerem parte da alteração e o deploy automático não as tiver aplicado.
- Nunca grave senhas, chaves privadas, tokens ou outros segredos neste repositório, nos commits ou em scripts versionados.
- Se autenticação, GitHub, Hostinger ou o deploy estiverem indisponíveis, não declare a entrega concluída: informe claramente o bloqueio e a evidência obtida.
