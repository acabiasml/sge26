"""Narrowly scoped production read; only encrypted output leaves SSH."""
import base64
import json
import os
import pathlib
import re
import tempfile
import time

import paramiko
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.ciphers.aead import AESGCM


def main():
    config = pathlib.Path('scripts/history-query.json')
    query = json.loads(config.read_text()) if config.exists() else {'person_id': 0}
    requested_scope = os.environ.get('HISTORY_QUERY_SCOPE', 'configured')
    if requested_scope != 'configured':
        if requested_scope not in ('person-record-verification', 'person-record-web-verification', 'enrollment-record-web-verification', 'history-pdf-verification', 'high-school-cohorts', 'school-diary-completion', 'diary-restoration-proposal', 'class-approval-audit'):
            raise ValueError('Escopo manual inválido.')
        query['scope'] = requested_scope
    scope = query.get('scope', 'student')
    if scope in ('person-record-web-verification', 'enrollment-record-web-verification') and requested_scope != scope:
        raise ValueError('A emissão web exige seleção manual explícita desse escopo.')
    key_pem = pathlib.Path('scripts/history-report-public.pem').read_bytes()
    if scope == 'class-approval-audit':
        approval_year = os.environ.get('APPROVAL_YEAR', '')
        approval_teacher = os.environ.get('APPROVAL_TEACHER_HASH', '')
        if requested_scope != scope or not re.fullmatch(r'(?:19|20)[0-9]{2}', approval_year) or not re.fullmatch(r'[0-9a-f]{64}', approval_teacher):
            raise ValueError('Informe ano e seletor do docente para a conferência do panorama.')
        key_pem = base64.b64decode(os.environ['APPROVAL_REPORT_KEY'], validate=True)
    public = serialization.load_pem_public_key(key_pem)
    sources = {'technical-credential-verification': 'scripts/technical-credential-query.php.stub',
               'student-identification-layout': 'scripts/student-identification-layout-query.php.stub',
               'person-record-web-verification': 'scripts/person-record-web-verification.php.stub',
               'enrollment-record-web-verification': 'scripts/person-record-web-verification.php.stub',
               'technical-cad-reconstruction': 'scripts/technical-cad-reconstruction-query.php.stub',
               'diary-supplement': 'scripts/diary-supplement-query.php.stub',
               'person-record-verification': 'scripts/person-record-verification.php.stub',
               'diary-restoration-proposal': 'scripts/diary-restoration-proposal-query.php.stub',
               'school-diary-completion': 'scripts/school-diary-completion-query.php.stub',
               'history-pdf-verification': 'scripts/history-pdf-verification.php.stub',
               'high-school-cohorts': 'scripts/high-school-cohorts-query.php.stub',
               'legacy-outcome-audit': 'scripts/legacy-outcome-audit.php.stub',
               'technical-enrollment-review': 'scripts/technical-enrollment-review.php.stub',
               'component-unification': 'scripts/component-unification-query.php.stub',
               'technical-grades': 'scripts/technical-grades-query.php.stub',
               'diary-workload-audit': 'scripts/diary-workload-audit-query.php.stub',
               'diary-progress': 'scripts/diary-progress-query.php.stub',
               'class-approval-audit': 'scripts/class-approval-audit.php.stub',
               'student': 'scripts/history-readonly-diagnosis.php.stub',
               'liceu-itinerary': 'scripts/liceu-itinerary-query.php.stub',
               'high-school-diversified': 'scripts/high-school-diversified-query.php.stub',
               'history-catalog-classification': 'scripts/history-catalog-classification-query.php.stub',
               'student-workload': 'scripts/student-workload-query.php.stub',
               'student-transfer-grades': 'scripts/student-transfer-grades-query.php.stub',
               'class-diary-reconciliation': 'scripts/class-diary-reconciliation-query.php.stub',
               'history-issuance': 'scripts/history-issuance-query.php.stub',
               'diary-batch': 'scripts/diary-batch-query.php.stub',
               'student-reenrollment': 'scripts/student-reenrollment-query.php.stub'}
    if scope not in sources:
        raise ValueError('Escopo de consulta inválido.')
    if scope == 'technical-cad-reconstruction':
        if type(query.get('school_id')) is not int or query['school_id'] <= 0 or not re.fullmatch(r'[0-9a-f]{32}', query.get('transport_key_id', '')):
            raise ValueError('Escola ou transporte inválido para conferir o CAD I.')
    if scope == 'diary-supplement':
        ids = query.get('assignment_ids', [])
        if type(query.get('school_id')) is not int or query['school_id'] <= 0 or not re.fullmatch(r'[0-9a-f]{32}', query.get('transport_key_id', '')) or not isinstance(ids, list) or not 1 <= len(ids) <= 20 or len(set(ids)) != len(ids) or any(type(i) is not int or i <= 0 for i in ids):
            raise ValueError('Diários ou transporte inválidos para conferir o preenchimento parcial.')
    if scope == 'history-pdf-verification':
        if type(query.get('person_id')) is not int or query['person_id'] <= 0 or type(query.get('history_id')) is not int or query['history_id'] <= 0:
            raise ValueError('Estudante e histórico obrigatórios para verificar a emissão com rollback.')
    if scope in ('person-record-verification', 'person-record-web-verification', 'enrollment-record-web-verification') and (type(query.get('person_id')) is not int or query['person_id'] <= 0):
        raise ValueError('Pessoa obrigatória para conferir a ficha com rollback.')
    if scope == 'enrollment-record-web-verification' and (type(query.get('enrollment_id')) is not int or query['enrollment_id'] <= 0):
        raise ValueError('Matrícula obrigatória para conferir a ficha individual pelo acesso web.')
    if scope == 'legacy-outcome-audit':
        for field, maximum in [('school_ids', 3), ('class_ids', 40), ('candidate_enrollment_ids', 500)]:
            ids = query.get(field, [])
            if not isinstance(ids, list) or not 1 <= len(ids) <= maximum or len(set(ids)) != len(ids) or any(type(i) is not int or i <= 0 for i in ids):
                raise ValueError('Seletores inválidos para conferência de resultados originais.')
    if scope == 'component-unification':
        if any(type(query.get(key)) is not int or query[key] <= 0 for key in ('keep_component_id', 'remove_component_id')):
            raise ValueError('Componentes inválidos para conferência de unificação.')
    if scope == 'technical-grades' and 'school_id' in query:
        if type(query['school_id']) is not int or query['school_id'] <= 0:
            raise ValueError('Escola inválida para conferir notas técnicas.')
    if scope == 'technical-enrollment-review':
        if any(type(query.get(key)) is not int or query[key] <= 0 for key in ('school_id', 'course_id', 'class_id')):
            raise ValueError('Oferta técnica inválida.')
        ids = query.get('person_ids', [])
        if not isinstance(ids, list) or not 1 <= len(ids) <= 10 or len(set(ids)) != len(ids) or any(type(i) is not int or i <= 0 for i in ids):
            raise ValueError('Selecione até dez pessoas para a conferência técnica.')
    source = pathlib.Path(sources[scope]).read_text()
    if scope == 'class-approval-audit':
        source = source.replace('__APPROVAL_YEAR__', approval_year).replace('__APPROVAL_TEACHER_HASH__', approval_teacher)
    source = source.replace('__ENROLLMENT_ID__', str(query['enrollment_id'] if scope == 'enrollment-record-web-verification' else 0))
    person_id = query['person_id']
    if type(person_id) is not int or person_id < 0:
        raise ValueError('Identificador de pessoa inválido.')
    if scope == 'student':
        selector = query.get('name_selector')
        if selector is not None:
            if (person_id != 0 or not isinstance(selector, dict)
                    or set(selector) != {'salt', 'name_sha256', 'school'}
                    or any(not isinstance(value, str) for value in selector.values())
                    or not re.fullmatch(r'[0-9a-f]{32}', selector['salt'])
                    or not re.fullmatch(r'[0-9a-f]{64}', selector['name_sha256'])
                    or not re.fullmatch(r'[a-z]{3,30}', selector['school'])):
                raise ValueError('Seletores da consulta por nome e escola inválidos.')
        source = source.replace('__NAME_SELECTOR__', json.dumps(selector, separators=(',', ':')))
    if scope == 'diary-workload-audit':
        if type(query.get('reference_year')) is not int or not 1900 <= query['reference_year'] <= 2100:
            raise ValueError('Ano inválido para auditoria das aulas previstas.')
    if scope == 'diary-progress':
        if 'teacher_first_name_sha256' in query:
            if (not re.fullmatch(r'[0-9a-f]{64}', query['teacher_first_name_sha256'])
                    or query.get('school_source') not in ('lar', 'laura', 'beaba')
                    or type(query.get('reference_year')) is not int or not 1900 <= query['reference_year'] <= 2100):
                raise ValueError('Seletores da docência e escola inválidos.')
        else:
            if any(type(query.get(key)) is not int or query[key] <= 0 for key in ('course_id', 'school_id')):
                raise ValueError('Informe a escola e a oferta dos diários para conferência.')
            comparisons = query.get('comparison_course_ids', [])
            if not isinstance(comparisons, list) or len(comparisons) > 10 or any(type(item) is not int or item <= 0 for item in comparisons):
                raise ValueError('Informe até dez ofertas da mesma escola para comparação.')
    if scope == 'diary-batch':
        if 'matrix_only' in query and type(query['matrix_only']) is not bool:
            raise ValueError('Seletor de conferência da matriz inválido.')
        if 'all_school_classes' in query:
            if (query['all_school_classes'] is not True
                    or type(query.get('school_id')) is not int or query['school_id'] <= 0
                    or type(query.get('reference_year')) is not int or not 1900 <= query['reference_year'] <= 2100
                    or query.get('source') not in ('lar', 'laura', 'beaba')
                    or 'classes' in query or 'course_ids' in query):
                raise ValueError('Selecione uma escola e um ano válidos para a conferência das turmas.')
        elif 'course_ids' in query:
            ids = query['course_ids']
            if (not isinstance(ids, list) or not 1 <= len(ids) <= 10 or len(set(ids)) != len(ids)
                    or any(type(item) is not int or item <= 0 for item in ids)
                    or type(query.get('school_id')) is not int or query['school_id'] <= 0):
                raise ValueError('Selecione até dez matrizes técnicas da mesma escola.')
        else:
            classes = query.get('classes', [])
            if query.get('source') not in ('lar', 'laura', 'beaba') or not isinstance(classes, list) or not 1 <= len(classes) <= 20:
                raise ValueError('Recorte de diários inválido.')
            for row in classes:
                if (not isinstance(row, dict) or type(row.get('reference_year')) is not int
                        or not 1900 <= row['reference_year'] <= 2100
                        or not isinstance(row.get('name'), str) or not 1 <= len(row['name']) <= 80):
                    raise ValueError('Turma ou ano inválido no recorte.')
    if scope == 'class-diary-reconciliation':
        key_id = query.get('transport_key_id', '')
        reference_year = query.get('reference_year')
        if person_id <= 0 or not re.fullmatch(r'[0-9a-f]{32}', key_id) or type(reference_year) is not int or not 1900 <= reference_year <= 2100:
            raise ValueError('Seletores da turma inválidos.')
        source = source.replace('__TRANSPORT_KEY_ID__', key_id).replace('__REFERENCE_YEAR__', str(reference_year))
    if scope == 'student-reenrollment':
        reference_year = query.get('reference_year')
        if person_id <= 0 or type(reference_year) is not int or not 1900 <= reference_year <= 2100:
            raise ValueError('Estudante ou ano inválido para conferir a rematrícula.')
        source = source.replace('__REFERENCE_YEAR__', str(reference_year))
    if scope in ('student-workload', 'student-transfer-grades'):
        name_hash = query.get('name_sha256', '')
        reference_year = query.get('reference_year')
        if not re.fullmatch(r'[0-9a-f]{64}', name_hash) or type(reference_year) is not int or not 1900 <= reference_year <= 2100:
            raise ValueError('Seletor de estudante ou ano inválido.')
        source = source.replace('__NAME_SHA256__', name_hash).replace('__REFERENCE_YEAR__', str(reference_year))
        if scope == 'student-transfer-grades':
            import datetime
            name_words = query.get('name_words', 20)
            if type(name_words) is not int or not 1 <= name_words <= 20:
                raise ValueError('Quantidade de palavras do nome inválida.')
            source = source.replace('__NAME_WORDS__', str(name_words))
            simulated_date = query.get('simulated_transfer_date', '')
            if simulated_date and datetime.date.fromisoformat(simulated_date).isoformat() != simulated_date:
                raise ValueError('Data de transferência simulada inválida.')
            source = source.replace('__SIMULATED_TRANSFER_DATE__', simulated_date)
    source = source.replace('__PERSON_ID__', str(person_id))
    source = source.replace('__ENCRYPTED_TARGET__', pathlib.Path('scripts/history-diagnosis-target.enc').read_text().strip())
    with tempfile.TemporaryDirectory() as directory:
        hosts = pathlib.Path(directory) / 'known_hosts'
        hosts.write_text(os.environ['HOSTINGER_KNOWN_HOSTS'])
        client = paramiko.SSHClient()
        client.load_host_keys(str(hosts))
        try:
            client.connect(**json.loads(os.environ['HOSTINGER_CONNECTION']), timeout=20, auth_timeout=20, banner_timeout=20, allow_agent=False, look_for_keys=False)
            expected = os.environ.get('EXPECTED_COMMIT')
            if expected:
                deadline = time.monotonic() + 180
                while True:
                    probe_in, probe_out, probe_err = client.exec_command('cd /home/u810745753/domains/ctjj.org/public_html/sge && git rev-parse HEAD', timeout=20)
                    probe_in.close()
                    try:
                        published = probe_out.read().decode().strip()
                        probe_err.read()
                        code = probe_out.channel.recv_exit_status()
                    finally:
                        probe_out.close()
                        probe_err.close()
                    if code == 0 and published == expected:
                        break
                    if time.monotonic() >= deadline:
                        raise RuntimeError('O deploy da consulta ainda não chegou ao servidor.')
                    time.sleep(10)
            stdin, stdout, stderr = client.exec_command('cd /home/u810745753/domains/ctjj.org/public_html/sge && php -d memory_limit=1G', timeout=360 if scope == 'student-identification-layout' else 180 if scope in ('technical-credential-verification', 'legacy-outcome-audit', 'history-pdf-verification', 'school-diary-completion', 'diary-restoration-proposal', 'diary-supplement', 'class-approval-audit') else 60)
            stdin.write(source)
            stdin.flush()
            stdin.channel.shutdown_write()
            raw = stdout.read()
            private_error = stderr.read()
            code = stdout.channel.recv_exit_status()
            if code:
                # Falhas também ficam criptografadas, sem perder a causa real
                # nem publicar registros escolares no log do Actions.
                raw = json.dumps({'diagnosis_failed': True, 'exit_code': code,
                                  'output': raw.decode(errors='replace'),
                                  'error': private_error.decode(errors='replace')}).encode()
            try:
                json.loads(raw)
            except (json.JSONDecodeError, UnicodeDecodeError):
                raw = json.dumps({'diagnosis_failed': True, 'exit_code': code,
                                  'output': raw.decode(errors='replace'),
                                  'error': private_error.decode(errors='replace')}).encode()
        finally:
            client.close()
    key, nonce = AESGCM.generate_key(bit_length=256), os.urandom(12)
    report = {'key': base64.b64encode(public.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))).decode(), 'nonce': base64.b64encode(nonce).decode(), 'ciphertext': base64.b64encode(AESGCM(key).encrypt(nonce, raw, None)).decode()}
    pathlib.Path('history-diagnosis.encrypted.json').write_text(json.dumps(report))
    print('Consulta concluída; relatório criptografado, sem dados pessoais no log.')


if __name__ == '__main__':
    main()
