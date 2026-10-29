<p align="center">
  <img src="public/brand/logo-square.png" alt="Logo do Beabá" width="160">
</p>

# Beabá

Sistema de gestão escolar das unidades mantidas pelo Centro Técnico Juvenil de Jarudore. O projeto reúne cadastro institucional, organização acadêmica, diários, vida escolar, documentos oficiais, comunicação e auditoria em uma aplicação Laravel multi-escola.

O Beabá substitui gradualmente bases legadas sem reproduzir sua estrutura: os dados importados preservam a origem e os identificadores antigos, mas passam a integrar um modelo relacional, auditável e organizado por escola.

## Estado atual

O sistema está em uso e contempla o ciclo escolar completo:

1. cadastro de escolas, pessoas e vínculos;
2. configuração do ano letivo, calendário, períodos e critérios;
3. criação de matrizes, componentes, turmas e horários;
4. matrícula e movimentação dos estudantes;
5. atribuição de docentes e operação dos diários;
6. consolidação de períodos e cálculo do resultado anual;
7. emissão e verificação de documentos oficiais;
8. fechamento e auditoria do ano letivo.

Unidades atendidas inicialmente:

- **Liceu Pedagógico São Francisco de Assis**, em Jarudore, Poxoréu-MT;
- **Lar São Domingos Sávio**, em Naboreiro, Rondonópolis-MT;
- **Escola Laura Vicuña**, em General Carneiro-MT, com salas anexas em Barra do Garças-MT.

O Centro Técnico Juvenil de Jarudore é a mantenedora, e a Operação Mato Grosso participa como parceira institucional.

## Stack

- PHP 8.4;
- Laravel 12;
- Livewire 3 e Rappasoft Laravel Livewire Tables;
- Laravel Socialite para autenticação Google;
- integração REST com o Google Workspace Admin SDK para provisionamento opcional de contas;
- Laravel Excel para exportações;
- Dompdf para documentos oficiais;
- Bootstrap 4/SB Admin 2 e estilos próprios na interface administrativa;
- Tailwind CSS 4 e Vite 7 no pipeline de assets;
- SQLite como padrão local e banco configurável por ambiente;
- PHPUnit 11.

## Modelo de domínio

O núcleo separa conceitos que não devem ser confundidos:

- **Pessoa**: identidade civil, contatos e dados institucionais;
- **Usuário**: credencial autenticada pelo Google;
- **Vínculo**: papel que uma pessoa exerce, globalmente ou em uma escola, com vigência;
- **Ano letivo**: calendário e regras acadêmicas de uma escola;
- **Matriz/curso**: organização curricular, etapa e carga horária;
- **Turma**: oferta concreta de uma ou mais matrizes;
- **Matrícula**: vínculo do estudante com a turma e suas matrizes;
- **Período avaliativo**: intervalo, avaliações, recuperação e fechamento;
- **Diário**: frequência, conteúdo e notas de um componente da turma;
- **Histórico escolar**: trajetória consolidada do estudante por etapa de ensino.

Uma pessoa pode exercer vários papéis e participar de escolas diferentes. Uma matrícula pode reunir matrizes paralelas e até componentes provenientes de anos letivos distintos, como ocorre na formação geral básica combinada com itinerário técnico.

## Perfis e acesso

Papéis disponíveis:

- **Administração**: acesso global e manutenção estrutural;
- **Gestão**: direção, coordenação ou secretaria no escopo de suas escolas;
- **Docência**: horários e diários dos componentes atribuídos;
- **Equipe escolar**: colaboradores sem atribuições de gestão;
- **Estudante**: consulta dos próprios horários, diários e documentos.

Regras principais:

