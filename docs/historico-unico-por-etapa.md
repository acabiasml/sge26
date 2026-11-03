# Histórico único por etapa

O cadastro da pessoa e a central de históricos exibem um documento para cada
etapa. A emissão usa exclusivamente o histórico unificado; os endereços antigos
redirecionam para esse mesmo documento. O cadastro de anos externos acrescenta
os dados diretamente ao documento da etapa, sem criar outro histórico separado.

Ao abrir o cadastro, a central ou o histórico, os anos dos documentos antigos são
incorporados em transação. Os IDs dos anos e registros, notas, cargas horárias,
faltas e dados de origem são preservados. As observações e bases legais são
reunidas. Fontes com várias etapas são divididas conforme a etapa de cada ano.
O cabeçalho antigo permanece inativo quando todos os seus anos foram incorporados.
Não é necessária migration de banco.

Anos sem identificação de etapa continuam disponíveis para revisão na central.
A emissão exige identificação das séries e os anos anteriores necessários.
Lançamentos da mesma série, ano civil e escola bloqueiam a emissão até revisão:
nenhuma das fontes é descartada automaticamente. Matrículas transferidas e
encerradas continuam incluídas; anos do sistema permanecem somente para leitura.

Validação local: PHP 8.4 em container, SQLite em memória e GD habilitado. Os testes
cobrem consolidação idempotente, etapas distintas, preservação dos registros,
endereços antigos de PDF, bloqueio de divergências e proteção de edição.
