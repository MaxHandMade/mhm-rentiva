# License Renewal — Phase 0 Pre-flight Notes

**Tarih:** 2026-04-26
**İlişkili plan:** [`2026-04-26-license-renewal-plan.md`](2026-04-26-license-renewal-plan.md)

Phase 0 audit'lerinin kayıtları + plan'a yansıması gereken düzeltmeler.

## ✅ Doğrulanan kalemler

### Rentiva version constant

```
File: c:/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php:78
define('MHM_RENTIVA_VERSION', '4.31.2');
```

**Durum:** Plan'daki tahminle uyumlu. Phase 3A Task C.7'de `4.31.2 → 4.32.0` bump bu constant üzerinden.

### CS version constant — DÜZELTME

Plan'da `MHM_CURRENCY_SWITCHER_VERSION` öngörülmüştü. Gerçek constant:

```
File: c:/projects/mhm-currency-switcher/mhm-currency-switcher.php:33
define( 'MHM_CS_VERSION', '0.6.5' );
```

**Plan etkisi:** Phase 3B Task D.6'da `MHM_CS_VERSION` adı kullanılır.

Yan constant'lar (plan ihtiyacı olabilir):
- `MHM_CS_FILE`, `MHM_CS_PATH`, `MHM_CS_URL`, `MHM_CS_BASENAME`

### CS get_site_hash visibility

```
File: c:/projects/mhm-currency-switcher/src/License/LicenseManager.php:499
public function get_site_hash(): string
```

**Durum:** Public (Rentiva v4.31.0 paritesinde). Phase 3B'de eklenen Task D.0 (visibility fix) **atlanır.**

### CS REST namespace

```
File: c:/projects/mhm-currency-switcher/src/Admin/RestAPI.php:41
const NAMESPACE_V1 = 'mhm-currency/v1';
```

Bu sınıf 8 mevcut route register ediyor. Yeni `/license/manage-subscription` route'u aynı sınıfa eklenir. Phase 3B Task D.2 hedefi.

### Baseline test sayıları + PHPCS

| Plugin | Test | Assertion | Skipped | PHPCS |
|---|---|---|---|---|
| mhm-license-server | 153 | 449 | 2 | 0 |
| mhm-polar-bridge | **N/A — infrastructure yok** | — | — | — |
| mhm-rentiva | 793 | 2768 | 7 | 0 |
| mhm-currency-switcher | 148 | 308 | 1 | 27 (baseline) |

**Plan etkisi:** Phase 1/3A/3B test count delta'ları aynı. Phase 2 için aşağıya bak.

---

## ⚠️ Plan'ı etkileyen kritik bulgular

### A) polar-bridge'de PHPUnit infrastructure YOK

**Dizin yapısı:**

```
c:/projects/wpalemi/plugins/mhm-polar-bridge/
├── CHANGELOG.md
├── includes/  (5 PHP dosyası)
├── mhm-polar-bridge.php
└── readme.txt
```

**Yok olanlar:**
- `tests/` klasörü
- `composer.json`
- `phpunit.xml`
- `vendor/`

**wpalemi monorepo seviyesinde:**
- `wp-tests-config.php` ✓ var (test DB config)
- `Dockerfile.cli` ✓ var
- Ama hiçbir plugin'de ne composer.json ne phpunit.xml var (`mhm-wpalemi-utils` da test'siz)

**Plan etkisi:** Phase 2 plan'ı **TDD discipline'da yazıldı** (~30 yeni test öngörülmüş). Şu an bu testlerin koşulacağı altyapı yok.

**Üç çözüm:**

1. **Phase 2'ye 0 numaralı task ekle: PHPUnit infrastructure kur** (~1 saat)
   - `composer.json` (dev-dependencies: `phpunit/phpunit`, `yoast/phpunit-polyfills`, `brain/monkey` veya WP test framework wrapper)
   - `phpunit.xml` (bootstrap path, test suite config)
   - `tests/bootstrap.php` (WP test lib include + plugin require)
   - `tests/` directory + `vendor/` install
   - Sonraki tüm Phase 2 task'ları TDD ile devam
   
   Avantaj: TDD disiplini korunur, gelecek katkılar için altyapı kazanılır
   Dezavantaj: ek 1 saat

2. **Phase 2 testleri scope'tan çıkar** — manuel smoke test ile geç
   
   Avantaj: 1 saat tasarruf
   Dezavantaj: TDD disiplini ihlal, regresyon riski yüksek (cron logic hassas), wp-conductor "Geliştirme + testler" gate'i geçemez
   