- não existe autocadastro público;
- o login usa Google Workspace e restringe o domínio por `GOOGLE_ALLOWED_DOMAIN`;
- a sessão termina após duas horas sem atividade ou ao fechar o navegador; não há opção de manter o usuário conectado;
- depois da implantação inicial, somente pessoas previamente autorizadas, ativas e com vínculo vigente acessam o sistema;
- o primeiro acesso pode criar o registro local de usuário, mas não cria permissão escolar;
- cadastros incompletos são direcionados para **Meu cadastro**;
- CPF e e-mail institucional são únicos;
- dados sensíveis possuem restrições de autoedição;
- a última Administração ativa não pode ser removida ou desativada;
- ações de Gestão são limitadas às escolas vinculadas;
- quando a pessoa possui mais de um papel ativo, o seletor ao lado do tema escolhe a visão persistida do sistema; menus, dados e permissões seguem apenas o papel escolhido. Se esse vínculo perder a vigência, o sistema usa o próximo papel ativo;
- a Administração pode retirar fechamentos, reativar anos inativos e alterar diários confirmados, matrículas e movimentações; bloqueios por estado acadêmico não impedem essas ações administrativas. As validações de integridade e a auditoria continuam aplicáveis.

O provisionamento de contas Google Workspace é opcional e depende de `GOOGLE_WORKSPACE_ENABLED` e das credenciais administrativas configuradas no ambiente.

## Cadastro e conformidade

O cadastro de pessoa mantém, conforme disponibilidade:

- nome completo e nome social;
- CPF, INEP, NIS e código legado da pasta;
- data e local de nascimento, nacionalidade e filiação;
- telefone, e-mails e endereço;
- participação em auxílio federal;
- contatos e responsáveis;
- vínculos escolares e suas vigências.

Os nomes sociais que já estavam cadastrados foram limpos por migration; o campo continua disponível para novos cadastros quando necessário.

Escolas possuem dados institucionais, CNPJ, INEP, atos autorizativos, contatos, endereço, logomarca e responsáveis por assinatura.

A central de **Conformidade** reúne bloqueios, avisos e atenções relativos a pessoas, responsáveis, vínculos, escolas, matrículas, estruturas acadêmicas e anos letivos. Documentos oficiais não são emitidos quando faltam dados indispensáveis da pessoa ou da escola, salvo fluxos que prevejam confirmação explícita para determinada pendência.

Todas as alterações relevantes usam trilha de auditoria com ator, papel, escola, ação, registro e valores anteriores e posteriores.

## Organização acadêmica

### Anos letivos e calendário

Cada escola mantém seus próprios anos letivos, que podem atravessar mais de um ano civil. O ano concentra:

- datas de início e término;
- quantidade mínima de dias letivos;
- frequência mínima e pontuação para aprovação;
- períodos avaliativos;
- calendário diário;
- matrizes, turmas e horários;
- aprovação, fechamento e reabertura controlada.

O calendário diferencia dias letivos, sábados, domingos, feriados, férias finais, recessos, estudos pedagógicos, conselhos de classe, marcos de períodos e outras ocorrências. Há validação da contagem de dias e emissão em PDF paisagem.

### Períodos, avaliações e recuperação

Em cada período avaliativo, a Gestão configura:

- nome, posição e intervalo de datas;
- quantidade, nomes e pesos das avaliações;
- forma de recuperação;
- permissão excepcional para docentes lançarem frequência e conteúdo fora dos limites do período.

A recuperação pode:

- compor a média como avaliação ponderada;
- substituir uma avaliação escolhida;
- substituir a menor nota;
- substituir a média do período quando produzir resultado maior.

Quando habilitada, a recuperação aparece como a última coluna de lançamento. Alterações de regra após o início dos lançamentos exigem confirmação e preservam as proteções contra perda acidental de resultados.

A média é arredondada para o múltiplo de `0,5` mais próximo. Conceitos, faixas de nota e critérios podem ter vigência, permitindo que mudanças futuras não alterem documentos de períodos anteriores.

### Matrizes e componentes

Matrizes representam cursos ou etapas e podem ser duplicadas. Componentes possuem área de conhecimento, carga horária, aulas semanais e período de oferta. A área é escolhida explicitamente no cadastro ou na edição do componente e se associa à Formação Geral Básica, ao Itinerário Formativo ou à Parte Complementar.

