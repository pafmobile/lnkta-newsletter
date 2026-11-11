#!/usr/bin/env python3
"""Run on the same trusted computer used to set up LNKTA Security Guard signing.
Requires Python cryptography and an authenticated GitHub CLI. No private key is printed.
"""
import base64,subprocess,shutil
from pathlib import Path
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
repo='pafmobile/lnkta-newsletter'
path=Path.home()/'.lnkta-release-signing'/'security-guard-ed25519.key'
if not path.is_file():raise SystemExit('Existing Security Guard signing key not found. Use the trusted computer where it was generated; do not generate a replacement.')
key=Ed25519PrivateKey.from_private_bytes(path.read_bytes())
public=key.public_key().public_bytes(serialization.Encoding.Raw,serialization.PublicFormat.Raw).hex()
expected='f208f57b47edc12fe9f38ab0386d280c6c9edac27bb3aaf16c07fe08665ba531'
if public!=expected:raise SystemExit('Existing signing key does not match the public key trusted by this plugin. No secrets were changed.')
if not shutil.which('gh'):raise SystemExit('Install GitHub CLI and run gh auth login first.')
subprocess.run(['gh','secret','set','LNKTA_RELEASE_SIGNING_KEY','--repo',repo],input=base64.b64encode(path.read_bytes()),check=True)
subprocess.run(['gh','variable','set','LNKTA_RELEASE_PUBLIC_KEY','--repo',repo,'--body',public],check=True)
print('Newsletter repository signing configured. No private key was printed or added to source.')
