<p align="center">
  <img src="public/brand/logo-square.png" alt="Logo do Beabá" width="160">
</p>

# Beabá

Sistema de Gestão Escolar em Laravel para as escolas mantidas pelo Centro Técnico Juvenil de Jarudore.

O Beabá está sendo construído para substituir gradualmente sistemas e bases legadas, preservando a possibilidade de migração dos dados antigos, mas com uma modelagem nova: auditável, organizada por escola e mais próxima da rotina real de secretaria, gestão, docência e estudantes.

O acesso institucional usa Google Workspace do domínio `ctjj.org`.

## Contexto

O sistema atende inicialmente:

- **Liceu Pedagógico São Francisco de Assis**, em Jarudore, distrito de Poxoréu-MT. Atende do 9º ano do Ensino Fundamental ao 3º ano do Ensino Médio e mantém itinerário formativo técnico em Móveis.
- **Lar São Domingos Sávio**, em Naboreiro, Rondonópolis-MT. Atende do 6º ano do Ensino Fundamental ao 3º ano do Ensino Médio e mantém internato feminino.
- **Escola Laura Vicuña**, em General Carneiro-MT, com salas anexas em Barra do Garças-MT. Atende do 6º ano do Ensino Fundamental ao 3º ano do Ensino Médio.

O Centro Técnico Juvenil de Jarudore é a mantenedora oficial. A Operação Mato Grosso aparece como parceira institucional.

## Stack

- PHP 8.2+
- Laravel 12
- SQLite no ambiente local
- Laravel Socialite para login com Google
- Livewire 3
- Rappasoft Laravel Livewire Tables
- Laravel Excel
- Dompdf
- Vite

## Conceitos Principais

O sistema separa três coisas que antes ficavam misturadas:

- **Pessoa**: cadastro civil e institucional.
- **Usuário**: credencial de acesso via Google Workspace.
- **Vínculo**: papel exercido por uma pessoa em uma escola e em um período.

Papéis atuais:

- **Administração**: acesso global.
- **Gestão**: direção, coordenação ou secretaria em uma escola.
- **Docência**: acesso aos diários e horários dos componentes atribuídos.
- **Estudante**: acesso ao próprio diário, horários e documentos.
- **Equipe escolar**: colaboradores sem função de gestão.

Uma pessoa pode ter vários vínculos, inclusive em escolas diferentes.

## Regras de Acesso

- Não há autocadastro livre.
- Se não houver Administração ativa, o primeiro login `@ctjj.org` cria a primeira pessoa administradora.
- Depois disso, só acessa quem já foi cadastrado por Administração ou Gestão.
- CPF e e-mail institucional são únicos.
- A própria pessoa não pode alterar o próprio e-mail institucional.
- Gestores, docentes, estudantes e equipe não podem alterar seus próprios dados sensíveis: nome completo, CPF, data de nascimento, e-mail institucional e nome da mãe.
- A última Administração ativa não pode se desativar, remover seu vínculo ou deixar o sistema sem Administração.
- Pessoas inativas não aparecem como pendência apenas por falta de dados, mas não podem receber novos vínculos, matrículas ou documentos quando não possuem CPF e e-mail institucional.

## Módulos Atuais

### Cadastro e Gestão

- Escolas, dados institucionais, INEP, CNPJ, endereço, contatos e logo.
- Pessoas, dados pessoais, endereço, contatos e responsáveis.
- Vínculos por papel, escola, data de início e fim.
- Pendências de cadastro filtradas por escola e apenas para pessoas ativas.
- Auditoria de alterações com ator, papel, escola, registro, valores antigos e novos.

### Estrutura Acadêmica

- Ano letivo por escola, com calendário, aprovação, frequência mínima e soma de pontos para aprovação.
- Períodos avaliativos com regras de avaliação e recuperação.
- Matrizes/cursos dentro do ano letivo.
- Componentes curriculares agrupados por formação e área do conhecimento.
- Turmas vinculadas a matrizes ativas.
- Validação visual do ano letivo, matriz, turma, horários e diários antes da operação acadêmica.

### Calendário Escolar

Cada escola possui seus próprios anos letivos. Um ano letivo pode atravessar mais de um ano civil.

No calendário:

- segundas a sextas iniciam como férias finais (`FF`);
- sábados e domingos iniciam sem marcação;
- períodos avaliativos transformam datas em dias letivos;
- dias não letivos entre períodos são normalizados como recesso (`RE`);
- tipos de dia incluem letivo, sábado, domingo, feriado, férias finais, recesso escolar, estudos pedagógicos, início/término de período, conselho de classe e outro.

O calendário pode ser impresso em PDF oficial, em página paisagem, com legenda, períodos, assinatura e papel timbrado.

### Matrizes e Turmas

- Matrizes podem ser duplicadas para reaproveitar componentes.
- Componentes têm área, formação, aulas semanais e duração por períodos avaliativos.
- A carga horária é calculada por:

```text
aulas semanais × minutos da hora-aula da matriz × 40 ÷ 60
```

- A impressão de matrizes agrupa componentes por formação, área e matriz, aproximando o formato das matrizes curriculares oficiais.
- A associação de docentes titulares e substitutos fica na turma, não na matriz, porque turmas com a mesma matriz podem ter professores diferentes.

### Horários

Cada turma pode ter versões de horário com validade por período.

O horário é visual, em matriz semanal, respeitando:

- dias letivos previstos no calendário;
- quantidade de aulas semanais do componente;
- duração da hora-aula da matriz;
- intervalos;
- cores consistentes por docente.

Há impressão do horário da turma, dos horários do ano letivo, dos horários do professor e dos horários do estudante.

### Matrículas

Matrículas ficam em módulo próprio, fora do ano letivo, porque um estudante pode cursar matrizes diferentes, inclusive em anos letivos com durações distintas.

Administração pode matricular em qualquer escola. Gestão pode matricular apenas nas escolas em que possui vínculo ativo.

O estudante só pode ser matriculado se:

- estiver ativo;
- possuir CPF;
- possuir e-mail institucional;
- tiver vínculo de estudante na escola;
- a matriz estiver ativa.

O módulo preserva transferência, reclassificação, cancelamento e emissão de ficha de matrícula em PDF.

### Diários

Diários são gerados por turma e componente curricular.

Docência pode:

- lançar frequência;
- lançar conteúdo por dia;
- lançar notas configuradas pela gestão;
- visualizar diário por período ou ano;
- confirmar lançamentos ao final do período avaliativo;
- imprimir diário e lista de chamada.

Gestão e Administração podem:

- acompanhar diários por filtros, cartões e indicadores;
- enviar alertas ao professor;
- corrigir lançamentos quando necessário;
- reabrir diário individual;
- consolidar período apenas quando os diários estiverem confirmados e sem pendências;
- reabrir período consolidado com justificativa;
- imprimir diários para assinatura.

Quando a turma tem horário cadastrado, as datas do diário seguem o horário. Quando não tem horário, o diário mantém seleção manual de datas, útil para cursos técnicos e ofertas especiais.

Conteúdo e frequência são vinculados por data: frequência sem conteúdo, ou conteúdo sem frequência, gera pendência.

### Avaliação e Recuperação

A gestão configura as avaliações por período avaliativo:

- quantidade de avaliações;
- nome;
- peso;
- regra de recuperação.

A recuperação pode:

- compor a média como nota separada com peso;
- substituir uma avaliação específica;
- substituir a menor nota do período.

A média do período é arredondada para o múltiplo de `0,5` mais próximo.

A aprovação anual considera soma de pontos definida no ano letivo, além da frequência mínima.

### Documentos

O sistema emite:

- fichas de pessoa e escola;
- calendários escolares;
- matrizes curriculares;
- ficha de matrícula;
- diários de classe;
- listas de chamada;
- horários;
- relatórios em PDF e Excel;
- documentos oficiais criados em editor próprio.

Todos os PDFs recebem código único:

```text
BEABA-XXXX-XXXX-XXXX
```

A verificação pública fica em:

```text
/documentos/verificar
```

O rodapé informa código, data/hora de emissão em Brasília e pessoa emissora.

O papel timbrado usa:

- logo e dados do Centro Técnico Juvenil de Jarudore à esquerda;
- logo e dados da escola à direita, quando houver escola associada ao documento.

### Dashboard e Menu

O dashboard apresenta métricas, recados, aniversariantes e calendário mensal integrado.

O menu lateral é organizado por uso real:

- **Meu espaço**: cadastro, calendário, horários e diários pessoais.
- **Gestão escolar**: escolas, anos letivos, pessoas e pendências.
- **Rotina acadêmica**: matrículas, justificativas e diários.
- **Documentos**: editor oficial e verificação de autenticidade.
- **Comunicação**: recados.
- **Administração**: auditoria.

## Acessibilidade

O Beabá prioriza:

- português do Brasil com acentuação correta;
- foco visível;
- link para pular ao conteúdo;
- botões com ícones acompanhados de `aria-label` e `title`;
- navegação lateral agrupada;
- textos legíveis;
- tabelas responsivas;
- compatibilidade com leitores de tela.

## Base Legada

Arquivos legados ficam em:

```text
database/legacy
```

Bases atuais:

- `database/legacy/u810745753_beaba.sql`
- `database/legacy/u810745753_lar.sql`
- `database/legacy/u810745753_laura.sql`

Importação local:

```bash
php artisan legacy:import --fresh
```

O importador preserva `legacy_source`, `legacy_id` e metadados relevantes. E-mails fora de `ctjj.org` são tratados como e-mail pessoal e aguardam e-mail institucional.

## Configuração Local

Instale as dependências:

```bash
composer install
npm install
```

Prepare o ambiente:

```bash
copy .env.example .env
php artisan key:generate
php artisan migrate
```

No ambiente local atual, o banco usa SQLite:

```env
DB_CONNECTION=sqlite
```

Garanta que o arquivo exista:

```bash
type nul > database/database.sqlite
```

Configure o Google no `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

No Google Cloud Console, a URI autorizada de redirecionamento precisa ser exatamente igual a `GOOGLE_REDIRECT_URI`.

## Execução

Servidor Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Assets em desenvolvimento:

```bash
npm run dev
```

Build dos assets:

```bash
npm run build
```

## Testes

Rode a suíte:

```bash
php artisan test
```

Ou:

```bash
composer test
```

## Observações de Desenvolvimento

- Mudanças estruturais devem preservar o caminho de migração das bases antigas.
- Alterações em dados acadêmicos aprovados devem respeitar os bloqueios de segurança.
- Documentos oficiais devem manter código de autenticidade.
- Telas novas devem respeitar acessibilidade, responsividade e a identidade visual do Beabá.