Em **Áreas curriculares**, a Administração pode rastrear os usos por escola, ano, matriz e componente, desvincular componentes e excluir áreas sem usos. A desvinculação preserva os componentes e seus registros acadêmicos.

No Ensino Médio, o sistema separa:

- **Formação Geral Básica**;
- **Itinerário Formativo**, inclusive Educação Profissional e Tecnológica.

O nome do itinerário e a regulamentação do curso técnico são mantidos na matriz e refletidos nos documentos acadêmicos. A carga horária calculada usa:

```text
semanas anuais = dias letivos previstos ÷ 5
semanas de oferta = semanas anuais × períodos de oferta ÷ total de períodos
carga horária = aulas semanais × minutos da hora-aula × semanas de oferta ÷ 60
```

Para 200 dias letivos e quatro períodos, uma oferta de dois períodos considera 20 semanas. O cálculo respeita a interseção dos períodos da matriz e do componente. Uma carga horária informada manualmente prevalece sobre o cálculo.

### Turmas, docentes e horários

Turmas podem reunir mais de uma matriz ativa. A atribuição de docentes titulares e substitutos pertence à turma/componente, permitindo equipes diferentes para turmas que usam a mesma matriz.

O resumo da turma mostra os estudantes matriculados, em ordem alfabética, com acesso à listagem completa. Turmas de anos fechados são identificadas como **encerradas**. Nas listagens que permitem consulta histórica, turmas que deixaram de ser operacionais ficam visíveis apenas para a Administração; uma turma técnica com matriz ativa e estudantes ainda em curso continua operacional mesmo quando seu ano letivo de origem está fechado.

Os horários possuem versões com vigência, matriz semanal, intervalos e cores consistentes por docente. Podem ser impressos por turma, ano letivo, professor ou estudante.

### Matrículas e movimentações

O módulo de matrículas é independente da tela do ano letivo. Ele suporta:

- matrícula em uma ou mais matrizes da turma;
- transferência e reversão de transferência;
- cancelamento e reversão de cancelamento;
- reclassificação;
- rematrícula após transferência;
- transcrição, por escola, período e componente, das médias, aulas e faltas da ficha recebida de outra instituição;
- ficha de matrícula e central de documentos.

Para nova matrícula, o estudante deve estar ativo, possuir os dados obrigatórios, ter vínculo de estudante na escola e selecionar matriz ativa.

O **atestado de transferência** pode ser emitido antes de efetivar a movimentação; a emissão do PDF não altera a situação da matrícula. Quando a transferência ou a reclassificação é efetivada, o resultado final correspondente é registrado. Uma transferência com data retroativa limita notas e faltas da matrícula antiga até a data de saída, mesmo que existam lançamentos posteriores.

Se o estudante retornar no mesmo ano e série, o boletim, a ficha individual e o atestado de frequência da matrícula nova reúnem os registros anteriores da rede, os resultados transcritos da escola de origem e os lançamentos após o retorno. A ficha externa pode ser cadastrada mesmo para períodos avaliativos anteriores à data da nova matrícula; resultados já registrados na rede não devem ser transcritos novamente. O procedimento para a secretaria, com capturas das telas, está em [Aluno veio de outra escola no meio de um período letivo. O que fazer?](docs/tutorial-aluno-outra-escola.pdf).

## Diários e frequência

Os diários são gerados por turma e componente. Docentes podem:

- lançar frequência por aula e por data;
- registrar o conteúdo ministrado;
- lançar notas nas avaliações configuradas;
- consultar o período ou o ano;
- confirmar o diário ao concluir o período;
- imprimir diário e lista de chamada.

Na lista **Meus diários**, cada componente mostra barras de conteúdo e frequência com aulas registradas, total previsto e percentual. A previsão considera o horário e o calendário letivo; sem horário, usa uma estimativa pela carga horária da matriz e pela duração da aula. Um conteúdo cobre as aulas daquele dia, conforme o horário ou a frequência registrada. As cores acompanham o tema, e os valores permanecem legíveis sem depender das cores. A contagem de inconsistências separa dias com frequência sem conteúdo, conteúdo sem frequência e notas pendentes de períodos encerrados, com atalhos para os bimestres. Notas da ficha de origem são reconhecidas, e avaliações de períodos ainda em andamento não geram essa pendência na lista.

