# Recomposição administrativa de diários

Operação distinta da importação de diários originais: a gestão confirma que as
aulas foram ministradas, informa a indisponibilidade dos registros e autoriza
explicitamente a recomposição com rastreabilidade administrativa. Referências
pedagógicas de outros diários não comprovam conteúdo ou data da aula original.

`AdministrativeDiaryReconstruction` aceita somente diários vazios de 2022 a 2025
da escola indicada no manifesto criptografado. Não substitui lançamentos de
outra origem e recusa alterações posteriores no próprio lote.

- Datas precisam pertencer ao calendário letivo e à vigência do componente.
- A carga do lote precisa corresponder à matriz, sem modificar a matriz.
- Cada estudante deve ter vínculo vigente na data, considerando ingresso,
  transferência, cancelamento e reclassificação.
- O limite de 420 minutos por estudante/dia soma todos os componentes e todas
  as matrículas relacionadas, inclusive de outras escolas. Aproveitamento de
  horas por reclassificação não representa novas aulas físicas.
- A chamada é derivada exclusivamente de registros existentes da mesma escola
  e do mesmo dia. No internato, qualquer ausência prevalece. Registros deste
  tipo de recomposição nunca servem como evidência para outra recomposição.
- Sem evidência, nenhuma presença ou falta é criada. A folha mostra “Sem
  lançamento”; gravar outras chamadas não converte essa lacuna em falta.
- Notas e resultados finais não são recalculados por essa operação.

Os textos pedagógicos contêm os temas. `legacy_source` e `legacy_metadata`
identificam obrigatoriamente a recomposição, a indisponibilidade dos registros,
as datas não confirmadas originalmente, as referências usadas apenas como
referência e os registros de apoio da frequência. Não há autoria atribuída ao
professor. A auditoria registra lote, hash, cópia de segurança e resultado.

Executar primeiro `administrative-diary-reconstruction` em `dry-run` pelo
workflow protegido. A aplicação exige o hash da fotografia atual, grava cópia
privada e executa tudo em transação. O mesmo manifesto íntegro é idempotente.
Depois de aplicar, retornar a configuração versionada para `dry-run` e
conferir ausência de novas escritas pendentes. Chaves e relatórios descriptografados
permanecem fora do repositório.
