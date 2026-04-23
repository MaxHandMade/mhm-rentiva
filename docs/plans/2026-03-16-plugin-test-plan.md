# MHM Rentiva Plugin — Kapsamlı Test Planı

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Hedef:** MHM Rentiva WordPress eklentisinin kalite güvencesini Docker ortamında sistematik biçimde doğrulamak — PHPUnit, PHPCS, WP Plugin Check ve standalone testleri kapsayan tam test döngüsünü yönetilebilir adımlara bölmek.

**Mimari:** Testler `rentiva-dev-wpcli-1` Docker container'ı üzerinden çalışır. Windows PowerShell sarmalayıcısı zorunludur. `composer test` → PHPUnit, `composer phpcs` → PHPCS, `composer check-release` → tüm kontroller.

**Tech Stack:** PHPUnit 9.6, PHPCS + WPCS 3.x, WP Plugin Check (WP-CLI), PHP 8.2 (Docker), MariaDB (Docker), yoast/phpunit-polyfills

---

## Ortam Bilgileri

| Değer | Açıklama |
|:---|:---|
| Container adı | `rentiva-dev-wpcli-1` |
| Plugin dizini (container içi) | `/var/www/html/wp-content/plugins/mhm-rentiva` |
| WordPress URL | `http://localhost:8080` |
| phpMyAdmin | `http://localhost:8084` |
| PowerShell komut sarmalayıcı | `powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c '...' "` |
| PHPUnit bayrağı | `--no-coverage` (Xdebug overhead'ini engeller) |
| Timeout (tam suite) | 300 saniye |
| Timeout (tek test) | 60 saniye |

---

## Test Kategorileri ve Dosya Yapısı

```
tests/
├── Admin/
│   ├── Core/           → SecurityHelper, tarih format testleri
│   ├── Frontend/       → Elementor widget testleri
│   ├── Messages/       → Mesajlaşma ayarları
│   └── Settings/       → SettingsHandler, SettingsService, RateLimiter, PHPCS vb.
├── Api/
│   └── REST/           → WebhookRateLimiter
├── Core/
│   ├── Attribute/      → Enum, Schema, TagMapping testleri
│   ├── Dashboard/      → DashboardDataProvider
│   ├── Financial/      → Ledger, Commission, AtomicPayout, Governance, Forensic...
│   ├── Orchestration/  → Lifecycle, Metering, Provisioning, QuotaRace
│   └── Services/       → Metrics, TrendService, VehiclePerformance
├── Frontend/
│   ├── Shortcodes/     → BookingForm, CommissionResolver, SearchResults...
│   └── EndpointHelper
├── Integration/        → E2E benzeri entegrasyon testleri
├── Integrations/       → Üçüncü taraf entegrasyonlar
├── Layout/             → Düzen testleri
├── Migration/          → DB migration testleri
├── Unit/               → Lisanslama birim testleri
├── standalone/         → WordPress bootstrap gerektirmeyen bağımsız testler
└── manual/             → Manuel benchmark ve analiz scriptleri
```

**Toplam:** 93 PHPUnit test dosyası + 7 standalone + 7 manual script

---

## Görev 1: Ortamı Doğrula (Ön Koşul Kontrolü)

**Adım 1: Docker Container Durumunu Kontrol Et**

```powershell
docker ps --filter "name=rentiva-dev-wpcli-1" --format "table {{.Names}}\t{{.Status}}"
```

Beklenen: `rentiva-dev-wpcli-1   Up X hours`

Eğer container kapalıysa:
```powershell
cd c:\projects\rentiva-dev
docker compose up -d
```

**Adım 2: PHP Sürümü ve PHPUnit Varlığını Doğrula**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && php --version && vendor/bin/phpunit --version'"
```

Beklenen: `PHP 8.2.x`, `PHPUnit 9.6.x`

**Adım 3: WordPress Test Ortamını Doğrula**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'wp core is-installed --allow-root && echo WP_OK'"
```

Beklenen: `WP_OK`

---

## Görev 2: Tam PHPUnit Test Suite Çalıştırma

**Config:** `phpunit.xml` — `tests/**/*Test.php` otomatik keşfedilir.

**Adım 1: Full Suite (tail ile kısa çıktı)**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage 2>&1 | tail -30'"
```

**Adım 2: Sonuç Yorumlama**

| Çıktı | Anlam | Aksiyon |
|:---|:---|:---|
| `OK (N tests, M assertions)` | Tümü geçti | Devam et |
| `FAILURES! Failures: X` | Assertion hatası | Görev 3'e geç |
| `ERRORS! Errors: X` | PHP/sınıf hatası | Görev 3'e geç |
| `Skipped: 4` | Beklenen (çevresel) | Normal |
| `Risky: X` | Assertion eksik | Assertion ekle |

**Adım 3: Başarı durumunda commit**

```bash
git commit -m "test: full suite OK [YYYY-MM-DD]"
```

---

## Görev 3: Başarısız Testleri İzole Et ve Hata Ayıkla

> Yalnızca Görev 2'de hata çıkarsa çalıştır.

**Adım 1: Tek Sınıfı Filtrele**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter SettingsHandlerTest --no-coverage 2>&1'"
```

**Adım 2: Tek Metodu İzole Et**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter SettingsHandlerTest::it_handles_rest_settings_save_action --no-coverage 2>&1'"
```

**Adım 3: Birden Fazla Sınıfı Birlikte Test Et**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter \"SettingsHandlerTest|SettingsServiceTest\" --no-coverage 2>&1'"
```

**Adım 4:** Çözülemeyen hatalarda `systematic-debugging` skill'ini uygula.

---

## Görev 4: Kategori Bazlı Test Grupları

### 4.1 Financial (Kritik — Gelir Güvencesi)

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter Financial --no-coverage 2>&1 | tail -20'"
```

Kapsayan: `LedgerTest`, `CommissionResolverTest`, `AtomicPayoutServiceTest`, `GovernanceTest`, `ForensicHardeningTest`, `TenantIsolationTest`, `CrossTenantKeyTest`

### 4.2 Settings (Admin Panel)

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter Settings --no-coverage 2>&1 | tail -20'"
```

Kapsayan: `SettingsHandlerTest`, `SettingsServiceTest`, `SettingsSanitizerTest`, `SettingsViewTest`, `ShortcodePagesTest`, `TabRendererRegistryTest`, `RateLimiterTest`

### 4.3 Frontend (Kullanıcı Arayüzü)

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter \"Frontend|Shortcode\" --no-coverage 2>&1 | tail -20'"
```

### 4.4 API / REST

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter WebhookRateLimiterTest --no-coverage 2>&1 | tail -20'"
```

### 4.5 Integration

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit ./tests/Integration --no-coverage 2>&1 | tail -20'"
```

### 4.6 Core / Orchestration

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter Orchestration --no-coverage 2>&1 | tail -20'"
```

---

## Görev 5: PHPCS — Kod Standardı Denetimi

**Config:** `phpcs.xml` — WPCS 3.x, WordPress kuralları

**Adım 1: Tam PHPCS Taraması**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs 2>&1 | tail -30'"
```

Beklenen: **0 errors, 0 warnings**

**Adım 2: Otomatik Düzeltme**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcbf 2>&1'"
```

> [!WARNING]
> `phpcbf` yalnızca biçimlendirme hatalarını düzeltir. Güvenlik/mantık hatalarını elle düzelt.

**Adım 3: Kritik Kontrol Listesi**

Her PHP dosyasında zorunlu:
- `declare(strict_types=1);`
- `if (!defined('ABSPATH')) { exit; }`
- Output: `esc_html()`, `esc_attr()`, `esc_url()`
- Input: `sanitize_text_field()`, `absint()`
- Text domain: `'mhm-rentiva'` (string literal)

---

## Görev 6: WP Plugin Check

**Adım 1: Composer Script ile Çalıştır**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && composer plugin-check:release 2>&1 | tail -50'"
```

**Adım 2: Çıktı Kategorileri**

| Kategori | Önem | Aksiyon |
|:---|:---|:---|
| `ERROR` | Kritik | Hemen düzelt, release bloklanır |
| `WARNING` | Orta | Release öncesi değerlendir |
| `INFO` | Düşük | İsteğe bağlı |

---

## Görev 7: Tam Release Kontrolü (check-release)

`composer check-release` = PHPCS + Plugin Check + PHPUnit (sıralı)

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && composer check-release 2>&1'"
```

> [!IMPORTANT]
> PHPCS başarısız olursa sonraki kontroller başlamaz. Hataları sırayla çöz.

**Başarı Kriterleri:**

- [ ] PHPCS: 0 error
- [ ] Plugin Check: 0 error
- [ ] PHPUnit: 0 failure, 0 error (max 4 skipped, 0 risky)

---

## Görev 8: Standalone Testleri Çalıştır

WordPress bootstrap gerektirmez — doğrudan PHP ile çalışır.

**Adım 1: Tüm Standalone Testleri Sırayla Çalıştır**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && php tests/standalone/frontend_settings_test.php 2>&1'"
```

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && php tests/standalone/messages_settings_test.php 2>&1'"
```

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && php tests/standalone/settings_view_integrity_test.php 2>&1'"
```

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && php tests/standalone/shortcode_pages_integrity_test.php 2>&1'"
```

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && php tests/standalone/system_settings_audit.php 2>&1'"
```

---

## Görev 9: Skipped Testlerin Durum Kontrolü

**Adım 1: Skipped Testleri Listele**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --verbose 2>&1 | grep -E \"Skipped|SKIP\"'"
```

**Adım 2: Yeni Skipped Test Ortaya Çıkarsa**

1. Neden skip edildiğini belgele
2. `docs/plans/skipped-tests-details.md` dosyasını güncelle
3. `$this->markTestSkipped('Neden: çevresel bağımlılık')` ekle

---

## Görev 10: Coverage Raporu (Release Öncesi)

> [!WARNING]
> Xdebug gerektirir, testi ~10 kat yavaşlatır. Yalnızca gerektiğinde çalıştır.

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --coverage-text 2>&1 | tail -50'"
```

HTML rapor:
```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --coverage-html build/coverage 2>&1'"
```

Minimum hedef: **%80 lines, %70 functions**

---

## Görev 11: Log ve Hata İzleme

**WordPress debug.log:**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'tail -50 /var/www/html/wp-content/debug.log 2>/dev/null || echo \"Debug log yok\"'"
```

**Container logları:**

```powershell
docker compose logs -f wordpress 2>&1 | Select-String -Pattern "Fatal|Error"
```

**Veritabanı bağlantı kontrolü:**

```powershell
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'wp db check --allow-root 2>&1'"
```

---

## Görev 12: Test Sonuç Raporu

Her tam test döngüsünden sonra `docs/plans/YYYY-MM-DD-test-results.md` oluştur:

```markdown
# Test Sonuç Raporu — YYYY-MM-DD

## PHPUnit
- Toplam: N tests, M assertions
- Passed: X | Failed: 0 | Errors: 0 | Skipped: 4 | Risky: 0

## PHPCS
- Errors: 0 | Warnings: 0

## WP Plugin Check
- Errors: 0 | Warnings: N (kabul edilebilir)

## Standalone Testler
- frontend_settings_test: OK
- messages_settings_test: OK
- settings_view_integrity_test: OK
- shortcode_pages_integrity_test: OK
- system_settings_audit: OK

## Sonuç: ✅ RELEASE HAZIR / ❌ BLOKÖR HATA VAR
```

---

## Hızlı Referans — Tek Satır Komutlar

```powershell
# Tam Suite
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage 2>&1 | tail -20'"

# PHPCS
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs 2>&1 | tail -20'"

# Tam Release Kontrolü
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && composer check-release 2>&1'"

# Financial testleri
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter Financial --no-coverage 2>&1 | tail -20'"

# Settings testleri
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter Settings --no-coverage 2>&1 | tail -20'"

# Integration testleri
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit ./tests/Integration --no-coverage 2>&1 | tail -20'"

# Verbose + özet
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --verbose 2>&1 | grep -E \"OK|FAIL|ERROR|Skip\"'"
```

---

## Başarı Kriterleri (Definition of Done)

| Kriter | Eşik |
|:---|:---|
| PHPUnit failures | **0** |
| PHPUnit errors | **0** |
| PHPUnit skipped | **≤ 4** (belgelenmiş) |
| PHPUnit risky | **0** |
| PHPCS errors | **0** |
| WP Plugin Check errors | **0** |
| Standalone test failures | **0** |
| WordPress debug.log fatals | **0** |

---

## Sık Karşılaşılan Sorunlar

| Belirti | Olası Neden | Çözüm |
|:---|:---|:---|
| `Class not found` | Autoloader bozuk | `composer dump-autoload` |
| `WP_TESTS_DIR not defined` | Windows shell kullanıldı | Container üzerinden çalıştır |
| `Connection refused` (DB) | MariaDB kapalı | `docker compose up -d` |
| Test 10× yavaş | Xdebug aktif | `--no-coverage` ekle |
| `Timeout` hatası | Suite çok uzun | `--filter` ile küçük grup kullan |
| PHPCS `Unknown standard` | WPCS kurulu değil | Container içinde `composer install` |
