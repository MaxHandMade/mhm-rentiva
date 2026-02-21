# CLI RUNBOOK

MHM Rentiva Layout motoru, tüm operasyonların CLI üzerinden yönetilmesini sağlar.

## 1. Canonical Commands

### Manifest Validasyonu (Dry-Run)
Veritabanına dokunmadan yalnızca manifestin yapısal olarak doğru olup olmadığını kontrol eder.
```bash
wp mhm-rentiva layout import path/to/manifest.json --dry-run
```

### Değişiklik Önizleme (Preview)
Var olan sayfalar ile manifest arasındaki farkları (diff) raporlar. **Yan etkisizdir.**
```bash
wp mhm-rentiva layout import path/to/manifest.json --preview
```

### Atomik İthalat (Import)
Tüm sayfaları içe aktarır. Biri bile hata verirse hiçbiri yazılmaz.
```bash
wp mhm-rentiva layout import path/to/manifest.json --create
```

## 2. Output Expectations

| Koşul | Beklenen Çıktı |
| :--- | :--- |
| **Başarı** | `Status: CREATED/UPDATED`, `ID: 123`, `Title: Home`, `Message: OK` |
| **Validasyon Hatası** | `Error: Missing required field 'composition' in page 'p1'.` |
| **Rollback** | `Import failed: ... Rollback executed. Database state preserved.` |

## 3. Command Evidence Standard (Mandatory)

CLI çıktıları aşağıdaki standartla kaydedilmelidir:

```text
### [CLI Verification Evidence]
- Command: `wp mhm-rentiva layout import <manifest.json> --dry-run`
  Exit Code: `<0|non-zero>`
  Summary: `<short output summary>`
- Command: `wp mhm-rentiva layout import <manifest.json> --preview`
  Exit Code: `<0|non-zero>`
  Summary: `<short output summary>`
- Command: `wp mhm-rentiva layout import <manifest.json> --create`
  Exit Code: `<0|non-zero>`
  Summary: `<short output summary>`
```

Kural:
- Dry-run ve preview evidence olmadan create/import onayı verilmez.

## 4. Side-Effect Free Guarantees

- **--dry-run:** Hiçbir post meta veya post content alanına yazılmaz. `Builder` çalıştırılmaz.
- **--preview:** Yalnızca Bellek üzerinde (In-memory) karşılaştırma yapılır. `_mhm_layout_hash` güncellemesi tetiklenmez.
- **Atomic Mode:** Bir `Exception` fırlatıldığında `undo_stack` ve `snapshots` kullanılarak DB eski haline döndürülür.


