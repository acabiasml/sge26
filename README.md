# Beabá - Sistema de Gestão Escolar

Beabá é um sistema de gestão escolar desenvolvido em Laravel para substituir, aos poucos, uma base legada. A proposta é construir um sistema novo sem perder a possibilidade de migração dos dados antigos.

O projeto está sendo desenvolvido para escolas mantidas pelo Centro Técnico Juvenil de Jarudore, ligadas ao domínio institucional `ctjj.org`, com login pelo Google Workspace. O Centro Técnico Juvenil de Jarudore também recebe apoio e recursos da Operação Mato Grosso.

## Contexto Institucional

O sistema atenderá inicialmente três escolas:

- Liceu Pedagógico São Francisco de Assis, no distrito de Jarudore, em Poxoréu-MT. A unidade mantém um internato masculino, atende do 9º ano do Ensino Fundamental ao 3º ano do Ensino Médio e oferece, no Ensino Médio, curso técnico em Móveis com 1200 horas como itinerário formativo.
- Lar São Domingos Sávio, em Naboreiro, Rondonópolis-MT. A unidade mantém um internato feminino e atende turmas do 6º ano do Ensino Fundamental ao 3º ano do Ensino Médio.
- Escola Laura Vicuña, em General Carneiro-MT, com salas anexas em Barra do Garças-MT. A unidade atende turmas do 6º ano do Ensino Fundamental ao 3º ano do Ensino Médio.

Essa configuração importa para o desenho do sistema: há escolas com internato, escolas com salas anexas, diferentes etapas de ensino, itinerário formativo técnico e vínculos de pessoas que podem atravessar mais de uma unidade.

## Objetivos

- Centralizar o cadastro de escolas, pessoas e vínculos escolares.
- Representar corretamente unidades, internatos, salas anexas e ofertas de ensino.
- Permitir que uma mesma pessoa tenha mais de um papel no sistema, inclusive em escolas diferentes.
- Registrar auditoria completa das alterações realizadas.
- Emitir relatórios e documentos com identificador único verificável no próprio sistema.
- Preservar caminho de migração da base antiga para a nova estrutura.

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

O sistema usa pessoas, usuários e vínculos como conceitos separados.

Uma pessoa pode ser estudante, docente, equipe escolar, gestão ou administração. Esses papéis são registrados como vínculos, com data de início e fim. O fim pode ser indeterminado.

Papéis atuais:

- Administração: acesso global ao sistema.
- Gestão: acesso de gestão limitado à escola do vínculo.
- Docência: vínculo docente.
- Estudante: acesso para consultas próprias, notas e documentos.
- Equipe escolar: acesso para dados próprios e futuras funções internas.

Para Gestão, também é registrado o cargo:

- Direção
- Coordenação
- Secretaria

Uma mesma pessoa pode acumular vínculos. Por exemplo: gestão em uma escola e docência em outra.

## Regras principais

- Não existe autocadastro livre.
- Se ainda não houver Administração ativa, o primeiro login com e-mail `@ctjj.org` cria a primeira pessoa administradora.
- Depois disso, só acessa quem tiver sido previamente cadastrado por Administração ou Gestão.
- Todos os usuários acessam pelo e-mail institucional do Google Workspace.
- CPF e e-mail institucional não podem se repetir.
- A própria pessoa não pode alterar seu e-mail institucional, inclusive Administração.
- Cadastro incompleto bloqueia o acesso às telas internas, principalmente quando falta CPF.
- A última Administração ativa não pode se desativar, remover seu vínculo ou deixar o sistema sem Administração ativa.
- Quando uma pessoa é desativada, seus vínculos ativos são encerrados.

## Auditoria

Alterações em dados auditáveis geram registros em `audit_logs`.

A auditoria guarda:

- quem fez a alteração;
- pessoa autora da alteração;
- papel usado pela pessoa no momento;
- escola relacionada, quando aplicável;
- registro alterado;
- ação realizada;
- valores anteriores e novos;
- data e hora;
- IP e navegador.

A visualização padrão usa o fuso horário de Brasília (`America/Sao_Paulo`). Administração pode escolher outro fuso de visualização.

## Relatórios e documentos

O sistema gera relatórios em Excel e PDF para:

- escolas;
- pessoas;
- vínculos;
- auditoria.

Também existem fichas individuais em PDF para escola e pessoa.

Todo PDF emitido é registrado em `issued_documents` e recebe um código único no formato `BEABA-XXXX-XXXX-XXXX`. Esse código pode ser verificado em:

```text
/documentos/verificar/{codigo}
```

## Telas atuais

- Login com Google
- Dashboard com indicadores gerais
- Cadastro de escolas
- Cadastro de pessoas
- Cadastro e manutenção de vínculos
- Auditoria
- Exportação de relatórios em Excel e PDF
- Verificação pública de documentos emitidos

## Base legada

Os arquivos relacionados ao banco antigo ficam em:

```text
database/legacy
```

A análise inicial da estrutura antiga está em:

```text
database/legacy/analise_estrutura_antiga.md
```

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

Configure as credenciais do Google no `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

No Google Cloud Console, a URI autorizada de redirecionamento deve bater exatamente com `GOOGLE_REDIRECT_URI`.

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

Também há script Composer:

```bash
composer test
```

## Observações para desenvolvimento

- Textos exibidos no sistema devem estar em português do Brasil, com acentuação correta.
- O visual segue a identidade do Beabá e usa a logo do sistema na interface principal.
- A logo da Operação Mato Grosso aparece de forma discreta na tela de login como mantenedora.
- As tabelas administrativas usam Livewire Tables.
- Exportações usam Laravel Excel e Dompdf.
- Qualquer alteração estrutural no banco deve considerar a migração futura da base legada.
