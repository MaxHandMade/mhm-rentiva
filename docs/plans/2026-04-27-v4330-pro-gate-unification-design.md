# v4.33.0 — Pro Gate Unification — Design

**Status:** Approved (2026-04-27)
**Target version:** mhm-rentiva v4.33.0 (minor, standalone release)
**Estimated effort:** 2–3 hours (mhm-rentiva) + ~45 min (mhm-license-server v1.11.2 patch)
**Branch:** `fix/pro-gate-unification` (henüz açılmadı)
**Predecessors:**
1. v4.32.0 (license renewal Phase 3, lokal HEAD; prod swap Phase 5 ile yapılacak)
2. **mhm-license-server v1.11.2** — `FeatureTokenIssuer::featuresFor()` mhm-rentiva için `'export' => true` ekler. Phase 5 sonrası ayrı atomik server-only release.

---

## 1. Motivation

v4.31.0 RSA asymmetric crypto migration sonrası **Mode::featureGranted()** tek otorite gate haline geldi. Ancak üç problem yarım kaldı:

1. **Eski `featureEnabled()` API'si 22 lokasyonda hâlâ kullanılıyor.** Bu metod token verification yapmaz — sadece `isPro()` + statik switch. Yani 22 callsite **cracked-binary + Mode patch** saldırısına hâlâ açık. v4.31.0'ın güvenlik kazanımı bu lokasyonlarda bypass ediliyor.
2. **LicenseAdmin sayfasında "All Pro features active" satırı statik metin** — gerçek gate'lere bakmadan yazılıyor. Token aktif olmasa bile lisans geçerliyse "tüm Pro özellikleri aktif" diyor → yanıltıcı UX. Müşteri "neden Vendor menüsü yok?" diye soruyor.
3. **Lokal'de Pro test imkânsız** — token RSA imzasıyla server'da üretiliyor. Geliştirme ortamında server yoksa token boş, gate fail-closed → vendor/messages/reports menüleri görünmüyor. Sarı banner "Tüm Pro özellikleri etkinleştirildi" diyor ama gate'ler false.

Bu üç problemi tek minor release'te kapatıyoruz.

---

## 2. Scope

### In scope (mhm-rentiva v4.33.0)