3. **Phase 2 testleri rentiva-dev veya CS test container'ında yaz** — paylaşılan altyapı
   
   Avantaj: 1 saat tasarruf, kısmi test koruma
   Dezavantaj: monorepo dışı, test'ler polar-bridge'in commit'lerinde yok, navigasyon zor

**Önerim: Çözüm 1.** TDD disiplini hot.md'de explicit gate. Ek 1 saatlik altyapı yatırımı; gelecek polar-bridge geliştirmeleri için kalıcı kazanç.

### B) Canlı wpalemi.com sandbox konfigürasyonu — TAMAM ✓

**İlk yanlış iddiam (DÜZELTİLDİ):** Bu raporun ilk versiyonunda "Polar API token tanımlı değil" demiştim. Yanılmışım — **lokal Docker sandbox'ı canlı sandbox sandım**.

**Gerçek durum (kullanıcı kanıtladı):**

Canlı wpalemi.com Hostinger sunucusundaki `wp-config.php`'de tanımlı:

```php
define('MHM_POLAR_WEBHOOK_SECRET', 'whsec_...');                  ✓
define('MHM_LICENSE_SERVER_API_KEY', 'mhm-polar-wpalemi-...');    ✓
define('MHM_LICENSE_API_KEY', 'mhm-polar-wpalemi-...');           ✓
define('MHM_POLAR_API_TOKEN', 'polar_oat_...');                   ✓
define('MHM_POLAR_SANDBOX', true);                                ✓
define('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', <<<'PEM' ...);   ✓
```

**Geliştirme stratejisi (kullanıcının "sandbox üzerinde çalışıyoruz" ifadesinin gerçek anlamı):**

- **Canlı wpalemi.com** = Polar Sandbox API endpoint'i kullanıyor (`MHM_POLAR_SANDBOX=true` → `sandbox-api.polar.sh`)
- Geliştirme + integration + E2E testleri **canlı wpalemi.com'da** yapılır
- Lokal Docker sandbox WP'si **PHPUnit unit testleri** için ortam (mocked everything)
- Production'a açılma = canlı wpalemi.com'da `MHM_POLAR_SANDBOX` constant'ı remove + token swap

**Phase 4 prerequisite:** Yok — sandbox aktif ve hazır.

**Öğrenme (memory'ye yazıldı):** [feedback_verified_claims_only.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_verified_claims_only.md) — iddia öncesi zorunlu çapraz doğrulama, lokal vs canlı ortam ayrımı, tempo baskısına karşı checklist disiplini.

---

## Plan dosyasına yansıması

Phase 0 sonuçlarına göre `2026-04-26-license-renewal-plan.md` Phase 2 başlangıcına eklenmesi gereken yeni task:

### Phase 2 — yeni Task B.0 (önerilen)

**Task B.0: PHPUnit infrastructure kurulumu**

- `composer.json` oluştur (dev-deps: phpunit ^9.6, yoast/phpunit-polyfills)
- `phpunit.xml` (testsuite + bootstrap)
- `tests/bootstrap.php` (WP test lib include)
- `composer install` → vendor/
- İlk smoke test (örn. `tests/SmokeTest.php` — `assertTrue(true)`) yeşil koşar
- Commit: `chore(polar-bridge): bootstrap PHPUnit test infrastructure`

Bu Task B.0 sonrası diğer Phase 2 task'ları TDD ile devam eder.

### Phase 3B — Task D.6 düzeltme

`MHM_CURRENCY_SWITCHER_VERSION` → `MHM_CS_VERSION` (constant adı düzeltmesi)

### Phase 4 öncesi prerequisite

Sandbox WP'sine `MHM_POLAR_API_TOKEN` + `MHM_POLAR_SANDBOX` constant'ları eklenmeli (kullanıcı aksiyonu).

---

## Karar (kullanıcı onayı sonrası)

1. **Phase 2 PHPUnit infrastructure** → **Çözüm 1: Yeni Task B.0 ekle** (PHPUnit bootstrap; ~1 saat ek). TDD discipline gate gerektirdiği için bu küçük altyapı yatırımı zorunlu kabul.
2. **Polar Sandbox API token** → **Konu kapandı** — canlı wpalemi.com'da zaten tanımlı; geliştirme canlı sandbox'ta yapılıyor.

Phase 1'e geçmek için engel yok.
