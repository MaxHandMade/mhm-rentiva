# M4 Release Checklist (MHM Rentiva v4.9.8)

## 1. Governance & Versioning
- [ ] **Strict Version Sync**: Ensure `4.9.8` in:
    - `mhm-rentiva.php` (Header & Constant)
    - `changelog.json` (Entry present)
    - `changelog-tr.json` (Entry present)
- [ ] **Changelog Validation**: Run `php bin/validate-changelog.php` (Must pass).

## 2. Quality Gates (HARD GATES)
- [ ] **PHPCS Strictness**: `composer run phpcs` must report **0 Errors**.
    - [ ] `StrictTypes` checks passed.
    - [ ] Code style violations resolved.
- [ ] **Plugin Check Compliance**: `composer run plugin-check:release` must report **0 Errors**.
    - **CRITICAL**: If *any* error exists, release is **BLOCKED**.
    - Parsing Logic: JSON output must be clean.

## 3. Build & Artifact
- [ ] **Clean Build**: Run `bin/build-release.ps1`.
- [ ] **Artifact Verification**:
    - [ ] Zip created: `build/mhm-rentiva.4.9.8.zip`.
    - [ ] Contents verified against `.distignore`.
    - [ ] No dev files (`.git`, `tests`, `phpcs.xml`) in zip.

## 4. Deployment
- [ ] **Tagging**: Git tag `4.9.8`.
- [ ] **Distribution**: Push tag and upload artifact.
