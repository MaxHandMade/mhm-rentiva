# Walkthrough Template

# Walkthrough: vX.Y.Z — Safe Rollback Engine

> Replace vX.Y.Z with the actual release version before finalizing this document.

Bu doküman, vX.Y.Z sürümünde eklenen Safe Rollback Engine özelliğinin teknik kapsamını, davranış modelini ve doğrulama sonuçlarını açıklar.

---

# 1. 🎯 Objective

Bu sürümün amacı:

- Layout versiyonlarını güvenli ve atomik şekilde geri alabilmek
- Deterministik hash altyapısını kullanarak güvenli flip davranışı sağlamak
- Governance kapılarını rollback sırasında da enforce etmek
- ΔQ ≤ 0 performans garantisini korumak

---

# 2. 🏗️ Architectural Changes

## 2.1 New Components

- `LayoutRollbackService`
- CLI: `layout rollback`
- Previous-state meta modeli:
  - `_mhm_layout_manifest_previous`
  - `_mhm_layout_hash_previous`
  - `_mhm_layout_version_timestamp_previous`

## 2.2 Modified Components

- `AtomicImporter` (shift logic separation)
- CLI command registration

## 2.3 Determinism Guarantee

- Previous manifest hash is re-verified via `LayoutNormalization`.
- Rollback will fail if hash mismatch is detected.
- No non-deterministic transformation occurs during restore.

---

# 3. 🔁 Rollback State Machine

Rollback aşağıdaki state akışına göre çalışır:

- STATE A — Snapshot current
- STATE B — Load previous
- STATE C — Validate & Gate
- STATE D — Apply & Flip
- STATE E — Failure Recovery
- STATE F — Success

Atomicity garantisi:

- Herhangi bir hata durumunda STATE A snapshot restore edilir.
- Kısmi yazma kesinlikle oluşmaz.

---

# 4. 🔐 Governance Enforcement

Rollback sırasında aşağıdaki kapılar çalıştırılır:

- Blueprint validation
- ContractAllowlist enforcement
- Token mapping validation
- Forbidden pattern scan
- Adapter validation

Rollback hiçbir validation katmanını bypass edemez.

---

# 5. ⚡ Performance Impact

- Rollback write-time işlemdir.
- Frontend render ΔQ = 0 korunmuştur.
- Asset handle snapshot değişmemiştir.
- Tailwind runtime tespiti yapılmamıştır.

---

# 6. 🧪 Test Coverage

Eklenen testler:

- RollbackPreconditionsTest
- RollbackHashMismatchTest
- RollbackSuccessFlipTest
- RollbackAtomicityTest
- RollbackDryRunNoSideEffectsTest
- RollbackDeltaQTest
- RollbackAssetSnapshotTest

Full PHPUnit Suite sonucu:

```text
OK (XXX tests, XXX assertions)
```

---

# 7. 🖥️ CLI Usage Examples

Dry-run:

```bash
wp mhm-rentiva layout rollback 123 --dry-run
```

Live:

```bash
wp mhm-rentiva layout rollback 123
```

Başarılı örnek çıktı:

```text
Rollback successful.
Post ID: 123
New Current Hash: abc...
New Previous Hash: def...
```

---

# 8. 🔄 Version Flip Semantics

Rollback sonrası:

- current ← old previous
- previous ← old current

Bu davranış, tek-seviyeli reversible flip sağlar.

---

# 9. 🛑 Failure Scenarios

Rollback başarısız olur:

- Previous manifest yoksa
- Hash mismatch varsa
- Validation kapılarından biri fail olursa

Bu durumlarda:

- Snapshot restore edilir
- DB state korunur

---

# 10. 📦 Evidence Summary

Detaylı kanıtlar aşağıdaki "Evidence Pack" bölümünde sunulmuştur.

## 📦 Minimum Evidence Pack Structure

Codex’in PR veya release dokümanında aşağıdaki başlıklar zorunlu olmalıdır:

### 📌 1. File Diff Summary

Added:

- `src/Layout/Versioning/LayoutRollbackService.php`
- `tests/Layout/Rollback/...`

Modified:

- `src/Layout/Ingestion/AtomicImporter.php`
- CLI command file

### 📌 2. PHPCS Evidence

```bash
vendor/bin/phpcs
```

```text
→ 0 errors
```

### 📌 3. PHPUnit Evidence

```bash
vendor/bin/phpunit
```

```text
OK (258+ tests, XXX assertions)
```

### 📌 4. Rollback-Specific Test Output

```bash
vendor/bin/phpunit --filter Rollback
```

```text
OK (...)
```

### 📌 5. CLI Verification

Dry-run output example:

```text
Rollback possible.
Current Hash: ...
Previous Hash: ...
No writes performed.
```

Live output example:

```text
Rollback successful.
Atomic restore completed.
```

### 📌 6. ΔQ Verification

Measurement:

```text
Render time delta (Queries) = 0
```

Tool used:

- Query Monitor / internal test

### 📌 7. Asset Snapshot Verification

Before / After handle comparison:

- No new global CSS/JS handles introduced.

### 📌 8. Governance Scan Result

- No Tailwind class usage detected
- No forbidden patterns detected

## 🔒 Evidence Rule

No evidence section = Release Blocked.

Evidence must be included in PR description or `walkthrough.md`.

## 🎯 Tasarım Kararı

Bu şablon:

- Sürümden bağımsızdır.
- v4.19.x ve sonrası için de tekrar kullanılabilir.
- Platform seviyesinde kalite kontrol sağlar.
