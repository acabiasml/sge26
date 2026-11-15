# Desempenho da auditoria e das telas

Revisão de 15/11/2026, incluindo processamento do servidor, consultas repetidas, listagens iniciais, interações Livewire e navegação nos três perfis.

## Auditoria

A listagem recalculava os agrupamentos de todo o histórico nas consultas de contagem e paginação. O custo crescia com a quantidade de registros, mesmo quando a tela exibia apenas dez grupos.

O índice auxiliar guarda somente o escopo de escolas e o primeiro ID, o último ID e a quantidade de registros de cada grupo. As regras de agrupamento continuam sendo calculadas pela consulta original: sequência, ator, escola, tipo, ação, papel, função, contexto da chamada/avaliação e intervalo de cinco minutos. Os campos apresentados e os detalhes vêm dos registros originais.

Cada conjunto de escolas tem seu próprio índice. Isso preserva inclusive a regra anterior pela qual registros de escolas invisíveis não interrompem uma sequência visível para a gestão. As permissões do usuário determinam o escopo em cada requisição.

Quando chegam novos registros, são recalculados o grupo final e a parte nova do histórico. Alterações e exclusões de registros existentes ou de relações que afetam as chaves provocam reconstrução. Uma revisão global sinaliza essas mudanças sem bloquear os checkpoints dos leitores, evitando inverter a ordem de bloqueios com as transações de escrita. A atualização de cada escopo e de seu checkpoint é transacional e possui tentativas de recuperação de deadlock.

O código usa a consulta original durante o intervalo de publicação em que a migration ainda não foi executada.

### Preparação e manutenção

A migration `2026_11_15_000001_create_audit_log_group_index.php` cria as tabelas auxiliares e os índices de escola/ID e data/ID. O diagnóstico de publicação aplica somente essa migration quando pendente e prepara os escopos existentes:

```bash
php artisan audit:refresh-index --benchmark
```

O comando informa quantidade de grupos e tempo de consulta, sem publicar nomes, identificadores de pessoas ou conteúdo dos registros. Uma nova combinação de escolas pode exigir uma primeira construção do índice. As construções seguintes aproveitam o checkpoint.

Após manutenção direta por SQL que altere ou remova auditorias ou seus contextos, reconstrua os índices:

```bash
php artisan audit:refresh-index --rebuild --benchmark
```

As tabelas auxiliares podem ser reconstruídas a partir da auditoria. Elas acrescentam armazenamento proporcional aos grupos de cada escopo; não substituem os registros originais.

## Demais telas

- Os vínculos ativos são consultados uma vez por pessoa e data dentro de cada requisição. Verificações repetidas de papel, escolas e cabeçalho reutilizam essa leitura. Alterações de vínculos invalidam a leitura imediatamente; uma nova requisição começa com dados novos.
- As listagens Livewire reaproveitam os dados que o próprio controller já autorizou e carregou para o HTML inicial. Isso elimina a segunda execução do mesmo carregamento na abertura de diários, conformidade, históricos, matrículas e outras listagens.
- A reutilização da listagem é limitada ao mesmo usuário, perfil, filtros e contexto da requisição inicial. Atualizações Livewire continuam passando pelos controllers; filtros aninhados da URL seguem o caminho normal de normalização.

## Medições reproduzidas

O benchmark usou PHP 8.4 em container e uma cópia local SQLite acrescida de 100 mil auditorias sintéticas. A comparação executou as versões anterior e nova sobre o mesmo banco, com três amostras de cada. Os valores abaixo são medianas do processamento após o bootstrap, sem rede, download de assets ou tempo de inicialização do container:

| Auditoria | Antes | Depois |
| --- | ---: | ---: |
| Processamento da página | 1.038 ms | aproximadamente 94 ms |
| Tempo das consultas | 926 ms | aproximadamente 12 ms |

Os 4.047 grupos do cenário foram comparados integralmente com a consulta original e coincidiram.

Amostras das consultas por abertura de tela no cenário local:

| Tela | Antes | Depois |
| --- | ---: | ---: |
| Painel inicial | 71 | 39 |
| Pessoas | 37 | 16 |
| Escolas | 40 | 11 |
| Diários | 86 | 32 |
| Históricos | 34 | 11 |
| Conformidade | 171 | 78 |
| Matrículas da turma | 69 | 27 |
| Boletim | 51 | 31 |

Esses números demonstram as reduções no cenário de teste; o tempo percebido em produção também depende do volume de dados, da hospedagem, da rede e do cache do navegador.

## Validação

- Suíte do projeto em PHP 8.4, SQLite em memória e configurações seguras de teste.
- Regressões específicas de agrupamento incremental, continuidade na fronteira do checkpoint, isolamento por escola, registros legados, revogação de permissões, filtros, paginação, rollback e atualização após falha.
- Verificação adicional das migrations e da consulta em MariaDB 10.11, incluindo leitores concorrentes e alteração concorrente do histórico em banco isolado.
- Navegação no Chromium por 67 rotas com dados fictícios, nos perfis de administração, docência e estudante, nas larguras de 390 e 1.440 pixels.
- Filtros da auditoria exercitados pelo endpoint Livewire.
- Verificação de produção pelo diagnóstico SSH do projeto, com commit ativo, migrations, preparação dos índices, medição da primeira página e resposta HTTP.
