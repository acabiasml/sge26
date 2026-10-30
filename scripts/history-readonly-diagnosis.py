"""Narrowly scoped production read; only encrypted output leaves SSH."""
import base64
import json
import os
import pathlib
import tempfile

import paramiko
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.ciphers.aead import AESGCM


def main():
    source = pathlib.Path('scripts/history-readonly-diagnosis.php.stub').read_text()
    config = pathlib.Path('scripts/history-query.json')
    person_id = json.loads(config.read_text())['person_id'] if config.exists() else 0
    if type(person_id) is not int or person_id < 0:
        raise ValueError('Identificador de pessoa inválido.')
    source = source.replace('__PERSON_ID__', str(person_id))
    source = source.replace('__ENCRYPTED_TARGET__', pathlib.Path('scripts/history-diagnosis-target.enc').read_text().strip())
    with tempfile.TemporaryDirectory() as directory:
        hosts = pathlib.Path(directory) / 'known_hosts'
        hosts.write_text(os.environ['HOSTINGER_KNOWN_HOSTS'])
        client = paramiko.SSHClient()
        client.load_host_keys(str(hosts))
        try:
            client.connect(**json.loads(os.environ['HOSTINGER_CONNECTION']), timeout=20, auth_timeout=20, banner_timeout=20, allow_agent=False, look_for_keys=False)
            stdin, stdout, stderr = client.exec_command('cd /home/u810745753/domains/ctjj.org/public_html/sge && php', timeout=60)
            stdin.write(source)
            stdin.flush()
            stdin.channel.shutdown_write()
            raw = stdout.read()
            stderr.read()
            code = stdout.channel.recv_exit_status()
            if code:
                raise RuntimeError('Consulta remota falhou; saída privada omitida.')
            json.loads(raw)
        finally:
            client.close()
    public = serialization.load_pem_public_key(pathlib.Path('scripts/history-report-public.pem').read_bytes())
    key, nonce = AESGCM.generate_key(bit_length=256), os.urandom(12)
    report = {'key': base64.b64encode(public.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))).decode(), 'nonce': base64.b64encode(nonce).decode(), 'ciphertext': base64.b64encode(AESGCM(key).encrypt(nonce, raw, None)).decode()}
    pathlib.Path('history-diagnosis.encrypted.json').write_text(json.dumps(report))
    print('Consulta concluída; relatório criptografado, sem dados pessoais no log.')


if __name__ == '__main__':
    main()
