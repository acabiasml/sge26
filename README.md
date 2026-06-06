<p align="center">
  <img src="public/brand/logo-square.png" alt="Logo do Beabá" width="160">
</p>

# Beabá - Sistema de Gestão Escolar

Beabá é um sistema de gestão escolar desenvolvido em Laravel para substituir gradualmente bases legadas, preservando a possibilidade de migração dos dados antigos para uma estrutura nova, auditável e mais coerente com a realidade das escolas.

O projeto atende escolas mantidas pelo Centro Técnico Juvenil de Jarudore, com acesso institucional pelo domínio `ctjj.org` via Google Workspace. A Operação Mato Grosso aparece como apoiadora e parceira institucional.

## Contexto institucional

O sistema atenderá inicialmente três escolas:

- **Liceu Pedagógico São Francisco de Assis**, no distrito de Jarudore, em Poxoréu-MT. Mantém internato masculino, atende do 9º ano do Ensino Fundamental ao 3º ano do Ensino Médio e oferece curso técnico em Móveis de 1200 horas como itinerário formativo no Ensino Médio.
- **Lar São Domingos Sávio**, em Naboreiro, Rondonópolis-MT. Mantém internato feminino e atende turmas do 6º ano do Ensino Fundamental ao 3º ano do Ensino Médio.
- **Escola Laura Vicuña**, em General Carneiro-MT, com salas anexas em Barra do Garças-MT. Atende turmas do 6º ano do Ensino Fundamental ao 3º ano do Ensino Médio.

Essa realidade orienta a modelagem: pessoas podem ter vínculos em mais de uma escola, escolas podem ter dados institucionais próprios para documentos, e os registros históricos precisam sobreviver à migração.

## Objetivos

- Centralizar o cadastro de escolas, pessoas, vínculos, responsáveis e contatos.
- Permitir múltiplos papéis para a mesma pessoa, inclusive em escolas diferentes e períodos diferentes.
- Registrar auditoria das alterações feitas no sistema.
- Emitir documentos e relatórios em PDF e Excel.
- Gerar código único de verificação para documentos emitidos.
- Manter caminho seguro de importação das bases antigas.
- Criar base para calendário escolar, recados, eventos, anos letivos e futura gestão acadêmica.

## Stack

- PHP 8.2+
- Laravel 12
- SQLite no ambiente local atual
- Laravel Socialite para login com Google
- Livewire 3
- Rappasoft Laravel Livewire Tables
- Laravel Excel
- Dompdf
- Vite

## Modelo de acesso

O sistema separa três conceitos:

- **Pessoa**: cadastro real da pessoa.
- **Usuário**: credencial de acesso vinculada ao Google Workspace.
- **Vínculo**: papel da pessoa no sistema, com escola e período quando aplicável.

Papéis atuais:

- **Administração**: acesso global.
- **Gestão**: acesso limitado à escola do vínculo.
- **Docência**: vínculo docente.
- **Estudante**: acesso futuro a dados próprios, notas e documentos.
- **Equipe escolar**: funcionários e demais colaboradores.

Para Gestão, o sistema também registra a função:

- Direção
- Coordenação
- Secretaria

Uma pessoa pode acumular vínculos. Exemplo: gestão em uma escola e docência em outra.

## Regras principais

- Não existe autocadastro livre.
- Se ainda não houver Administração ativa, o primeiro login `@ctjj.org` cria a primeira pessoa administradora.
- Depois disso, só acessa quem foi previamente cadastrado por Administração ou Gestão.
- Todos os acessos usam e-mail institucional do Google Workspace.
- CPF e e-mail institucional não podem se repetir.
- A própria pessoa não pode alterar seu e-mail institucional, inclusive Administração.
- Cadastro incompleto bloqueia acesso às telas internas.
- A última Administração ativa não pode se desativar, remover seu vínculo ou deixar o sistema sem Administração ativa.
- Quando uma pessoa é desativada, seus vínculos ativos são encerrados.
- Pessoas inativas não aparecem como pendência apenas por falta de dados.
- Pessoa inativa sem CPF e e-mail institucional não pode receber novos vínculos nem emitir documentos.

## Cadastros

### Escolas

O cadastro de escola mantém dados administrativos e institucionais usados no papel timbrado:

- nome da escola;
- razão social;
- CNPJ;
- código INEP;
- telefone, e-mail e site;
- endereço;
- data de fundação;
- texto institucional para cabeçalho;
- logo da escola.

