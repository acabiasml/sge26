# Consulta protegida de históricos em produção

O workflow **Consulta protegida de histórico** permite consultar a base por SSH
no runner do GitHub quando este ambiente não alcança a Hostinger diretamente.
Usa os segredos já existentes `HOSTINGER_CONNECTION` e `HOSTINGER_KNOWN_HOSTS`.
Não cria uma página pública de consulta nem habilita SQL arbitrário.

A consulta é limitada ao estudante selecionado e executa em transação MySQL
somente de leitura, encerrada com rollback. O resultado inclui históricos,
anos, contagens de notas e auditoria disponível. Não chama sincronizadores,
não salva modelos e não restaura ou altera dados.

## Executar uma nova consulta

1. Identifique o número da pessoa no endereço do cadastro do Beabá.
2. No checkout, execute `python3 scripts/prepare-history-query.py ID_DA_PESSOA`.
   O comando requer `cryptography`. Ele gera uma chave privada RSA fora do
   checkout e atualiza apenas a chave pública e o identificador consultado.
   Guarde o caminho da chave privada: ela não pode ser publicada ou perdida.
3. Revise e publique `scripts/history-query.json` e
   `scripts/history-report-public.pem` na `main`. O push dispara a consulta.
   A preparação gera uma chave nova mesmo quando a pessoa não muda.
4. Confirme a execução em GitHub Actions. Se a API estiver bloqueada, a página
   pública de Actions permite conferir o estado. A criação de uma branch
   `consulta-historico-ID_DA_EXECUCAO` confirma que o resultado foi produzido.
5. Recolha por Git: `git fetch origin refs/heads/consulta-historico-ID_DA_EXECUCAO`
   e `git show FETCH_HEAD:report.json > /CAMINHO_FORA_DO_CHECKOUT/report.json`.
6. Descriptografe localmente com
   `python3 scripts/read-history-query.py RELATORIO_CIFRADO CHAVE_PRIVADA ARQUIVO_PRIVADO`.
   O comando não sobrescreve arquivos existentes. O envelope é
   JSON com `key`, `nonce` e `ciphertext` em base64. A chave AES foi encapsulada
   com RSA-OAEP SHA-256; o conteúdo usa AES-256-GCM. Salve o resultado privado
   fora do checkout com permissão 0600 e mostre apenas os dados necessários.

As branches de consulta contêm somente conteúdo criptografado, sem parentesco
com a `main`; nunca devem ser integradas à aplicação. Não publique relatório
descriptografado, chave privada, senha ou arquivo `.env`. O arquivo de alvo
criptografado anterior é compatibilidade para a busca inicial por nome; com um
ID positivo a consulta não depende da chave da aplicação fornecida localmente.

O caminho depende do GitHub Actions e do SSH/segredos continuarem disponíveis.
Não é garantia de disponibilidade de serviços externos nem substitui backups.
Uma consulta mostra o estado atual; auditoria incompleta não prova a causa de
uma perda nem permite reconstruir automaticamente dados apagados.