- **Bug C** — Mode.php'ye dev-mode bypass (token-check skip)
- **Bug B** — LicenseAdmin "Active features" listesini gerçek gate'lerden türet + sarı banner subtext fix
- **Bug A2** — 22 callsite `featureEnabled()` → `canUse*()` migrate, eski API'yi soft deprecate
- Yeni `canUseExport()` wrapper (Mode.php'de eksik)
- PHPUnit testleri (~14 yeni test)
- i18n: yeni admin string'leri .pot/.po/.mo

### In scope (mhm-license-server v1.11.2 — companion patch)

- `FeatureTokenIssuer::featuresFor()` mhm-rentiva product için `'export' => true` ekle
- 1 yeni PHPUnit test (`testFeaturesForRentivaIncludesExport`)
- Version bump 1.11.1 → 1.11.2, CHANGELOG entry, ZIP build, GH release, Hostinger MCP deploy
- *Sebep:* v4.33.0 client'ında `canUseExport()` yeni gate `featureGranted('export')` çağırıyor. Token allowlist'inde `'export'` olmazsa Pro kullanıcılar bile `xlsx`/`pdf` export erişimini kaybeder.

### Out of scope

- **D** — License-server license_row plan/customer_id backfill helper *(cross-repo, mhm-license-server + polar-bridge)*
- **E** — Renewal reminder email body redundant text *(polar-bridge)*
- **F** — License-server admin "Hazırlık Ortamı" kolonu heuristic *(server)*
- Yeni Pro feature eklenmesi (export dışında)
- featureEnabled() hard delete (v5.0'a kalır)
- License renewal Phase 5 prod swap *(ayrı seans, ayrı release)*

---

## 3. Architecture

### 3.1 Mevcut durumu (Mode.php gerçek hali — 2026-04-27)

```php
public static function featureEnabled( string $feature ): bool {
    if ( self::isPro() ) {
        return true;
    }
    switch ( $feature ) {
        case self::FEATURE_EXPORT:
            return true;  // Lite'a açık (intentional, ama gate'siz)
        case self::FEATURE_REPORTS_ADV:
        case self::FEATURE_MESSAGES:
            return false;
    }
    return false;
}

public static function canUseMessages(): bool        { return self::featureGranted('messaging'); }
public static function canUseAdvancedReports(): bool { return self::featureGranted('advanced_reports'); }
public static function canUseVendorMarketplace(): bool { return self::featureGranted('vendor_marketplace'); }
public static function canUseVendorPayout(): bool    { return self::featureGranted('vendor_marketplace'); }
// canUseExport() — YOK, eklenecek

private static function featureGranted( string $feature ): bool {
    if ( ! self::isPro() ) return false;
    $token = LicenseManager::instance()->getFeatureToken();
    if ( $token === '' ) return false;
    $verifier = new FeatureTokenVerifier();
    if ( ! $verifier->verify( $token, LicenseManager::instance()->getSiteHash() ) ) return false;
    return $verifier->hasFeature( $token, $feature );
}
```

### 3.2 Bug C — Dev Mode Bypass (Mode.php)

`featureGranted()`'a 4 satır eklenecek (isPro check'inden sonra, token check'inden önce):

```php
private static function featureGranted( string $feature ): bool {
    if ( ! self::isPro() ) {
        return false;  // Pro license gerekli — bu kapı kapalı kalır
    }

    // Dev-mode bypass — production'da WP_DEBUG=false olduğu için aktif değil.
    // wp-config.php'ye `define('MHM_RENTIVA_DEV_PRO', true);` eklenirse,
    // WP_DEBUG=true ortamında token check atlanır. Lokal Pro test için.
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG
         && defined( 'MHM_RENTIVA_DEV_PRO' ) && MHM_RENTIVA_DEV_PRO ) {
        return true;
    }

    $licenseManager = LicenseManager::instance();
    $token          = $licenseManager->getFeatureToken();
    if ( $token === '' ) {
        return false;
    }
    $verifier = new FeatureTokenVerifier();
    if ( ! $verifier->verify( $token, $licenseManager->getSiteHash() ) ) {
        return false;
    }
    return $verifier->hasFeature( $token, $feature );
}
```

**Çift guard:**
- `WP_DEBUG=true` (Hostinger production'da false default)
- `MHM_RENTIVA_DEV_PRO=true` (explicit opt-in constant)

Her iki guard birden true olmadan bypass aktifleşmez.

**Constant adı:** `MHM_RENTIVA_DEV_PRO` — mhm-currency-switcher'daki `MHM_CS_DEV_PRO` ile paralel.

### 3.3 Bug B — LicenseAdmin Dynamic Active Features (LicenseAdmin.php:268)

Mevcut tek satır (her zaman aynı metni basıyor):

```php
echo '<p>' . esc_html__('All Pro features active: Unlimited vehicles/bookings, export, advanced reports, Vendor & Payout.', 'mhm-rentiva') . '</p>';
```

Yeni implementasyon — gerçek gate'lerden türet:

```php
$active_features = [];
if ( Mode::canUseVendorMarketplace() )  { $active_features[] = __( 'Vendor & Payout', 'mhm-rentiva' ); }
if ( Mode::canUseAdvancedReports() )    { $active_features[] = __( 'Advanced Reports', 'mhm-rentiva' ); }
if ( Mode::canUseMessages() )           { $active_features[] = __( 'Messages', 'mhm-rentiva' ); }
if ( Mode::canUseExport() )             { $active_features[] = __( 'Expanded Export', 'mhm-rentiva' ); }

if ( ! empty( $active_features ) ) {
    /* translators: %s — comma-separated list of active Pro features */
    printf(
        '<p>%s</p>',
        esc_html( sprintf(
            __( 'Active Pro features: %s', 'mhm-rentiva' ),
            implode( ', ', $active_features )
        ) )
    );
} else {
    echo '<div class="notice notice-warning inline"><p>'
       . esc_html__( 'License active but no feature tokens loaded yet. Click "Re-validate Now" to refresh.', 'mhm-rentiva' )
       . '</p></div>';
}
```

**Davranış:**
- Token doluysa: "Active Pro features: Vendor & Payout, Messages, Advanced Reports, Expanded Export"
- Token boşsa: warning notice "Re-validate Now" CTA ile

### 3.4 Bug B alt — Sarı "Geliştirici Modu" Banner Subtext (LicenseAdmin.php)

`$is_dev_mode` true olduğunda gösterilen mevcut sarı banner — "Tüm Pro özellikleri etkinleştirildi" diyor ama gate'ler false dönüyor (token yok). Yeni metin koşullu:

```php
if ( $is_dev_mode ) {
    echo '<div class="notice notice-warning"><p>';
    if ( defined( 'MHM_RENTIVA_DEV_PRO' ) && MHM_RENTIVA_DEV_PRO ) {
        echo '<strong>' . esc_html__( '🔧 Geliştirici Modu Etkin', 'mhm-rentiva' ) . '</strong> &mdash; ';
        echo esc_html__( 'Pro feature\'lar test edilebilir (token check atlanıyor).', 'mhm-rentiva' );
    } else {
        echo '<strong>' . esc_html__( '🔧 Geliştirici Modu', 'mhm-rentiva' ) . '</strong> &mdash; ';
        echo esc_html__( 'Pro feature\'lar test edilemez. wp-config.php\'ye `define(\'MHM_RENTIVA_DEV_PRO\', true);` ekleyin.', 'mhm-rentiva' );
    }
    echo '</p></div>';
}
```

### 3.5 Bug A2 — Gate Vocabulary Unification

#### 3.5.1 Mode.php'ye `canUseExport()` ekle

```php
/**
 * Check if expanded export formats (XLSX, PDF) module can be used.
 *
 * Lite users have CSV/JSON access via direct format check in callers;
 * this gate covers Pro-only formats.
 */
public static function canUseExport(): bool {
    return self::featureGranted( 'export' );
}
```

**Sembolik feature key:** `'export'` — mhm-license-server **v1.11.2** companion patch'iyle `FeatureTokenIssuer::featuresFor()` allowlist'ine eklenir (bkz. §2 "In scope mhm-license-server v1.11.2"). v1.11.2 deploy edilmeden v4.33.0 release edilirse Pro kullanıcılar Export'u kaybeder — bu yüzden release sıralaması zorunlu: **v1.11.2 server deploy → mhm-rentiva v4.33.0 release**.

#### 3.5.2 22 callsite migration map

| # | Dosya | Satır | Mevcut çağrı | Yeni çağrı |
|---|-------|-------|--------------|------------|
| 1 | AccountRenderer.php | 248 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 2 | WooCommerceIntegration.php | 87 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 3 | WooCommerceIntegration.php | 121 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 4 | Messages/Core/Messages.php | 745 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 5 | Messages/Frontend/CustomerMessages.php | 95 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 6 | Messages/REST/Messages.php | 31 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 7 | Messages/REST/Customer/SendMessage.php | 29 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 8 | Messages/REST/Customer/SendReply.php | 32 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 9 | Reports/Reports.php | 226 | `featureEnabled(FEATURE_REPORTS_ADV)` | `canUseAdvancedReports()` |
| 10 | Reports/Reports.php | 393 | `featureEnabled(FEATURE_REPORTS_ADV)` | `canUseAdvancedReports()` |
| 11 | Export/Export.php | 97 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |
| 12 | Export/Export.php | 338 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |
| 13 | Export/Export.php | 1302 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |
| 14 | Export/ExportReports.php | 26 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |
| 15 | Export/ExportReports.php | 103 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |
| 16 | Export/ExportReports.php | 142 | `featureEnabled(FEATURE_REPORTS_ADV)` | `canUseAdvancedReports()` |
| 17 | Export/ExportReports.php | 244 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |
| 18 | Export/ExportReports.php | 249 | `featureEnabled(FEATURE_REPORTS_ADV)` | `canUseAdvancedReports()` |
| 19 | Export/ExportReports.php | 274 | `featureEnabled(FEATURE_REPORTS_ADV)` | `canUseAdvancedReports()` |
| 20 | Menu.php | 121 | `featureEnabled(FEATURE_REPORTS_ADV)` | `canUseAdvancedReports()` |
| 21 | Menu.php | 133 | `featureEnabled(FEATURE_MESSAGES)` | `canUseMessages()` |
| 22 | Menu.php | 145 | `featureEnabled(FEATURE_EXPORT)` | `canUseExport()` |

**Tek atomik commit** — mekanik replace, conflict riski yok.

#### 3.5.3 `featureEnabled()` Soft Deprecation

```php
/**
 * @deprecated 4.33.0 — Use Mode::canUse{FeatureName}() instead. This method
 * lacks RSA token verification, so source-edit attacks can bypass it.
 * Will be removed in v5.0.
 *
 * @param string $feature Feature name
 * @return bool True if enabled
 */
public static function featureEnabled( string $feature ): bool {
    _deprecated_function( __METHOD__, '4.33.0', 'Mode::canUse{FeatureName}()' );

    // Body kept identical to v4.32.0 behavior to preserve back-compat for any
    // third-party code (rare for closed plugin). Removal in v5.0.
    if ( self::isPro() ) {
        return true;
    }
    switch ( $feature ) {
        case self::FEATURE_EXPORT:
            return true;
        case self::FEATURE_REPORTS_ADV:
        case self::FEATURE_MESSAGES:
            return false;
    }
    return false;
}
```

`_deprecated_function()` WP_DEBUG=true durumunda warning fırlatır; production sessiz.

---

## 4. Behavior Change Notice (KRİTİK)

A2 migration yapılınca **Lite kullanıcılar için davranış değişir**:

| Senaryo | Eski (v4.32.0) | Yeni (v4.33.0) |
|---------|----------------|-----------------|
| Lite + `xlsx` export isteği | ✅ İzin (featureEnabled bypass) | ❌ Reddedilir (canUseExport false) |
| Lite + `csv`/`json` export | ✅ İzin (format check) | ✅ İzin (format check değişmedi) |
| Lite + Messages | ❌ Zaten kapalı | ❌ Zaten kapalı |
| Lite + Advanced Reports | ❌ Zaten kapalı | ❌ Zaten kapalı |
| Pro + token doğru | ✅ Aktif | ✅ Aktif (token verify) |
| Pro + token boş (legacy server) | ✅ Aktif (cracked binary açık) | ❌ Reddedilir (fail closed) |
| Pro + cracked binary `Mode::isActive()` patch | ✅ Aktif (saldırı başarılı) | ❌ Reddedilir (token zorunlu) |
| Lokal dev + `MHM_RENTIVA_DEV_PRO` true + WP_DEBUG | ❌ Token yok, fail closed | ✅ Bypass aktif |

**En önemli kazançlar:**
1. Cracked-binary + Mode patch saldırı yüzeyi 22 lokasyonda kapanır.
2. Lokal dev'de Pro feature'lar test edilebilir.
3. UX yanıltıcılığı düzelir (sarı banner gerçeği söyler).

**Kabul edilen kayıp:** Lite kullanıcılar (varsa) `xlsx` formatına erişimi kaybeder. Pratikte bu zaten beklenen Pro davranışı; mevcut hal bug'dı.

---

## 5. Testing

### 5.1 Yeni test dosyaları

#### `tests/Licensing/ModeDevBypassTest.php` (5 test)

- `test_bypass_active_when_constant_and_wp_debug_true()` — token boş, `MHM_RENTIVA_DEV_PRO=true`, `WP_DEBUG=true` → `canUseMessages()` true
- `test_bypass_inactive_when_wp_debug_false()` — constant true, WP_DEBUG=false → false
- `test_bypass_inactive_when_constant_false()` — constant false, WP_DEBUG=true → false
- `test_bypass_inactive_when_constant_undefined()` — constant define edilmemiş → false
- `test_bypass_requires_isPro()` — license aktif değilse, constant+WP_DEBUG true olsa da → false (defense-in-depth)

#### `tests/Licensing/LicenseAdminActiveFeaturesTest.php` (4 test)

- `test_displays_only_granted_features()` — sadece `canUseMessages()` true → output sadece "Messages" içerir
- `test_displays_all_features_when_all_granted()` — 4 gate true → "Vendor & Payout, Advanced Reports, Messages, Expanded Export" sırasıyla
- `test_shows_warning_when_no_features_granted()` — license active ama token yok → warning notice gösterilir
- `test_does_not_render_misleading_text_when_partially_granted()` — sadece Vendor true → "All Pro features active" string'i çıktıda olmamalı

#### `tests/Licensing/ModeGateMigrationTest.php` (6 test)

- `test_canUseExport_delegates_to_featureGranted()` — `canUseExport()` `featureGranted('export')` çağırır
- `test_canUseMessages_returns_token_grant()` — token'da messaging feature varsa true
- `test_canUseAdvancedReports_returns_token_grant()` — aynı
- `test_canUseExport_returns_token_grant()` — aynı
- `test_featureEnabled_emits_deprecation_warning()` — `_deprecated_function` notice fırlatır (WP_DEBUG=true)
- `test_featureEnabled_preserves_legacy_behavior()` — body değişmedi, eski callsite'lar aynı sonucu alır (back-compat)

### 5.2 Baseline

- Şu anki: 793 test / 2726 assertion
- v4.33.0 sonrası: ~808 test / ~2755 assertion (5 + 4 + 6 = 15 yeni test)

### 5.3 Pre-push CI parity gate (zorunlu)

[feedback_pre_push_ci_parity.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_pre_push_ci_parity.md) disiplinine göre push'tan önce dört yönlü kontrol:

```bash
composer phpcs                       # 0 error / 0 warning
composer phpstan -- --memory-limit=2G # 0 error
docker exec rentiva-dev-wp php -d memory_limit=512M /var/www/html/wp-content/plugins/mhm-rentiva/vendor/bin/phpunit  # PHP 7.4 baseline
docker exec rentiva-dev-wp-php82 ... phpunit  # PHP 8.2 alternate
```

Hepsi yeşil olmadan push yok.

---

## 6. Release Sequence

```
1. Branch:            git checkout -b fix/pro-gate-unification main
2. Bug C (Mode.php):
   - 4 test dosyası RED
   - featureGranted() bypass ekle
   - GREEN
   - Commit
3. Bug B (LicenseAdmin.php):
   - 4 test dosyası RED
   - Active features dinamik + sarı banner subtext
   - GREEN
   - Commit
4. Bug A2 (22 callsite + featureEnabled deprecate):
   - canUseExport() Mode.php'ye ekle
   - 22 callsite mekanik migrate (find/replace)
   - featureEnabled() body'sine _deprecated_function + @deprecated tag
   - 6 test
   - GREEN
   - Commit
5. CI parity 4-yönlü doğrulama
6. i18n: pot regen, msgmerge (fuzzy check!), .mo + .l10n.php compile
7. Version bump 4.32.0 → 4.33.0:
   - mhm-rentiva.php "Version:" + MHM_RENTIVA_VERSION constant
   - readme.txt Stable tag
   - README.md / README-tr.md badge
8. Changelog:
   - changelog-tr.json
   - changelog.json
   - readme.txt Changelog section
9. Docs blog: mhm-rentiva-docs/blog/2026-04-XX-v4.33.0-release.md (TR + EN)
10. Docker ZIP build: python bin/build-release.py
11. Tag annotated: git tag -a v4.33.0 -m "Pro Gate Unification"
12. Push tag + branch
13. GitHub release with ZIP asset (English release notes)
14. Merge to main (--no-ff, ya da fast-forward seçim)
```

---

## 7. Release Chain — Phase 5 → Server v1.11.2 → mhm-rentiva v4.33.0

**Üç ayrı release, üç ayrı seans, sıralı.**

### Aşama 1 — Phase 5 (license renewal prod swap, mevcut plan)
- mhmrentiva.com canlıda **v4.30.2** + server **v1.9.3** (eski).
- Atomik 4-repo deploy: server **v1.11.1** + bridge **v1.9.1** + Rentiva **v4.32.0** + CS **v0.7.0**.
- Detay: [2026-04-27-license-renewal-production-deploy.md](2026-04-27-license-renewal-production-deploy.md)

### Aşama 2 — mhm-license-server v1.11.2 (yeni, companion patch)
- Phase 5 sonrası standalone server-only release.
- Tek değişiklik: `FeatureTokenIssuer::featuresFor()` mhm-rentiva için `'export' => true`.
- 1 PHPUnit test, version bump, ZIP build, Hostinger MCP deploy.
- ~45 dakika. Yeni token issue cycle'ı 24h içinde tüm aktif clients'a yayılır (daily cron).

### Aşama 3 — mhm-rentiva v4.33.0 (bu spec)
- v1.11.2 deploy doğrulandıktan sonra başlar (smoke test: Pro user'a yeni token verildiğinde `'export'` claim taşıyor mu).
- Lokal'de v4.32.0 üzerine bina edilir.
- Detay: §6 Release Sequence + writing-plans çıktısı.

**Gerekçe ayrı release tutmak:**
- v4.33.0, v4.32.0 üzerine bina edilecek; prod'a v4.32.0 koymadan v4.33.0 deploy yapılırsa license renewal regression riski v4.33.0 ile karışır.
- Server v1.11.2 mhm-rentiva client'ından önce gitmeli (yoksa Pro kullanıcılar geçici Export erişimini kaybeder).
- Üçü atomik bundle yaparsak rollback granülaritesi kaybolur.

---

## 8. Risks & Mitigations

| Risk | Etki | Azaltma |
|------|------|---------|
| `MHM_RENTIVA_DEV_PRO` constant yanlışlıkla production'da define edilir | Pro açılır (saldırı yüzeyi) | Çift guard: `WP_DEBUG=true` da gerek. Hostinger production'da WP_DEBUG default false. + LicenseAdmin sarı banner kullanıcıyı bilgilendirir. |
| 22 callsite'tan biri kaçar (Grep eksik) | Eski API kullanılır → güvenlik açığı kalır | `_deprecated_function()` warning WP_DEBUG'da loglanır. PHPUnit suite featureEnabled deprecation test'i ile yakalar. Ek olarak release sonrası Grep kontrolü. |
| Server v1.11.2 deploy edilmeden v4.33.0 release edilir | Pro kullanıcılar Export'u kaybeder (24h boyunca, daily cron'da yeni token gelene kadar süresiz) | Release chain disiplini (§7): v1.11.2 server smoke test edildikten sonra v4.33.0 implementation başlar. Pre-release checklist'te server token allowlist Doğrulandı maddesi var. |
| Server v1.11.2 token cache'leme — eski token'lar `'export'` taşımaz | Pro kullanıcı 24h Export'u görmez | Daily cron yeni token alır; manuel "Re-validate Now" butonu LicenseAdmin'de mevcut. Release notes'ta "lisansınızı yeniden doğrulayın" notu. |
| Lite kullanıcı `xlsx` erişimi kaybeder | Davranış değişikliği | Changelog'da BREAKING CHANGE notu, blog post'ta açıklama. Pratikte bu zaten doğru davranış. |
| `featureEnabled()` deprecation 3rd-party koda kırar | Sessiz fail | Body korunur (back-compat); sadece `_deprecated_function()` notice. Hard delete v5.0'a kalır. |

---

## 9. i18n Strings (yeni)

EN + TR mandatory:

| Key | EN | TR |
|-----|----|----|
| Active features label | Active Pro features: %s | Aktif Pro özellikler: %s |
| Vendor & Payout | Vendor & Payout | Bayi & Ödeme |
| Advanced Reports | Advanced Reports | Gelişmiş Raporlar |
| Messages | Messages | Mesajlar |
| Expanded Export | Expanded Export | Genişletilmiş Dışa Aktarım |
| No features warning | License active but no feature tokens loaded yet. Click "Re-validate Now" to refresh. | Lisans etkin ancak özellik token'ları henüz yüklenmedi. Yenilemek için "Şimdi Yeniden Doğrula"ya tıklayın. |
| Dev mode active | 🔧 Geliştirici Modu Etkin | (zaten TR — yeni değil) |
| Dev mode test edilebilir | Pro feature'lar test edilebilir (token check atlanıyor). | (TR yeni) |
| Dev mode test edilemez | Pro feature'lar test edilemez. wp-config.php'ye `define('MHM_RENTIVA_DEV_PRO', true);` ekleyin. | (TR yeni) |

**msgmerge fuzzy check zorunlu:** [feedback_l10n_php.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_l10n_php.md) — yeni string ekledikten sonra `grep -c '#, fuzzy' languages/mhm-rentiva-tr_TR.po` = 0 olmalı.

---

## 10. References

- Memory: [project_pro_gate_unification_v4.33.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/project_pro_gate_unification_v4.33.md)
- v4.31.0 RSA crypto migration: [docs/plans/2026-04-25-asymmetric-crypto-license-design.md](2026-04-25-asymmetric-crypto-license-design.md)
- License renewal Phase 5: [docs/plans/2026-04-27-license-renewal-production-deploy.md](2026-04-27-license-renewal-production-deploy.md)
- Pre-push CI parity discipline: [feedback_pre_push_ci_parity.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_pre_push_ci_parity.md)
- WP `_deprecated_function()` API: https://developer.wordpress.org/reference/functions/_deprecated_function/

---

**Bu spec onaylandı 2026-04-27. Bir sonraki adım: writing-plans skill'i ile implementation plan oluştur.**
