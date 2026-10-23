"""Production verification over SSH, with explicitly requested migrations."""
import json
import os
import pathlib
import tempfile

import paramiko

config = json.loads(os.environ['HOSTINGER_CONNECTION'])
with tempfile.TemporaryDirectory() as directory:
    known_hosts = pathlib.Path(directory) / 'known_hosts'
    known_hosts.write_text(os.environ['HOSTINGER_KNOWN_HOSTS'])
    client = paramiko.SSHClient()
    client.load_host_keys(str(known_hosts))
    try:
        client.connect(**config, timeout=20, auth_timeout=20, banner_timeout=20,
                       allow_agent=False, look_for_keys=False)
        # Fixed read-only commands. No arbitrary commands or user input reach the shell.
        commands = [
            ('Commit publicado', 'git rev-parse HEAD'),
            ('Arquivos modificados', 'git status --short'),
            ('PHP', 'php -r \'echo PHP_VERSION, PHP_EOL;\''),
            ('Botão na chamada', "grep -c 'data-all-present' resources/views/teacher-diaries/attendance.blade.php"),
        ]
        if os.environ.get('APPLY_VISIBILITY_MIGRATION') == 'true':
            commands.append((
                'Aplicar migration de visibilidade',
                'php artisan migrate --path=database/migrations/2026_10_21_000001_add_visibility_to_official_documents_table.php --force --no-ansi',
            ))
        if os.environ.get('APPLY_PRIVATE_OWNER_MIGRATION') == 'true':
            commands.append((
                'Aplicar migration de proprietário privado',
                'php artisan migrate --path=database/migrations/2026_10_21_000002_add_private_owner_to_official_documents_table.php --force --no-ansi',
            ))
        if os.environ.get('APPLY_RECLASSIFICATION_MIGRATION') == 'true':
            commands.append((
                'Aplicar migration de resultados das reclassificações',
                'php artisan migrate --path=database/migrations/2026_10_23_000001_backfill_reclassified_enrollment_results.php --force --no-ansi',
            ))
        if os.environ.get('APPLY_SOCIAL_NAME_CLEANUP') == 'true':
            count_social_names = 'php artisan tinker --execute=\'echo DB::table("people")->whereNotNull("social_name")->count();\''
            commands.append(('Nomes sociais cadastrados antes da limpeza', count_social_names))
            commands.append((
                'Aplicar migration de limpeza dos nomes sociais',
                'php artisan migrate --path=database/migrations/2026_10_23_000002_clear_existing_social_names.php --force --no-ansi',
            ))
            commands.append(('Nomes sociais cadastrados após a limpeza', count_social_names))
        commands.append(('Migrations', 'php artisan migrate:status --no-ansi'))
        if os.environ.get('REFRESH_VIEWS') == 'true':
            commands.append(('Limpar cache das telas', 'php artisan view:clear --no-ansi'))
        for label, command in commands:
            print(f'\n--- {label} ---', flush=True)
            _, stdout, stderr = client.exec_command(
                'cd /home/u810745753/domains/ctjj.org/public_html/sge && ' + command,
                timeout=45,
            )
            output = stdout.read().decode(errors='replace')
            error = stderr.read().decode(errors='replace')
            code = stdout.channel.recv_exit_status()
            print(output, flush=True)
            if label == 'Commit publicado' and output.strip() != os.environ['EXPECTED_COMMIT']:
                raise RuntimeError('O commit esperado ainda não foi publicado.')
            if label == 'Migrations' and 'Pending' in output:
                raise RuntimeError('Há migrations pendentes em produção.')
            if label == 'Nomes sociais cadastrados após a limpeza' and output.strip() != '0':
                raise RuntimeError('Ainda há nomes sociais cadastrados em produção.')
            if code:
                # Do not print application errors, which may contain sensitive values.
                raise RuntimeError(f'{label}: comando falhou com código {code}')
    finally:
        client.close()
