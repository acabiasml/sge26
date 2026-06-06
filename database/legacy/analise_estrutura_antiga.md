# Analise da estrutura antiga

Arquivo analisado: `estrutura_antiga.sql.sql`

Banco exportado: `u810745753_beaba`

Servidor original: MariaDB 11.8.6

## Tabelas encontradas

- `areas`
- `avisos`
- `calendarios`
- `componentes`
- `cursos`
- `diarios`
- `escolas`
- `frequencias`
- `horarios`
- `medias`
- `migrations`
- `periodos`
- `personal_access_tokens`
- `turmas`
- `users`

## Nucleo funcional inferido

- `users`: tabela central para pessoas e acesso. Parece misturar alunos, professores, responsaveis e usuarios administrativos por meio do campo `tipo`.
- `escolas`: dados institucionais, com diretor, coordenador e secretario apontando para `users`.
- `calendarios`: calendario escolar por escola e ano.
- `periodos`: periodos dentro de um calendario.
- `cursos`: curso vinculado a calendario, com periodo inicial e final.
- `areas`: areas de conhecimento.
- `componentes`: disciplinas/componentes curriculares, ligados a curso, area e professor.
- `turmas`: matriculas/vinculos de usuarios em cursos, com status, tipo, data de matricula e transferencia.
- `diarios`: registros de aula por componente.
- `frequencias`: presenca de usuario em diario e turma.
- `medias`: notas por usuario, componente e periodo.
- `horarios`: grade/recorrencia de aulas por componente.
- `avisos`: avisos por escola e usuario remetente.

## Relacionamentos principais

- `avisos.escola` -> `escolas.id`
- `avisos.enviadopor` -> `users.id`
- `calendarios.escolas_id` -> `escolas.id`
- `periodos.calendarios_id` -> `calendarios.id`
- `cursos.calendarios_id` -> `calendarios.id`
- `cursos.inicio` -> `periodos.id`
- `cursos.fim` -> `periodos.id`
- `componentes.area_id` -> `areas.id`
- `componentes.cursos_id` -> `cursos.id`
- `componentes.professor` -> `users.id`
- `diarios.componentes_id` -> `componentes.id`
- `frequencias.diarios_id` -> `diarios.id`
- `frequencias.users_id` -> `users.id`
- `frequencias.turmas_id` -> `turmas.id`
- `horarios.componentes_id` -> `componentes.id`
- `medias.componentes_id` -> `componentes.id`
- `medias.users_id` -> `users.id`
- `medias.periodos_id` -> `periodos.id`
- `turmas.cursos_id` -> `cursos.id`
- `turmas.users_id` -> `users.id`
- `turmas.usermatricula` -> `users.id`
- `turmas.usertransf` -> `users.id`
- `escolas.diretor` -> `users.id`
- `escolas.coordenador` -> `users.id`
- `escolas.secretario` -> `users.id`

## Pontos de atencao para o sistema novo

- A tabela `users` acumula muitos dados pessoais, escolares, documentais, de saude, bancarios e de login. No sistema novo, provavelmente vale separar isso em perfis/documentos/contatos/endereco/dados escolares.
- Muitos campos que parecem numericos ou enumeracoes estao como `varchar(255)`, por exemplo `status`, `tipo`, `sexo`, `cor`, `arquivado`, `geminada`, `presenca`, `horas`, `dias_semana` e `aulas_semana`.
- `frequencias` possui constraints duplicadas para as mesmas colunas, algumas com `ON DELETE CASCADE` e outras sem. Na modelagem nova, escolher apenas uma regra clara.
- `email` em `users` e unico, mas e nullable. Em MySQL/MariaDB isso permite multiplos NULLs; no Laravel novo precisamos decidir se email sera obrigatorio para acesso ou apenas para usuarios com login.
- `turmas` parece representar matricula/vinculo do aluno em curso, nao uma turma escolar tradicional com nome/ano/sala. O nome da entidade nova precisa ser decidido com cuidado.
- `cursos.inicio` e `cursos.fim` apontam para `periodos`, embora os nomes parecam datas. Vale renomear para algo como `periodo_inicio_id` e `periodo_fim_id`.
- `diarios.geminada` e `NOT NULL`, mas sem default. Ao migrar dados, precisamos garantir valor em todos os registros.
- Nao ha `created_at`/`updated_at` na maioria das tabelas antigas. Se o sistema novo usar timestamps, eles podem ser nullable ou preenchidos durante a migracao.

## Decisoes de migracao ja tomadas

- Os dumps com dados atuais sao:
  - `u810745753_beaba.sql`: Liceu Pedagogico Sao Francisco de Assis.
  - `u810745753_lar.sql`: Lar Sao Domingos Savio.
  - `u810745753_laura.sql`: Escola Laura Vicuna.