O alerta de atraso compara os lançamentos já realizados com as aulas previstas até a data atual, respeitando a vigência do componente e o encerramento da turma. As cores se intensificam gradualmente até o vermelho conforme o prazo se aproxima e o atraso aumenta, inclusive quando as barras estão em zero. Após o prazo, aulas ainda por registrar recebem alerta crítico. Lançamentos futuros não compensam o atraso atual. Sem horário, a distribuição da carga é estimada pelos dias letivos do intervalo, ou pelas datas de início e término quando não há calendário. Atraso e inconsistências são apresentados separadamente: ausência completa de registros pode exigir atenção mesmo sem divergências entre registros existentes.

Gestão e Administração podem acompanhar pendências, enviar alertas, corrigir registros, reabrir diários e consolidar períodos. A Administração visualiza anos letivos de todas as escolas, inclusive inativos, com filtro por ano. O filtro de componente reúne nomes iguais e encontra os diários correspondentes nas diferentes turmas. Frequência sem conteúdo, ou conteúdo sem frequência, é sinalizada como inconsistência.

Quando existe horário, as datas seguem os dias e aulas previstos. Sem horário, permanece disponível a seleção manual, necessária em ofertas técnicas e especiais. Para docentes, o lançamento fora do intervalo do período é bloqueado por padrão e depende da liberação da Gestão no próprio período. A Administração possui permissão de alteração independentemente desses bloqueios de estado acadêmico.

Justificativas de ausência, inclusive atestados médicos, são registradas separadamente. As faltas permanecem no registro bruto, enquanto o cálculo de frequência efetiva considera as justificativas conforme a regra acadêmica adotada.

Os resumos e documentos de frequência respeitam o intervalo efetivo de cada matrícula. Faltas recebidas de outra instituição são cadastradas na ficha da escola de origem, sem modificar os diários dos professores.

## Consolidação e fechamento

Um período somente pode ser consolidado quando os diários obrigatórios estão confirmados e sem pendências. Reaberturas são controladas e exigem justificativa.

O resultado anual considera pontos acumulados, frequência mínima, recuperações aplicadas, situação da matrícula e regras vigentes da escola. Resultados de transferência e reclassificação são registrados como **Transferido** e **Reclassificado**; os resultados finais calculados ficam auditáveis. Uma matrícula aprovada de ano já fechado aparece como **Concluído** na Vida escolar, com saída no último dia letivo pertinente.

Antes do fechamento do ano, a conferência verifica calendário aprovado, períodos consolidados, resultados finais, dias letivos, turmas e matrículas. O fechamento bloqueia alterações acadêmicas sensíveis para os demais perfis. A Administração pode reabrir, reativar e corrigir o ano; a reabertura administrativa mantém os demais usuários em consulta.

## Vida escolar e históricos

A tela **Vida escolar** reúne matrículas, responsáveis, boletins, fichas individuais, frequência, desempenho, históricos, convalidações, documentos e movimentações.

Históricos recebidos de outras instituições aceitam estruturas flexíveis: anos, séries, fases, escolas, localidades, componentes livres, formações, áreas, notas ou conceitos, frequência, carga horária, resultados e observações.

O histórico unificado é separado por etapa — Fundamental, Médio ou Técnico — e combina, quando pertinente:

- registros cadastrados manualmente;
- dados consolidados das matrículas no Beabá;
- Formação Geral Básica;
- Itinerários Formativos cursados em matriz ou ano letivo paralelo;
- módulos e cargas horárias de cursos técnicos;
- atos legais e regularizações da vida escolar.

