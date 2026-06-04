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

- O dump `estrutura_antiga_com_dados.sql` representa apenas o Liceu Pedagogico Sao Francisco de Assis.
- Por enquanto, cursos, turmas, componentes, notas e frequencias nao serao migrados.
- `users.arquivado`: `1` significa inativo e `0` significa ativo. Valores nulos sao inconsistentes e devem ser tratados como inativos na importacao inicial, exceto a pessoa Acabias.
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
- Responsaveis devem ser tratados como pessoas relacionadas ao estudante. Para menores de idade, os dados de contato de responsavel sao obrigatorios para o cadastro ficar adequado.
- `escolas.fundacao`, `escolas.info` e `escolas.site` fazem parte de informacoes institucionais usadas em papel timbrado/cabecalho e devem ser preservadas por escola.

## Estrategia sugerida de migracao

1. Manter o dump antigo somente como fonte de leitura/importacao.
2. Criar migrations novas com nomes e tipos mais expressivos.
3. Criar comandos Laravel de importacao por etapas, preservando os IDs antigos em colunas `legacy_id`.
4. Migrar primeiro cadastros-base: users/pessoas, escolas, calendarios, periodos, areas.
5. Migrar depois estrutura academica: cursos, componentes, vinculos/matriculas.
6. Migrar por ultimo registros operacionais: diarios, frequencias, medias, avisos e horarios.
7. Validar contagens e integridade referencial a cada etapa.
