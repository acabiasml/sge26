"""Prepare a scoped read and a fresh encryption key; never put private keys in Git."""
import argparse
import json
import pathlib
import uuid

from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa

parser = argparse.ArgumentParser()
parser.add_argument('person_id', type=int)
parser.add_argument('--key-directory', default='/workspace/.sge26-cloud/private-reports')
args = parser.parse_args()
if args.person_id <= 0:
    parser.error('O identificador deve ser positivo.')
repository = pathlib.Path(__file__).resolve().parent.parent
directory = pathlib.Path(args.key_directory).resolve()
if directory.is_relative_to(repository):
    parser.error('Guarde a chave privada fora do repositório.')
directory.mkdir(parents=True, exist_ok=True, mode=0o700)
key = rsa.generate_private_key(public_exponent=65537, key_size=3072)
private_path = directory / (str(uuid.uuid4()) + '.pem')
with private_path.open('xb') as stream:
    private_path.chmod(0o600)
    stream.write(key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()))
(repository / 'scripts/history-report-public.pem').write_bytes(key.public_key().public_bytes(serialization.Encoding.PEM, serialization.PublicFormat.SubjectPublicKeyInfo))
(repository / 'scripts/history-query.json').write_text(json.dumps({'person_id': args.person_id}) + '\n')
print('Consulta preparada. Chave privada local:', private_path)
