# GOVERNANCE GATES

Bu doküman, projenin mühendislik disiplinini koruyan "Yönetişim Kapıları"nı tanımlar.

## 1. No Tailwind Usage Enforcement
Projede Tailwind runtime (CDN veya dinamik sınıf üretimi) ve Tailwind utility class kullanımı yasaktır.
- **Kontrol:** `AssetManager` içinde `tailwind` stringi içeren script handle'ları yasaktır.
- **Denetim:** Statik taramalarda `.css` dosyalarında `@tailwind` direktifleri aranır.

## 2. Contract Allowlist / Drift Gate
`CompositionBuilder`, yalnızca `ContractAllowlist` içinde beyan edilmiş bileşenleri render eder.
- Beyan edilmemiş bir bileşen manifestte görülürse, `AtomicImporter` işlemi durdurur ve rollback yapar.

## 3. Asset Snapshot Diff
Her yayın öncesinde enqueued asset listesi kontrol edilmelidir.
- Yeni bir global CSS/JS eklenmesi, kıdemli mühendis onayına tabidir.
- Gereksiz yüklemeleri önlemek için "Conditional Loading" zorunludur.

## 4. ΔQ ≤ 0 Performance Policy
Layout motorunun render sırasında hiçbir ek veritabanı sorgusu yapmaması gerekir (ΔQ = 0).
- Tüm veriler manifestten gelmelidir.
- `get_post_meta` gibi WordPress core fonksiyonları sadece SSOT değerini okumak için bir kez çalıştırılır, döngü içinde veri sorgulanmaz.

## 5. Fail Conditions
Aşağıdaki durumlardan herhangi birinin tespiti durumunda PR (Pull Request) reddedilir:
- Tailwind sınıflarının HTML markup içinde görülmesi.
- Render sırasında tetiklenen ek SQL sorguları.
- `ContractAllowlist` baypas edilerek doğrudan PHP template çağırılması.

## 6. Gate Evidence Matrix

| Gate | Verification Command / Method | Pass Criteria | Required Evidence |
| :--- | :--- | :--- | :--- |
| No Tailwind Usage | Static scan (`rg "@tailwind|tailwind"`), asset handle review | `0` finding | Scan output summary + reviewed file list |
| Contract Allowlist / Drift | Manifest import in `--dry-run` and `--preview` + allowlist validation logs | No undeclared component | CLI output summary + allowlist validation note |
| Asset Snapshot Diff | Compare before/after enqueue handle snapshot | No unexpected global CSS/JS | Snapshot diff report (`before`/`after`) |
| ΔQ ≤ 0 Performance | Query Monitor or internal performance test | Delta queries `<= 0` | Measurement output (`baseline`, `after`, `delta`) |
| Atomic Safety | Import failure simulation and rollback check | DB state preserved on failure | Failure scenario output + rollback confirmation |

Evidence missing for any gate means automatic fail.


