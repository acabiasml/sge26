# Listagens reativas

As listagens de Pessoas, Escolas e Auditoria usam os componentes Rappasoft existentes. As demais listagens abaixo usam `App\Livewire\ScreenListing`, com as regras de acesso e consultas dos seus controllers:

- Históricos escolares.
- Anos letivos de uma escola.
- Áreas curriculares, componentes vinculados e usos nos históricos.
- Justificativas de ausência.
- Diários docentes e acompanhamento da gestão.
- Conformidade (filtros de escola e gravidade).
- Visão geral das matrículas e matrículas de uma turma.
- Recados cadastrados.
- Documentos emitidos e suas abas de visibilidade.
- Diários do estudante e horário docente.
- Livros de registro, registros, histórico de operações e substituições de gestão.
- Documentos preparados para assinatura digital e versões assinadas.
- Ocorrências e grupos de auditoria.
- Cursos técnicos, cursos livres, certificados emitidos em lote e acessos docentes.

A pesquisa textual envia a consulta após 350 ms sem digitação. Filtros de seleção atualizam imediatamente; mudar a pesquisa reinicia a página. A paginação usa os controles Livewire, sem navegação de documento. Listas previamente carregadas como coleções são filtradas antes do recorte de página. Quadros de horário e indicadores agrupados preservam sua organização.

O componente aceita somente telas e filtros explicitamente cadastrados. A tela e o contexto (por exemplo, escola ou turma) são propriedades `Locked`. Os controllers conferem novamente as permissões em cada renderização. Novos fornecedores devem retornar `View` sem renderizar a página completa; não usar `response()->view()` como fornecedor, pois isso renderizaria o componente recursivamente.

Links antigos com parâmetros GET continuam aceitos. Os filtros reativos usam o parâmetro `f` da URL. Listagens na mesma página usam parâmetros de paginação próprios, como `page`, `events_page` e `free_courses_page`.

Tabelas que já recebem todas as linhas da tela, inclusive grades de edição, usam o componente `TableViewport` por meio de `<x-livewire-table>`. A paginação dessas tabelas é local ao componente, com dez linhas por página. Os campos de todas as páginas permanecem disponíveis para envio e validação. A chamada exibe todos os alunos e os quadros com células agrupadas preservam a apresentação conjunta. Formulários e relatórios PDF usam suas rotas, validações e autorizações existentes.

Ao criar outra listagem interativa, validar a pesquisa além da primeira página, o reinício da paginação, a alteração de filtros dependentes, o resultado vazio, a limpeza e o isolamento entre escolas.