Na edição do histórico unificado, a secretaria lança séries cursadas em outras instituições sem precisar desfazer uma transferência. Todas as séries cursadas em escolas da rede são sincronizadas a partir das matrículas e exibidas para consulta, sem edição manual de notas, faltas ou resultados. A sincronização também ocorre ao abrir, editar ou emitir o histórico; uma matrícula reclassificada aparece com esse resultado.

Na tabela principal dos históricos da educação básica, **CHC** significa carga horária cursada, com legenda no documento. Anos ainda em andamento não exibem CHC. O Ensino Médio apresenta totais previstos e cursados por formação e por ano, além dos totais gerais. Anos em modo **Global** usam uma coluna unificada. O cabeçalho da matriz curricular se repete somente quando a tabela continua em outra página.

## Documentos

### Diferença entre os documentos do estudante

- **Boletim escolar**: documento de acompanhamento. Mostra identificação essencial, turma, período em andamento, notas, faltas, frequência, carga horária, situação da matrícula e resultado final quando já calculado.
- **Ficha individual**: registro anual completo. Reúne cadastro detalhado, dados da matrícula, todos os períodos, pontos, frequência, carga horária, justificativas, consolidação e resultado final.
- **Histórico escolar**: documento da trajetória. Consolida anos, etapas, instituições, currículos, resultados e cargas horárias para comprovação de estudos.

Os três mantêm Formação Geral Básica e Itinerário Formativo em blocos próprios, com apenas os períodos correspondentes a cada formação.

A ação **Ficha individual** da Vida escolar usa a matrícula mais recente, mesmo quando ela já foi transferida. A ficha é assinada pela direção e pela secretaria escolar; os nomes vêm dos vínculos da escola, quando cadastrados.

### Emissões disponíveis

O sistema emite, entre outros:

- fichas de pessoa e escola;
- calendário e matrizes curriculares;
- ficha de matrícula;
- boletim escolar e boletins da turma;
- relatório individual de situação de notas e frequência, com pontos faltantes por componente e estimativa da margem anual de faltas em horas;
- ficha individual;
- histórico escolar por etapa;
- recibo de atividade voluntária, com pessoa, escola emissora, período e valor;
- diários, listas de chamada e espelho de notas;
- horários de turma, docente e estudante;
- atestados de frequência, inclusive mensais e por período;
- atestado de transferência;
- declarações de matrícula, escolaridade e conclusão;
- atas de resultados finais por turma;
- consolidação de resultados do ano letivo;
- relatório anual de frequência;
- relatórios de escolas, pessoas, vínculos e auditoria em PDF e Excel;
- documentos oficiais livres, produzidos em editor próprio;
- relatório de conformidade documental e acadêmica.

Os PDFs acadêmicos usam a fonte local **Atkinson Hyperlegible Next**, tamanho mínimo de 11px, papel timbrado, espaço para assinaturas e rodapé de autenticação. O rodapé compartilhado mantém o QR Code à esquerda e centraliza as informações institucionais, o código de verificação, a data e o responsável pela emissão.

Na **Central de Emissão**, a escolha do tipo antecede a busca do registro. A busca de pessoas alcança todas as pessoas visíveis para o perfil; a busca de turmas oferece filtros de escola e ano civil na mesma linha. Para perfis não administrativos, as turmas encerradas ou inativas não aparecem como opções de emissão. Os filtros e as opções apresentados dependem do documento escolhido.

O relatório **Situação de notas e frequência** é emitido por matrícula para a gestão da escola e administradores. Ele mostra os lançamentos atuais e os pontos restantes para a meta anual em cada componente. A tabela informa as horas de falta lançadas em cada componente. O resumo geral discrimina faltas lançadas, aulas abonadas pelos dias cobertos por justificativas e atestados, e faltas efetivas, também em horas. A margem estimada usa as faltas efetivas e a carga curricular prevista; a frequência mínima é avaliada para a matrícula inteira. Notas parciais e a margem podem mudar com novos lançamentos, e o resultado final registrado continua sendo a referência oficial. Enquanto a matrícula estiver ativa e não houver resultado definitivo, a situação apresentada é **Cursando**.

