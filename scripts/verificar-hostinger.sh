#!/usr/bin/env bash
# Verificação somente de leitura. A autenticação é realizada pelo próprio SSH.
set -euo pipefail

if [[ ${1:-} == --help || ${1:-} == -h ]]; then
    cat <<'HELP'
Uso: ./scripts/verificar-hostinger.sh [commit]

Sem argumento, compara a produção com o HEAD deste repositório.
Solicita a senha SSH quando necessário (a digitação não aparece na tela).
Não faz deploy, não executa migrations e não altera arquivos no servidor.

Configuração opcional por variáveis de ambiente:
  SGE_SSH_HOST, SGE_SSH_PORT, SGE_SSH_USER, SGE_REMOTE_DIR, SGE_SITE_URL
HELP
    exit 0
fi
if (( $# > 1 )); then
    echo 'Informe apenas o commit desejado ou use --help.' >&2
    exit 2
fi
for dependency in git ssh curl; do
    command -v "$dependency" >/dev/null || { echo "Comando necessário: $dependency" >&2; exit 2; }
done
script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
repo_dir=$(git -C "$script_dir" rev-parse --show-toplevel)
commit_ref=${1:-HEAD}
if [[ $commit_ref != HEAD && ! $commit_ref =~ ^[a-fA-F0-9]{7,40}$ ]]; then
    echo 'Use um hash de commit com 7 a 40 caracteres hexadecimais.' >&2
    exit 2
fi
expected_commit=$(git -C "$repo_dir" rev-parse --verify "${commit_ref}^{commit}")
ssh_host=${SGE_SSH_HOST:-212.1.209.21}
ssh_port=${SGE_SSH_PORT:-65002}
ssh_user=${SGE_SSH_USER:-u810745753}
remote_dir=${SGE_REMOTE_DIR:-/home/u810745753/domains/ctjj.org/public_html/sge}
site_url=${SGE_SITE_URL:-https://ctjj.org/sge/}

printf 'Commit esperado: %s\nConectando a %s:%s como %s…\n' "$expected_commit" "$ssh_host" "$ssh_port" "$ssh_user"
echo 'Se solicitado, digite a senha SSH. Nenhum caractere será exibido.'
# %q preserva espaços e impede que a configuração seja interpretada como comandos.
remote_payload=$(printf 'expected_commit=%q\nremote_dir=%q\n' "$expected_commit" "$remote_dir"
cat <<'REMOTE'
set -euo pipefail
cd -- "$remote_dir"
result=0
actual_commit=$(git rev-parse HEAD)
printf '\nCommit no servidor: %s\n' "$actual_commit"
if [[ $actual_commit == "$expected_commit" ]]; then
    echo '[OK] O commit esperado está publicado.'
else
    echo '[PENDENTE] O commit no servidor difere do esperado.'
    result=1
fi
php_version=$(php -r 'echo PHP_VERSION;')
printf 'PHP no servidor: %s\n' "$php_version"
if [[ $php_version == 8.4.* ]]; then
    echo '[OK] PHP 8.4.'
else
    echo '[FALHA] A versão esperada neste projeto é PHP 8.4.'
    result=1
fi
if migration_status=$(php artisan migrate:status --no-ansi 2>&1); then
    printf '\n%s\n' "$migration_status"
    if [[ $migration_status == *Pending* ]]; then
        echo '[PENDENTE] Existem migrations não executadas. Nenhuma foi executada por este script.'
        result=1
    else
        echo '[OK] Não há migrations pendentes.'
    fi
else
    printf '%s\n[FALHA] Não foi possível consultar as migrations.\n' "$migration_status"
    result=1
fi
exit "$result"
REMOTE
)
result=0
if ssh -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=2 \
    -p "$ssh_port" -l "$ssh_user" "$ssh_host" 'bash -s' <<< "$remote_payload"; then
    echo '[OK] Verificações SSH concluídas.'
else
    ssh_status=$?
    printf '[FALHA/PENDÊNCIA] A verificação SSH retornou código %s.\n' "$ssh_status"
    result=1
fi
if http_code=$(curl --silent --show-error --location --max-time 30 --output /dev/null --write-out '%{http_code}' "$site_url"); then
    if [[ $http_code == 200 ]]; then
        echo '[OK] A página de entrada respondeu HTTP 200.'
    else
        printf '[FALHA] A página de entrada respondeu HTTP %s.\n' "$http_code"
        result=1
    fi
else
    echo '[FALHA] Não foi possível acessar a página de entrada.'
    result=1
fi
if (( result == 0 )); then
    echo 'Verificação concluída: commit correto, PHP 8.4, migrations em dia e site respondendo.'
else
    echo 'Verificação incompleta: confira as falhas ou pendências acima.'
fi
exit "$result"
