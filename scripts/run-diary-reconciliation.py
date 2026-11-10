"""Run the bounded diary reconciliation; publish only an encrypted report."""
import base64
import json
import os
import pathlib
import tempfile
import time

import paramiko
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.ciphers.aead import AESGCM


def main():
    with tempfile.TemporaryDirectory() as directory:
        hosts = pathlib.Path(directory) / 'known_hosts'
        hosts.write_text(os.environ['HOSTINGER_KNOWN_HOSTS'])
        client = paramiko.SSHClient()
        client.load_host_keys(str(hosts))
        try:
            client.connect(**json.loads(os.environ['HOSTINGER_CONNECTION']), timeout=20,
                           auth_timeout=20, banner_timeout=20, allow_agent=False, look_for_keys=False)
            expected = os.environ['EXPECTED_COMMIT']
            deadline = time.monotonic() + 180
            while True:
                stdin, stdout, stderr = client.exec_command('cd /home/u810745753/domains/ctjj.org/public_html/sge && git rev-parse HEAD', timeout=20)
                stdin.close()
                published = stdout.read().decode().strip()
                stderr.read()
                if stdout.channel.recv_exit_status() == 0 and published == expected:
                    break
                if time.monotonic() >= deadline:
                    raise RuntimeError('O commit conferido não chegou ao servidor.')
                time.sleep(10)
            stdin, stdout, stderr = client.exec_command('cd /home/u810745753/domains/ctjj.org/public_html/sge && php', timeout=300)
            stdin.write(pathlib.Path('scripts/run-diary-reconciliation.php.stub').read_text())
            stdin.flush()
            stdin.channel.shutdown_write()
            raw = stdout.read()
            private_error = stderr.read()
            code = stdout.channel.recv_exit_status()
            try:
                result = json.loads(raw)
            except (json.JSONDecodeError, UnicodeDecodeError):
                result = {'mode': 'error', 'exit_code': code,
                          'private_stdout': raw.decode(errors='replace')[-20000:],
                          'private_stderr': private_error.decode(errors='replace')[-20000:]}
                raw = json.dumps(result).encode()
        finally:
            client.close()
    public = serialization.load_pem_public_key(pathlib.Path('scripts/history-report-public.pem').read_bytes())
    key, nonce = AESGCM.generate_key(bit_length=256), os.urandom(12)
    envelope = {
        'key': base64.b64encode(public.encrypt(key, padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))).decode(),
        'nonce': base64.b64encode(nonce).decode(),
        'ciphertext': base64.b64encode(AESGCM(key).encrypt(nonce, raw, None)).decode(),
    }
    pathlib.Path('diary-reconciliation-report.encrypted.json').write_text(json.dumps(envelope))
    print('Reconciliação concluída no modo', result['mode'], '; relatório privado criptografado.')
    if result['mode'] == 'error':
        raise RuntimeError('A conferência foi interrompida. O motivo está somente no relatório criptografado.')


if __name__ == '__main__':
    main()