Cada emissão oficial recebe código no formato:

```text
BEABA-XXXX-XXXX-XXXX
```

A consulta pública está disponível em `/documentos/verificar`. O código, a data, a pessoa emissora e os metadados necessários ficam registrados em `issued_documents`.

No editor de documentos oficiais:

- a nova emissão começa como **privada**, mas a pessoa emissora pode selecionar **pública**;
- a aba **Públicos** mostra os documentos das escolas visíveis ao usuário; **Meus** reúne seus documentos privados e os públicos que ele emitiu; **Todos** é exclusiva da Administração;
- documentos anteriores à introdução da visibilidade foram classificados como públicos;
- somente a Administração muda a visibilidade. Ao tornar um documento privado, ela escolhe explicitamente o usuário proprietário;
- o proprietário pode excluir seus documentos privados; somente a Administração pode excluir documentos públicos;
- **Reeditar** reaproveita o conteúdo para uma nova emissão, com outro registro e código;
- **Reemitir** abre a mesma geração para visualizar ou imprimir novamente, mantendo o código;
- a lista de documentos emitidos possui paginação e mostra código, data e responsável pela emissão.

O editor usa Summernote 0.9.1 (MIT), servido localmente em `public/template/vendor/summernote`, sem CDN. Permite tabelas, imagens PNG/JPEG, redimensionamento, descrição alternativa e desfazer/refazer. As imagens são incorporadas ao conteúdo salvo e ao PDF; reemitir mantém o arquivo original. Limites: 1 MB por imagem, 4096 pixels por lado, 8 megapixels e 4 MB de imagens por documento. O servidor valida as imagens e preserva apenas HTML e estilos permitidos, incluindo células mescladas e alinhamentos.

O editor também oferece cores e marca-texto, links clicáveis, tachado, sobrescrito/subscrito, linha horizontal, espaçamento por parágrafo e tamanhos de fonte de 8 a 36. Esses recursos são preservados no HTML salvo e no PDF. Detalhes da revisão documental estão em [docs/auditoria-documental-pdfs.md](docs/auditoria-documental-pdfs.md).

## Dashboard, comunicação e relatórios

O dashboard combina indicadores conforme o perfil, recados, aniversariantes e calendário mensal. O menu é organizado em Meu espaço, Gestão escolar, Rotina acadêmica, Documentos, Comunicação e Administração.

Recados podem ser cadastrados e editados, com público, vigência e destaque. Os destaques ainda não vistos aparecem em um modal central após o login. **Marcar como já visto** salva a leitura por usuário; fechar sem marcar permite a apresentação no próximo acesso.

A auditoria apresenta nomes e descrições dos registros e agrupa ações consecutivas do mesmo usuário, tipo e contexto, com intervalo de até cinco minutos entre registros. Chamadas e avaliações distintas permanecem separadas. Cada grupo permite consultar os registros originais e os detalhes das alterações. O agrupamento é feito antes da paginação.

Relatórios administrativos aceitam busca e filtros respeitando o escopo escolar do usuário.

## Acessibilidade e interface

- interface em português do Brasil e italiano, com bandeiras em SVG para exibição consistente entre navegadores;
- paletas beathema, gov.br, Aurora, Instagram, TikTok, Gmail e Outlook selecionáveis no ícone de paleta, com preferência salva por usuário;
- luminosidade clara ou escura determinada automaticamente pela configuração do dispositivo, sem escolha manual; beathema e gov.br usam a base Aurora no modo escuro;
- identidade visual própria para cada paleta nas telas do sistema; PDFs e documentos de impressão mantêm formatação independente do tema;
- tema gov.br inspirado no Design System de Governo, com fonte Rawline local e foco visível;
- fonte local Atkinson Hyperlegible Next, sem dependência de Google Fonts;
- foco visível e link para pular ao conteúdo;
- navegação agrupada por contexto;
- ícones com nomes acessíveis;
- confirmações de ações e descrição de imagens do editor em modais acessíveis, sem caixas nativas de alerta do navegador;
- calendário com legenda e distinção visual dos tipos de dia, usando as cores do tema ativo;
- tabelas responsivas;
- retorno à lista de Pessoas pela seta no cabeçalho, preservando busca, filtros e página na mesma aba;
- faixa colorida no topo durante envios de formulários e atualizações do Livewire, incluindo paginação, busca e filtros;
- prevenção de cliques repetidos na paginação enquanto a atualização está em andamento;
- cores, pesos e tamanhos voltados à legibilidade;
- suporte à navegação por teclado e leitores de tela.

