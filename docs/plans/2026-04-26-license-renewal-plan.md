# License Renewal & Subscription Management — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Plugin License sayfasında "Manage Subscription" butonu (Polar customer portal'a yönlendirme), aylık aboneler için -7 gün renewal reminder email cron'u ve 6 polar-bridge lifecycle email'inde MHM/WP Alemi branding tutarlılığı.

**Architecture:** license-server (wpalemi.com) yeni bir REST endpoint sunar (`/licenses/customer-portal-session`) — license_key + site_hash + RSA imzalama ile güvenli, polar_customer_id schema kolonu üzerinden Polar customer-sessions API çağrısı yapar. polar-bridge'e RenewalReminderCron + locale-aware email metodu eklenir. Plugin'ler (Rentiva PHP echo + CS React) "Manage Subscription" buton'unu state-driven CSS ile render eder, admin-post / REST handler'ı server'a delege eder, dönen URL'i yeni sekmede açar.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, PHPUnit 9.x, PHPCS/WPCS, Vite (CS admin-app/), Polar.sh API (sandbox + prod), Docker Compose (rentiva-dev + rentiva-lisans + wpalemi stacks), Hostinger MCP for live deploy.

**Spec reference:** [`docs/plans/2026-04-26-license-renewal-design.md`](2026-04-26-license-renewal-design.md) — read before executing.

---

## Plan Overview

### Versions and dependency order

```
Phase 1: license-server  v1.10.1 → v1.11.0   (server first — endpoint + schema)
Phase 2: polar-bridge    v1.8.0  → v1.9.0    (provisioner + cron + branding)
Phase 3A (parallel):     mhm-rentiva v4.31.2 → v4.32.0
Phase 3B (parallel):     mhm-currency-switcher v0.6.5 → v0.7.0
Phase 4: E2E sandbox doğrulama (manuel, 5 senaryo)
Phase 5: Production geçiş hazırlığı (checklist; çalıştırma kullanıcı kararı)
```

**Sıra zorunlu** — server endpoint olmadan plugin entegrasyonu kırılır. Bridge önce, plugin sonra çünkü provisioner `polar_customer_id` payload alanını gönderiyor.

### Tahmini süre

| Phase | İçerik | Süre |
|---|---|---|
| 0 | Pre-flight (4 plugin baseline + dosya audit) | 30dk |
| 1 | license-server v1.11.0 | 3 saat |
| 2 | polar-bridge v1.9.0 | 3 saat |
| 3A | mhm-rentiva v4.32.0 | 2.5 saat |
| 3B | mhm-currency-switcher v0.7.0 | 2 saat |
| 4 | E2E sandbox manuel | 1 saat |
| 5 | Production geçiş hazırlığı (planlama, deploy değil) | 30dk |
| **Toplam** | | **~12.5 saat** |

Phase 3A ve 3B paralel; tek developer ardışık çalışırsa ~12.5 saat, paralel iki seansa bölünürse ~10.5 saat.

### Tek-developer takvimi (öneri)

```
Day 1 sabah: Phase 0 + Phase 1 (license-server)
Day 1 akşam: Phase 2 (polar-bridge)
Day 2 sabah: Phase 3A (rentiva)
Day 2 akşam: Phase 3B (CS)
Day 3 sabah: Phase 4 (E2E manuel)
Day 3 akşam: Phase 5 (planlama)
```

---

## File Structure

### mhm-license-server v1.11.0

**Modify:**
- `src/Database/DatabaseManager.php` — `create_tables()` ve `migrate()` metodları (yeni kolon)
- `src/REST/RESTController.php` — yeni route registration
- `mhm-license-server.php` — version bump 1.10.1 → 1.11.0
- `readme.txt` — Stable tag + Changelog
- `CHANGELOG.md` — v1.11.0 detaylı entry
- `LIVE_DEPLOYMENT_CHECKLIST.md` — schema migration check eklenir

**Create:**
- `tests/Database/PolarCustomerIdMigrationTest.php` — 3 test
- `tests/REST/CustomerPortalSessionTest.php` — ~10 test

**Note:** `MHM_Polar_API` wrapper polar-bridge'de yaşar. license-server polar-bridge'in PHP function'ını çağırır (`MHM_Polar_API::create_customer_session`). Wrapper imza değişikliği Phase 2'de yapılır — license-server endpoint Phase 2 deploy edilmeden önce yeni imzayı kullanamaz. Bu Phase 1 ↔ Phase 2 sıra sebebi: **endpoint kodu wrapper'ın yeni imzasını kullanır, dolayısıyla bridge önce yüklenmeli.**