### Pessoas

O cadastro de pessoa concentra dados pessoais, endereço, CPF, e-mail institucional, e-mail pessoal, telefone e situação do cadastro.

Responsáveis que não acessam o sistema, como pai, mãe ou responsável legal, são registrados em **Responsáveis e contatos**, sem criar uma pessoa com acesso ao sistema.

### Vínculos

Os vínculos ligam a pessoa a um papel, escola e período. Administração é global; Gestão, Docência, Estudante e Equipe escolar normalmente ficam vinculados a uma escola.

## Auditoria

Alterações em dados auditáveis geram registros em `audit_logs`.

A auditoria registra:

- quem fez a alteração;
- pessoa autora;
- papel usado no momento;
- escola relacionada, quando aplicável;
- registro alterado;
- ação realizada;
- valores anteriores e novos;
- data e hora;
- IP e navegador.

A visualização padrão usa o fuso de Brasília (`America/Sao_Paulo`). Administração pode escolher outro fuso para visualização.

## Documentos e relatórios

O sistema gera relatórios em Excel e PDF para:

- escolas;
- pessoas;
- vínculos;
- auditoria.

Também existem fichas individuais em PDF para escola e pessoa.

Todo PDF emitido é registrado em `issued_documents` e recebe código único no formato:

```text
BEABA-XXXX-XXXX-XXXX
```

Esse código pode ser verificado publicamente em:

```text
/documentos/verificar
```

O rodapé dos documentos informa o código, data/hora de emissão em Brasília e a pessoa emissora.

## Calendário escolar

O sistema já possui base para:

- cadastro de anos letivos por escola;
- nome livre do ano letivo, como Educação Básica ou Ensino Técnico;
- data de início e fim;
- hora-aula do ano letivo;
- geração inicial de dias letivos;
- recesso escolar;
- opção de ignorar sábados e domingos na geração;
- períodos avaliativos com nomes livres, sem sobreposição;
- eventos por escola;
- PDF do calendário em página única, paisagem, para assinatura.

O calendário, eventos próximos, aniversários e recados aparecem no dashboard.

## Recados

Administração pode criar recados globais. Gestão pode criar recados para sua escola. Recados têm período de exibição e aparecem no dashboard para o público correspondente.

## Telas atuais

- Login com Google
- Verificação pública de documentos
- Dashboard
- Escolas
- Pessoas
- Vínculos
- Pendências
- Anos letivos
- Eventos
- Recados
- Auditoria
- Relatórios em Excel e PDF

## Base legada

Arquivos da base antiga ficam em:

```text
database/legacy
```

A análise inicial está em:

```text
database/legacy/analise_estrutura_antiga.md
```

Bases legadas atuais:

- `database/legacy/u810745753_beaba.sql`
- `database/legacy/u810745753_lar.sql`
- `database/legacy/u810745753_laura.sql`

Importação local:

```bash
php artisan legacy:import --fresh
```

O importador preserva `legacy_source`, `legacy_id` e metadados relevantes. E-mails fora de `ctjj.org` são tratados como e-mail pessoal e aguardam e-mail institucional.

Na importação inicial, ficam ativos:

- Acabias, como Administração global;
- estudantes vinculados a cursos/calendários de 2026 que não estejam transferidos;
- docentes vinculados a componentes de cursos/calendários de 2026;
- Gestão indicada no cadastro das escolas.

## Configuração local

Instale as dependências:

```bash
composer install
npm install
```

Copie o ambiente, gere a chave e rode as migrações:

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

No Google Cloud Console, a URI autorizada de redirecionamento precisa ser exatamente a mesma de `GOOGLE_REDIRECT_URI`.

## Executando

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

## Observações de desenvolvimento

- Textos exibidos devem estar em português do Brasil, com acentuação correta.
- O visual segue a identidade do Beabá, com a logo do sistema na interface principal e no favicon.
- A logo do Centro Técnico Juvenil de Jarudore é usada no papel timbrado dos documentos.
- A logo da escola aparece no papel timbrado quando cadastrada.
- A logo da Operação Mato Grosso aparece de forma discreta na tela de login.
- Tabelas administrativas usam Livewire Tables.
- Exportações usam Laravel Excel e Dompdf.
- Alterações estruturais precisam considerar a migração futura da base legada.
