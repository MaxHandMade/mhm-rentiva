# FOLDER STRUCTURE

Applicability: plugin-only, theme-only, hybrid

The Layout Engine uses a strict separation between Core Engine and Project Preset layers.

## 1. Canonical Directories

```text
src/Layout/
├── Adapters/          # Project-specific component adapters
├── CLI/               # Core CLI commands
├── Config/            # Project-specific rules and allowlist
├── Ingestion/         # Core atomic import and hashing
├── Tokens/            # Core token pipeline + project mapping tables
└── Core Classes       # Validator, Builder, Diff, etc.
```

## 2. Core vs Preset Matrix

| Layer | Changes Per Project | Never Changes |
| :--- | :---: | :---: |
| Layout Engine Core (Builder/Validator) | No | Yes |
| ContractAllowlist (Wrappers/Slots) | Yes | No |
| Token Mapping Tables | Yes | No |
| Atomic Import Logic | No | Yes |
| Governance Gates | No | Yes |
| Adapters (Component Transform) | Yes | No |

## 3. Placement Guide
- ContractAllowlist: `src/Layout/Config/ContractAllowlist.php`
- TokenMapper: keep core algorithm, update only mapping tables.
- Fixtures: store project-specific manifests under `tests/fixtures/`.


