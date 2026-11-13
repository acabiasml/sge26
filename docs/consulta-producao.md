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
   `consulta-historico-ID_DA_EXECUCAO-NUMERO_DA_TENTATIVA` confirma que o resultado
   foi produzido. O nome exato aparece no resumo da execução. Cada reexecução
   incrementa a tentativa e preserva os relatórios anteriores.
5. Recolha por Git:
   `git fetch origin refs/heads/consulta-historico-ID_DA_EXECUCAO-NUMERO_DA_TENTATIVA`
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

Relatórios antigos continuam nas branches `consulta-historico-ID_DA_EXECUCAO`,
sem o número da tentativa. Reexecutar uma execução anterior à correção usa o
workflow do commit antigo e ainda pode falhar com `fetch first` ao publicar o
relatório. Para usar a correção, execute uma nova consulta a partir de um commit
que já contenha o nome de branch por tentativa. Não use force push nem tente
mesclar os commits independentes dos relatórios.

O caminho depende do GitHub Actions e do SSH/segredos continuarem disponíveis.
Não é garantia de disponibilidade de serviços externos nem substitui backups.
Uma consulta mostra o estado atual; auditoria incompleta não prova a causa de
uma perda nem permite reconstruir automaticamente dados apagados.

O escopo `diary-batch` confere até 20 turmas expressamente selecionadas em
`classes` (`reference_year` e `name`), limitadas à fonte `lar`, `laura` ou `beaba`.
Retorna matrizes, notas, chamadas e conteúdos do recorte em transação somente
de leitura. Identidades, resultados e demais dados continuam restritos ao
relatório criptografado; a conferência não executa importação ou correção.
O parâmetro opcional `transport_key_id`, com 32 caracteres hexadecimais,
prepara o mesmo transporte RSA privado no servidor para um lançamento posterior.
O relatório inclui a chave pública, os vínculos, o calendário e as atribuições.

O escopo `student-reenrollment` usa `person_id` positivo e `reference_year`.
Consulta os vínculos desse estudante no ano e, se o dump privado original ainda
estiver disponível, lê somente a tabela legada `turmas` e retorna apenas as
linhas da mesma pessoa e dos cursos correspondentes. Não executa SQL do dump,
não importa matrículas e não altera as datas. A consulta informa a disponibilidade
e o hash da fonte; seu resultado permanece criptografado.

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

## Reconciliação de uma turma a partir dos diários originais

O escopo `class-diary-reconciliation` seleciona a turma por `person_id` e
`reference_year`, com `transport_key_id` aleatório de 32 caracteres hexadecimais.
Ele consulta a turma em transação somente de leitura e prepara uma chave RSA
privada exclusivamente em `storage/app/private/diary-reconciliation` no servidor.
O relatório criptografado contém somente a chave pública desse transporte.

Extraia os PDFs com `scripts/extract_legacy_diary_pdfs.py`. Confira todos os
estudantes, períodos, aulas, notas e divergências da matriz. O manifesto privado
inclui o SHA-256 de cada PDF, o alvo exato (escola, ano, turma e matriz) e a matriz
confirmada pelo usuário. Criptografe com AES-256-GCM e encapsule a chave AES com
RSA-OAEP SHA-1, compatível com o OpenSSL do PHP 8.4. Somente o envelope
`scripts/diary-reconciliation.encrypted.json` é publicado; nunca o manifesto
legível ou a chave privada. O envelope contém `key`, `nonce`, `ciphertext` e `tag`
em base64. A chave de transporte permanece no servidor, com permissão 0600.

O workflow **Reconciliação protegida de diários documentados** lê
`scripts/diary-reconciliation.json`, que contém `transport_key_id` e `mode`.
Publique primeiro `mode: dry-run`. Revise o relatório criptografado e só então
publique `mode: apply` com `expected_snapshot_sha256` da conferência anterior.
A aplicação aborta se a turma mudou ou houver conflito de identidade, notas,
presenças, quantidade de aulas ou período. Não há substituição de notas nem de
chamadas existentes. São preenchidas chamadas e conteúdos ausentes, ajustada
somente a matriz selecionada e atualizadas as cargas/frequências dos anos de
histórico com origem `system` vinculados às matrículas dessa turma.