- Cada dump contem apenas uma escola cadastrada.
- Por enquanto, cursos, turmas, componentes, notas e frequencias nao serao migrados.
- `users.arquivado`: `1` significa inativo e `0` significa ativo, mas o campo esta inconsistente e nao define sozinho a ativacao inicial.
- Na importacao inicial, ficam ativos: Acabias; estudantes em cursos/calendarios de 2026 que nao estejam transferidos; docentes vinculados a componentes de cursos/calendarios de 2026; e Gestao indicada em cada escola.
- `users.tipo = admin`: representa Administracao global no sistema antigo. Gestao da escola tambem recebia esse perfil.
- `users.tipo = prof`: deve virar vinculo de Docencia quando for migrado.
- `users.tipo = estud`: deve virar vinculo de Estudante quando for migrado.
- `users.tipo = apoio`: deve virar Equipe escolar, salvo excecoes identificadas manualmente.
- `escolas.diretor`, `escolas.coordenador` e `escolas.secretario`: migrar como vinculos de Gestao com os cargos Direcao, Coordenacao e Secretaria.
- Pessoas sem CPF devem ser importadas como cadastro incompleto e bloqueado ate regularizacao.
- E-mails antigos invalidos ou fora de `ctjj.org` devem ser tratados como e-mail pessoal, aguardando preenchimento futuro do e-mail institucional para login.
- `users.codigo` e um identificador fisico da pasta da pessoa; o prefixo indica o vinculo existente quando a pasta foi criada.
- `users.inep` e o codigo INEP/Educacenso do aluno e se aplica somente a estudantes.
- Endereco da pessoa deve existir no sistema novo.
- Pais, maes e responsaveis sem acesso ao sistema devem ser tratados como contatos da pessoa (`person_contacts`), nao como pessoas cadastradas em `people`. A relacao entre duas pessoas cadastradas (`person_relationships`) fica reservada para casos em que o responsavel tambem existe como pessoa do sistema.
- `escolas.fundacao`, `escolas.info` e `escolas.site` fazem parte de informacoes institucionais usadas em papel timbrado/cabecalho e devem ser preservadas por escola.

## Conclusoes apos leitura das tres bases com dados

### Quantidades por base

| Base | Escola | Pessoas | Estudantes | Docentes | Administracao | Apoio | Matriculas |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `u810745753_beaba.sql` | Liceu Pedagogico Sao Francisco de Assis | 600 | 490 | 54 | 10 | 46 | 147 |
| `u810745753_lar.sql` | Lar Sao Domingos Savio | 171 | 153 | 12 | 6 | 0 | 275 |
| `u810745753_laura.sql` | Escola Laura Vicuna | 163 | 147 | 12 | 4 | 0 | 202 |

### Situacao dos dados

- A base do Liceu e a mais historica e inconsistente: 508 pessoas com `arquivado = 1`, 70 com `arquivado = 0` e 22 nulas.
- As bases Lar e Laura parecem mais atuais: Lar tem todas as pessoas com `arquivado = 0`; Laura tem 162 ativas e 1 inativa.
- Acabias aparece nas tres bases como `admin`. No Liceu esta `arquivado = 1`; no Lar e na Laura esta `arquivado = 0`. Na importacao inicial, Acabias deve permanecer ativo mesmo quando a origem vier do Liceu.
- Existem pessoas repetidas entre bases por e-mail, CPF e codigo. A importacao precisa unificar pessoas por CPF quando existir, e usar outros criterios somente com cautela.
- Ha CPFs duplicados dentro das bases Lar e Laura, alem de muitos CPFs ausentes no Liceu. A migracao deve aceitar cadastro incompleto, mas nao pode violar unicidade definitiva de CPF sem uma fila de revisao.
- A maioria dos e-mails antigos nao e institucional `ctjj.org`. Eles devem ir para e-mail pessoal, deixando o e-mail institucional vazio ate regularizacao.
- O campo `codigo` e mais completo no Liceu; no Lar e Laura aparece pouco preenchido. Ele deve ser preservado como codigo legado/pasta, mas nao pode ser usado sozinho para identificar pessoa entre escolas.
- O campo `inep` do aluno aparece no Liceu e Lar, mas nao na Laura. Tambem ha repeticoes; deve ser importado como dado escolar do estudante, nao como identificador global de pessoa.
- As referencias de direcao, coordenacao e secretaria nas escolas apontam para usuarios `admin`; devem virar vinculos de Gestao com cargo especifico, alem do eventual vinculo de Administracao global quando couber.

### Implicacoes para a importacao inicial

1. Importar primeiro as tres escolas, preservando dados institucionais (`fundacao`, `info`, `site`) nos novos campos de documentos.
2. Importar pessoas de todas as bases com uma chave de origem composta por base + id legado, porque os IDs se repetem entre bases.
3. Tentar unificar pessoas por CPF valido. Quando CPF estiver ausente ou duplicado, importar como pendencia de revisao.
4. Tratar `email` fora de `ctjj.org` como e-mail pessoal.
5. Criar e-mail institucional vazio para pessoas sem `ctjj.org`, bloqueando login ate regularizacao.
6. Transformar `tipo` em vinculos:
   - `admin` -> Administracao global.
   - `prof` -> Docencia na escola da base.
   - `estud` -> Estudante na escola da base.
   - `apoio` -> Equipe escolar na escola da base.
7. Criar vinculos de Gestao para diretor, coordenador e secretario de cada escola, marcando esses vinculos como ativos.
8. Importar pais, maes e responsaveis como `person_contacts`, preservando nome, CPF e telefones quando existirem, sem permitir login e sem criar cadastro completo em `people`.

## Estrategia sugerida de migracao

1. Manter o dump antigo somente como fonte de leitura/importacao.
2. Criar migrations novas com nomes e tipos mais expressivos.
3. Criar comandos Laravel de importacao por etapas, preservando os IDs antigos em colunas `legacy_id`.
4. Migrar primeiro cadastros-base: users/pessoas, escolas, calendarios, periodos, areas.
5. Migrar depois estrutura academica: cursos, componentes, vinculos/matriculas.
6. Migrar por ultimo registros operacionais: diarios, frequencias, medias, avisos e horarios.
7. Validar contagens e integridade referencial a cada etapa.
