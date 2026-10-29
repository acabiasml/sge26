"""Production verification over SSH, with explicitly requested migrations."""
import json
import base64
import os
import pathlib
import datetime
import shlex
import tempfile
import time

import paramiko
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa
from cryptography.hazmat.primitives.ciphers.aead import AESGCM


def encrypt_report(output, public_key_text):
    public_key = serialization.load_pem_public_key(base64.b64decode(public_key_text, validate=True))
    if not isinstance(public_key, rsa.RSAPublicKey) or public_key.key_size < 2048:
        raise RuntimeError('Use uma chave pública RSA temporária de pelo menos 2048 bits.')
    key = AESGCM.generate_key(bit_length=256)
    nonce = os.urandom(12)
    ciphertext = AESGCM(key).encrypt(nonce, output.encode(), None)
    encrypted_key = public_key.encrypt(key, padding.OAEP(mgf=padding.MGF1(algorithm=hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
    return json.dumps({
        'key': base64.b64encode(encrypted_key).decode(),
        'nonce': base64.b64encode(nonce).decode(),
        'ciphertext': base64.b64encode(ciphertext).decode(),
    })


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
        if os.environ.get('CORRECT_GEOGRAPHY_486') == 'true':
            commands.append((
                'Corrigir nota importada de Geografia da matrícula 486 conforme solicitação',
                'php artisan diaries:correct-imported-grade 486 Geografia 4 6 --expected=4 --apply --no-ansi',
            ))
        inspection_id = os.environ.get('INSPECT_ENROLLMENT', '').strip()
        diary_date = os.environ.get('INSPECT_DIARY_DATE', '').strip()
        if diary_date:
            if datetime.date.fromisoformat(diary_date).isoformat() != diary_date:
                raise RuntimeError('Informe a data do diário no formato AAAA-MM-DD.')
            diary_code = (
                f'$date = "{diary_date}"; '
                'require base_path("scripts/inspect-diary-date.php");'
            )
            commands.append(('Conferência dos lançamentos do diário sem dados pessoais', 'php artisan tinker --execute=' + shlex.quote(diary_code)))
        if inspection_id:
            if not inspection_id.isascii() or not inspection_id.isdecimal():
                raise RuntimeError('Informe somente o ID numérico da matrícula para diagnóstico.')
            inspection_code = (
                f'$e = App\\Models\\StudentEnrollment::with("schoolClass.academicYear.school.academicCriteria")->findOrFail({int(inspection_id)}); '
                '$y = $e->schoolClass->academicYear; $s = $y->school; '
                '$r = app(App\\Support\\StudentFinalResultCalculator::class)->calculate($e); '
                '$report = app(App\\Support\\StudentReportCardBuilder::class)->build($e); '
                '$document = new App\\Models\\IssuedDocument(["issued_at" => now(), "verification_code" => "PREVIA-DIAGNOSTICO"]); '
                '$document->setRelation("issuedBy", null); '
                '$pdf = Barryvdh\\DomPDF\\Facade\\Pdf::loadView("reports.student-individual-record", ['
                '"report" => $report, "scoreView" => "numeros", "issuedDocument" => $document, '
                '"verificationUrl" => route("documents.verify", "PREVIA-DIAGNOSTICO"), '
                '"letterhead" => App\\Support\\PdfLetterhead::make($s)])->setPaper("a4", "portrait"); '
                '$layout = ["signature_bottom" => 0, "footer_top" => 10000, "signature_pages" => []]; '
                '$pdf->getDomPDF()->setCallbacks([["event" => "end_frame", "f" => function ($frame, $canvas) use (&$layout) { '
                '$node = $frame->get_node(); if (!($node instanceof DOMElement)) { return; } '
                '$box = $frame->get_border_box(); '
                'if ($node->getAttribute("class") === "signature-role") { '
                '$layout["signature_bottom"] = max($layout["signature_bottom"], $box[1] + $box[3]); '
                '$layout["signature_pages"][] = $canvas->get_page_number(); } '
                'if ($node->getAttribute("class") === "document-footer") { $layout["footer_top"] = min($layout["footer_top"], $box[1]); } '
                '}]]); $pdf->output(); '
                '$layout["pages"] = $pdf->getDomPDF()->getCanvas()->get_page_count(); '
                '$layout["footer_clearance"] = round($layout["footer_top"] - $layout["signature_bottom"], 2); '
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
                '"passing_points" => $y->passing_points, "individual_record_layout" => $layout], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);'
            )
            commands.append(('Conferência do resultado da matrícula', 'php artisan tinker --execute=' + shlex.quote(inspection_code)))
        pending_year_id = os.environ.get('PENDING_YEAR_FOR_ENROLLMENT', '').strip()
        if pending_year_id:
            if not pending_year_id.isascii() or not pending_year_id.isdecimal():
                raise RuntimeError('Informe somente o ID numérico da matrícula que identifica o ano.')
            # Validate the encryption key before retrieving any student information.
            encrypt_report('{}', os.environ.get('REPORT_PUBLIC_KEY', ''))
            pending_code = (
                f'$e = App\\Models\\StudentEnrollment::with("schoolClass.academicYear")->findOrFail({int(pending_year_id)}); '
                '$year = $e->schoolClass->academic_year_id; '
                '$rows = App\\Models\\StudentEnrollment::with(["student:id,full_name", "schoolClass:id,name"])->whereHas("schoolClass", fn ($q) => $q->where("academic_year_id", $year))'
                '->where("status", App\\Models\\StudentEnrollment::STATUS_ENROLLED)->where("final_result_status", App\\Models\\StudentEnrollment::FINAL_PENDING)->get()'
                '->sortBy(fn ($item) => $item->student->full_name)->values()->map(fn ($item) => ['
                '"enrollment_id" => $item->id, "student" => $item->student->full_name, "class" => $item->schoolClass->name, '
                '"reason" => $item->final_result_details["reason"] ?? null, '
                '"missing_attendance" => $item->final_result_details["missing_attendance"] ?? false, '
                '"pending_components" => array_column($item->final_result_details["pending_components"] ?? [], "name")]); '
                'echo json_encode(["academic_year_id" => $year, "students" => $rows], JSON_UNESCAPED_UNICODE);'
            )
            commands.append(('Resultados pendentes para relatório criptografado', 'php artisan tinker --execute=' + shlex.quote(pending_code)))
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
            if label == 'Resultados pendentes para relatório criptografado':
                if code:
                    raise RuntimeError('Não foi possível consultar os resultados pendentes.')
                report = json.loads(output)
                pathlib.Path('pending-results.encrypted.json').write_text(encrypt_report(output, os.environ['REPORT_PUBLIC_KEY']))
                print(f'Relatório criptografado gerado: {len(report["students"])} registros. Nenhum nome foi publicado no log.', flush=True)
            else:
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