Antes da gravação, uma cópia privada dos registros é salva no servidor. Todas
as mudanças de banco são transacionais; a auditoria referencia o manifesto e
essa cópia. O resultado sai apenas criptografado na branch
`reconciliacao-diarios-ID_DA_EXECUCAO`, pelo mesmo leitor de relatórios privados.
Ao concluir, volte a `mode: dry-run` para verificar que não restam chamadas ou
marcação de presença por preencher. As branches de relatório não são integradas
à aplicação. A duração da aula e a carga prevista são decisões documentais:
não devem ser deduzidas da contagem de conteúdos ou da aprovação dos estudantes.

### Lote autorizado por diários originais

`operation: diary-batch` usa o mesmo transporte criptografado e um manifesto
`legacy-diary-batch-v1`, com `classes` contendo até 20 manifestos individuais.
Turmas e matrizes devem ser distintas. O hash da simulação inclui o estado e
o manifesto de cada turma; a aplicação inteira ocorre em uma única transação.
Cada turma mantém sua cópia privada anterior e auditoria. As notas persistidas
são verificadas antes e depois da gravação.

Somente quando o usuário declarou a fonte correta, `source_authoritative: true`
permite corrigir presenças divergentes e retirar marcações cuja data tenha `#`
explícito (`not_enrolled`) no PDF. Ausência de uma linha não autoriza exclusão.
`allow_grade_rounding: true` aceita a mesma aproximação para meio ponto já usada
pelo sistema; não modifica nenhuma nota. Linhas duplicadas de frequência com
marcas conflitantes são rejeitadas pelo extrator. Em rematrículas, uma marca
explícita prevalece sobre `#` da outra linha do mesmo estudante.

`defer_incomplete_sessions: true` preserva a quantidade de uma chamada já
existente quando o conteúdo não a informa. Sem quantidade documentada nem
chamada existente, a data é relatada como pendente e não é criada. Marcações
fora das datas do vínculo também são relatadas, sem alterar matrícula ou
inventar frequência. Documentos contendo apenas notas preservam as chamadas
atuais e não geram novas. Cargas anuais podem ser corrigidas pelos cabeçalhos;
duração e aulas semanais permanecem como estavam quando não há confirmação.
Uma quantidade semanal nula pode ser preservada, mas não introduzida em uma
matriz que já possuía esse valor.

Quando o usuário confirmar que a duração vale para todas as turmas de um ano,
o lote pode incluir `school_year_class_hours`, com escola, ano e duração de
60 minutos por alvo. A simulação confere também o estado de todas as matrizes
e diários desses anos. A aplicação corrige primeiro a duração pelo serviço
anual e então reconcilia os PDFs, tudo na mesma transação. O hash inicial cobre
ambos os recortes. Quantidades estimadas com autorização do usuário devem
trazer `lesson_count_basis`, com o critério e as datas de referência; essa
proveniência acompanha a chamada criada e a contagem de estimativas no relatório.
Não substituir uma quantidade explícita apenas para atingir uma carga anual.

Se a importação fundiu uma saída e rematrícula, o lote aceita
`restore_enrollment_episodes` somente para suas turmas documentadas. Exige a
confirmação da rematrícula, as duas linhas originais e o hash do dump privado
ainda presente no servidor. Confere identidade, datas sem sobreposição e a
linha importada na matrícula atual. Preserva o ingresso atual, restaura o
vínculo transferido e seus cursos e corrige o identificador legado da volta,
sem copiar ou mover notas. Backup, simulação e transação abrangem os vínculos.
Chamadas são associadas ao único vínculo válido na data; sobreposição bloqueia
a reconciliação. O relatório atual já agrega a frequência do vínculo anterior
pela continuidade de matrículas no mesmo ano e série.

### Duração da aula para toda uma escola e ano

