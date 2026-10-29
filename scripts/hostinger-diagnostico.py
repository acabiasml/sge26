"""Production verification over SSH, with explicitly requested migrations."""
import json
import os
import pathlib
import shlex
import tempfile
import time

import paramiko


def run_remote(client, command):
    stdin, stdout, stderr = client.exec_command(
        'cd /home/u810745753/domains/ctjj.org/public_html/sge && ' + command,
        timeout=45,
    )
    stdin.close()
    try:
        output = stdout.read().decode(errors='replace')
        stderr.read()  # Não publicar erros da aplicação que possam conter dados sensíveis.
        code = stdout.channel.recv_exit_status()
        return output, code
    finally:
        stdout.close()
        stderr.close()


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
            ('Configuração da sessão', 'php -r \'require "vendor/autoload.php"; '
             '$app = require "bootstrap/app.php"; '
             '$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
             'echo config("session.lifetime"), " ", '
             'config("session.expire_on_close") ? "true" : "false", " ", '
             'config("session.driver"), PHP_EOL;\''),
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
        if os.environ.get('APPLY_ATTENDANCE_JUSTIFICATION_MIGRATION') == 'true':
            commands.append((
                'Aplicar migration do histórico das justificativas de ausência',
                'php artisan migrate --path=database/migrations/2026_10_28_000001_track_attendance_justification_edits.php --force --no-ansi',
            ))
        if os.environ.get('APPLY_ROLE_VIEW_MIGRATION') == 'true':
            commands.append((
                'Aplicar migration da preferência de visão do sistema',
                'php artisan migrate --path=database/migrations/2026_10_28_000002_add_preferred_role_view_to_users_table.php --force --no-ansi',
            ))
        if os.environ.get('APPLY_HISTORY_GRADE_MIGRATION') == 'true':
            commands.append((
                'Aplicar migration da identificação dos anos do histórico escolar',
                'php artisan migrate --path=database/migrations/2026_10_29_000001_add_grade_identity_to_history_years.php --force --no-ansi',
            ))
        if os.environ.get('REFRESH_VIEWS') == 'true':
            commands.append(('Limpar cache das telas', 'php artisan view:clear --no-ansi'))
        if os.environ.get('INVALIDATE_ALL_SESSIONS') == 'true':
            commands.append((
                'Invalidar todas as sessões',
                'php artisan sessions:invalidate-all --force --no-ansi',
            ))
        if os.environ.get('CORRECT_2025_CRITERIA') == 'true':
            commands.append((
                'Corrigir critérios confirmados do ano de 2025 da matrícula 486',
                'php artisan academic-years:correct-criteria 486 --passing-points=22.5 --dependency-limit=2 --apply --no-ansi',
            ))
        inspection_id = os.environ.get('INSPECT_ENROLLMENT', '').strip()
        if inspection_id:
            if not inspection_id.isascii() or not inspection_id.isdecimal():
                raise RuntimeError('Informe somente o ID numérico da matrícula para diagnóstico.')
            inspection_code = (
                f'$e = App\\Models\\StudentEnrollment::with("schoolClass.academicYear.school.academicCriteria")->findOrFail({int(inspection_id)}); '
                '$y = $e->schoolClass->academicYear; $s = $y->school; '
                '$r = app(App\\Support\\StudentFinalResultCalculator::class)->calculate($e); '
                'echo json_encode(["enrollment_id" => $e->id, "closed" => $y->isClosed(), '
                '"year_end" => $y->ends_at?->toDateString(), '
                '"school_limit" => $s->dependency_component_limit, '
                '"applicable_limit" => $s->dependencyComponentLimitForDate($y->ends_at), '
                '"criteria" => $s->academicCriteria->map(fn ($c) => ["from" => $c->effective_from->toDateString(), "limit" => $c->dependency_component_limit])->all(), '
                '"recorded_status" => $e->final_result_status, '
                '"recorded_limit" => $e->final_result_details["dependency_component_limit"] ?? null, '
                '"recorded_failed_count" => count($e->final_result_details["failed_components"] ?? []), '
                '"calculated_status" => $r["status"], '
                '"calculated_failed_count" => count($r["details"]["failed_components"] ?? []), '
                '"passing_points" => $y->passing_points], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);'
            )
            commands.append(('Conferência do resultado da matrícula', 'php artisan tinker --execute=' + shlex.quote(inspection_code)))
        commands.append(('Migrations', 'php artisan migrate:status --no-ansi'))
        expected_commit = os.environ['EXPECTED_COMMIT']
        deadline = time.monotonic() + 180
        while True:
            print('\n--- Commit publicado ---', flush=True)
            output, code = run_remote(client, commands[0][1])
            print(output, flush=True)
            if code:
                raise RuntimeError(f'Commit publicado: comando falhou com código {code}')
            if output.strip() == expected_commit:
                break
            if time.monotonic() >= deadline:
                raise RuntimeError('O commit esperado não foi publicado após três minutos de espera.')
            print('Aguardando o deploy automático antes de continuar o diagnóstico.', flush=True)
            time.sleep(min(10, max(0, deadline - time.monotonic())))

        for label, command in commands[1:]:
            print(f'\n--- {label} ---', flush=True)
            output, code = run_remote(client, command)
            print(output, flush=True)
            if label == 'Configuração da sessão' and output.strip() not in ('120 true database', '120 true file'):
                raise RuntimeError('A sessão em produção não está limitada a duas horas de inatividade e ao navegador aberto.')
            if label == 'Migrations' and 'Pending' in output:
                raise RuntimeError('Há migrations pendentes em produção.')
            if label == 'Nomes sociais cadastrados após a limpeza' and output.strip() != '0':
                raise RuntimeError('Ainda há nomes sociais cadastrados em produção.')
            if code:
                # Do not print application errors, which may contain sensitive values.
                raise RuntimeError(f'{label}: comando falhou com código {code}')
    finally:
        client.close()