Os arquivos e a licença da fonte ficam em `public/template/fonts/atkinson-hyperlegible-next`.

## Instalação local

Pré-requisitos:

- PHP 8.4 com extensões exigidas pelo Laravel e pelos drivers escolhidos;
- Composer;
- Node.js e npm;
- SQLite ou outro banco compatível configurado no ambiente.

Prepare o arquivo de ambiente e o banco SQLite antes da instalação automatizada:

```bash
cp .env.example .env
touch database/database.sqlite
composer run setup
```

O script instala dependências, gera a chave, executa migrations, instala pacotes JavaScript e compila os assets. Se outro banco for usado, configure as variáveis `DB_*` antes de executá-lo.

Para preparar manualmente:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

No Windows, substitua `cp` e `touch` pelos comandos equivalentes ou crie os arquivos manualmente.

Configuração mínima do Google:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
GOOGLE_ALLOWED_DOMAIN=ctjj.org
```

A URI cadastrada no Google Cloud precisa ser idêntica a `GOOGLE_REDIRECT_URI`.

Para provisionamento opcional do Workspace:

```env
GOOGLE_WORKSPACE_ENABLED=false
GOOGLE_WORKSPACE_CREDENTIALS_PATH=
GOOGLE_WORKSPACE_ADMIN_EMAIL=
GOOGLE_WORKSPACE_ORG_UNIT=/
GOOGLE_WORKSPACE_TEMPORARY_PASSWORD=
```

Nunca versione `.env`, credenciais JSON, senhas, tokens ou chaves privadas.

## Execução e qualidade

Ambiente completo de desenvolvimento:

```bash
composer run dev
```

Ou processos separados:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

Build de produção:

```bash
npm run build
```

Testes devem rodar em **PHP 8.4, dentro de container**, com SQLite em memória. A configuração está em `phpunit.xml`:

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
```

Dentro do container preparado com as dependências do projeto:

```bash
php -v
php -r 'echo extension_loaded("gd") ? "GD habilitada\n" : "GD ausente\n";'
php -d memory_limit=4G artisan test
```

A extensão GD é necessária nos testes de PDF. Falhas de versão do PHP, extensões, memória ou permissões devem ser resolvidas no ambiente antes de serem tratadas como defeito da aplicação. Não usar a base de produção nos testes locais.

Formatação PHP:

```bash
./vendor/bin/pint
```

## Importação e manutenção de dados legados

As bases de origem usadas pelo importador ficam no armazenamento privado `storage/app/private` e não devem ser versionadas. `database/legacy` mantém apenas referências de estrutura e análise. O importador conserva `legacy_source`, `legacy_id`, `legacy_code` e metadados necessários para rastreabilidade.

Importação geral em uma base local descartável:

```bash
php artisan legacy:import --fresh
```

Comandos especializados existentes:

```bash
php artisan legacy:import-2026-diaries
php artisan legacy:import-2026-grades
php artisan legacy:import-diary-pdfs
php artisan data:normalize-title-case
```

Consulte `php artisan help <comando>` antes de executar opções de importação. Não use `--fresh` em uma base que deva ser preservada.

## Publicação

A branch principal é publicada no GitHub e aciona o deploy automático na Hostinger. Uma entrega só é considerada concluída depois de:

