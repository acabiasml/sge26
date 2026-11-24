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


def run_remote(client, command, timeout=45):
    stdin, stdout, stderr = client.exec_command(
        'cd /home/u810745753/domains/ctjj.org/public_html/sge && ' + command,
        timeout=timeout,
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


source = r'''
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection();
$db->statement("START TRANSACTION READ ONLY");
try {
    $schools = $db->table("schools")->select("id", "name", "legal_name", "cnpj", "inep", "city", "state", "active",
        "creation_act", "accreditation_act", "document_authorizations", "letterhead_text", "letterhead_text_elementary", "letterhead_text_high_school")->orderBy("id")->get();
    $programs = $db->table("technical_programs")->select("school_id", "name", "accreditation_act", "authorization_act",
        "regulatory_process", "regulatory_opinion", "official_gazette_reference", "authorization_starts_at", "authorization_ends_at", "offer_forms")
        ->orderBy("school_id")->get();
    $offers = $db->table("academic_courses")->join("academic_years", "academic_years.id", "=", "academic_courses.academic_year_id")
        ->where("academic_years.reference_year", ">=", 2025)
        ->select("academic_years.school_id", "academic_years.reference_year", "academic_courses.stage", "academic_courses.modality")
        ->distinct()->orderBy("academic_years.school_id")->get();
    echo json_encode(compact("schools", "programs", "offers"), JSON_THROW_ON_ERROR);
} finally {
    $db->statement("ROLLBACK");
}
'''
with tempfile.TemporaryDirectory() as directory:
    known_hosts = pathlib.Path(directory) / 'known_hosts'
    known_hosts.write_text(os.environ['HOSTINGER_KNOWN_HOSTS'])
    with paramiko.SSHClient() as client:
        client.load_host_keys(str(known_hosts))
        client.connect(**json.loads(os.environ['HOSTINGER_CONNECTION']), timeout=20, auth_timeout=20, banner_timeout=20,
                       allow_agent=False, look_for_keys=False)
        output, code = run_remote(client, 'php -d display_errors=0 -r ' + shlex.quote(source))
        if code != 0:
            raise RuntimeError('A consulta de cadastro não foi concluída no servidor.')
        json.loads(output)
        public_key = base64.b64encode(pathlib.Path('scripts/authorized-school-query-public.pem').read_bytes()).decode()
        pathlib.Path('atos-escolas.encrypted.json').write_text(encrypt_report(output, public_key))
