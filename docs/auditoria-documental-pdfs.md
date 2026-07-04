# Auditoria documental dos PDFs

Data da revisão: 02/07/2026

Esta auditoria compara os PDFs emitidos pelo Beabá com os requisitos escolares e administrativos já definidos para o sistema e com os referenciais oficiais consultados:

- Lei nº 9.394/1996 (LDB): organização da educação básica, dias letivos, carga horária, frequência e registros escolares.
- Resolução Normativa nº 018/2025/CEE-MT: emissão, registro e expedição de histórico escolar, certificados e diplomas no Sistema Estadual de Ensino de Mato Grosso.
- BNCC: organização por etapas, áreas do conhecimento e componentes curriculares.
- LGPD: tratamento, minimização e rastreabilidade de dados pessoais.

## Bloqueio por cadastro incompleto

Documentos oficiais não devem ser emitidos com dados essenciais ausentes. O Beabá bloqueia a emissão quando faltam campos obrigatórios da pessoa ou da escola relacionada.

Pessoa/estudante: nome completo, CPF, data de nascimento, naturalidade, UF de naturalidade, nacionalidade, nome da mãe, e-mail institucional, telefone, endereço, cidade, UF e CEP.

Escola: nome, razão social, CNPJ, código INEP, data de fundação, telefone, e-mail, endereço, cidade, UF, CEP e texto institucional/autorizativo do papel timbrado.

Histórico escolar recebido: título, fundamento legal, escola, etapa/série/fase, ano letivo, município, UF, resultado e carga horária dos componentes informados.
## Regras gerais dos documentos

Situação atual: atendido com observações.

- Todos os PDFs são emitidos em papel A4, com orientação definida por documento.
- Todos os PDFs emitidos pelo sistema geram código único de verificação.
- O rodapé informa que o documento foi emitido pelo Beabá, o código de autenticidade, a data/hora em Brasília e a pessoa emissora.
- O cabeçalho usa papel timbrado com logo da mantenedora à esquerda e logo da escola à direita, quando houver.
- O cabeçalho escolar exibe mantenedora, certificação, escola, CNPJ, INEP, fundação, telefone, e-mail, site, endereço e texto institucional/autorizativo da escola, quando os dados estiverem cadastrados.
- A auditoria do sistema registra a autoria das alterações nos dados que alimentam os documentos.

Observação: assinatura digital com certificado ICP-Brasil/e-CPF/e-CNPJ ainda não foi implementada. Hoje a autenticidade é por código verificável no próprio sistema.

## Boletim escolar

Situação atual: adequado para comunicação escolar periódica.

Inclui:

- identificação da escola pelo papel timbrado;
- identificação do estudante;
- turma, etapa, ano letivo e calendário;
- áreas e componentes curriculares;
- notas ou conceitos, conforme perfil de visualização;
- comportamento por período avaliativo;
- faltas por período e totais;
- carga horária prevista e cursada em horas;
- critérios de aprovação por soma de pontos e frequência mínima;
- legenda de notas, faltas, carga horária e conceitos;
- espaço para assinatura da direção e secretaria escolar;
- código verificável.

Observação: o estudante visualiza conceitos, não notas numéricas, conforme regra definida no sistema.

## Ficha individual

Situação atual: adequado como registro escolar anual consolidado, desde que o período/ano esteja fechado.

Inclui:

- identificação completa do estudante, com CPF, INEP, filiação e endereço;
- dados da matrícula, turma, ano letivo e matriz;
- rendimento por período avaliativo;
- comportamento;
- frequência efetiva e faltas;
- carga horária prevista e cursada;
- resultado final calculado;
- observações de retenção, aprovação, transferência ou cancelamento quando calculadas;
- legendas e assinaturas;
- código verificável.

Observação: deve ser emitida preferencialmente após consolidação dos períodos e cálculo do resultado final.

## Histórico escolar

Situação atual: funcional e flexível para históricos recebidos de outras escolas, com ajustes realizados nesta auditoria.

Inclui:

- papel timbrado da escola emissora ou relacionada;
- título livre do documento;
- identificação do estudante, CPF, INEP, nascimento, naturalidade e filiação;
- fundamento legal;
- componentes curriculares livres, mantendo a nomenclatura do documento de origem;
- ano/série/fase/etapa, modalidade, escola, município, UF, resultado final;
- nota/conceito, carga horária, frequência e resultado por componente;
- carga horária total;
- observações livres para reclassificação, pandemia, equivalências, convalidações ou outras anotações;
- local, data, assinaturas e código verificável.

Observações:

- A estrutura é propositalmente flexível porque históricos recebidos de outras redes podem variar na nomenclatura dos componentes e na distribuição das cargas horárias.
- Ainda não há certificado/diploma de conclusão implementado. Quando for criado, deverá ser revisado diretamente contra a RN 018/2025/CEE-MT.

## Matriz curricular

Situação atual: adequado para conferência pedagógica e impressão interna.

Inclui:

- papel timbrado;
- etapa, ano letivo e período do calendário;
- formação, área, componente curricular e aulas semanais;
- cálculo de carga horária pela hora-aula da matriz;
- agrupamento por etapa/formação/área;
- espaço para assinatura.

Observação: a matriz deve estar vinculada ao ano letivo e às turmas antes de matrícula e geração de diários.

## Calendário escolar

Situação atual: adequado ao fluxo escolar definido.

Inclui:

- calendário anual visual em A4 paisagem;
- tipos de dia, siglas e legenda;
- períodos avaliativos;
- dias letivos calculados;
- mínimo legal informado;
- aprovação do calendário;
- assinatura da direção escolar.

Observação: mudanças após aprovação são sensíveis porque podem afetar turmas, diários e documentos já emitidos.

## Diários e listas de frequência

Situação atual: adequado para lançamento e conferência operacional.

Inclui:

- diário por turma, componente e período;
- frequência, conteúdo, notas, recuperação e comportamento;
- confirmação do professor;
- consolidação pela gestão;
- reabertura controlada;
- alertas da gestão para professores;
- impressão para assinatura.

Observação: documentos de diário devem ser emitidos preferencialmente após confirmação do professor e consolidação do período.

## Pendências normativas ou institucionais

1. Validar com a escola se os documentos finais precisam de assinatura digital ICP-Brasil ou se a assinatura física com código de verificação é suficiente para o uso pretendido.
2. Criar, quando necessário, certificado/diploma de conclusão e revisar diretamente contra a RN 018/2025/CEE-MT.
3. Definir política formal de retenção, arquivamento e acesso aos documentos emitidos, em alinhamento com LGPD.
4. Confirmar se o texto institucional/autorizativo de cada escola está completo e atualizado no cadastro da escola.
5. Antes de produção, revisar exemplos reais emitidos em PDF com direção e secretaria escolar.
## Regras de bloqueio por cadastro incompleto

Documentos oficiais de escola, pessoa, matrícula, boletim, ficha individual e histórico escolar não devem ser emitidos quando faltar dado essencial de identificação civil, institucional ou escolar.

Para emissão documental, a escola precisa ter nome, razão social, CNPJ, INEP, data de fundação, telefone, e-mail, endereço, cidade, UF, CEP e texto institucional/autorizativo.

Para emissão documental de pessoa estudante ou vinculada à escola, a pessoa precisa ter nome completo, CPF, data de nascimento, nome da mãe, e-mail institucional e telefone.

Históricos escolares recebidos de outras escolas exigem escola relacionada, etapa, fundamento legal, local/data de emissão, anos/séries/fases, carga horária, resultado final e componentes curriculares.
