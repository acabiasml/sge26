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

## Parte diversificada no Ensino Médio

Para identificar de quem são os históricos e quais matrizes usam essa denominação,
execute `python3 scripts/prepare-history-query.py --scope high-school-diversified
--key-directory /CAMINHO_PRIVADO_FORA_DO_REPOSITORIO` em uma única linha. Publique
a configuração e a chave pública pelo mesmo fluxo descrito acima. A consulta
retorna somente identificação dos estudantes/históricos, escolas, anos e nomes
dos componentes desse recorte; não consulta notas, faltas ou documentos pessoais.
O resultado continua criptografado, em transação somente de leitura. O workflow
aguarda o commit chegar ao servidor antes de executar a consulta.

## Conferência de carga horária por estudante e ano

O escopo `student-workload` aceita `person_id: 0`, `name_sha256` (SHA-256 do
nome completo em minúsculas, sem espaços nas extremidades) e `reference_year`.
Use uma chave pública nova e mantenha a chave privada fora do checkout, pelo
mesmo fluxo de publicação e leitura. O nome em texto não deve ser publicado.
Somente uma correspondência permite retornar dados. A consulta compara as
cargas registradas no histórico com componentes, aulas semanais, duração da
hora-aula e períodos das matrizes do ano escolhido. Não emite documento,
não sincroniza histórico e não grava registros; o relatório segue criptografado.

O escopo `student-transfer-grades` usa os mesmos seletores e retorna as notas
persistidas, datas das avaliações, médias exibidas por bimestre e uma prévia
privada da ficha individual. A prévia não registra emissão. Tanto os dados
quanto o PDF ficam exclusivamente no relatório criptografado.
Para divergência na grafia do sobrenome, `name_words` limita a comparação às
primeiras palavras (1 a 20). Mais de uma correspondência impede a consulta
das notas; após identificar a pessoa, prefira voltar ao nome completo.
O parâmetro opcional `simulated_transfer_date` (AAAA-MM-DD) monta a ficha com
uma cópia da matrícula em memória. O relatório identifica explicitamente essa
simulação e inclui separadamente a situação efetivamente lida do banco.
Não registra nem desfaz uma transferência real.

## Classificação dos componentes de Móveis

O escopo `--scope history-catalog-classification` consulta somente as áreas de
Técnico em Móveis, definições vinculadas, matrizes, referências históricas e
auditoria das edições dos componentes dessas matrizes. Não consulta notas, faltas
ou documentos pessoais. Use o mesmo fluxo de chave externa, publicação e leitura
do relatório criptografado. A consulta não executa correções.