Para lançar apenas conteúdos e presenças a partir de um documento fornecido,
o workflow de reconciliação aceita `operation: documented-diary-import`.
O manifesto criptografado `documented-diary-import-v1` identifica o hash da
fonte e, por diário, a atribuição, escola, turma, componente, docente, ano,
carga conferida e encontros. Admite turmas que compartilham a mesma matriz.
Esse modo não altera matrizes, notas, matrículas nem insere observações nos
diários. Confere dias letivos, períodos, exclusões e datas dos vínculos,
inclusive cancelamento, reclassificação e rematrícula. Divergências com
lançamentos existentes bloqueiam a operação. Exige simulação anterior e seu
hash, guarda cópia privada e auditoria e grava o lote em uma transação.
Depois de aplicar, volte a `dry-run` e confirme zero lançamentos pendentes.
O campo opcional `max_daily_minutes` confere a soma das aulas existentes e
novas para cada estudante e data, usando a duração da hora-aula de cada matriz,
inclusive em matrículas de outras turmas. O limite não depende da marcação de
presença. Essas chamadas e vínculos também integram o hash da simulação para
impedir a aplicação de uma conferência desatualizada.
Para redistribuir um lote recém-importado, `replace_previous_import` deve apontar
para a cópia privada produzida naquela aplicação. A fonte, os alvos e as cargas
precisam ser os mesmos; auditoria, integridade do lote e ausência de edições
posteriores são obrigatórias. A simulação desconta somente esse lote ao conferir
o limite diário, e a substituição é transacional, com nova cópia de segurança.
Depois da aplicação, retire esse campo do manifesto e confira as pendências em
`dry-run`. A redistribuição não altera notas nem registros de outras disciplinas.

O mesmo workflow aceita `operation: school-year-class-hours` na configuração,
com `school_id`, `reference_year`, `class_hour_minutes: 60` e `mode: dry-run`.
Esse modo não lê o envelope de PDFs. A simulação lista todas as matrizes do
recorte e reconhece as conversões de carga produzidas pelo importador de 50
minutos: só quando a carga e as aulas semanais ainda correspondem exatamente
à fórmula original elas voltam à carga legada, com a duração confirmada.
Matrizes já corrigidas para 60 minutos e cargas manuais são preservadas.

Revise as alterações e publique `mode: apply` com `expected_snapshot_sha256`
do relatório. A alteração abrange o calendário, as matrizes e as horas dos
históricos com origem `system` vinculados às matrículas desse ano. Notas,
resultados, chamadas e conteúdos são protegidos por um hash antes e depois.
Há backup privado e transação, inclusive para a atualização dos históricos.
Outras escolas, anos, históricos recebidos e calendários plurianuais ficam
fora dessa correção. Ao concluir, retorne a `mode: dry-run` e confirme que não
resta duração ou conversão por ajustar.

## Classificação dos componentes de Móveis

O escopo `--scope history-catalog-classification` consulta somente as áreas de
Técnico em Móveis, definições vinculadas, matrizes, referências históricas e
auditoria das edições dos componentes dessas matrizes. Não consulta notas, faltas
ou documentos pessoais. Use o mesmo fluxo de chave externa, publicação e leitura
do relatório criptografado. A consulta não executa correções.

## Itinerário por módulos do 3º ano do Liceu de 2026

O escopo `liceu-itinerary` consulta as ofertas e o recorte da regularização
expressamente autorizada: os alunos do 3º ano de 2026 e as matrículas desses
mesmos estudantes no 2º ano de 2025. O 1º e o 2º ano atuais já integram o técnico
na própria matrícula e não recebem novas associações nessa regularização.
O relatório informa as associações nas matrizes e os componentes técnicos incorporados
em cada ano, pelo término do módulo, sem sincronizar nem emitir documentos.

O comando `php artisan itineraries:link-liceu-third-year` apenas confere esse
recorte. A opção `--apply --expected=SHA256_CONFERIDO` exige o hash das identidades
consultadas; salva uma cópia privada anterior e registra as associações em uma
transação auditada, sem alterar notas, frequência, períodos ou componentes.
A opção específica do Diagnóstico Hostinger aplica somente o recorte conferido.
Depois da aplicação, execute novamente a conferência e verifique `pending: 0`.
A matriz é a fonte da definição do itinerário para todas as suas turmas. Não há
seleção por estudante; o campo individual da regularização anterior é legado
e não determina mais a composição ou a emissão dos documentos.
