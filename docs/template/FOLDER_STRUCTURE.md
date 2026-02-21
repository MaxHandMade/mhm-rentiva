# FOLDER STRUCTURE

MHM Rentiva Layout motoru, "Core Engine" ve "Project Preset" katmanlarının kesin ayrımı üzerine kuruludur.

## 1. Canonical Directories

```text
src/Layout/
├── Adapters/          # UI Bileşen Adaptörleri (Project Specific)
├── CLI/               # CLI Komutları (Core)
├── Config/            # Kurallar ve Allowlist (Project Specific)
├── Ingestion/         # Atomal İthalat ve Hashing (Core)
├── Tokens/            # Core Token altyapısı + Project-specific mapping tabloları
└── Core Classes       # Validator, Builder vb. (Core)
```

## 2. Core vs Preset Matrix

Aşağıdaki tablo, hangi katmanların her projede değişmesi gerektiğini (Preset) ve hangilerinin asla dokunulmaması gerektiğini (Core) gösterir:

| Layer | Changes Per Project | Never Changes |
| :--- | :---: | :---: |
| Layout Engine Core (Builder/Validator) | ❌ | ✔ |
| ContractAllowlist (Wrappers/Slots) | ✔ | ❌ |
| Token Mapping (Values/Tables) | ✔ | ❌ |
| Atomic Import Logic (Rollback) | ❌ | ✔ |
| Governance Gates (Zero-Tailwind) | ❌ | ✔ |
| Adapters (Component Transform) | ✔ | ❌ |

## 3. Placement Guide

- **ContractAllowlist:** `src/Layout/Config/ContractAllowlist.php` adresinde bulunur. Yeni projede tüm izinli bileşenler ve sarmalayıcı stiller burada tanımlanır.
- **TokenMapperConfig:** `src/Layout/Tokens/TokenMapper.php` içinde Core algoritma korunur; sadece mapping tabloları projenin tasarım sistemine göre güncellenir.
- **Fixtures:** `tests/fixtures/` altında projeye özgü test manifestleri tutulur.