1. validar a alteração localmente;
2. sincronizar com o remoto sem descartar trabalho existente;
3. criar commit e enviar a branch;
4. aguardar o deploy automático;
5. confirmar em produção o commit ativo e a resposta da aplicação;
6. verificar o status das migrations;
7. executar migrations pendentes somente quando a alteração exigir e o deploy não as tiver aplicado.

O código da aplicação Laravel não deve ser editado diretamente no servidor de produção. Mensagens de commit devem ser escritas em português. As regras operacionais completas e a fonte de verdade para agentes estão em [AGENTS.md](AGENTS.md).

### Diagnóstico da Hostinger em redes que bloqueiam SSH

O workflow **Diagnóstico Hostinger** pode ser executado manualmente pela aba Actions ou por `gh workflow run hostinger-diagnostico.yml --ref main`. O runner do GitHub estabelece a conexão SSH; o computador local usa somente HTTPS para acionar e consultar a execução. O diagnóstico aguarda até três minutos pelo commit esperado antes de verificar os demais itens, para acompanhar o deploy automático. Por padrão, a operação é somente de leitura: consulta commit, arquivos modificados, PHP, migrations e presença do botão da chamada, além de verificar a resposta HTTP.

As configurações ficam nos GitHub Actions Secrets `HOSTINGER_CONNECTION` (JSON de conexão) e `HOSTINGER_KNOWN_HOSTS` (chaves do servidor previamente verificadas). Nunca colocar esses valores no código. O cliente rejeita servidores com chaves desconhecidas. O workflow só executa na branch `main` e não aceita comandos livres. A opção `refresh_views=true` executa somente `php artisan view:clear` para renovar as telas compiladas, sem alterar dados. As opções `apply_visibility_migration=true`, `apply_private_owner_migration=true`, `apply_reclassification_migration=true`, `apply_social_name_cleanup=true` e `apply_attendance_justification_migration=true` executam somente as migrations correspondentes, depois de confirmar o commit publicado. A opção de nomes sociais também confere a contagem antes e depois da limpeza. Use essas opções apenas se o deploy automático tiver deixado a migration específica pendente. A opção `invalidate_all_sessions=true` desconecta todos os usuários e invalida os tokens antigos de login; use-a somente mediante solicitação explícita. Alterações de código e banco continuam seguindo o fluxo de publicação e validação descrito acima.

### Verificação manual por SSH

Execute no terminal do seu computador:

```bash
./scripts/verificar-hostinger.sh
```

O SSH solicita a senha quando necessário; ela não aparece durante a digitação e não é armazenada pelo script. A verificação compara o commit em produção com o `HEAD` local, confere PHP 8.4 e migrations pendentes e consulta a resposta HTTP da página de entrada. Para verificar um commit específico, passe seu hash como argumento. O script retorna código zero somente quando todas essas verificações passam. Ele não publica código nem executa migrations. Consulte `--help` para configurar outro endereço ou caminho.

### Página institucional

A página pública de `ctjj.org` é independente do Laravel, cuja aplicação está em `/sge`. Sua fonte está em [site-institucional](site-institucional/README.md), com publicação manual por SSH/SFTP, cópia prévia fora do diretório público e substituição atômica dos arquivos. O deploy do Laravel não publica automaticamente essa página. Ela reúne unidades escolares, trajetórias em abas, documentos e referências externas, além do acesso ao Beabá e à verificação de autenticidade.

## Diretrizes de contribuição

- preservar o caminho de migração das bases antigas;
- nunca reescrever migrations já executadas em produção;
- respeitar o escopo por escola e as regras de autorização;
- manter alterações acadêmicas e documentos auditáveis;
- preservar os bloqueios de anos e períodos para os perfis restritos e as permissões administrativas explícitas;
- garantir código de autenticidade nos documentos oficiais;
- manter textos em português correto e UFs em maiúsculas;
- preservar acessibilidade, responsividade e identidade visual;
- adicionar ou atualizar testes para regras de negócio alteradas;
- nunca incluir segredos no repositório.

## Licença

Este repositório usa a licença MIT. Consulte [LICENSE](LICENSE).
