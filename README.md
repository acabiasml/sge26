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
- Tailwind CSS 4 e Vite 7;
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
- depois da implantação inicial, somente pessoas previamente autorizadas, ativas e com vínculo vigente acessam o sistema;
- o primeiro acesso pode criar o registro local de usuário, mas não cria permissão escolar;
- cadastros incompletos são direcionados para **Meu cadastro**;
- CPF e e-mail institucional são únicos;
- dados sensíveis possuem restrições de autoedição;
- a última Administração ativa não pode ser removida ou desativada;
- ações de Gestão são limitadas às escolas vinculadas.

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

Matrizes representam cursos ou etapas e podem ser duplicadas. Componentes possuem área de conhecimento, formação, carga horária, aulas semanais e período de oferta.

No Ensino Médio, o sistema separa:

- **Formação Geral Básica**;
- **Itinerário Formativo**, inclusive Educação Profissional e Tecnológica.

O nome do itinerário e a regulamentação do curso técnico são mantidos na matriz e refletidos nos documentos acadêmicos. A carga horária calculada usa:

```text
aulas semanais × minutos da hora-aula × 40 ÷ 60
```

### Turmas, docentes e horários

Turmas podem reunir mais de uma matriz ativa. A atribuição de docentes titulares e substitutos pertence à turma/componente, permitindo equipes diferentes para turmas que usam a mesma matriz.

Os horários possuem versões com vigência, matriz semanal, intervalos e cores consistentes por docente. Podem ser impressos por turma, ano letivo, professor ou estudante.

### Matrículas e movimentações

O módulo de matrículas é independente da tela do ano letivo. Ele suporta:

- matrícula em uma ou mais matrizes da turma;
- transferência e reversão de transferência;
- cancelamento e reversão de cancelamento;
- reclassificação;
- rematrícula após transferência;
- convalidação de notas e frequência trazidas de outra escola;
- ficha de matrícula e central de documentos.

Para nova matrícula, o estudante deve estar ativo, possuir os dados obrigatórios, ter vínculo de estudante na escola e selecionar matriz ativa.

## Diários e frequência

Os diários são gerados por turma e componente. Docentes podem:

- lançar frequência por aula e por data;
- registrar o conteúdo ministrado;
- lançar notas nas avaliações configuradas;
- consultar o período ou o ano;
- confirmar o diário ao concluir o período;
- imprimir diário e lista de chamada.

Gestão e Administração podem acompanhar pendências, enviar alertas, corrigir registros, reabrir diários e consolidar períodos. Frequência sem conteúdo, ou conteúdo sem frequência, é sinalizada como inconsistência.

Quando existe horário, as datas seguem os dias e aulas previstos. Sem horário, permanece disponível a seleção manual, necessária em ofertas técnicas e especiais. O lançamento fora do intervalo do período é bloqueado por padrão e só é liberado quando a Gestão habilita essa opção no próprio período.

Justificativas de ausência, inclusive atestados médicos, são registradas separadamente. As faltas permanecem no registro bruto, enquanto o cálculo de frequência efetiva considera as justificativas conforme a regra acadêmica adotada.

## Consolidação e fechamento

Um período somente pode ser consolidado quando os diários obrigatórios estão confirmados e sem pendências. Reaberturas são controladas e exigem justificativa.

O resultado anual considera pontos acumulados, frequência mínima, recuperações aplicadas, situação da matrícula e regras vigentes da escola.

Antes do fechamento do ano, a conferência verifica calendário aprovado, períodos consolidados, resultados finais, dias letivos, turmas e matrículas. O fechamento bloqueia alterações acadêmicas sensíveis, mas pode ser revertido por usuários autorizados.

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

## Documentos

### Diferença entre os documentos do estudante

- **Boletim escolar**: documento de acompanhamento. Mostra identificação essencial, turma, período em andamento, notas, faltas, frequência, carga horária, situação da matrícula e resultado final quando já calculado.
- **Ficha individual**: registro anual completo. Reúne cadastro detalhado, dados da matrícula, todos os períodos, pontos, frequência, carga horária, justificativas, consolidação e resultado final.
- **Histórico escolar**: documento da trajetória. Consolida anos, etapas, instituições, currículos, resultados e cargas horárias para comprovação de estudos.

Os três mantêm Formação Geral Básica e Itinerário Formativo em blocos próprios, com apenas os períodos correspondentes a cada formação.

### Emissões disponíveis

O sistema emite, entre outros:

- fichas de pessoa e escola;
- calendário e matrizes curriculares;
- ficha de matrícula;
- boletim escolar e boletins da turma;
- ficha individual;
- histórico escolar por etapa;
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

Os PDFs acadêmicos usam a fonte local **Atkinson Hyperlegible Next**, tamanho mínimo de 11px, papel timbrado, espaço para assinaturas e rodapé de autenticação.

Cada emissão oficial recebe código no formato:

```text
BEABA-XXXX-XXXX-XXXX
```

A consulta pública está disponível em `/documentos/verificar`. O código, a data, a pessoa emissora e os metadados necessários ficam registrados em `issued_documents`.

Detalhes da revisão documental estão em [docs/auditoria-documental-pdfs.md](docs/auditoria-documental-pdfs.md).

## Dashboard, comunicação e relatórios

O dashboard combina indicadores conforme o perfil, recados, aniversariantes e calendário mensal. O menu é organizado em Meu espaço, Gestão escolar, Rotina acadêmica, Documentos, Comunicação e Administração.

Recados podem ter público e vigência. Relatórios administrativos aceitam busca e filtros respeitando o escopo escolar do usuário.

## Acessibilidade e interface

- interface em português do Brasil;
- fonte local Atkinson Hyperlegible Next, sem dependência de Google Fonts;
- foco visível e link para pular ao conteúdo;
- navegação agrupada por contexto;
- ícones com nomes acessíveis;
- tabelas responsivas;
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

Testes:

```bash
composer test
```

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

O código da aplicação não deve ser editado diretamente no servidor de produção.

## Diretrizes de contribuição

- preservar o caminho de migração das bases antigas;
- nunca reescrever migrations já executadas em produção;
- respeitar o escopo por escola e as regras de autorização;
- manter alterações acadêmicas e documentos auditáveis;
- não enfraquecer bloqueios de anos ou períodos consolidados;
- garantir código de autenticidade nos documentos oficiais;
- manter textos em português correto e UFs em maiúsculas;
- preservar acessibilidade, responsividade e identidade visual;
- adicionar ou atualizar testes para regras de negócio alteradas;
- nunca incluir segredos no repositório.

## Licença

Este repositório usa a licença MIT. Consulte [LICENSE](LICENSE).
