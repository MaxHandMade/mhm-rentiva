# CI CHECKLIST

Her PR ve Release öncesinde aşağıdaki "Acceptance Gate" (Kabul Kapısı) kontrol edilmelidir.

## 1. Minimum Project Bootstrap Success Criteria

CI hattında aşağıdaki blok "PASS" vermelidir:

- [ ] **composer install:** Bağımlılıklar sözleşme uyumlu mu?
- [ ] **phpcs:** WordPress Coding Standards (WPCS) ve Strict Types uyumu.
- [ ] **phpunit:** Tüm testler geçti mi? (`0 failures`, `0 errors`, `0 risky`, `0 warnings`).
- [ ] **layout import --dry-run:** `wp mhm-rentiva layout import <manifest.json> --dry-run` komutu hatasız mı?
- [ ] **layout import --preview:** `wp mhm-rentiva layout import <manifest.json> --preview` çıktısı beklenen diff ile uyumlu mu?
- [ ] **ΔQ ≤ 0:** Render performans sınırı içinde mi?
- [ ] **No Tailwind Scan:** Kod içinde Tailwind direktifi veya runtime tespiti negatif mi?

## 2. Evidence Format for PR Approval

PR açılırken aşağıdaki formatta kanıt sunulması zorunludur:

```text
### [Verification Evidence]
- PHPUnit: `OK (30 tests, 75 assertions)`
- Dry-Run: `All pages validated successfully.`
- Preview: `2 pages modified, 1 page created, 0 deleted.`
- Performance: `Render time delta (Queries) = 0`
- No Tailwind: `Grep/Scan returned zero findings.`
```

## 3. "No Evidence = Fail" Policy

Kanıt sunulmayan veya yukarıdaki kriterlerden herhangi birini sağlamayan hiçbir kod bloğu `master` dalına birleştirilemez. Bu politika tavizsiz uygulanır.

## 4. Contract Docs Sync Gate

Shortcode veya block parametre sözleşmesinde değişiklik varsa aşağıdakiler zorunludur:

- [ ] `SHORTCODES.md` güncellendi mi? (Canonical params + Block inspector params)
- [ ] Alias/Deprecation tabloları güncellendi mi?
- [ ] Validation rules ve tag-level alias matrix güncellendi mi?
- [ ] Contract Change Log satırı eklendi mi?
- [ ] Block editor ayarı ve frontend çıktısı parity kontrolünden geçti mi?

Not:
- Bu başlıklardan biri eksikse değişiklik "tamamlandı" sayılmaz.

## 5. Elementor Smoke Gate

Elementor widget davranışını etkileyen değişikliklerde aşağıdakiler zorunludur:

- [ ] `docs/internal/ELEMENTOR_WIDGETS_SMOKE_CHECKLIST.md` adımları uygulandı mı?
- [ ] Kritik 4 widget için editor panel kontrol görünürlüğü doğrulandı mı?
- [ ] Kritik 4 widget için frontend parity (ayar -> çıktı) doğrulandı mı?
- [ ] PASS/FAIL ve kısa bulgu notu evidence içine eklendi mi?

Not:
- Widget/category/control parity doğrulaması yoksa PR onayı verilmez.


