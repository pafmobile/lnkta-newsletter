# LNKTA Newsletter Plugin

Version **2.5.0** exports existing WordPress newsletters and creates Brevo drafts.
Every successful deliberate click creates a new campaign with the latest saved
newsletter content. Replaying one request returns its existing campaign instead
of producing accidental duplicates. Existing campaigns are not changed or sent.

## Install on WordPress

Upload `lnkta-newsletter-plugin-v2.5.0.zip` once and replace the existing LNKTA
Newsletter plugin. Existing ACF fields, posts, settings and API keys are retained.
The old Petfeelings Newsletter Exporter should remain inactive.

Settings > LNKTA Newsletter configures Brevo, newsletter branding and colors.
Tools > LNKTA Newsletter Updates configures GitHub updates independently of
LNKTA Security Guard. Enable **Automatically install verified releases of this
plugin** only when the signed baseline release is published and Check GitHub now
reports success. A same-version baseline verifies setup without reinstalling.

## Publish the repository

The intended repository is **pafmobile/lnkta-newsletter**, public so WordPress can
download release packages without keeping a GitHub token on the website.
Repository creation and the first signed release are required; preparing a ZIP
does not create or publish either of them.

Create that empty repository in GitHub. Extract this source bundle on your trusted
computer. From its root (the folder containing this README and .github), run:

```sh
git init -b main
git add .
git commit -m "Add LNKTA Newsletter 2.5.0 with signed updates"
git remote add origin https://github.com/pafmobile/lnkta-newsletter.git
git push -u origin main
```

## Reuse the existing Security Guard signing key

The plugin trusts this PUBLIC Ed25519 key, already used by Security Guard:

```
f208f57b47edc12fe9f38ab0386d280c6c9edac27bb3aaf16c07fe08665ba531
```

Do not generate a new key or upload private signing files to the repository.
Use the same trusted computer where Security Guard signing was configured. The
script reads the existing `~/.lnkta-release-signing/security-guard-ed25519.key`,
checks that it matches the public key above, and sets the NEW repository's secret
and variable through GitHub CLI without printing the private key.

```sh
python -m pip install cryptography
gh auth login
python tools/setup-signing.py
gh workflow run release.yml --repo pafmobile/lnkta-newsletter
```

Alternatively, add the existing `github-secret.txt` content as repository secret
`LNKTA_RELEASE_SIGNING_KEY` and the public key above as repository variable
`LNKTA_RELEASE_PUBLIC_KEY` in Settings > Secrets and variables > Actions.
These settings do not automatically carry across from Security Guard's repository.

The workflow tests and builds the source, signs `manifest.json`, and publishes
`v2.5.0` with its installation ZIP and public key. It refuses to publish without
signing setup and does not overwrite existing releases.

## Future releases

Change the plugin header version and the updater VERSION together. Commit all
source changes as one reviewed change to main. Changing the main plugin file on
main triggers the release workflow. WordPress checks signed published releases,
not arbitrary commits. Release metadata includes the exact plugin slug, source
repository, version, PHP/WordPress requirements, download URL and ZIP SHA-256.
The updater rejects wrong keys, modified metadata, other repositories, unexpected
package URLs, incompatible versions and hashes that do not match.

PHP sodium, working WordPress scheduled tasks and writable plugin files are
required for automatic installation. Native sodium verification must also be
checked on the live host; tests can use a clearly labeled verifier shim in an
isolated PHP environment lacking sodium. The Python signing step uses real
Ed25519 signing and verifies its own output.

Explicit constants LNKTA_NEWSLETTER_RELEASE_REPOSITORY and
LNKTA_NEWSLETTER_RELEASE_PUBLIC_KEY can override the bundled trust settings.
No Brevo key, GitHub token, website credentials or private signing key belongs
in this source repository, installation ZIP or release assets.