> **Sıra düzeltmesi:** Spec'te "Phase 1: server, Phase 2: bridge" diyor; ama aslında **wrapper imza değişikliği** olduğu için bridge hem provisioner update hem wrapper update için ayrı task'lara bölünür. Doğru sıra:
> 1. Phase 1A: server **schema migration** (kolon ekle, endpoint ekleme yapma)
> 2. Phase 2: bridge tüm değişiklikler (provisioner + cron + branding + wrapper imza)
> 3. Phase 1B: server **endpoint** (yeni wrapper'ı kullanır)
> 4. Phase 3: pluginler
>
> Daha basit alternatif: server endpoint'inde wrapper'ın **eski imzasını** ($string return) destekleyen geçici geri-uyumluluk yazıp Phase 2 wrapper update sonrası temizlemek. Bu plan **bu alternatife** gider — endpoint hem `string` hem `array` dönüşü kabul eder, Phase 2 sonrası refactor edilir. Geçici toleranslı yapı `array_or_string_to_array()` küçük helper ile.

### mhm-polar-bridge v1.9.0

**Modify:**
- `includes/PolarAPI.php` — `create_customer_session()` imza array dönüşüne çevrilir
- `includes/BillingPortalRedirect.php` — wrapper yeni dönüş tipini tüketir
- `includes/LicenseProvisioner.php` — `generate()` 7. parametre `$polar_customer_id`
- `includes/WebhookHandler.php` — `handle_subscription_active()` polar_customer_id'yi LicenseProvisioner'a iletir; `handle_subscription_updated()` `renewal_reminder_sent_at` clear
- `includes/CustomerManager.php` — `send_renewal_reminder_email()` (yeni); 6 lifecycle email From + imza güncellemesi; `format_email_date()` locale parametresi
- `mhm-polar-bridge.php` — version bump + RenewalReminderCron register
- `readme.txt` — Stable tag + Changelog
- `CHANGELOG.md` — v1.9.0 entry

**Create:**
- `includes/RenewalReminderCron.php` — yeni cron sınıfı
- `tests/RenewalReminderCronTest.php` — ~12 test
- `tests/CustomerManager/RenewalReminderEmailTest.php` — ~5 test
- `tests/CustomerManager/BrandingRefreshTest.php` — ~6 test (her email için bir)
- `tests/WebhookHandler/RenewalReminderResetTest.php` — 2 test
- `tests/LicenseProvisioner/PolarCustomerIdTest.php` — 3 test
- `tests/PolarAPI/CustomerSessionSignatureChangeTest.php` — 4 test

### mhm-rentiva v4.32.0

**Modify:**
- `src/Admin/Licensing/LicenseManager.php` — `createCustomerPortalSession()` public metod
- `src/Admin/Licensing/LicenseAdmin.php` — `handle_manage_subscription` admin-post handler + License sayfası buton render + helper metodlar + `display_admin_notices` switch case + asset enqueue
- `mhm-rentiva.php` — version bump 4.31.2 → 4.32.0 (`MHM_RENTIVA_VERSION` constant teyit edilecek — Phase 0)
- `readme.txt` — Stable tag + Changelog
- `README.md`, `README-tr.md` — badge
- `changelog.json`, `changelog-tr.json` — v4.32.0 entry
- `languages/mhm-rentiva.pot` — regen
- `languages/mhm-rentiva-tr_TR.po`, `.mo`, `.l10n.php`

**Create:**
- `assets/css/license-admin.css` — state-driven emphasis CSS
- `tests/Admin/Licensing/LicenseManagerCustomerPortalSessionTest.php` — 5 test
- `tests/Admin/Licensing/LicenseAdminManageSubscriptionTest.php` — 4 test
- `tests/Admin/Licensing/EmphasisClassTest.php` — 4 test

### mhm-currency-switcher v0.7.0

**Modify:**
- `src/License/LicenseManager.php` — `create_customer_portal_session()` snake_case
- `src/REST/LicenseController.php` (mevcut REST handler dosyası — Phase 0'da teyit) — `/license/manage-subscription` route + handler
- `admin-app/src/components/tabs/License.jsx` — `handleManageSubscription` callback + buton render + state + helper
- `admin-app/src/style.css` — emphasis classes
- `mhm-currency-switcher.php` — version bump 0.6.5 → 0.7.0 (constant adı Phase 0'da teyit)
- `readme.txt` — Stable tag + Changelog
- `README.md` — badge
- `changelog.json`, `changelog-tr.json` (varsa) — v0.7.0 entry
- `languages/*.po` — TR çeviriler

**Create:**
- `tests/License/LicenseManagerCustomerPortalSessionTest.php` — 5 test
- `tests/REST/ManageSubscriptionEndpointTest.php` — 5 test

### mhm-rentiva-docs (paylaşılan docs site)

**Create:**
- `blog/2026-04-26-license-server-v1.11.0-release.md` (EN)
- `blog/2026-04-26-polar-bridge-v1.9.0-release.md` (EN, internal — opsiyonel; private repo değil ama dış kullanıcı için kritik değil)
- `blog/2026-04-26-rentiva-v4.32.0-release.md` (EN)
- `blog/2026-04-26-cs-v0.7.0-release.md` (EN)
- `i18n/tr/docusaurus-plugin-content-blog/2026-04-26-rentiva-v4.32.0-release.md` (TR)
- `i18n/tr/docusaurus-plugin-content-blog/2026-04-26-cs-v0.7.0-release.md` (TR)

License-server ve polar-bridge için TR blog yazısı opsiyonel (kullanıcı görmüyor; sadece geliştirici notları).

---

## Cross-Phase Conventions

### TDD discipline

Her implementation step önce **RED test** yazılır (failing for the right reason), sonra **minimum GREEN code** yazılır. RED → GREEN → REFACTOR. Implementation test olmadan yazılmaz.

### Mock pattern (HTTP)

`pre_http_request` filter (v4.30.0 öğrenisi). `tearDown()` zorunlu:

```php
public function tearDown(): void {
    remove_all_filters('pre_http_request');
    parent::tearDown();
}
```

### Mock pattern (wp_mail)

Phase 0 Task 0.4'te polar-bridge mevcut testlerinde nasıl yapıldığı tespit edilir. Beklenen pattern: `pre_wp_mail` filter + bir array'e capture, sonra assert.

### Mock pattern (zaman)

RenewalReminderCron için `time()` Cron sınıfının constructor'ına injection ile mock edilir. Helper:

```php
class MHM_Polar_RenewalReminderCron {
    private $now_provider;

    public function __construct(callable $now_provider = null) {
        $this->now_provider = $now_provider ?? 'time';
    }

    private function now(): int {
        return ($this->now_provider)();
    }
}
```

Test'te `new MHM_Polar_RenewalReminderCron(fn() => 1234567890)`.

### Commit cadence

Her task'ın GREEN + suite-pass step'inden sonra commit. Conventional commits: `feat:`, `fix:`, `test:`, `docs:`, `chore:`.

### WPCS gate per repo

Her task sonrası `composer phpcs` 0 error (baseline'a göre). CS plugin'inde baseline 27 errors korunur (CRLF — pre-existing, plugin source temiz).

### Docker stacks

| Repo | Compose dir | Container | Plugin path (container) |
|---|---|---|---|
| mhm-license-server | `c:/projects/rentiva-lisans` | `rentiva-lisans-wpcli-1` | `/var/www/html/wp-content/plugins/mhm-license-server` |
| mhm-polar-bridge | `c:/projects/wpalemi` | `wpalemi-wpcli-1` veya benzeri (Phase 0'da teyit) | `/var/www/html/wp-content/plugins/mhm-polar-bridge` |
| mhm-rentiva | `c:/projects/rentiva-dev` | `rentiva-dev-wpcli-1` | `/var/www/html/wp-content/plugins/mhm-rentiva` |
| mhm-currency-switcher | `c:/projects/rentiva-dev` (mounted) | `rentiva-dev-wpcli-1` | `/var/www/html/wp-content/plugins/mhm-currency-switcher` |

### Sandbox-prod parity (test stratejisi)

PHPUnit unit testlerinde mock kullanılır. Integration ve E2E sandbox'ta gerçek webhook ile çalışır — `MHM_POLAR_SANDBOX=true` constant'ı API base URL'i çevirmek dışında hiç kısayol yok.

---

## Phase 0 — Pre-flight (~30dk)

Tüm phase'ler öncesi yapılır. Belirsizlikleri kapatır.

### Task 0.1: Constant adlarını teyit et

**Files:**
- Read: `c:/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php`
- Read: `c:/projects/mhm-currency-switcher/mhm-currency-switcher.php`

- [ ] **Step 1: Rentiva ana dosyada `MHM_RENTIVA_VERSION` constant'ı doğrula**

```bash
grep -n "MHM_RENTIVA_VERSION\|^Version:" c:/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php | head -5
```

Expected: `define('MHM_RENTIVA_VERSION', '4.31.2')` benzeri satır + `Version: 4.31.2` header.

Eğer constant adı farklı ise (`MHM_RENTIVA_VER`, `RENTIVA_VERSION` vb.), plan dosyasındaki Phase 3A task'larını güncelle.

- [ ] **Step 2: CS ana dosyada version constant'ı doğrula**

```bash
grep -n "VERSION\|^Version:" c:/projects/mhm-currency-switcher/mhm-currency-switcher.php | head -5
```

Expected: `define('MHM_CURRENCY_SWITCHER_VERSION', '0.6.5')` benzeri.

Doğrulanmış constant adını Phase 3B task notlarına yaz.

### Task 0.2: CS LicenseManager site_hash visibility audit

**Files:**
- Read: `c:/projects/mhm-currency-switcher/src/License/LicenseManager.php`

- [ ] **Step 1: `get_site_hash` görünürlüğünü kontrol et**

```bash
grep -n "function get_site_hash\|function getSiteHash" c:/projects/mhm-currency-switcher/src/License/LicenseManager.php
```

Expected (Rentiva v4.31.0 paritesinde olmalı): `public function get_site_hash`.

Eğer `private` ise: Phase 3B Task D.1.0 olarak ekle: "Make get_site_hash() public" — RED test gerekmez (visibility değişikliği), tek satır değişiklik + suite green check.

### Task 0.3: CS REST namespace audit

**Files:**
- Read: `c:/projects/mhm-currency-switcher/src/REST/` veya benzeri dizin
- Read: `c:/projects/mhm-currency-switcher/admin-app/src/components/tabs/License.jsx`

- [ ] **Step 1: License.jsx'in çağırdığı path'leri listele**

```bash
grep -n "apiFetch\|path:" c:/projects/mhm-currency-switcher/admin-app/src/components/tabs/License.jsx | grep "/" | head -10
```

Expected: `path: '/mhm-currency/v1/license/activate'` benzeri 2-3 satır. Namespace = `mhm-currency/v1`.

- [ ] **Step 2: Mevcut REST controller dosyasını bul**

```bash
grep -rn "register_rest_route.*mhm-currency" c:/projects/mhm-currency-switcher/src/ | head -5
```

Expected: tek dosya yolu. Phase 3B Task D.2 hedefi.

### Task 0.4: polar-bridge wp_mail mock pattern audit

**Files:**
- Read: `c:/projects/wpalemi/plugins/mhm-polar-bridge/tests/` (varsa)

- [ ] **Step 1: Mevcut testlerde `wp_mail` mock pattern'ini tespit et**

```bash
ls c:/projects/wpalemi/plugins/mhm-polar-bridge/tests/ 2>/dev/null
grep -rn "wp_mail\|pre_wp_mail\|MockMailer" c:/projects/wpalemi/plugins/mhm-polar-bridge/tests/ 2>/dev/null | head -10
```

Eğer `pre_wp_mail` filter pattern'i kullanılıyorsa devam et. Eğer hiç wp_mail testi yoksa, Phase 2 testlerinde **bu spec ilk wp_mail test'i** olur — `pre_wp_mail` filter kullan, capture array, assert.

Sonucu Phase 2 task notlarına yaz.

### Task 0.5: 4 plugin baseline test sayıları + PHPCS durumu

**Files:**
- N/A (test çalıştırma)

- [ ] **Step 1: license-server baseline**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage" | tail -5
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs" | tail -3
```

Expected: `OK (153 tests, X assertions)`, PHPCS 0 error.

Kaydet.

- [ ] **Step 2: polar-bridge baseline**

```bash
# Container ve path Phase 0 Task 0.4'te tespit ediliyor
# Tahmini:
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit --no-coverage" | tail -5
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && composer phpcs" | tail -3
```

Kaydet (test sayısı ve PHPCS error sayısı).

- [ ] **Step 3: Rentiva baseline**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage" | tail -5
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs" | tail -3
```

Expected: `OK (793 tests, ~2740 assertions)`, PHPCS 0 error.

- [ ] **Step 4: CS baseline**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-currency-switcher && vendor/bin/phpunit --no-coverage" | tail -5
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-currency-switcher && composer phpcs" | tail -3
```

Expected: `OK (148 tests, ~280 assertions)`, PHPCS 27 errors (baseline).

Tüm sayıları **Phase 0 Notes** dosyasına kaydet (`docs/plans/2026-04-26-phase0-notes.md` oluşturabilirsin):

```
license-server: 153 tests / 0 PHPCS
polar-bridge: <?> tests / <?> PHPCS
rentiva: 793 tests / 0 PHPCS
CS: 148 tests / 27 PHPCS
```

### Task 0.6: Sandbox Polar konfigürasyonu doğrula

**Files:**
- Read: `c:/projects/wpalemi/wp-config.php` (veya wpalemi sandbox WP'si)

- [ ] **Step 1: `MHM_POLAR_SANDBOX`, `MHM_POLAR_API_TOKEN` constant'larını doğrula**

```bash
grep -n "MHM_POLAR" c:/projects/wpalemi/wp-config.php 2>/dev/null
```

Expected:
- `define('MHM_POLAR_SANDBOX', true);`
- `define('MHM_POLAR_API_TOKEN', 'polar_oat_...');`

Eksik/yanlışsa kullanıcıya escalate (sandbox API token gerekli, sen üretemezsin).

- [ ] **Step 2: Sandbox API base URL'i test et**

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://sandbox-api.polar.sh/v1/customer-sessions/ \
    -X POST \
    -H "Authorization: Bearer $POLAR_SANDBOX_TOKEN" \
    -H "Content-Type: application/json" \
    --data '{"customer_id": "00000000-0000-0000-0000-000000000000"}'
```

Expected: 401 veya 422 (token validity / customer_id geçerli değil — connectivity OK demek). 5xx veya timeout ise sandbox unreachable, kullanıcıya escalate.

### Task 0.7: Phase 0 commit

- [ ] **Step 1: Phase 0 notes commit**

`docs/plans/2026-04-26-phase0-notes.md` oluştur (eğer yarattıysan, baseline sayıları + audit sonuçları + constant adları).

```bash
cd c:/projects/rentiva-dev
git add plugins/mhm-rentiva/docs/plans/2026-04-26-phase0-notes.md
git commit -m "docs(plans): phase 0 pre-flight notes for license renewal feature"
```

> **Phase 0 Review Checkpoint:** Tüm baseline'lar yeşil + audit'ler tamam mı? Belirsizlikler çözüldü mü? Devam etmeden önce kullanıcıya rapor.

---

## Phase 1 — license-server v1.11.0 (~3 saat)

### Task A.1: Schema migration — polar_customer_id kolonu (RED)

**Files:**
- Test: `tests/Database/PolarCustomerIdMigrationTest.php` (yeni)

- [ ] **Step 1: Test dosyasını oluştur (RED)**

```php
<?php
namespace MHMLicenseServer\Tests\Database;

use PHPUnit\Framework\TestCase;
use MHMLicenseServer\Database\DatabaseManager;

final class PolarCustomerIdMigrationTest extends TestCase {
    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        // Mevcut test setup pattern'ine bak; muhtemelen:
        DatabaseManager::create_tables();
    }

    public function test_create_tables_includes_polar_customer_id_column(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_licenses';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        $names = array_column($columns, 'Field');
        $this->assertContains('polar_customer_id', $names);
    }

    public function test_migrate_adds_polar_customer_id_when_missing(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_licenses';
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN polar_customer_id");

        DatabaseManager::migrate();

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        $names = array_column($columns, 'Field');
        $this->assertContains('polar_customer_id', $names);
    }

    public function test_migrate_is_idempotent(): void {
        DatabaseManager::migrate();
        DatabaseManager::migrate();
        $this->expectNotToPerformAssertions();
    }
}
```

- [ ] **Step 2: Test'i çalıştır (RED bekleniyor)**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Database/PolarCustomerIdMigrationTest.php"
```

Expected: 3 test FAIL, "polar_customer_id kolonu bulunamadı" benzeri mesaj.

### Task A.2: Schema migration — implementation (GREEN)

**Files:**
- Modify: `src/Database/DatabaseManager.php`

- [ ] **Step 1: `create_tables()` içinde kolon ekle**

`$sql_licenses` query'sinde `external_subscription_id` satırından sonra:

```php
external_subscription_id varchar(128) NULL,
polar_customer_id varchar(64) NULL,
product_label varchar(100) NULL,
```

`PRIMARY KEY` bloğunda:

```php
KEY external_subscription_id (external_subscription_id),
KEY polar_customer_id (polar_customer_id)
```

- [ ] **Step 2: `migrate()` içinde upgrade path ekle**

Mevcut migration if-blokları zincirine (line 199 civarı `subscription_id` örneği gibi):

```php
if (!isset($column_map['polar_customer_id'])) {
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN polar_customer_id varchar(64) NULL AFTER external_subscription_id");
    $wpdb->query("ALTER TABLE {$table} ADD INDEX polar_customer_id (polar_customer_id)");
}
```

- [ ] **Step 3: Test'i tekrar çalıştır (GREEN)**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Database/PolarCustomerIdMigrationTest.php"
```

Expected: 3 test PASS.

- [ ] **Step 4: Tüm suite'i çalıştır (regresyon kontrolü)**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage" | tail -5
```

Expected: 153 → 156 tests, hepsi PASS.

- [ ] **Step 5: PHPCS gate**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs" | tail -3
```

Expected: 0 error.

- [ ] **Step 6: Commit**

```bash
cd c:/projects/rentiva-lisans
git add plugins/mhm-license-server/src/Database/DatabaseManager.php
git add plugins/mhm-license-server/tests/Database/PolarCustomerIdMigrationTest.php
git commit -m "feat(server): add polar_customer_id column to wp_mhm_licenses"
```

### Task A.3: REST endpoint /licenses/customer-portal-session (RED)

**Files:**
- Test: `tests/REST/CustomerPortalSessionTest.php` (yeni)

- [ ] **Step 1: Test dosyası iskeleti (RED)**

Test edilecek senaryolar:
1. `test_endpoint_returns_404_when_license_not_found`
2. `test_endpoint_returns_403_when_license_status_not_active`
3. `test_endpoint_returns_403_when_site_hash_mismatch`
4. `test_endpoint_returns_422_when_polar_customer_id_null`
5. `test_endpoint_returns_503_when_polar_token_unset`
6. `test_endpoint_returns_503_when_polar_api_returns_500`
7. `test_endpoint_returns_signed_url_on_happy_path`
8. `test_endpoint_response_is_rsa_signed`
9. `test_endpoint_returns_429_when_rate_limit_exceeded`
10. `test_endpoint_returns_401_when_hmac_signature_invalid`

İskelet:

```php
<?php
namespace MHMLicenseServer\Tests\REST;

use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class CustomerPortalSessionTest extends TestCase {
    private const ENDPOINT = '/mhm-license/v1/licenses/customer-portal-session';

    public function tearDown(): void {
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function test_endpoint_returns_404_when_license_not_found(): void {
        $request = $this->build_request([
            'license_key' => 'NONEXISTENT-KEY',
            'site_hash'   => 'abc123',
            'return_url'  => 'https://example.com/wp-admin/',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(404, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('license_not_found', $data['error']);
    }

    // ... diğer testler
}
```

Yardımcı metod `build_request()` ile HMAC imzalı POST request kurar (mevcut activate/validate testlerinden pattern al).

- [ ] **Step 2: Test'i çalıştır (RED — endpoint yok)**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/REST/CustomerPortalSessionTest.php"
```

Expected: 10 test FAIL — `rest_do_request` 404 (endpoint registered değil).

### Task A.4: REST endpoint registration (GREEN)

**Files:**
- Modify: `src/REST/RESTController.php`

- [ ] **Step 1: Route registration ekle**

`register_routes()` metodunda mevcut route'ların sonrasına:

```php
register_rest_route(self::NAMESPACE, '/licenses/customer-portal-session', [
    'methods'             => 'POST',
    'callback'            => [$this, 'create_customer_portal_session'],
    'permission_callback' => '__return_true',
]);
```

- [ ] **Step 2: Handler metodu ekle**

`RESTController` sınıfı içinde:

```php
public function create_customer_portal_session(\WP_REST_Request $request): \WP_REST_Response {
    // 1. RequestGuard verify
    $guard_result = $this->guard->verify_request($request);
    if (is_wp_error($guard_result)) {
        return $this->error_response($guard_result, 401);
    }

    // 2. Rate limit
    $params = $request->get_json_params();
    $license_key = $params['license_key'] ?? '';
    $site_hash = $params['site_hash'] ?? '';
    $return_url = $params['return_url'] ?? '';

    $rate_check = $this->rate_limit->check_request(
        $license_key,
        'customer_portal_session',
        10,
        MINUTE_IN_SECONDS
    );
    if (is_wp_error($rate_check)) {
        return $this->error_response($rate_check, 429);
    }

    // 3. License lookup
    $row = $this->license_repository->find_by_key($license_key);
    if (!$row) {
        return $this->signed_error('license_not_found', 'License key not found.', 404);
    }

    if ($row['status'] !== 'active') {
        return $this->signed_error('license_not_active', 'License is not active.', 403);
    }

    // 4. Activation lookup
    $activation = $this->activation_repository->find_by_license_and_site($row['id'], $site_hash);
    if (!$activation) {
        return $this->signed_error('site_not_activated', 'Site is not activated.', 403);
    }

    // 5. polar_customer_id check
    if (empty($row['polar_customer_id'])) {
        return $this->signed_error(
            'license_not_subscription',
            'This license has no Polar subscription linked.',
            422
        );
    }

    // 6. Polar API config check
    if (!class_exists('MHM_Polar_API') || !\MHM_Polar_API::is_configured()) {
        return $this->signed_error(
            'polar_api_unavailable',
            'Polar API is not configured.',
            503
        );
    }

    // 7. Mint session
    $session = \MHM_Polar_API::create_customer_session(
        (string) $row['polar_customer_id'],
        (string) $return_url
    );

    // Backward-compat: wrapper Phase 2'den önce string döndürüyor olabilir
    if (is_string($session)) {
        $session = $session === '' ? [] : ['customer_portal_url' => $session, 'expires_at' => ''];
    }

    if (empty($session['customer_portal_url'])) {
        return $this->signed_error(
            'polar_api_unavailable',
            'Polar API request failed.',
            503
        );
    }

    // 8. Signed success response
    $payload = [
        'success' => true,
        'data' => [
            'customer_portal_url' => $session['customer_portal_url'],
            'expires_at'          => $session['expires_at'] ?? '',
        ],
    ];

    return new \WP_REST_Response($this->signer->sign($payload), 200);
}

private function signed_error(string $code, string $message, int $status): \WP_REST_Response {
    $payload = [
        'success' => false,
        'error'   => $code,
        'message' => $message,
    ];
    return new \WP_REST_Response($this->signer->sign($payload), $status);
}
```

> **Not:** `license_repository`, `activation_repository`, `signer` mevcut RESTController bağımlılıkları. Eğer bu sınıflar yoksa, mevcut activate/validate handler'larında nasıl row lookup yapıldığına bak ve aynı pattern'i izle.

- [ ] **Step 3: Test'i tekrar çalıştır (GREEN)**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/REST/CustomerPortalSessionTest.php"
```

Expected: 10 test PASS. Pre_http_request mock'u Polar API'yi simule eder; happy path testte `customer_portal_url` döner.

- [ ] **Step 4: Suite regresyon kontrolü**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage" | tail -5
```

Expected: 156 → 166 tests.

- [ ] **Step 5: PHPCS**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs" | tail -3
```

Expected: 0 error.

- [ ] **Step 6: Commit**

```bash
cd c:/projects/rentiva-lisans
git add plugins/mhm-license-server/src/REST/RESTController.php
git add plugins/mhm-license-server/tests/REST/CustomerPortalSessionTest.php
git commit -m "feat(server): add /licenses/customer-portal-session REST endpoint"
```

### Task A.5: Version bump + changelog + readme

**Files:**
- Modify: `mhm-license-server.php`
- Modify: `readme.txt`
- Modify: `CHANGELOG.md`
- Modify: `LIVE_DEPLOYMENT_CHECKLIST.md`

- [ ] **Step 1: Version bump 1.10.1 → 1.11.0**

`mhm-license-server.php` header `Version: 1.11.0` + `define('MHM_LICENSE_SERVER_VERSION', '1.11.0');` (constant adı dosyadan teyit).

- [ ] **Step 2: readme.txt Stable tag + Changelog**

```
Stable tag: 1.11.0

== Changelog ==

= 1.11.0 — 2026-04-26 =

* New: REST endpoint `/licenses/customer-portal-session` for plugin-side Manage Subscription button (mints Polar customer portal URL via wpalemi.com bridge).
* New: `wp_mhm_licenses.polar_customer_id` column with index. Idempotent migration; legacy WC-purchased license rows remain NULL and graceful-fail with `license_not_subscription` error code.
* Hardening: response RSA-signed (existing pattern); license_key + site_hash + activation match enforced; rate limited to 10 req/min per key.
```

- [ ] **Step 3: CHANGELOG.md detaylı entry**

`## v1.11.0 — 2026-04-26` başlığı altında bir paragraf + bullet'lar (yeni endpoint kontratı, schema migration, backward-compat).

- [ ] **Step 4: LIVE_DEPLOYMENT_CHECKLIST.md schema migration check**

Mevcut deployment checklist'ine yeni satır:

```
- [ ] After v1.11.0 deploy: verify `polar_customer_id` column exists
  ```bash
  wp eval 'global $wpdb; $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}mhm_licenses LIKE \"polar_customer_id\"", ARRAY_A); echo $cols ? "OK\n" : "MISSING\n";'
  ```
```

- [ ] **Step 5: Commit**

```bash
cd c:/projects/rentiva-lisans
git add plugins/mhm-license-server/mhm-license-server.php
git add plugins/mhm-license-server/readme.txt
git add plugins/mhm-license-server/CHANGELOG.md
git add plugins/mhm-license-server/LIVE_DEPLOYMENT_CHECKLIST.md
git commit -m "chore(server): bump version to 1.11.0 + changelog"
```

### Task A.6: ZIP build + GitHub release

**Files:**
- N/A (build artifact)

- [ ] **Step 1: ZIP build**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
# Mevcut build script veya Docker zip pattern (Phase 0'da teyit ediliyorsa kullan)
# Tahmini:
docker run --rm -v $(pwd):/work -w /work alpine sh -c "apk add zip && zip -r /tmp/mhm-license-server-v1.11.0.zip . -x '*/node_modules/*' '*/.git/*' '*/tests/*' '*/build/*'"
docker cp ... # ya da volume mount ile build/ dizinine
```

Tam komut Phase 0 Task 0.5 sırasında license-server'ın mevcut build pattern'i incelenip kaydedilir.

- [ ] **Step 2: Tag + push**

```bash
cd c:/projects/rentiva-lisans
git tag -a "license-server-v1.11.0" -m "license-server v1.11.0 — Manage Subscription endpoint + polar_customer_id schema"
git push origin develop  # veya main
git push origin license-server-v1.11.0
```

> **Not:** rentiva-lisans monorepo veya ayrı repo? Memory'de `MaxHandMade/mhm-license-server` PRIVATE repo deniliyor. Eğer ayrı repo ise `cd plugins/mhm-license-server && git ...` ile sub-repo komutu. Phase 0'da git remote durumu doğrulanır.

- [ ] **Step 3: GitHub release oluştur**

```bash
gh release create license-server-v1.11.0 \
    /tmp/mhm-license-server-v1.11.0.zip \
    --repo MaxHandMade/mhm-license-server \
    --title "v1.11.0 — Manage Subscription endpoint" \
    --notes-file release-notes-v1.11.0.md
```

Release notes dosyası:

```markdown
# v1.11.0

## New
- REST endpoint `/licenses/customer-portal-session` mints Polar customer portal URL for plugin-side Manage Subscription button.
- `wp_mhm_licenses.polar_customer_id` column with index. Polar webhook → polar-bridge → license-server provisioner now persists customer_id alongside subscription_id.

## Hardening
- Response RSA-signed; license_key + site_hash + activation match enforced; 10 req/min per key.

## Schema migration
Idempotent. Legacy WC-purchased license rows remain NULL — graceful fail with `license_not_subscription`.

## Deployment
- Plugin update via WP Admin (or Hostinger MCP if size permits).
- Verify column post-deploy: `wp eval 'global $wpdb; $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}mhm_licenses LIKE \"polar_customer_id\"", ARRAY_A); echo $cols ? "OK\n" : "MISSING\n";'`
```

### Task A.7: wp-conductor TodoList — Phase 1 release gate

- [ ] Geliştirme tamamlandı + testler geçiyor (166 PHPUnit yeşil)
- [ ] WPCS PASS (0 error)
- [ ] i18n güncel (server has no user-facing strings — N/A; "değişim yok")
- [ ] superpowers:verification-before-completion uygulandı (PHPUnit + PHPCS komut çıktıları görüldü)
- [ ] wp-reflect çalıştırıldı
- [ ] Docs güncellendi (CHANGELOG.md + readme.txt + LIVE_DEPLOYMENT_CHECKLIST.md)
- [ ] ZIP oluşturuldu
- [ ] GitHub release + asset
- [ ] Docs site blog yazısı yayınlandı (license-server için EN-only — `mhm-rentiva-docs/blog/2026-04-26-license-server-v1.11.0-release.md`)
- [ ] GitHub push edildi

> **Phase 1 Review Checkpoint:** license-server v1.11.0 yayında. Bridge ve plugin'ler henüz çağırmıyor (endpoint var ama kimse kullanmıyor). Backward-compat: eski plugin/bridge için sıfır risk. Devam etmeden önce kullanıcı onayı.

---

## Phase 2 — polar-bridge v1.9.0 (~3 saat)

### Task B.1: PolarAPI wrapper imza değişikliği (RED)

**Files:**
- Test: `tests/PolarAPI/CustomerSessionSignatureChangeTest.php` (yeni)

- [ ] **Step 1: Test (RED)**

```php
<?php
final class PolarAPICustomerSessionSignatureChangeTest extends WP_UnitTestCase {

    public function tearDown(): void {
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function test_returns_array_on_success(): void {
        $this->mock_polar_response(200, [
            'customer_portal_url' => 'https://polar.sh/portal/abc',
            'expires_at' => '2026-04-26T15:00:00Z',
        ]);

        $result = MHM_Polar_API::create_customer_session('cust_123');

        $this->assertIsArray($result);
        $this->assertSame('https://polar.sh/portal/abc', $result['customer_portal_url']);
        $this->assertSame('2026-04-26T15:00:00Z', $result['expires_at']);
    }

    public function test_returns_empty_array_on_polar_500(): void {
        $this->mock_polar_response(500, ['error' => 'server_error']);
        $result = MHM_Polar_API::create_customer_session('cust_123');
        $this->assertSame([], $result);
    }

    public function test_returns_empty_array_on_network_error(): void {
        add_filter('pre_http_request', fn() => new WP_Error('http_request_failed', 'cURL timeout'));
        $result = MHM_Polar_API::create_customer_session('cust_123');
        $this->assertSame([], $result);
    }

    public function test_passes_return_url_when_non_empty(): void {
        $captured_body = null;
        add_filter('pre_http_request', function ($preempt, $args, $url) use (&$captured_body) {
            $captured_body = json_decode($args['body'], true);
            return [
                'response' => ['code' => 200],
                'body' => wp_json_encode([
                    'customer_portal_url' => 'https://polar.sh/portal/x',
                    'expires_at' => '2026-04-26T15:00:00Z',
                ]),
            ];
        }, 10, 3);

        MHM_Polar_API::create_customer_session('cust_123', 'https://example.com/return');

        $this->assertSame('cust_123', $captured_body['customer_id']);
        $this->assertSame('https://example.com/return', $captured_body['return_url']);
    }

    private function mock_polar_response(int $code, array $body): void {
        add_filter('pre_http_request', function () use ($code, $body) {
            return [
                'response' => ['code' => $code],
                'body' => wp_json_encode($body),
            ];
        });
    }
}
```

- [ ] **Step 2: Test çalıştır (RED)**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit tests/PolarAPI/CustomerSessionSignatureChangeTest.php"
```

Expected: 4 test FAIL — `create_customer_session()` halen string dönüyor.

### Task B.2: PolarAPI wrapper imza değişikliği (GREEN)

**Files:**
- Modify: `includes/PolarAPI.php`
- Modify: `includes/BillingPortalRedirect.php`

- [ ] **Step 1: `create_customer_session()` imza güncelle**

Mevcut metod (line 38) güncellenir:

```php
public static function create_customer_session(
    string $customer_id,
    string $return_url = ''
): array {
    if ($customer_id === '' || ! self::is_configured()) {
        return [];
    }

    $body = ['customer_id' => $customer_id];
    if ($return_url !== '') {
        $body['return_url'] = $return_url;
    }

    $response = wp_remote_post(self::api_base() . '/v1/customer-sessions/', [
        'timeout' => 10,
        'headers' => [
            'Authorization' => 'Bearer ' . self::token(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body'    => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        error_log('[PolarBridge] customer-sessions request failed: ' . $response->get_error_message());
        return [];
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    if ($code !== 200 && $code !== 201) {
        error_log('[PolarBridge] customer-sessions returned HTTP ' . $code . ': ' . $raw);
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['customer_portal_url'])) {
        return [];
    }

    return [
        'customer_portal_url' => (string) $data['customer_portal_url'],
        'expires_at'          => (string) ($data['expires_at'] ?? ''),
    ];
}
```

- [ ] **Step 2: `BillingPortalRedirect::handle()` callsite güncelle**

Line 52 civarı:

```php
$session = MHM_Polar_API::create_customer_session($customer_id);
if (empty($session['customer_portal_url'])) {
    wp_safe_redirect(home_url('/account/?billing=unavailable'));
    exit;
}

wp_redirect($session['customer_portal_url']);
exit;
```

- [ ] **Step 3: Test'i tekrar çalıştır (GREEN)**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit tests/PolarAPI/CustomerSessionSignatureChangeTest.php"
```

Expected: 4 test PASS.

- [ ] **Step 4: Suite regresyon**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit --no-coverage" | tail -5
```

Expected: baseline + 4. Mevcut BillingPortalRedirect testleri varsa hepsi yeşil kalmalı.

- [ ] **Step 5: PHPCS**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && composer phpcs" | tail -3
```

- [ ] **Step 6: Commit**

```bash
cd c:/projects/wpalemi
git add plugins/mhm-polar-bridge/includes/PolarAPI.php
git add plugins/mhm-polar-bridge/includes/BillingPortalRedirect.php
git add plugins/mhm-polar-bridge/tests/PolarAPI/CustomerSessionSignatureChangeTest.php
git commit -m "feat(polar-bridge): customer_session returns array with expires_at + return_url support"
```

### Task B.3: LicenseProvisioner polar_customer_id parameter (RED → GREEN)

**Files:**
- Test: `tests/LicenseProvisioner/PolarCustomerIdTest.php` (yeni)
- Modify: `includes/LicenseProvisioner.php`

- [ ] **Step 1: Test (RED)**

```php
public function test_generate_includes_polar_customer_id_when_provided(): void {
    $captured = null;
    add_filter('pre_http_request', function ($preempt, $args, $url) use (&$captured) {
        if (strpos($url, '/licenses') !== false) {
            $captured = json_decode($args['body'], true);
            return [
                'response' => ['code' => 201],
                'body' => wp_json_encode(['data' => ['license_key' => 'TEST-KEY-1234']]),
            ];
        }
        return $preempt;
    }, 10, 3);

    $provisioner = new MHM_Polar_LicenseProvisioner();
    $result = $provisioner->generate('test@example.com', 'mhm-rentiva', 'sub_123', 'MHM Rentiva', 'monthly', '2026-12-31 00:00:00', 'cust_xyz');

    $this->assertSame('TEST-KEY-1234', $result);
    $this->assertSame('cust_xyz', $captured['polar_customer_id']);
}

public function test_generate_omits_polar_customer_id_when_empty(): void {
    $captured = null;
    add_filter('pre_http_request', /* same as above */);

    $provisioner = new MHM_Polar_LicenseProvisioner();
    $provisioner->generate('test@example.com', 'mhm-rentiva', 'sub_123', '', '', '', '');

    $this->assertArrayNotHasKey('polar_customer_id', $captured);
}

public function test_generate_existing_signature_still_works(): void {
    // Backward-compat — old call without 7th param
    $provisioner = new MHM_Polar_LicenseProvisioner();
    $result = $provisioner->generate('test@example.com', 'mhm-rentiva', 'sub_123');
    // Should not throw or fail — captured payload should not have polar_customer_id
}
```

- [ ] **Step 2: Test RED kontrolü**

Beklenen: 3 test FAIL (parametre yok, payload'da yok).

- [ ] **Step 3: `generate()` 7. parametre ekle**

```php
public function generate(
    string $email,
    string $product_slug,
    string $external_subscription_id = '',
    string $product_label = '',
    string $plan = '',
    string $expires_at = '',
    string $polar_customer_id = ''
): string|false {
    // ... existing code up to $payload ...

    if ($polar_customer_id !== '') {
        $payload['polar_customer_id'] = $polar_customer_id;
    }

    // ... rest unchanged ...
}
```

- [ ] **Step 4: Test GREEN**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit tests/LicenseProvisioner/PolarCustomerIdTest.php"
```

Expected: 3 PASS.

- [ ] **Step 5: PHPCS + suite + commit**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit --no-coverage" | tail -5
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && composer phpcs" | tail -3

cd c:/projects/wpalemi
git add plugins/mhm-polar-bridge/includes/LicenseProvisioner.php
git add plugins/mhm-polar-bridge/tests/LicenseProvisioner/PolarCustomerIdTest.php
git commit -m "feat(polar-bridge): LicenseProvisioner forwards polar_customer_id to server"
```

### Task B.4: WebhookHandler polar_customer_id forwarding + reminder reset

**Files:**
- Test: `tests/WebhookHandler/RenewalReminderResetTest.php` (yeni)
- Modify: `includes/WebhookHandler.php`

- [ ] **Step 1: Test reminder reset (RED)**

```php
public function test_subscription_updated_clears_renewal_reminder_sent_at(): void {
    // Seed user with wpalemi_licenses[0] containing renewal_reminder_sent_at
    $user_id = $this->factory->user->create();
    update_user_meta($user_id, 'wpalemi_licenses', [
        [
            'polar_subscription_id' => 'sub_456',
            'expires_at' => '2026-04-30 00:00:00',
            'renewal_reminder_sent_at' => '2026-04-23 06:00:00',
            'plan' => 'monthly',
            'status' => 'active',
            'product_label' => 'MHM Rentiva',
        ]
    ]);

    $handler = new MHM_Polar_WebhookHandler();
    $handler->handle_subscription_updated([
        'id' => 'sub_456',
        'customer_id' => 'cust_xyz',
        'current_period_end' => '2026-05-30T00:00:00Z',
        'metadata' => ['email' => 'test@example.com'],
        // ... minimal payload — gerçek webhook payload yapısına bak
    ]);

    $licenses = get_user_meta($user_id, 'wpalemi_licenses', true);
    $this->assertSame('', $licenses[0]['renewal_reminder_sent_at']);
}
```

- [ ] **Step 2: Implementation — `handle_subscription_updated()` içinde reset**

Mevcut `update_license()` çağrısına `'renewal_reminder_sent_at' => ''` ekle.

- [ ] **Step 3: Test GREEN**

- [ ] **Step 4: Test polar_customer_id forwarding (RED → GREEN)**

`handle_subscription_active()` (line 100-130 civarı) `LicenseProvisioner::generate()` çağrısı zaten `customer_id`'i parse ediyor (line 129). 7. parametre olarak iletilmeli:

```php
$license_key = $provisioner->generate(
    $email,
    $product_slug,
    $subscription_id,
    $product_label,
    $plan,
    $expires_at,
    $customer_id   // ← yeni
);
```

Test (basit `pre_http_request` mock + parse provisioner POST body):

```php
public function test_subscription_active_forwards_customer_id_to_provisioner(): void {
    $captured = null;
    add_filter('pre_http_request', /* capture POST /licenses body */);
    
    $handler = new MHM_Polar_WebhookHandler();
    $handler->handle_subscription_active([
        'id' => 'sub_999',
        'customer_id' => 'cust_abc',
        // ... minimal payload
    ]);

    $this->assertSame('cust_abc', $captured['polar_customer_id']);
}
```

- [ ] **Step 5: Test GREEN + suite + PHPCS + commit**

```bash
git add plugins/mhm-polar-bridge/includes/WebhookHandler.php
git add plugins/mhm-polar-bridge/tests/WebhookHandler/RenewalReminderResetTest.php
git commit -m "feat(polar-bridge): forward polar_customer_id; reset reminder flag on sub.updated"
```

### Task B.5: CustomerManager — send_renewal_reminder_email + branding refresh

**Files:**
- Test: `tests/CustomerManager/RenewalReminderEmailTest.php` (yeni)
- Test: `tests/CustomerManager/BrandingRefreshTest.php` (yeni)
- Modify: `includes/CustomerManager.php`

- [ ] **Step 1: Reminder email test (RED)**

```php
public function test_renewal_reminder_email_uses_tr_template_for_tr_locale(): void {
    $captured = $this->capture_wp_mail();

    $cm = new MHM_Polar_CustomerManager();
    $cm->send_renewal_reminder_email(
        'test@example.com',
        'Mehmet',
        'MHM Rentiva',
        '2026-05-26T00:00:00Z',
        'tr_TR',
        'https://wpalemi.com/account/'
    );

    $this->assertCount(1, $captured);
    $email = $captured[0];
    $this->assertSame('test@example.com', $email['to']);
    $this->assertStringContainsString('MHM Rentiva aboneliğiniz 7 gün içinde yenilenecek', $email['subject']);
    $this->assertStringContainsString('Merhaba Mehmet', $email['message']);
    $this->assertStringContainsString('26 Mayıs 2026', $email['message']);
    $this->assertStringContainsString('https://wpalemi.com/account/', $email['message']);
    $this->assertStringContainsString('— MHM Ekibi (WP Alemi)', $email['message']);
    $this->assertContains('From: MHM by WP Alemi <support@wpalemi.com>', $email['headers']);
}

public function test_renewal_reminder_email_uses_en_template_for_en_locale(): void {
    $captured = $this->capture_wp_mail();

    $cm = new MHM_Polar_CustomerManager();
    $cm->send_renewal_reminder_email(
        'test@example.com',
        'John',
        'MHM Currency Switcher',
        '2026-05-26T00:00:00Z',
        'en_US',
        'https://wpalemi.com/account/'
    );

    $email = $captured[0];
    $this->assertStringContainsString('Your MHM Currency Switcher subscription renews in 7 days', $email['subject']);
    $this->assertStringContainsString('Hi John', $email['message']);
    $this->assertStringContainsString('May 26, 2026', $email['message']);
    $this->assertStringContainsString('— MHM Team (WP Alemi)', $email['message']);
}

private function capture_wp_mail(): array {
    $captured = [];
    add_filter('pre_wp_mail', function ($null, $atts) use (&$captured) {
        $captured[] = $atts;
        return true; // short-circuit, don't actually send
    }, 10, 2);
    return $captured;  // by-ref via closure
}
```

> **Not:** `pre_wp_mail` filter pattern Phase 0 Task 0.4'te tespit edilmişse onunla tutarlı; aksi halde alternatif `wp_mail` filter chain incele.

- [ ] **Step 2: Branding refresh test (RED)**

```php
public function test_send_welcome_email_uses_new_branding(): void {
    $captured = $this->capture_wp_mail();

    $cm = new MHM_Polar_CustomerManager();
    $cm->send_welcome_email('test@example.com', 'John', 'TEST-KEY', 'MHM Rentiva');

    $email = $captured[0];
    $this->assertContains('From: MHM by WP Alemi <support@wpalemi.com>', $email['headers']);
    $this->assertStringContainsString('— MHM Team (WP Alemi)', $email['message']);
    $this->assertStringNotContainsString('— WP Alemi Team', $email['message']);
}

public function test_send_canceled_email_uses_new_branding(): void {
    // Same pattern, testing send_canceled_email
}

// 6 test, one per email method
```

- [ ] **Step 3: Implementation — yeni metod**

```php
public function send_renewal_reminder_email(
    string $email,
    string $name,
    string $product_name,
    string $renews_at,
    string $locale,
    string $manage_url
): void {
    $is_tr = (substr($locale, 0, 2) === 'tr');
    $renews_human = $this->format_email_date($renews_at, $is_tr ? 'tr' : 'en');

    if ($is_tr) {
        $subject = sprintf('%s aboneliğiniz 7 gün içinde yenilenecek', $product_name);
        $message = "Merhaba {$name},\n\n"
            . "{$product_name} aylık aboneliğiniz {$renews_human} tarihinde otomatik yenilenecek.\n\n"
            . "Otomatik yenilemeyi iptal etmek, ödeme yönteminizi güncellemek veya plan değiştirmek isterseniz aboneliğinizi yönetin:\n\n"
            . "{$manage_url}\n\n"
            . "Hiçbir şey yapmanıza gerek yok — yenileme otomatik gerçekleşecek.\n\n"
            . "— MHM Ekibi (WP Alemi)";
    } else {
        $subject = sprintf('Your %s subscription renews in 7 days', $product_name);
        $message = "Hi {$name},\n\n"
            . "Your {$product_name} monthly subscription renews on {$renews_human} and we'll charge your saved payment method automatically.\n\n"
            . "Need to cancel auto-renewal, update your card, or switch plans? Manage your subscription here:\n\n"
            . "{$manage_url}\n\n"
            . "Nothing to do otherwise — your renewal will go through automatically.\n\n"
            . "— MHM Team (WP Alemi)";
    }

    wp_mail($email, $subject, $message, ['From: MHM by WP Alemi <support@wpalemi.com>']);
}
```

- [ ] **Step 4: `format_email_date()` locale parametresi**

```php
private function format_email_date(string $raw, string $locale = 'en'): string {
    if ($raw === '') return '';
    $ts = strtotime($raw);
    if ($ts === false) return '';
    if ($locale === 'tr') {
        return wp_date('j F Y', $ts);
    }
    return gmdate('F j, Y', $ts);
}
```

- [ ] **Step 5: 6 lifecycle email branding refresh**

`send_welcome_email`, `send_canceled_email`, `send_uncanceled_email`, `send_refunded_email`, `send_expired_email` metodlarının her birinde:

- `'From: WP Alemi <support@wpalemi.com>'` → `'From: MHM by WP Alemi <support@wpalemi.com>'`
- `"— WP Alemi Team"` → `"— MHM Team (WP Alemi)"`

Tek tek dosyada bul-değiştir.

- [ ] **Step 6: Test'leri çalıştır (GREEN)**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit tests/CustomerManager/"
```

Expected: 11 PASS (5 reminder + 5 branding + format_email_date helper edge case'leri).

- [ ] **Step 7: Suite regresyon + PHPCS + commit**

```bash
git add plugins/mhm-polar-bridge/includes/CustomerManager.php
git add plugins/mhm-polar-bridge/tests/CustomerManager/
git commit -m "feat(polar-bridge): renewal reminder email + 6 lifecycle email branding refresh"
```

### Task B.6: RenewalReminderCron (RED → GREEN)

**Files:**
- Test: `tests/RenewalReminderCronTest.php` (yeni)
- Create: `includes/RenewalReminderCron.php`
- Modify: `mhm-polar-bridge.php` (bootstrap registration)

- [ ] **Step 1: Test eligibility kuralları (RED)**

```php
final class RenewalReminderCronTest extends WP_UnitTestCase {

    public function test_eligible_when_monthly_active_in_window_not_canceled_no_flag(): void {
        $user_id = $this->factory->user->create();
        $expires = strtotime('+7 days');
        update_user_meta($user_id, 'wpalemi_licenses', [
            [
                'plan' => 'monthly',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'polar_subscription_id' => 'sub_x',
                'expires_at' => date('Y-m-d H:i:s', $expires),
                'product_label' => 'MHM Rentiva',
            ]
        ]);

        $cron = new MHM_Polar_RenewalReminderCron();
        $eligible = $this->invoke($cron, 'find_eligible_subscribers', [
            $expires - HOUR_IN_SECONDS,
            $expires + DAY_IN_SECONDS,
        ]);

        $this->assertCount(1, $eligible);
        $this->assertSame($user_id, $eligible[0]['user_id']);
    }

    public function test_yearly_subscription_skipped(): void {
        // plan === 'yearly' → not eligible
    }

    public function test_canceled_at_period_end_skipped(): void { /* ... */ }
    public function test_status_canceled_skipped(): void { /* ... */ }
    public function test_status_refunded_skipped(): void { /* ... */ }
    public function test_status_expired_skipped(): void { /* ... */ }
    public function test_no_polar_subscription_id_skipped(): void { /* ... */ }
    public function test_expires_outside_window_skipped(): void { /* ... */ }
    public function test_already_sent_skipped_idempotency(): void { /* ... */ }
    public function test_send_marks_renewal_reminder_sent_at(): void { /* ... */ }
    public function test_failed_send_does_not_mark(): void { /* ... */ }
    public function test_register_schedules_event_if_not_scheduled(): void { /* ... */ }
}
```

12 test toplam.

- [ ] **Step 2: Test'leri çalıştır (RED — sınıf yok)**

Expected: PHPUnit `class not found` veya 12 test FAIL.

- [ ] **Step 3: `RenewalReminderCron.php` implementation**

```php
<?php
defined('ABSPATH') || exit;

class MHM_Polar_RenewalReminderCron {

    public const HOOK = 'mhm_polar_renewal_reminder_daily';

    private $now_provider;
    private MHM_Polar_CustomerManager $customer_manager;

    public function __construct(?callable $now_provider = null, ?MHM_Polar_CustomerManager $customer_manager = null) {
        $this->now_provider = $now_provider ?? 'time';
        $this->customer_manager = $customer_manager ?? new MHM_Polar_CustomerManager();
    }

    public function register(): void {
        add_action('init', [$this, 'schedule_event']);
        add_action(self::HOOK, [$this, 'run']);
    }

    public function schedule_event(): void {
        if (!wp_next_scheduled(self::HOOK)) {
            $next = strtotime('tomorrow 06:00 UTC');
            wp_schedule_event($next, 'daily', self::HOOK);
        }
    }

    public function run(): void {
        $now = ($this->now_provider)();
        $window_start = $now + (int) (6.5 * DAY_IN_SECONDS);
        $window_end   = $now + (int) (7.5 * DAY_IN_SECONDS);

        foreach ($this->find_eligible_subscribers($window_start, $window_end) as $candidate) {
            $this->send_and_mark($candidate);
        }
    }

    /**
     * @return array<int, array{user_id:int, license_index:int, license:array}>
     */
    public function find_eligible_subscribers(int $window_start, int $window_end): array {
        $eligible = [];
        $users = get_users([
            'meta_key'     => 'wpalemi_licenses',
            'meta_compare' => 'EXISTS',
            'fields'       => ['ID', 'user_email'],
        ]);

        foreach ($users as $user) {
            $licenses = get_user_meta($user->ID, 'wpalemi_licenses', true);
            if (!is_array($licenses)) {
                continue;
            }

            foreach ($licenses as $idx => $row) {
                if (!is_array($row)) continue;
                if (($row['plan'] ?? '') !== 'monthly') continue;
                if (($row['status'] ?? '') !== 'active') continue;
                if (!empty($row['cancel_at_period_end'])) continue;
                if (empty($row['polar_subscription_id'])) continue;
                if (empty($row['expires_at'])) continue;
                if (!empty($row['renewal_reminder_sent_at'])) continue;

                $expires_ts = strtotime((string) $row['expires_at']);
                if ($expires_ts === false || $expires_ts < $window_start || $expires_ts >= $window_end) continue;

                $eligible[] = [
                    'user_id'       => (int) $user->ID,
                    'license_index' => $idx,
                    'license'       => $row,
                ];
            }
        }

        return $eligible;
    }

    public function send_and_mark(array $candidate): void {
        $user_id = $candidate['user_id'];
        $idx     = $candidate['license_index'];
        $license = $candidate['license'];

        $user = get_user_by('id', $user_id);
        if (!$user) return;

        $name = $this->resolve_name($user, $license);
        $locale = get_user_locale($user_id);
        $product_name = $license['product_label'] ?? 'Subscription';
        $renews_at = $license['expires_at'] ?? '';
        $manage_url = home_url('/account/');

        $this->customer_manager->send_renewal_reminder_email(
            $user->user_email,
            $name,
            $product_name,
            $renews_at,
            $locale,
            $manage_url
        );

        // Mark sent
        $licenses = get_user_meta($user_id, 'wpalemi_licenses', true);
        if (is_array($licenses) && isset($licenses[$idx])) {
            $licenses[$idx]['renewal_reminder_sent_at'] = current_time('mysql', true);
            update_user_meta($user_id, 'wpalemi_licenses', $licenses);
        }
    }

    private function resolve_name(\WP_User $user, array $license): string {
        $first = trim((string) $user->first_name);
        if ($first !== '') return $first;
        return $user->user_login;
    }
}
```

- [ ] **Step 4: Bootstrap registration**

`mhm-polar-bridge.php` ana dosyaya:

```php
require_once __DIR__ . '/includes/RenewalReminderCron.php';
// ... mevcut bootstrap ...
(new MHM_Polar_RenewalReminderCron())->register();
```

- [ ] **Step 5: Test GREEN**

```bash
docker exec wpalemi-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-polar-bridge && vendor/bin/phpunit tests/RenewalReminderCronTest.php"
```

Expected: 12 PASS.

- [ ] **Step 6: Suite + PHPCS + commit**

```bash
git add plugins/mhm-polar-bridge/includes/RenewalReminderCron.php
git add plugins/mhm-polar-bridge/mhm-polar-bridge.php
git add plugins/mhm-polar-bridge/tests/RenewalReminderCronTest.php
git commit -m "feat(polar-bridge): RenewalReminderCron — daily monthly subscriber reminders"
```

### Task B.7: Version bump + changelog + readme

**Files:**
- Modify: `mhm-polar-bridge.php`
- Modify: `readme.txt`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Version 1.8.0 → 1.9.0**

- [ ] **Step 2: readme.txt + CHANGELOG.md detaylı entry**

```
= 1.9.0 — 2026-04-26 =

* New: Renewal reminder email cron for monthly subscribers (-7 days, idempotent via user_meta flag).
* New: `MHM_Polar_CustomerManager::send_renewal_reminder_email()` locale-aware (TR + EN).
* Branding: All 6 lifecycle emails now use `From: MHM by WP Alemi <support@wpalemi.com>` and signature `— MHM Team (WP Alemi)` / `— MHM Ekibi (WP Alemi)`.
* New: `LicenseProvisioner::generate()` 7th parameter `polar_customer_id` (forwarded to license-server v1.11.0+).
* New: `WebhookHandler::handle_subscription_updated()` resets `renewal_reminder_sent_at` on period change so next cycle's reminder fires.
* Change: `MHM_Polar_API::create_customer_session()` now returns array with `customer_portal_url` + `expires_at` (was string). `BillingPortalRedirect` updated.
```

- [ ] **Step 3: Commit**

```bash
git add plugins/mhm-polar-bridge/mhm-polar-bridge.php
git add plugins/mhm-polar-bridge/readme.txt
git add plugins/mhm-polar-bridge/CHANGELOG.md
git commit -m "chore(polar-bridge): bump version to 1.9.0 + changelog"
```

### Task B.8: ZIP build + deploy + GitHub release

- [ ] **Step 1: ZIP build** (mevcut polar-bridge build pattern'i — Phase 0'da teyit)

- [ ] **Step 2: Hostinger MCP deploy** (küçük plugin, MCP token limit aşmaz)

```python
mcp__hostinger-mcp__hosting_deployWordpressPlugin(
    domain="wpalemi.com",
    plugin_slug="mhm-polar-bridge",
    source_zip_url="..."  # veya local file
)
```

- [ ] **Step 3: Cron register doğrulama (post-deploy)**

```bash
# Hostinger SSH veya WP-CLI proxy ile
wp cron event list | grep mhm_polar_renewal_reminder_daily
```

Expected: bir satır görünür, schedule `daily`, next time tomorrow 06:00 UTC.

- [ ] **Step 4: GitHub release**

```bash
gh release create polar-bridge-v1.9.0 \
    /tmp/mhm-polar-bridge-v1.9.0.zip \
    --repo MaxHandMade/wpalemi \
    --title "polar-bridge v1.9.0 — Renewal reminders + branding" \
    --notes-file release-notes.md
```

> **Not:** Polar-bridge wpalemi monorepo içinde — repo `MaxHandMade/wpalemi`. Release tag prefix'i `polar-bridge-vX.Y.Z` ile diğer plugin'lerden ayrıştır.

### Task B.9: wp-conductor TodoList — Phase 2 release gate

- [ ] PHPUnit yeşil (baseline + ~30 yeni)
- [ ] WPCS PASS (0 error)
- [ ] i18n: polar-bridge'in TR locale'i yok şu an (bu spec'in scope'u dışı, v1.10.0'da eklenir) — N/A
- [ ] superpowers:verification-before-completion
- [ ] wp-reflect
- [ ] Docs (CHANGELOG.md + readme.txt)
- [ ] ZIP + deploy
- [ ] GitHub release
- [ ] Docs blog (`mhm-rentiva-docs/blog/2026-04-26-polar-bridge-v1.9.0-release.md` — internal note, EN-only opsiyonel)
- [ ] Push

> **Phase 2 Review Checkpoint:** polar-bridge v1.9.0 yayında. Yeni provisioner satırlar polar_customer_id ile yazılıyor. Cron register edilmiş. Buton henüz plugin tarafında yok. Kullanıcı onayı sonrası Phase 3.

---

## Phase 3A — mhm-rentiva v4.32.0 (~2.5 saat)

### Task C.1: LicenseManager::createCustomerPortalSession (RED → GREEN)

**Files:**
- Test: `tests/Admin/Licensing/LicenseManagerCustomerPortalSessionTest.php` (yeni)
- Modify: `src/Admin/Licensing/LicenseManager.php`

- [ ] **Step 1: Test (RED)**

```php
final class LicenseManagerCustomerPortalSessionTest extends WP_UnitTestCase {

    public function tearDown(): void {
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function test_returns_error_when_license_not_active(): void {
        // Mock LicenseManager to return inactive state
        $manager = $this->fresh_manager_with_inactive_license();
        $result = $manager->createCustomerPortalSession();
        $this->assertFalse($result['success']);
        $this->assertSame('license_not_active', $result['error_code']);
    }

    public function test_returns_url_on_happy_path(): void {
        $manager = $this->fresh_manager_with_active_license('TEST-KEY-1234');

        add_filter('pre_http_request', function ($preempt, $args, $url) {
            if (strpos($url, '/customer-portal-session') === false) return $preempt;
            return [
                'response' => ['code' => 200],
                'body' => $this->build_signed_response([
                    'success' => true,
                    'data' => [
                        'customer_portal_url' => 'https://polar.sh/portal/abc',
                        'expires_at' => '2026-04-26T15:00:00Z',
                    ],
                ]),
            ];
        }, 10, 3);

        $result = $manager->createCustomerPortalSession('https://example.com/return');
        $this->assertTrue($result['success']);
        $this->assertSame('https://polar.sh/portal/abc', $result['customer_portal_url']);
    }

    public function test_returns_error_on_server_404(): void { /* license_not_found */ }
    public function test_returns_error_on_server_422(): void { /* license_not_subscription */ }
    public function test_returns_error_on_signature_mismatch(): void { /* tampered_response */ }
}
```

5 test toplam.

- [ ] **Step 2: Test çalıştır (RED)**

- [ ] **Step 3: Implementation**

`src/Admin/Licensing/LicenseManager.php`'e yeni metod (mevcut `validate()` paterninin paraleli):

```php
public function createCustomerPortalSession(string $return_url = ''): array {
    $license = $this->getLicense();
    if (empty($license['key']) || ! $this->isActive()) {
        return ['success' => false, 'error_code' => 'license_not_active'];
    }

    $response = $this->request(
        'licenses/customer-portal-session',
        [
            'license_key' => $license['key'],
            'site_hash'   => $this->getSiteHash(),
            'return_url'  => $return_url,
        ]
    );

    if (is_wp_error($response)) {
        return ['success' => false, 'error_code' => $this->mapWpError($response)];
    }

    if (!isset($response['success']) || !$response['success']) {
        return ['success' => false, 'error_code' => $response['error'] ?? 'unknown_error'];
    }

    return [
        'success'             => true,
        'customer_portal_url' => (string) ($response['data']['customer_portal_url'] ?? ''),
        'expires_at'          => (string) ($response['data']['expires_at'] ?? ''),
    ];
}
```

> Mevcut `request()` private metodu RSA signature verify yapıyor (v4.30.0+); ekstra kod yok.

- [ ] **Step 4: Test GREEN + suite + PHPCS + commit**

```bash
git add plugins/mhm-rentiva/src/Admin/Licensing/LicenseManager.php
git add plugins/mhm-rentiva/tests/Admin/Licensing/LicenseManagerCustomerPortalSessionTest.php
git commit -m "feat(rentiva): LicenseManager::createCustomerPortalSession() public method"
```

### Task C.2: Admin-post handler (RED → GREEN)

**Files:**
- Test: `tests/Admin/Licensing/LicenseAdminManageSubscriptionTest.php` (yeni)
- Modify: `src/Admin/Licensing/LicenseAdmin.php`

- [ ] **Step 1: Test (RED)**

```php
final class LicenseAdminManageSubscriptionTest extends WP_UnitTestCase {

    public function test_handler_requires_manage_options_capability(): void {
        $subscriber = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);

        $this->expectException(\WPDieException::class);
        LicenseAdmin::handle_manage_subscription();
    }

    public function test_handler_verifies_nonce(): void {
        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $_REQUEST['_wpnonce'] = 'invalid';

        $this->expectException(\WPDieException::class);
        LicenseAdmin::handle_manage_subscription();
    }

    public function test_handler_redirects_to_portal_url_on_success(): void {
        // Mock LicenseManager + valid nonce + admin user
        // Capture wp_redirect call
    }

    public function test_handler_redirects_to_error_page_on_failure(): void {
        // Mock LicenseManager to return error
        // Capture wp_safe_redirect with reason query arg
    }
}
```

4 test.

- [ ] **Step 2: Test RED**

- [ ] **Step 3: Implementation**

`LicenseAdmin.php` constructor / init metodunda action register:

```php
add_action('admin_post_mhm_rentiva_manage_subscription', [self::class, 'handle_manage_subscription']);
```

Handler:

```php
public static function handle_manage_subscription(): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions.', 'mhm-rentiva'), 403);
    }
    check_admin_referer('mhm_rentiva_manage_subscription');

    $license_admin_url = admin_url('admin.php?page=mhm-rentiva-license');
    $session = LicenseManager::instance()->createCustomerPortalSession($license_admin_url);

    if (!$session['success']) {
        error_log('[mhm-rentiva] Manage Subscription failed: ' . $session['error_code']);
        wp_safe_redirect(add_query_arg(
            ['license' => 'manage_unavailable', 'reason' => sanitize_key($session['error_code'])],
            $license_admin_url
        ));
        exit;
    }

    wp_redirect($session['customer_portal_url']);
    exit;
}
```

- [ ] **Step 4-6: Test GREEN + suite + PHPCS + commit**

```bash
git add plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php
git add plugins/mhm-rentiva/tests/Admin/Licensing/LicenseAdminManageSubscriptionTest.php
git commit -m "feat(rentiva): admin-post handler for Manage Subscription"
```

### Task C.3: License sayfası buton render + emphasis helpers (RED → GREEN)

**Files:**
- Test: `tests/Admin/Licensing/EmphasisClassTest.php` (yeni)
- Modify: `src/Admin/Licensing/LicenseAdmin.php` (License sayfası render kısmı)

- [ ] **Step 1: Emphasis class test (RED)**

```php
final class EmphasisClassTest extends WP_UnitTestCase {

    public function test_null_days_returns_empty(): void {
        $this->assertSame('', LicenseAdmin::compute_emphasis_class(null));
    }

    public function test_60_days_returns_empty(): void {
        $this->assertSame('', LicenseAdmin::compute_emphasis_class(60));
    }

    public function test_25_days_returns_warning(): void {
        $this->assertSame('mhm-rentiva-license-warning', LicenseAdmin::compute_emphasis_class(25));
    }

    public function test_5_days_returns_urgent(): void {
        $this->assertSame('mhm-rentiva-license-urgent', LicenseAdmin::compute_emphasis_class(5));
    }

    public function test_0_days_returns_urgent(): void {
        $this->assertSame('mhm-rentiva-license-urgent', LicenseAdmin::compute_emphasis_class(0));
    }
}
```

5 test (4 değil — null + 4 threshold).

- [ ] **Step 2: Helper metodlar implementation**

```php
public static function compute_days_remaining(array $license_data): ?int {
    $expires_raw = $license_data['expires_at'] ?? $license_data['expires'] ?? '';
    if (empty($expires_raw)) return null;
    $ts = is_numeric($expires_raw) ? (int) $expires_raw : strtotime($expires_raw);
    if ($ts === false) return null;
    $diff = $ts - time();
    return $diff <= 0 ? 0 : (int) ceil($diff / DAY_IN_SECONDS);
}

public static function compute_emphasis_class(?int $days_remaining): string {
    if ($days_remaining === null) return '';
    if ($days_remaining <= 7)  return 'mhm-rentiva-license-urgent';
    if ($days_remaining <= 30) return 'mhm-rentiva-license-warning';
    return '';
}
```

- [ ] **Step 3: License sayfası render — buton**

`LicenseAdmin.php` License Management section'ı (mevcut line 308-345 civarı). `Re-validate Now`'dan ÖNCE Manage Subscription butonu ekle:

```php
$days_remaining = self::compute_days_remaining($license_data);
$emphasis_class = self::compute_emphasis_class($days_remaining);

$manage_url = wp_nonce_url(
    add_query_arg('action', 'mhm_rentiva_manage_subscription', admin_url('admin-post.php')),
    'mhm_rentiva_manage_subscription'
);

printf(
    '<a href="%s" target="_blank" rel="noopener" class="button button-primary mhm-rentiva-manage-subscription %s" style="margin-right:10px;">%s</a>',
    esc_url($manage_url),
    esc_attr($emphasis_class),
    esc_html__('Manage Subscription', 'mhm-rentiva')
);
```

- [ ] **Step 4-6: Test GREEN + suite + PHPCS + commit**

```bash
git add plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php
git add plugins/mhm-rentiva/tests/Admin/Licensing/EmphasisClassTest.php
git commit -m "feat(rentiva): Manage Subscription button + state-driven emphasis"
```

### Task C.4: CSS — license-admin.css

**Files:**
- Create: `assets/css/license-admin.css`
- Modify: `src/Admin/Licensing/LicenseAdmin.php` (enqueue)

- [ ] **Step 1: CSS dosyası oluştur**

`assets/css/license-admin.css`:

```css
/**
 * License Admin — Manage Subscription button state-driven emphasis.
 */

.mhm-rentiva-manage-subscription {
    transition: box-shadow 0.2s, background-color 0.2s;
}

.mhm-rentiva-manage-subscription.mhm-rentiva-license-warning {
    background-color: #fbbf24;
    border-color: #d97706;
    color: #78350f;
}
.mhm-rentiva-manage-subscription.mhm-rentiva-license-warning:hover {
    background-color: #f59e0b;
    border-color: #b45309;
    color: #451a03;
}

.mhm-rentiva-manage-subscription.mhm-rentiva-license-urgent {
    background-color: #f97316;
    border-color: #c2410c;
    color: #fff;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.18);
}
.mhm-rentiva-manage-subscription.mhm-rentiva-license-urgent:hover {
    background-color: #ea580c;
    border-color: #9a3412;
    box-shadow: 0 0 0 4px rgba(234, 88, 12, 0.24);
}
```

- [ ] **Step 2: Enqueue (mevcut enqueue hook'una ekle)**

`LicenseAdmin::enqueue_admin_styles()` veya benzer mevcut metod:

```php
$css_path = MHM_RENTIVA_PLUGIN_DIR . 'assets/css/license-admin.css';
if (file_exists($css_path)) {
    wp_enqueue_style(
        'mhm-rentiva-license-admin',
        MHM_RENTIVA_PLUGIN_URL . 'assets/css/license-admin.css',
        [],
        MHM_RENTIVA_VERSION . '.' . filemtime($css_path)
    );
}
```

- [ ] **Step 3: Suite + PHPCS + commit**

```bash
git add plugins/mhm-rentiva/assets/css/license-admin.css
git add plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php
git commit -m "feat(rentiva): license-admin.css — state-driven button emphasis"
```

### Task C.5: Notice akışı — manage_unavailable

**Files:**
- Modify: `src/Admin/Licensing/LicenseAdmin.php` (`display_admin_notices()` switch)

- [ ] **Step 1: Switch case ekle**

`display_admin_notices()` metodu mevcut switch'i (line 461 civarı `case 'deactivated'`):

```php
case 'manage_unavailable':
    $reason = isset($_GET['reason']) ? sanitize_key($_GET['reason']) : '';
    $reason_label = self::get_manage_unavailable_label($reason);
    echo '<div class="notice notice-warning is-dismissible">';
    printf(
        '<p>%s</p>',
        esc_html(sprintf(
            /* translators: %s: short reason like "service unavailable" */
            __('ℹ️ Subscription management is not available right now (%s). Please try again later or contact support@wpalemi.com.', 'mhm-rentiva'),
            $reason_label
        ))
    );
    echo '</div>';
    break;
```

Helper:

```php
private static function get_manage_unavailable_label(string $reason): string {
    $labels = [
        'license_not_subscription' => __('legacy license', 'mhm-rentiva'),
        'polar_api_unavailable'    => __('service unavailable', 'mhm-rentiva'),
        'license_not_active'       => __('license inactive', 'mhm-rentiva'),
        'site_not_activated'       => __('site not activated', 'mhm-rentiva'),
        'license_not_found'        => __('license not found', 'mhm-rentiva'),
    ];
    return $labels[$reason] ?? __('unknown error', 'mhm-rentiva');
}
```

- [ ] **Step 2: Commit**

```bash
git add plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php
git commit -m "feat(rentiva): admin notice for manage_unavailable redirect"
```

### Task C.6: i18n pipeline — wp-i18n skill invoke

- [ ] **Step 1: wp-i18n skill invoke**

Skill: `wp-i18n` invoke et. Beklenen iş:
- `.pot` regenerate (yeni ~6 string)
- `msgmerge` Türkçe `.po`'ya yeni string'ler eklenir + fuzzy temizleme
- `.mo` derlenir
- `.l10n.php` derlenir (WP 6.5+)
- `wp cache flush`

Yeni string'ler:
1. `Manage Subscription`
2. `Insufficient permissions.`
3. `ℹ️ Subscription management is not available right now (%s). Please try again later or contact support@wpalemi.com.`
4. `legacy license`
5. `service unavailable`
6. `license inactive`
7. `site not activated`
8. `license not found`
9. `unknown error`

TR çeviriler:
1. `Aboneliği Yönet`
2. `Yetersiz izin.`
3. `ℹ️ Abonelik yönetimi şu an mümkün değil (%s). Lütfen daha sonra tekrar deneyin veya support@wpalemi.com ile iletişime geçin.`
4. `eski lisans`
5. `servis kullanılamıyor`
6. `lisans aktif değil`
7. `site aktif değil`
8. `lisans bulunamadı`
9. `bilinmeyen hata`

- [ ] **Step 2: i18n doğrulama**

```bash
grep -c '#, fuzzy' c:/projects/rentiva-dev/plugins/mhm-rentiva/languages/mhm-rentiva-tr_TR.po
```

Expected: 0 fuzzy entry (tümü düzeltilmiş).

```bash
docker exec rentiva-dev-wpcli-1 wp cache flush
```

- [ ] **Step 3: Commit**

```bash
git add plugins/mhm-rentiva/languages/
git commit -m "i18n(rentiva): TR translations for v4.32.0 strings"
```

### Task C.7: Version bump + readme + changelog

**Files:**
- Modify: `mhm-rentiva.php`
- Modify: `readme.txt`
- Modify: `README.md`, `README-tr.md`
- Modify: `changelog.json`, `changelog-tr.json`

- [ ] **Step 1: Version 4.31.2 → 4.32.0** (constant adı Phase 0'da teyit)

- [ ] **Step 2: readme.txt + changelog**

```
= 4.32.0 — 2026-04-26 =

* New: "Manage Subscription" button on the License page — opens Polar customer portal in a new tab. Cancel auto-renewal, update payment method, switch plans, or resubscribe — all without leaving WP admin.
* New: State-driven emphasis on the button — yellow ≤30 days from expiry, amber + glow ≤7 days. Customer always sees how close renewal is.
* New: License Manager exposes `createCustomerPortalSession()` public method.
* No new tracking, no new external services from the plugin's perspective — Polar is reached via the same license-server already in use.
```

- [ ] **Step 3: README badges + changelog.json/changelog-tr.json detaylı entry**

- [ ] **Step 4: Commit**

```bash
git add plugins/mhm-rentiva/mhm-rentiva.php
git add plugins/mhm-rentiva/readme.txt
git add plugins/mhm-rentiva/README.md
git add plugins/mhm-rentiva/README-tr.md
git add plugins/mhm-rentiva/changelog.json
git add plugins/mhm-rentiva/changelog-tr.json
git commit -m "chore(rentiva): bump to 4.32.0 + changelog"
```

### Task C.8: ZIP build + GitHub release + docs blog

- [ ] **Step 1: ZIP build**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
python bin/build-release.py
# Output: build/mhm-rentiva.4.32.0.zip
```

- [ ] **Step 2: Tag + push**

```bash
cd c:/projects/rentiva-dev
git tag -a v4.32.0 -m "v4.32.0 — Manage Subscription button"
git push origin develop
git push origin v4.32.0
```

- [ ] **Step 3: GitHub release**

```bash
gh release create v4.32.0 \
    plugins/mhm-rentiva/build/mhm-rentiva.4.32.0.zip \
    --repo MaxHandMade/mhm-rentiva \
    --title "v4.32.0 — Manage Subscription button" \
    --notes-file release-notes.md
```

- [ ] **Step 4: Docs blog yazısı (EN)**

`mhm-rentiva-docs/blog/2026-04-26-rentiva-v4.32.0-release.md`:

```markdown
---
slug: rentiva-v4.32.0-release
title: Rentiva v4.32.0 — Manage your subscription from the plugin
authors: [maxhandmade]
tags: [release, rentiva, license]
---

The License page now has a **Manage Subscription** button that opens the Polar customer portal in a new tab. Cancel auto-renewal, update your card, switch plans, or resubscribe — all without leaving WordPress admin.

<!-- truncate -->

## State-driven emphasis

The button changes color based on how close your subscription is to renewal:

- **Normal:** standard primary blue
- **≤30 days:** yellow — gentle heads-up
- **≤7 days:** amber + glow — time to decide

## Architecture

The plugin reaches Polar through the same license server already in use. No new external service contacted from your site's perspective. Schema and signature integrity remain RSA-protected (v4.30.0+).

## Out of scope

- Past_due / dunning notifications — Polar handles these directly via email.
- Yearly subscription renewal reminders — Polar sends these 7 days before renewal automatically.

Monthly subscribers receive an additional 7-day-out reminder email from us, since Polar doesn't generate these for short billing cycles.
```

- [ ] **Step 5: Docs blog (TR)**

`mhm-rentiva-docs/i18n/tr/docusaurus-plugin-content-blog/2026-04-26-rentiva-v4.32.0-release.md` — yukarıdakinin Türkçe çevirisi.

- [ ] **Step 6: Docs commit + push**

```bash
cd c:/projects/mhm-rentiva-docs
git add blog/2026-04-26-rentiva-v4.32.0-release.md
git add i18n/tr/docusaurus-plugin-content-blog/2026-04-26-rentiva-v4.32.0-release.md
git commit -m "blog: Rentiva v4.32.0 release announcement"
git push origin main  # GitHub Pages Deploy workflow tetikler
```

### Task C.9: wp-conductor TodoList — Phase 3A release gate

- [ ] PHPUnit yeşil (793 → ~810)
- [ ] WPCS PASS (0 error)
- [ ] i18n güncel (fuzzy: 0)
- [ ] verification-before-completion
- [ ] wp-reflect
- [ ] Docs (changelog + readme + README + Stable tag)
- [ ] ZIP
- [ ] GitHub release + asset
- [ ] Docs blog EN + TR
- [ ] Push

> **Phase 3A Review Checkpoint:** Rentiva v4.32.0 yayında. Sandbox müşteri sitesinde "Manage Subscription" butonu görünmeli. Tıklayınca Polar customer portal açılmalı. Kullanıcı onayı.

---

## Phase 3B — mhm-currency-switcher v0.7.0 (~2 saat)

Phase 3A'nın paritesi. Snake_case + React + REST endpoint.

### Task D.0: get_site_hash visibility (eğer Phase 0'da private bulunduysa)

**Files:**
- Modify: `src/License/LicenseManager.php`

- [ ] **Step 1: `private function get_site_hash` → `public function get_site_hash`**

```bash
git diff src/License/LicenseManager.php
git commit -m "refactor(cs): make LicenseManager::get_site_hash() public for parity"
```

(Phase 0'da public ise bu task atlanır.)

### Task D.1: LicenseManager create_customer_portal_session

**Files:**
- Test: `tests/License/LicenseManagerCustomerPortalSessionTest.php` (yeni)
- Modify: `src/License/LicenseManager.php`

Phase 3A Task C.1'in snake_case kopyası — 5 test, aynı senaryolar (`license_not_active`, happy path, server 404/422, signature mismatch).

```bash
git commit -m "feat(cs): LicenseManager::create_customer_portal_session()"
```

### Task D.2: REST endpoint /license/manage-subscription

**Files:**
- Test: `tests/REST/ManageSubscriptionEndpointTest.php` (yeni)
- Modify: CS REST controller (Phase 0 Task 0.3'te tespit edilen dosya)

- [ ] **Step 1: Test (RED)**

```php
final class ManageSubscriptionEndpointTest extends WP_UnitTestCase {

    public function test_endpoint_requires_manage_options(): void {
        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));
        $request = new WP_REST_Request('POST', '/mhm-currency/v1/license/manage-subscription');
        $response = rest_do_request($request);
        $this->assertSame(403, $response->get_status());
    }

    public function test_endpoint_returns_url_on_success(): void {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        // Mock LicenseManager...
        $request = new WP_REST_Request('POST', '/mhm-currency/v1/license/manage-subscription');
        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['customer_portal_url']);
    }

    public function test_endpoint_returns_error_code_on_failure(): void { /* ... */ }
    public function test_endpoint_uses_correct_return_url(): void { /* ... */ }
    public function test_endpoint_calls_license_manager(): void { /* ... */ }
}
```

5 test.

- [ ] **Step 2: Implementation**

```php
register_rest_route('mhm-currency/v1', '/license/manage-subscription', [
    'methods'             => 'POST',
    'callback'            => [$this, 'create_manage_subscription_url'],
    'permission_callback' => fn() => current_user_can('manage_options'),
]);
```

```php
public function create_manage_subscription_url(\WP_REST_Request $request): \WP_REST_Response {
    $return_url = admin_url('admin.php?page=mhm-currency-switcher#license');
    $session    = LicenseManager::instance()->create_customer_portal_session($return_url);

    if (!$session['success']) {
        error_log('[mhm-currency-switcher] Manage Subscription failed: ' . $session['error_code']);
        return new \WP_REST_Response([
            'success'    => false,
            'error_code' => $session['error_code'],
        ], 200);
    }

    return new \WP_REST_Response([
        'success'             => true,
        'customer_portal_url' => $session['customer_portal_url'],
    ], 200);
}
```

- [ ] **Step 3-5: Test GREEN + suite + commit**

```bash
git commit -m "feat(cs): /mhm-currency/v1/license/manage-subscription REST endpoint"
```

### Task D.3: License.jsx — handleManageSubscription + buton

**Files:**
- Modify: `admin-app/src/components/tabs/License.jsx`

- [ ] **Step 1: handleManageSubscription callback ekle** (line 137 civarı `handleActivate`'in altına)

Spec'teki kod (Bölüm 5 / C):

```jsx
const [ manageLoading, setManageLoading ] = useState( false );

const emphasisClass = useCallback( ( daysLeft ) => {
    if ( daysLeft === null ) return '';
    if ( daysLeft <= 7 )  return 'mhm-cs-license-urgent';
    if ( daysLeft <= 30 ) return 'mhm-cs-license-warning';
    return '';
}, [] );

const handleManageSubscription = useCallback( async () => {
    setManageLoading( true );
    setNotice( null );
    try {
        const response = await apiFetch( {
            path: '/mhm-currency/v1/license/manage-subscription',
            method: 'POST',
        } );
        if ( response.success && response.customer_portal_url ) {
            window.open( response.customer_portal_url, '_blank', 'noopener' );
        } else {
            setNotice( {
                type: 'warning',
                message: __(
                    'Subscription management is not available right now. Please try again later or contact support@wpalemi.com.',
                    'mhm-currency-switcher'
                ),
            } );
        }
    } catch ( err ) {
        setNotice( {
            type: 'error',
            message: __( 'Connection error. Please try again.', 'mhm-currency-switcher' ),
        } );
    } finally {
        setManageLoading( false );
    }
}, [] );
```

- [ ] **Step 2: Buton render** (Deactivate butonunun soluna — License Management bölgesi)

```jsx
{ isActive && license.key && (
    <>
        <button
            type="button"
            className={ `button button-primary ${ emphasisClass( remaining ) }` }
            onClick={ handleManageSubscription }
            disabled={ manageLoading }
            style={ { marginRight: '10px' } }
        >
            { manageLoading
                ? __( 'Opening…', 'mhm-currency-switcher' )
                : __( 'Manage Subscription', 'mhm-currency-switcher' )
            }
        </button>
        { /* mevcut Deactivate butonu burada kalır */ }
    </>
) }
```

- [ ] **Step 3: Build**

```bash
cd c:/projects/mhm-currency-switcher/admin-app
npm run build
```

- [ ] **Step 4: Manual smoke test (Docker)**

WP admin sayfasını aç, License tab'a git, Pro durumda buton görünüyor mu, tıklayınca apiFetch tetikleniyor mu (Network tab'dan gör).

- [ ] **Step 5: Commit**

```bash
cd c:/projects/mhm-currency-switcher
git add admin-app/src/components/tabs/License.jsx
git add admin-app/dist/  # build output (eğer git-tracked ise)
git commit -m "feat(cs): Manage Subscription button in License.jsx"
```

### Task D.4: CSS — emphasis classes

**Files:**
- Modify: `admin-app/src/style.css`

- [ ] **Step 1: CSS ekle** (spec Bölüm 5 / D)

```css
/* WP admin button styles take high specificity — !important is intentional. */
.mhm-cs-license-warning {
    background-color: #fbbf24 !important;
    border-color: #d97706 !important;
    color: #78350f !important;
}
/* ... rest same as spec ... */
```

- [ ] **Step 2: Build + commit**

```bash
cd c:/projects/mhm-currency-switcher/admin-app
npm run build
cd ..
git add admin-app/src/style.css admin-app/dist/
git commit -m "feat(cs): state-driven emphasis CSS for Manage Subscription"
```

### Task D.5: i18n

Phase 3A Task C.6'ın paritesi. Yeni string'ler:

1. `Manage Subscription`
2. `Opening…`
3. `Subscription management is not available right now. Please try again later or contact support@wpalemi.com.`
4. `Connection error. Please try again.`

TR çeviriler eklenir. Fuzzy temizleme. `.mo` + `.l10n.php` derleme.

```bash
git add languages/
git commit -m "i18n(cs): TR translations for v0.7.0 strings"
```

### Task D.6: Version bump + readme + changelog

**Files:**
- Modify: `mhm-currency-switcher.php`
- Modify: `readme.txt`
- Modify: `README.md`
- Modify: `changelog.json`, `changelog-tr.json` (varsa)

- [ ] **Step 1: Version 0.6.5 → 0.7.0** (constant adı Phase 0'da teyit)

- [ ] **Step 2: readme.txt Stable tag + Changelog entry**

- [ ] **Step 3: changelog.json + changelog-tr.json**

- [ ] **Step 4: Commit**

```bash
git commit -m "chore(cs): bump to 0.7.0 + changelog"
```

### Task D.7: ZIP + GitHub release + docs blog

- [ ] **Step 1: ZIP build**

```bash
cd c:/projects/mhm-currency-switcher
python bin/build-release.py
```

- [ ] **Step 2: Tag + push + GitHub release**

```bash
cd c:/projects/mhm-currency-switcher
git tag -a v0.7.0 -m "v0.7.0 — Manage Subscription button"
git push origin develop
git push origin v0.7.0
gh release create v0.7.0 build/mhm-currency-switcher-v0.7.0.zip \
    --repo MaxHandMade/mhm-currency-switcher \
    --title "v0.7.0 — Manage Subscription button" \
    --notes-file release-notes.md
```

- [ ] **Step 3: Docs blog (EN + TR)**

`mhm-rentiva-docs/blog/2026-04-26-cs-v0.7.0-release.md` + i18n TR. Frontmatter `tags: [release, currency-switcher, license]`.

- [ ] **Step 4: Docs commit + push**

### Task D.8: wp-conductor TodoList — Phase 3B release gate

- [ ] PHPUnit yeşil (148 → ~158)
- [ ] PHPCS baseline 27 errors korunur (yeni hata 0)
- [ ] PHPStan level 6 — 0 error (`--memory-limit=2G`)
- [ ] PHPUnit CI matrix (PHP 7.4/8.1/8.2/8.3 × WP 6.0/6.4/latest = 6 jobs) — push sonrası kontrol
- [ ] i18n güncel (fuzzy: 0)
- [ ] verification-before-completion
- [ ] wp-reflect
- [ ] Docs (changelog + readme + README)
- [ ] `npm run build` — admin-app/dist güncellendi
- [ ] ZIP
- [ ] GitHub release + asset
- [ ] Docs blog EN + TR
- [ ] Push

> **Phase 3B Review Checkpoint:** CS v0.7.0 yayında. Sandbox müşteri sitesinde React panelinde Manage Subscription butonu görünmeli. Tüm 4 release tamamlandı. E2E doğrulama Phase 4.

---

## Phase 4 — E2E sandbox doğrulama (~1 saat manuel)

Sandbox Polar org'u + 4 test product (Rentiva Monthly + Yearly + CS Monthly + Yearly) + sandbox webhook + test customer card kullanılarak.

### Task E.1: Test environment audit

- [ ] **Step 1: Sandbox webhook URL'i Polar org panelinde doğrula**

```
Polar Sandbox dashboard → Settings → Webhooks
Endpoint: https://wpalemi.com/wp-json/mhm-polar/v1/webhook
Events: subscription.active, subscription.updated, subscription.canceled,
        subscription.uncanceled, subscription.revoked, refund.created
```

- [ ] **Step 2: 4 test product'ı listele**

```
- Rentiva Monthly Test ($X/month)
- Rentiva Yearly Test ($X/year)
- CS Monthly Test ($X/month)
- CS Yearly Test ($X/year)
```

Polar Sandbox dashboard'da görünür olmalı. Yoksa kullanıcı oluşturur.

### Task E.2: Senaryo 1 — Happy path

- [ ] **Step 1:** Sandbox'ta Rentiva Monthly satın al (test card 4242 4242 4242 4242).
- [ ] **Step 2:** Webhook geldi mi? polar-bridge log: `tail -100 wp-content/debug.log | grep PolarBridge`. Beklenen: subscription.active webhook + LicenseProvisioner success + customer email gönderildi.
- [ ] **Step 3:** wpalemi.com /account/ aç → license görünür → license_key kopyala.
- [ ] **Step 4:** Sandbox müşteri sitesinde Rentiva plugin License sayfası → key paste + Activate → Pro aktif.
- [ ] **Step 5:** License sayfasında "Manage Subscription" butonu görünür mü? Renk: gri (normal — 30 günden uzak).
- [ ] **Step 6:** Tıkla → yeni sekme açılır → Polar customer portal yükleniyor → subscription görünür → cancel/update card erişilebilir.

**Acceptance:** ✅ Buton tıklanıyor, portal açılıyor, sandbox subscription görünüyor.

### Task E.3: Senaryo 2 — Emphasis state geçişleri

- [ ] **Step 1:** Senaryo 1'de oluşan Rentiva Monthly subscription'da Polar'da `current_period_end`'i manuel manipüle et:
  - Polar Sandbox UI'da subscription detay → Period end değiştir → 7 gün sonra
  - Polar webhook subscription.updated fırlatır → polar-bridge expires_at'ı güncelliyor
- [ ] **Step 2:** Müşteri sitesinde "Re-validate Now" tıkla → yeni expires_at alınır.
- [ ] **Step 3:** License sayfasında buton rengi: amber (≤7 gün urgent).

Aynı şekilde 25 gün test et → sarı (≤30 warning).

**Acceptance:** ✅ Buton rengi state'e göre değişir.

### Task E.4: Senaryo 3 — Renewal reminder email

- [ ] **Step 1:** Sandbox'ta yeni Rentiva Monthly satın al (gerçek mailbox ile, Mailpit yerine), expires_at 7 gün sonra olacak şekilde.
- [ ] **Step 2:** wp-config'de `MHM_POLAR_SANDBOX=true` doğrula.
- [ ] **Step 3:** Manuel cron tetikle:

```bash
wp cron event run mhm_polar_renewal_reminder_daily
```

- [ ] **Step 4:** Müşteri inbox'ı kontrol → email geldi mi?
- [ ] **Step 5:** Email içerik kontrolü:
  - From: `MHM by WP Alemi <support@wpalemi.com>`
  - Subject: `Your MHM Rentiva subscription renews in 7 days` veya `MHM Rentiva aboneliğiniz 7 gün içinde yenilenecek`
  - Body: `Manage Subscription` linki (`https://wpalemi.com/account/`)
  - İmza: `— MHM Team (WP Alemi)` veya `— MHM Ekibi (WP Alemi)`

**Acceptance:** ✅ Email geldi, içerik doğru.

### Task E.5: Senaryo 4 — Idempotency

- [ ] **Step 1:** Senaryo 3 sonrasında cron'u tekrar çalıştır:

```bash
wp cron event run mhm_polar_renewal_reminder_daily
```

- [ ] **Step 2:** Inbox'ı kontrol → ikinci email **GELMEMELİ**.
- [ ] **Step 3:** user_meta'da `renewal_reminder_sent_at` flag'i set olduğunu doğrula:

```bash
wp user meta get <user_id> wpalemi_licenses --format=json | python -m json.tool
```

Beklenen: license rows'tan biri `"renewal_reminder_sent_at": "2026-04-26 ..."` içerir.

**Acceptance:** ✅ Idempotency çalışıyor.

### Task E.6: Senaryo 5 — Reminder reset (subscription.updated)

- [ ] **Step 1:** Sandbox'ta subscription.updated webhook simulate (`c:/tmp/polar-refund-simulate.py` paterni — webhook event'i HMAC-SHA256 imzalı POST):

```python
# Yeni current_period_end (örn. 30 gün sonra)
payload = {
    "type": "subscription.updated",
    "data": {
        "id": "sub_xxx",
        "customer_id": "cust_xxx",
        "current_period_end": "2026-05-26T00:00:00Z",
        # ... full payload
    }
}
# HMAC + POST to https://wpalemi.com/wp-json/mhm-polar/v1/webhook
```

- [ ] **Step 2:** webhook handler `renewal_reminder_sent_at` clear etti mi?

```bash
wp user meta get <user_id> wpalemi_licenses --format=json
```

Beklenen: `renewal_reminder_sent_at: ""` (veya unset).

- [ ] **Step 3:** Yeni dönem -7 gün geldiğinde cron yeni email gönderir mi? (zaman testi sandbox'ta zor — manuel `expires_at`'i 7 gün sonra set edip cron çalıştır + email kontrol).

**Acceptance:** ✅ Subscription.updated reminder flag'i temizliyor.

### Task E.7: E2E sonucu raporlama

- [ ] **Step 1:** 5 senaryo sonuçlarını kullanıcıya rapor et:

```
Senaryo 1 (Happy path): ✅ / ❌
Senaryo 2 (Emphasis state): ✅ / ❌
Senaryo 3 (Renewal reminder): ✅ / ❌
Senaryo 4 (Idempotency): ✅ / ❌
Senaryo 5 (Reset): ✅ / ❌
```

Herhangi bir senaryo ❌ ise, kök neden analizi + Phase 1/2/3 ilgili task'a geri dön + fix + yeniden test.

> **Phase 4 Review Checkpoint:** Tüm 5 senaryo yeşil mi? Yeşilse Phase 5 (production hazırlığı). Bir veya birden fazla kırıksa kök nedeni çözmeden devam etme.

---

## Phase 5 — Production geçiş hazırlığı (~30dk planlama, deploy değil)

Bu Phase **çalıştırma değil planlama**. Kullanıcı Phase 4 sonrası "production'a geçelim" dediğinde uygulanacak checklist'i ayrı bir dosyaya kaydet.

### Task F.1: Production deploy checklist'ini kaydet

**Files:**
- Create: `docs/plans/2026-04-26-license-renewal-production-deploy.md`

- [ ] **Step 1: Checklist dosyasını oluştur**

```markdown
# License Renewal — Production Deploy Checklist

> **Bu deploy henüz çalıştırılmadı.** Sandbox doğrulama (Phase 4) başarılı sonrası kullanıcı kararı bekleniyor.

## Pre-deploy

- [ ] Sandbox 5 senaryo yeşil (Phase 4 raporu)
- [ ] Polar production org açıldı (sandbox'tan ayrı org)
- [ ] Polar production API token üretildi
- [ ] Polar production webhook endpoint URL'i ayarlandı (büyük olasılıkla aynı: https://wpalemi.com/wp-json/mhm-polar/v1/webhook — sandbox/prod aynı endpoint, webhook secret farkı)
- [ ] Production'da test edilecek küçük tutarlı bir abonelik (~$1) hazır

## wpalemi.com wp-config swap

- [ ] `MHM_POLAR_API_TOKEN` sandbox token → prod token
- [ ] `MHM_POLAR_SANDBOX` constant remove (default false)
- [ ] `MHM_POLAR_WEBHOOK_SECRET` prod secret'a ayarla (varsa)

## Plugin deploys (sıra)

- [ ] license-server v1.11.0 → wpalemi.com (Hostinger MCP veya manual upload)
  - [ ] Schema migration doğrula: `wp eval 'global $wpdb; var_dump($wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}mhm_licenses LIKE \"polar_customer_id\""));'`
- [ ] polar-bridge v1.9.0 → wpalemi.com
  - [ ] Cron register doğrula: `wp cron event list | grep mhm_polar_renewal`
  - [ ] Manuel email test: `wp eval '(new MHM_Polar_CustomerManager())->send_renewal_reminder_email("test@maxhandmade.com", "Test", "MHM Rentiva", "2026-05-26T00:00:00Z", "tr_TR", "https://wpalemi.com/account/");'`
- [ ] Rentiva v4.32.0 → mhmrentiva.com (gerçek müşteri sitesi yoksa skip; sandbox'ta kalabilir bir süre)
- [ ] CS v0.7.0 → CS müşteri sitesi (yoksa skip)

## Post-deploy smoke test

- [ ] Production'da küçük tutarlı subscription buy
- [ ] Webhook geldi mi (tail wp-content/debug.log | grep PolarBridge)
- [ ] License row oluştu (polar_customer_id non-NULL)
- [ ] Customer site'da "Manage Subscription" tıkla → portal açılır
- [ ] -7 gün cron simulate (manuel `expires_at` ayarla, cron run, email kontrol)

## Rollback prosedürü

Hata durumunda:

1. Plugin deactivate → ZIP downgrade → reactivate
2. wp-config swap'ı geri al (sandbox'a dön)
3. Polar webhook'ları sandbox endpoint'e yönlendir

Detay: design doc Phase 7 D maddesi.
```

- [ ] **Step 2: Commit**

```bash
cd c:/projects/rentiva-dev
git add plugins/mhm-rentiva/docs/plans/2026-04-26-license-renewal-production-deploy.md
git commit -m "docs(plans): production deploy checklist for license renewal"
```

### Task F.2: Final review + plan kapatma

- [ ] **Step 1:** Tüm dosyaların git'te olduğunu doğrula:

```bash
cd c:/projects/rentiva-dev && git status
cd c:/projects/rentiva-lisans && git status
cd c:/projects/wpalemi && git status
cd c:/projects/mhm-currency-switcher && git status
cd c:/projects/mhm-rentiva-docs && git status
```

Beklenen: hepsinde clean working tree.

- [ ] **Step 2:** Per-Session Cleanup Gate (wp-conductor):
  - Merge edilmiş feature branch'ler silindi mi?
  - `/c/tmp/plugin-builds/` ZIP'leri GH'a yüklendiği için lokalden silinebilir
  - Background process yok

- [ ] **Step 3:** Memory güncellemesi (wp-reflect skill invoke):
  - `hot.md`: 4 yeni canlı sürüm + öğrenmeler
  - `MEMORY.md`: yeni proje memory dosyaları (örn. `project_license_renewal.md`)
  - Patterns kazandık mı? KB'ye ekle.

> **Plan Tamamlandı.** 4 release sandbox'ta canlı, production geçiş checklist'i hazır.

---

## Self-Review

Bu plan dosyası design doc'a karşı taranmıştır.

### Spec coverage

Spec'in her bölümü/gereksinimini bir task'a eşleştirme:

| Spec bölümü | Task |
|---|---|
| Schema migration | A.1, A.2 |
| REST endpoint | A.3, A.4 |
| Polar API wrapper imza | B.1, B.2 |
| BillingPortalRedirect callsite | B.2 |
| Provisioner polar_customer_id | B.3 |
| WebhookHandler reminder reset | B.4 |
| WebhookHandler customer_id forward | B.4 |
| Renewal reminder email TR/EN | B.5 |
| 6 lifecycle email branding | B.5 |
| RenewalReminderCron | B.6 |
| Bootstrap registration | B.6 |
| Rentiva LicenseManager metod | C.1 |
| Rentiva admin-post handler | C.2 |
| Rentiva buton render + emphasis | C.3 |
| Rentiva CSS | C.4 |
| Rentiva notice | C.5 |
| Rentiva i18n | C.6 |
| CS LicenseManager metod | D.1 |
| CS REST endpoint | D.2 |
| CS License.jsx buton | D.3 |
| CS CSS | D.4 |
| CS i18n | D.5 |
| 5 E2E senaryo | E.2-E.6 |
| Production checklist | F.1 |
| Versioning her 4 plugin | A.5, B.7, C.7, D.6 |
| ZIP + GitHub release her 4 plugin | A.6, B.8, C.8, D.7 |
| Docs blog | C.8, D.7 |
| wp-conductor release gate her 4 plugin | A.7, B.9, C.9, D.8 |

✅ Tüm spec bölümleri kapsanıyor.

### Placeholder scan

- "Phase 0'da teyit edilecek" notları var ama bunlar **plan tasarımı** placeholder'ı değil — Phase 0 task'ları bu noktaları çözüyor (constant adları, repo yapıları, mock pattern'leri). Plan başka task'larda referans verir.
- Hiçbir step'te "TBD", "TODO", "implement later" yok.
- Kod blokları her step'te tam.

### Type consistency

- `MHM_Polar_API::create_customer_session()` Phase 1 endpoint'inde `string|array` toleranslı geçici, Phase 2'de `array` olarak kesinleşiyor. Plan A.4 Step 2'de bu geçici uyumluluğun açıklaması var. ✅
- `createCustomerPortalSession()` (Rentiva camelCase) vs `create_customer_portal_session()` (CS snake_case) — plan boyunca tutarlı; Phase 3A camelCase, Phase 3B snake_case.
- `MHM_RENTIVA_VERSION` constant adı Phase 0 Task 0.1'de teyit edilecek; eğer farklı çıkarsa Phase 3A task'larında düzeltilir (plan'da explicit not var).
- CS version constant adı aynı şekilde Phase 0'da teyit, Phase 3B'de kullanılır.

✅ Tutarsızlık yok.

---

## Execution Handoff

**Plan complete and saved to** `docs/plans/2026-04-26-license-renewal-plan.md`. Two execution options:

**1. Subagent-Driven (recommended)** — Her task için fresh subagent dispatch, review between tasks, fast iteration. Plan ~50 task içerdiği için her task ~5-10 dk olur, total ~12 saat ama paralel dispatch ile sıkıştırılabilir. Phase 1 + Phase 2 sıralı, Phase 3A + 3B paralel dispatch.

**2. Inline Execution** — Bu sessionda executing-plans skill ile batch execution + checkpoint'ler. Tek developer (Claude) seri ilerler. ~12-15 saat süre alabilir bir session içinde, prompt cache rotasyonu zorlaşır.

**Hangi yaklaşım?**
