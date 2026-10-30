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

A pesquisa textual envia a consulta após 350 ms sem digitação. Filtros de seleção atualizam imediatamente; mudar a pesquisa reinicia a página. A paginação usa os controles Livewire, sem navegação de documento. Listas previamente carregadas como coleções são filtradas antes do recorte de página. Quadros de horário e indicadores agrupados preservam sua organização.

O componente aceita somente telas e filtros explicitamente cadastrados. A tela e o contexto (por exemplo, escola ou turma) são propriedades `Locked`. Os controllers conferem novamente as permissões em cada renderização. Novos fornecedores devem retornar `View` sem renderizar a página completa; não usar `response()->view()` como fornecedor, pois isso renderizaria o componente recursivamente.

Links antigos com parâmetros GET continuam aceitos. O estado reativo fica no parâmetro `f` da URL, e a página no parâmetro `page`. Os relatórios PDF e os formulários de gravação continuam usando suas rotas, validações e autorizações existentes. A lista de documentos é independente do editor, para preservar o rascunho durante buscas e mudanças de aba.

Ao criar outra listagem interativa, usar um componente Livewire e validar: pesquisa além da primeira página, reinício da paginação, alteração de filtros dependentes, resultado vazio, limpeza e isolamento entre escolas. Evitar combinar paginação JavaScript e Livewire na mesma tabela.
