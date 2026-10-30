"""Decrypt a report into a private local file, never into Actions/public logs."""
import argparse
import base64
import json
import pathlib

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

parser = argparse.ArgumentParser()
parser.add_argument('report')
parser.add_argument('private_key')
parser.add_argument('output')
args = parser.parse_args()
repository = pathlib.Path(__file__).resolve().parent.parent
output = pathlib.Path(args.output).resolve()
if output.is_relative_to(repository):
    parser.error('Guarde o relatório privado fora do repositório.')
report = json.loads(pathlib.Path(args.report).read_text())
private_key = serialization.load_pem_private_key(pathlib.Path(args.private_key).read_bytes(), password=None)
key = private_key.decrypt(base64.b64decode(report['key'], validate=True), padding.OAEP(mgf=padding.MGF1(hashes.SHA256()), algorithm=hashes.SHA256(), label=None))
raw = AESGCM(key).decrypt(base64.b64decode(report['nonce'], validate=True), base64.b64decode(report['ciphertext'], validate=True), None)
json.loads(raw)
with output.open('xb') as stream:
    output.chmod(0o600)
    stream.write(raw)
print('Relatório descriptografado em arquivo local privado:', output)
