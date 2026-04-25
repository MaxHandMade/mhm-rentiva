# Asymmetric Crypto License Refactor — Implementation Spec

**Tarih:** 2026-04-25
**Durum:** SPEC — review bekliyor, kod başlamadı
**Tahmini efor:** 4-6 saat (buffer ile 6-8h)
**Hedef releaseler:**
- `mhm-license-server` v1.9.3 → **v1.10.0**
- `mhm-rentiva` v4.30.2 → **v4.31.0**
- `mhm-currency-switcher` v0.5.2 → **v0.6.0**

---

## 1. Motivasyon (Neden Şimdi?)

### v4.30.x Sonrası Kapatılamayan Saldırı

v4.30.0/v1.9.0 Phase A/B/C/D ile HMAC-imzalı response + reverse site validation + HMAC feature token + per-product slug binding canlıya alındı. Ancak **bir saldırı senaryosu açık kaldı:**

Saldırgan:
1. Polar üzerinden kendi adına **gerçek** Rentiva Pro lisansı satın alır (cracked binary değil, real key)
2. Cracked Rentiva binary'i kendi sitesine yükler — `LicenseManager::isActive()` veya `Mode::canUse*()` metodlarını patch'ler
3. Server activate başarılı (legitimate license + reverse ping geçer çünkü kendi sitesi)
4. Pro features açılır (gate metodları `return true;` yapılmış)

**Neden v4.30.x bunu kapatamadı:** HMAC-tabanlı feature token verify yapmak için client'ın HMAC secret'ı bilmesi gerek. "Müşteri-vari deploy" hedefi için (wp-config'e secret koymama), `FEATURE_TOKEN_KEY` müşteride tanımsız → `Mode::featureGranted()` legacy `isPro()` fallback'ine düşüyor → source-edit'e karşı korumasız.

### Tasarım Paradoksu

> Müşteri-vari (zero-config) **+** cracked-binary protection **HMAC ile aynı anda mümkün değil** — çünkü HMAC iki yönlü gizli paylaşım gerektirir, müşteri secret koymak istemiyor.

### Çözüm: Asymmetric (Public Key) Crypto

- Server **RSA private key** tutar (sadece server'da, wp-config'de PEM constant)
- Plugin source'una **RSA public key gömülür** (heredoc class constant)
- Server feature token'ı RSA-sign eder → client `openssl_verify(token, signature, public_key)` ile doğrular
- Saldırgan public key'le yeni token üretemez (private key gerekir)
- Müşteri wp-config'e hiçbir şey koymaz → zero-config deploy korunur

### Hedef
Cracked-binary + product-matching license + Mode patch saldırısını da kapatmak. Mevcut müşteri yok (Polar sandbox), bu sebeple acil operasyonel risk değil — ama global launch öncesi devrede olmalı.

---

## 2. Mevcut Durum (Starting Point)

### License Server (`mhm-license-server` v1.9.3 — wpalemi.com'da CANLIDA)
- HMAC-imzalı response (`Security/ResponseSigner`) ✅
- Reverse site validation (`Security/SiteVerifier`) ✅
- **HMAC feature token** (`License/FeatureTokenIssuer`) — `{base64_payload}.{hmac_hex}` format ✅
- 3 wp-config secret: `MHM_LICENSE_SERVER_RESPONSE_HMAC_SECRET`, `MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY`, `MHM_LICENSE_SERVER_PING_SECRET`
- File fallback (`wp-uploads/mhm-license-secrets/`) RESPONSE_HMAC + FEATURE_TOKEN için aktif, PING_SECRET için kaldırılmış (v1.9.2)
- 143/143 PHPUnit, 0 PHPCS

### Rentiva Client (`mhm-rentiva` v4.30.2 — mhmrentiva.com'da CANLIDA)
- `Admin/Licensing/ClientSecrets` — 3 wp-config define resolver
- `Admin/Licensing/ResponseVerifier` — HMAC response verify
- `Admin/Licensing/FeatureTokenVerifier` — HMAC token verify, `hasFeature()` accessor
- `Admin/Licensing/VerifyEndpoint` — REST `/wp-json/mhm-rentiva-verify/v1/ping`, site_hash fallback (v4.30.1)
- `LicenseManager::request()` response sig verify, `activate()` body'sine `client_version`, feature_token store
- `Mode::canUse*()` 4 gate `featureGranted()` private helper üzerinden token check
- **Backward-compat fallback:** `FEATURE_TOKEN_KEY` tanımsız → legacy `isPro()` davranışı (mevcut müşteriler kırılmıyor — bu da source-edit attack'a kapı)
- 781/2726 PHPUnit, 0 PHPCS

### Currency Switcher Client (`mhm-currency-switcher` v0.5.2 — GH release yayında, canlı CS site yok)
- Rentiva pattern'inin kopyası: `License/ClientSecrets`, `License/ResponseVerifier`, `License/FeatureTokenVerifier`, `License/VerifyEndpoint`
- 6 gate (`can_use_geolocation/fixed_prices/payment_restrictions/auto_rate_update/multilingual/rest_api_filter`) `feature_granted()` token check
- Aynı backward-compat legacy `is_pro()` fallback
- 137/137 PHPUnit

---

## 3. Saldırı Modeli

| Senaryo | v4.30.x Durum | v4.31.0 / v1.10.0 Sonrası |
|---|---|---|
| `Mode::canUse*() { return true; }` patch | ✗ Bypass (legacy fallback) | ✓ Bloke (token RSA verify gerekli) |
| `LicenseManager::isActive() { return true; }` patch | ✗ Bypass (legacy fallback) | ✓ Bloke (token yok) |
| `LicenseManager::getFeatureToken() { return $valid_token; }` patch | n/a | ✓ Bloke (RSA sig kontrol — token forge edilemez) |
| Cracked binary + own legitimate license + Mode patches | ✗ Bypass (legacy fallback) | ✓ Bloke (Mode helper RSA verify, fallback kaldırıldı) |
| Pirated binary distribution + nulled-sites | ✗ Bypass 24h+ | ✓ Token TTL 24h, refresh için server contact + site_hash match şart |
| Network MITM token tamper | ✓ Bloke (HMAC sig) | ✓ Bloke (RSA sig) |
| Reverse engineer public key + try forge | n/a | ✓ Bloke (private key gerekli — server'da, RSA-2048 forge pratikte imkansız) |

**v4.30.x'te kapatılan saldırılar v4.31.0'da da kapalı kalmaya devam ediyor** — Phase A/B/C savunmaları korunur, sadece feature token mekanizması RSA'ya geçer.

---

## 4. Mimari — RSA Asymmetric Crypto

### 4.1 Genel Akış

```
[wpalemi.com]                                    [mhmrentiva.com / CS site]
mhm-license-server v1.10.0                       Rentiva v4.31.0 / CS v0.6.0
─────────────────────────                        ─────────────────────────────
RSA Private Key (wp-config)                      RSA Public Key (heredoc const)
        ↓                                                    ↓
LicenseValidator::validate()                     LicenseManager::validate()
        ↓                                                    ↓
FeatureTokenIssuer::issue()                              ↑
   - canonical_payload = ksort + json_encode               ↑
   - signature = openssl_sign(canonical, PRIVATE)          ↑
   - token = base64url(canonical) + "." + base64url(sig)   ↑
        ↓                                                  ↑
   response['feature_token'] = token  ──── HTTPS ────→ FeatureTokenVerifier::verify()
                                                            - canonical = base64url_decode(payload)
                                                            - sig = base64url_decode(sig)
                                                            - openssl_verify(canonical, sig, PUBLIC)
                                                            - validate site_hash + expires_at
                                                            ↓
                                                       Mode::canUse*() gate
```

### 4.2 Token Format

Mevcut HMAC pattern korunur, sadece imza algoritması RSA olur:

```
{base64url(canonical_payload)}.{base64url(rsa_sig_binary)}
```

**base64url** (RFC 4648 §5): standart base64'ten farklı olarak `+` → `-`, `/` → `_`, padding `=` strip edilir. URL/header safe.

### 4.3 Token Payload (değişmez — mevcut HMAC payload aynısı)

```php
[
    'license_key_hash' => sha256($license_key),
    'product_slug'     => 'mhm-rentiva' | 'mhm-currency-switcher',
    'plan'             => 'pro' | 'free',
    'features'         => [
        // Rentiva pro: vendor_marketplace, advanced_reports, messaging,
        //              full_rest_api, gdpr_tools, custom_emails
        // CS pro:      fixed_pricing, nav_menu_switcher, flag_library,
        //              geolocation, payment_restrictions, auto_rate_update,
        //              multilingual, rest_api_filter
    ],
    'site_hash'        => $request_site_hash,
    'issued_at'        => time(),
    'expires_at'       => time() + 86400, // 24h TTL
]
```

### 4.4 Canonical Signing Form

Server VE client AYNI canonical form üretmeli — yoksa imza patlar (mevcut HMAC pattern'inde de bu kritik):

```php
// Server side (FeatureTokenIssuer::canonicalize)
private function canonicalize(array $payload): string {
    $sorted = $this->recursiveKsort($payload);
    return wp_json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

private function recursiveKsort(array $arr): array {
    ksort($arr);
    foreach ($arr as $key => $value) {
        if (is_array($value)) {
            $arr[$key] = $this->recursiveKsort($value);
        }
    }
    return $arr;
}
```

Client tarafı (FeatureTokenVerifier) base64url_decode ile zaten canonical string'i geri alır — kendi canonicalize çağırmaz, sadece imzayı verify eder. Yani recursive ksort sadece **server tarafında** kritik.

### 4.5 Server-Side (mhm-license-server v1.10.0)

#### Yeni / Modifiye Sınıflar

**`src/Security/RsaSigner.php`** (YENİ)
```php
namespace MHM\LicenseServer\Security;

class RsaSigner {
    public function sign(string $canonical_payload): string {
        $private_key = $this->loadPrivateKey();
        if (!openssl_sign($canonical_payload, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('RSA signing failed');
        }
        return $signature; // binary, base64url-encode in caller
    }

    private function loadPrivateKey(): \OpenSSLAsymmetricKey {
        static $key = null;
        if ($key === null) {
            $pem = SecretManager::getRsaPrivateKey(); // wp-config constant only
            if ($pem === '') {
                throw new \RuntimeException('RSA private key not configured');
            }
            $key = openssl_pkey_get_private($pem);
            if ($key === false) {
                throw new \RuntimeException('RSA private key invalid PEM');
            }
        }
        return $key;
    }
}
```

**`src/Security/SecretManager.php`** (MODIFIYE)
```php
public static function getRsaPrivateKey(): string {
    if (defined('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM')) {
        return (string) MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM;
    }
    return ''; // NO file fallback — auto-gen wouldn't match plugin public key
}
```

**`src/License/FeatureTokenIssuer.php`** (MODIFIYE)
- HMAC sign branch kaldırılır
- `RsaSigner` inject edilir
- Token format yine `{base64url(payload)}.{base64url(sig)}` ama sig artık RSA binary
- Payload yapısı **değişmez** (4.3)
- Server private key tanımsızsa: `feature_token` field response'tan ÇIKARILIR + admin notice + error_log

**`src/License/LicenseValidator.php`** (MODIFIYE)
- `signResult()` helper feature_token üretirken RsaSigner kullanır
- Private key yoksa try-catch → `feature_token` yok, response signature (RESPONSE_HMAC) hâlâ var

**`src/License/LicenseActivator.php`** (MODIFIYE)
- LicenseValidator ile aynı pattern — feature_token RSA olur

#### Kaldırılan / Deprecated

- `License/FeatureTokenIssuer` HMAC sign metodu kaldırılır (artık RSA)
- `MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY` constant — **deprecated**, code'da artık okunmuyor; deploy guide'da "silinebilir" notu
- `wp-uploads/mhm-license-secrets/feature_token.key` file fallback — kaldırılır (artık kullanılmıyor)

### 4.6 Client-Side (Rentiva v4.31.0 / CS v0.6.0)

#### Yeni / Modifiye Sınıflar

**Rentiva: `src/Admin/Licensing/LicenseServerPublicKey.php`** (YENİ)
**CS: `src/License/LicenseServerPublicKey.php`** (YENİ)

```php
final class LicenseServerPublicKey {
    private const PEM = <<<PEM
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
{HER İKİ PLUGIN'DE AYNI public key — server'ın private key eşleniği}
-----END PUBLIC KEY-----
PEM;

    public static function resource(): \OpenSSLAsymmetricKey {
        static $key = null;
        if ($key === null) {
            $key = openssl_pkey_get_public(self::PEM);
            if ($key === false) {
                throw new \RuntimeException('Embedded public key parse failed');
            }
        }
        return $key;
    }
}
```

**Rentiva: `src/Admin/Licensing/FeatureTokenVerifier.php`** (MODIFIYE — HMAC → RSA)
**CS: `src/License/FeatureTokenVerifier.php`** (MODIFIYE — HMAC → RSA)

```php
public function verify(string $token, string $expected_site_hash): bool {
    $parts = explode('.', $token);
    if (count($parts) !== 2) return false;

    $canonical = $this->base64UrlDecode($parts[0]);
    $signature = $this->base64UrlDecode($parts[1]);
    if ($canonical === false || $signature === false) return false;

    // RSA verify (replaces HMAC verify)
    $public_key = LicenseServerPublicKey::resource();
    $verified = openssl_verify($canonical, $signature, $public_key, OPENSSL_ALGO_SHA256);
    if ($verified !== 1) return false;

    $payload = json_decode($canonical, true);
    if (!is_array($payload)) return false;

    // site_hash binding + expires_at — mevcut mantık aynı
    if (($payload['site_hash'] ?? '') !== $expected_site_hash) return false;
    if (($payload['expires_at'] ?? 0) < time()) return false;

    return true;
}

public function hasFeature(string $token, string $feature_name): bool {
    // payload decode + features array check — değişmez
}
```

#### Kaldırılan / Deprecated

- `Admin/Licensing/ClientSecrets::getFeatureTokenKey()` — RSA için secret yok, public key gömülü; ama metod kalıyor (Response HMAC + Ping secret hâlâ aktif). Sadece feature token branch'i kaldırılır.
- `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` constant — **deprecated**, code'da artık okunmuyor; v4.31.0 release notes "wp-config'den silebilirsiniz" notu
- `MHM_CS_LICENSE_FEATURE_TOKEN_KEY` constant — aynı
- `Mode::featureGranted()` legacy `isPro()` fallback — **KALDIRILIR**. Token RSA verify tek truth source (önemli enforcement değişikliği)

### 4.7 Mode Gate Davranışı (BREAKING)

**v4.30.x:**
```php
private function featureGranted(string $name): bool {
    if (!$this->licenseManager->isActive()) return false;

    $key = ClientSecrets::getFeatureTokenKey();
    if ($key === '') {
        return $this->licenseManager->isPro(); // LEGACY FALLBACK
    }
    // HMAC verify...
}
```

**v4.31.0:**
```php
private function featureGranted(string $name): bool {
    if (!$this->licenseManager->isActive()) return false;

    $token = $this->licenseManager->getFeatureToken();
    if ($token === '') return false; // NO FALLBACK

    $verifier = new FeatureTokenVerifier();
    if (!$verifier->verify($token, $this->licenseManager->getSiteHash())) return false;

    return $verifier->hasFeature($token, $name);
}
```

**Bu kasıtlı bir breaking change.** Müşterinin Pro features alabilmesi için server'dan geçerli RSA-imzalı token alması ŞART. Server private key yoksa veya outage ise → grace period devreye girer (4.8).

#### Defense-in-Depth Sınırı (Bilinmesi Gereken)

`featureGranted()` private helper **tek bir patch noktası**: saldırgan `Mode::featureGranted() { return true; }` yaparsa 4 (Rentiva) / 6 (CS) gate'in tamamı düşer. RSA crypto'nun **çözdüğü problem token forge**, çözmediği problem **single-method-patch**.

Bunun bilinçli kabul gerekçesi:
1. DRY önemli — her gate için verify mantığı kod tekrarı (~15 satır × 6 gate = 90 satır)
2. RSA'nın asıl katkısı: saldırgan kendi token'ını üretemez. Mevcut HMAC mimarisi'nde de saldırgan secret'ı bulursa kendi token'ı üretebiliyordu (server emulation senaryosu). RSA bunu kapatır.
3. Single-method-patch bypass için saldırgan plugin'i source'tan derlemiş olmalı → cracked binary distribution → DMCA / hukuki süreç

**Future work (v1.11.0 / v4.32.0 / v0.7.0):** Her gate inline RSA verify yapar (DRY ihlali kabul) → saldırı yüzeyi 1 → 4-6 patch noktasına çıkar. Şu an YAGNI.

### 4.8 Grace Period Davranışı

`LicenseManager::getFeatureToken()` cached token'ı `wp_options`'tan döndürür. Server her validate cycle'ında token refresh ediyor (haftalık + daily). Server outage senaryosu:

| Durum | Davranış |
|---|---|
| Cached token TTL içinde (≤24h) | Pro çalışır |
| Cached token expired (>24h) | Pro kapanır, daily cron'da retry |
| Cached token verify fail (sig invalid) | Pro kapanır immediate |
| Hiç token yok (fresh install, server unreachable) | Pro kapanır, license activate başarısız zaten |

**Grace period 7 gün** — license server'ın `validate()` cevabı 7 gün içinde alınmazsa license deactivate edilir (mevcut davranış). Token TTL 24h → her 24h server contact gerekli, ama 7 gün license-level grace var. Bu mevcut mantık değişmez.

---

## 5. Backward Compatibility & Migration

### 5.1 Single-Field Swap (Brainstorm Soru 2'de A seçildi)

Server v1.10.0'da `feature_token` field'ının formatı **silently** HMAC → RSA değiştirir:

| Client Versiyonu | `feature_token` Davranışı | Sonuç |
|---|---|---|
| v4.30.x — `FEATURE_TOKEN_KEY` tanımsız | İgnore (legacy fallback `isPro()`) | ✓ Çalışmaya devam eder (mhmrentiva.com güncel durum bu) |
| v4.30.x — `FEATURE_TOKEN_KEY` tanımlı (varsayımsal) | HMAC verify dener → fail (RSA sig HMAC olarak yorumlanmaz) | ⚠️ `Mode::featureGranted()` false → Pro kapanır |
| v4.31.0+ | RSA verify (public key gömülü) | ✓ Yeni davranış |

**Realite kontrolü (mhmrentiva.com v4.30.2 + wpalemi.com v1.9.3):**
- wpalemi.com (server) wp-config'de hiçbir secret manuel tanımlı değil — RESPONSE_HMAC + FEATURE_TOKEN file fallback ile çalışıyor (hot.md doğrulanmış)
- mhmrentiva.com (client) wp-config'inde `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` durumu **doğrudan doğrulanmamış** — "müşteri-vari deploy hedefi" politikasına göre **muhtemelen** tanımsız, ama Phase B deploy'undan ÖNCE Hostinger File Manager veya SSH ile kontrol edilmeli
- Eğer client'ta tanımlıysa: v4.30.2 client server'ın yeni RSA token'ını HMAC olarak verify etmeye çalışır → fail → `Mode::featureGranted()` false → Pro kapanır
- **Mitigation:** Phase A deploy ÖNCESİ mhmrentiva.com wp-config kontrol; tanımlıysa operator silmeli (artık deprecated zaten)
- CS canlı site yok, etki yok

**Net:** Pratikte mevcut deploy'larda backward-compat sorunu yok. v4.31.0 release notes'unda explicit not:

> **v4.31.0 BREAKING:** `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` constant'ı artık kullanılmıyor — silebilirsiniz. Pro features'in çalışması için server v1.10.0+ olması ŞART. Eski server v1.9.x ile v4.31.0 client çalışmaz (server token RSA-imzalı emit etmiyor → client verify fail).

### 5.2 Asymmetric Deploy Senaryoları

Operasyonel olarak **server v1.10.0 + client v4.31.0 birlikte deploy edilmeli.** Senaryolar:

| Server | Client | Davranış |
|---|---|---|
| v1.9.x (eski) | v4.30.x (eski) | ✓ Mevcut çalışma (HMAC token, legacy fallback) |
| v1.9.x (eski) | v4.31.0 (yeni) | ❌ Client RSA verify fail (server hâlâ HMAC sign) → Pro kapanır |
| v1.10.0 (yeni) | v4.30.x (eski) | ✓ Eski client RSA token ignore eder, legacy fallback (mhmrentiva.com'da net etkisiz) |
| v1.10.0 (yeni) | v4.31.0 (yeni) | ✓ Tam enforcement |

**Deploy order:** Server v1.10.0 ÖNCE → v4.31.0 clients sonra (Phase 6'da detay).

### 5.3 wp-config Migration

| Constant | v4.30.x | v4.31.0 |
|---|---|---|
| `MHM_LICENSE_SERVER_RESPONSE_HMAC_SECRET` | Aktif | Aktif (response signing korunur) |
| `MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY` | Aktif | **DEPRECATED** (kaldırılabilir) |
| `MHM_LICENSE_SERVER_PING_SECRET` | Aktif (opsiyonel) | Aktif (opsiyonel, site_hash fallback v1.9.1+) |
| `MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM` | YOK | **YENİ — ZORUNLU** |
| `MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET` (client) | Aktif | Aktif |
| `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` (client) | Aktif (opsiyonel) | **DEPRECATED** (silinebilir) |
| `MHM_RENTIVA_LICENSE_PING_SECRET` (client) | Aktif (opsiyonel) | Aktif (opsiyonel) |

CS için aynı (`MHM_CS_LICENSE_*`).

---

## 6. Phase Yapısı & Deploy Sırası

### Phase A — Server v1.10.0 (~2.5h)

**Yeni:**
- `src/Security/RsaSigner.php` (sign + loadPrivateKey)
- `tests/fixtures/test-rsa-private.pem` + `tests/fixtures/test-rsa-public.pem` (test-only key pair, openssl ile generate, repo'ya commit)
- `tests/Integration/Security/RsaSignerTest.php` (sign + verify roundtrip, key parse fail, missing key)

**Modifiye:**
- `src/Security/SecretManager.php`: `getRsaPrivateKey()` (constant only, no file fallback)
- `src/License/FeatureTokenIssuer.php`: HMAC sign → RsaSigner; private key yoksa exception → caller skip token field
- `src/License/LicenseValidator.php`: feature_token branch RsaSigner kullanır, exception'da field ÇIKAR
- `src/License/LicenseActivator.php`: aynı
- `mhm-license-server.php`: 1.9.3 → 1.10.0 header + constant
- `readme.txt`: Stable tag 1.9.3 → 1.10.0
- `CHANGELOG.md`: v1.10.0 entry
- `LIVE_DEPLOYMENT_CHECKLIST.md`: yeni constant + RSA key üretim komutları

**Kaldırılan:**
- HMAC sign metodu (FeatureTokenIssuer içinden)
- File fallback for FEATURE_TOKEN_KEY (auto-gen path)
- `wp-uploads/mhm-license-secrets/feature_token.key` (eski deploylarda manuel temizlik gerekebilir, deploy guide'da not)

**Test hedefi:** 143 → ~155 (+12 yeni test)

**Deploy:** wpalemi.com Hostinger MCP via `hosting_deployWordpressPlugin`. wp-config'e `MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM` define edilir (heredoc multi-line PEM). `MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY` deprecated ama wp-config'den silmek opsiyonel (artık okunmuyor).

### Phase B — Rentiva v4.31.0 (~1.5h)

**Yeni:**
- `src/Admin/Licensing/LicenseServerPublicKey.php` (heredoc class constant + static cache)

**Modifiye:**
- `src/Admin/Licensing/FeatureTokenVerifier.php`: HMAC verify → `openssl_verify` (LicenseServerPublicKey::resource())
- `src/Admin/Licensing/Mode.php`: `featureGranted()` legacy `isPro()` fallback **KALDIRILDI** — token şart
- `src/Admin/Licensing/ClientSecrets.php`: `getFeatureTokenKey()` metodu kaldırılır (artık kullanılmıyor); RESPONSE_HMAC + PING_SECRET getter'ları korunur
- `mhm-rentiva.php`: 4.30.2 → 4.31.0
- `readme.txt`: Stable tag + Changelog entry
- `README.md` / `README-tr.md`: badge bump
- `changelog-tr.json`: v4.31.0 detaylı entry (BREAKING uyarısı + wp-config migration notu)
- `languages/mhm-rentiva.pot` regen + TR `.po`/`.mo`/`.l10n.php` (yeni string varsa)

**Test hedefi:** 781 → ~790 (+9 yeni test). `FeatureTokenVerifierTest` HMAC test'leri RSA'ya port edilir, `ModeFeatureTokenTest` legacy fallback test'leri kaldırılır + new "no-fallback enforcement" test eklenir.

**Test fixture'ları:**
- `tests/fixtures/test-rsa-private.pem` + `tests/fixtures/test-rsa-public.pem` (server ile AYNI — fixtures iki repo'da senkron)
- Test-only key pair, prod key DEĞİL — `LicenseServerPublicKey::PEM` constant'ı override edilebilir mi? Hayır (private const). Test'lerde `FeatureTokenVerifier`'a public key dependency-inject edilebilir hale getirmek (constructor opsiyonel param) → testabilite

**Deploy:** mhmrentiva.com'a v4.31.0 ZIP upload. Server v1.10.0 ÖNCE deploy edilmiş olmalı. wp-config'de `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` artık gerekli değil (varsa silinir, yoksa hiçbir şey yapılmaz).

### Phase C — Currency Switcher v0.6.0 (~1h)

**Yeni:**
- `src/License/LicenseServerPublicKey.php` — Rentiva ile AYNI public key PEM (heredoc constant)

**Modifiye:**
- `src/License/FeatureTokenVerifier.php`: HMAC → RSA
- `src/License/Mode.php`: 6 gate `feature_granted()` legacy `is_pro()` fallback kaldırıldı
- `src/License/ClientSecrets.php`: `get_feature_token_key()` kaldırıldı
- `mhm-currency-switcher.php`: 0.5.2 → 0.6.0
- `readme.txt`, `CHANGELOG.md`
- `languages/mhm-currency-switcher.pot` regen + TR çeviriler

**Test hedefi:** 137 → ~143 (+6). Aynı pattern: HMAC tests → RSA, legacy fallback removal tests.

**Deploy:** GH release. Canlı CS site yok, deploy bekliyor (gelecek müşteri için hazır).

### Phase D — E2E Validation (~1h)

3 senaryo, 2 ortam:

**Local Docker:**
1. **Happy path:** server v1.10.0 + Rentiva v4.31.0 docker stack, activate → Pro features açık (RSA verify çalışıyor)
2. **Token tamper:** Docker'da response interceptor (mock_http) ile feature_token'ın canonical_payload'ını 1 byte değiştir → openssl_verify fail → Mode false → Pro kapanır. RSA crypto'nun **asıl koruma noktası bu**.
3. **Token forge attempt:** Saldırgan kendi private key'iyle token üretip plugin'e enjekte etse → openssl_verify fail (server public key eşleşmez) → Pro kapanır. Bu test'i pseudo-realistic yap: test fixture private key ile imzala, prod public key ile verify et → fail beklenir.
4. **Source-edit attack — Mode patch:** `Mode::featureGranted() { return true; }` patch → Pro AÇIK GÖZÜKÜR (mevcut tek-nokta-patch zayıflığı, Section 4.7 "Defense-in-Depth Sınırı"nda kabul edilmiş). Bu test'i çalıştırıp **kasıtlı sınırı kanıtla** — RSA bunu kapatmaz, future work v1.11.0+.
5. **Legacy compat:** server v1.10.0 + Rentiva v4.30.x → eski client RSA token ignore eder, legacy fallback ile çalışır (mhmrentiva.com'un mevcut durumu sürdürülebilir → asymmetric deploy senaryosu Section 5.2)

**Live (mhmrentiva.com):**
1. Server v1.10.0 deploy → Rentiva v4.30.2 hâlâ canlıda → Pro çalışmaya devam (regression yok)
2. Rentiva v4.31.0 ZIP upload → license re-activate → Chrome DevTools MCP ile Pro feature smoke test (ör. Vendor Marketplace dashboard, Advanced Reports)
3. Source-edit attack canlı simülasyon (sadece bilgi için, prod'da yapılmaz): docker'da clone

---

## 7. Test Stratejisi

### 7.1 Test Fixture Generation

Tek seferlik (dev tarafında) `openssl` ile test-only key pair üret:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out test-rsa-private.pem
openssl pkey -in test-rsa-private.pem -pubout -out test-rsa-public.pem
```

Fixture'lar 3 repo'da AYNI olmalı:
- `mhm-license-server/tests/fixtures/test-rsa-{private,public}.pem`
- `mhm-rentiva/tests/fixtures/test-rsa-{private,public}.pem`
- `mhm-currency-switcher/tests/fixtures/test-rsa-{private,public}.pem`

Fixture'lar repo'ya commit edilir (test-only key pair, prod kullanım için kesinlikle DEĞİL — README ile uyarı).

### 7.2 Server Test Pattern

```php
// tests/Integration/Security/RsaSignerTest.php
public function setUp(): void {
    parent::setUp();
    $pem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    define('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', $pem);
}

public function testSignProducesVerifiableSignature(): void {
    $signer = new RsaSigner();
    $sig = $signer->sign('canonical-payload-here');

    $public = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));
    $verified = openssl_verify('canonical-payload-here', $sig, $public, OPENSSL_ALGO_SHA256);

    $this->assertSame(1, $verified);
}
```

### 7.3 Client Test Pattern

`FeatureTokenVerifier` constructor opsiyonel `?\OpenSSLAsymmetricKey $public_key` alır — null ise `LicenseServerPublicKey::resource()` kullanılır. Test'te fixture public key inject edilir.

```php
public function testVerifyAcceptsValidToken(): void {
    $payload = [...]; // canonical
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $private = openssl_pkey_get_private(file_get_contents(FIXTURE_PRIVATE_PEM));
    openssl_sign($canonical, $sig, $private, OPENSSL_ALGO_SHA256);

    $token = $this->base64UrlEncode($canonical) . '.' . $this->base64UrlEncode($sig);

    $public = openssl_pkey_get_public(file_get_contents(FIXTURE_PUBLIC_PEM));
    $verifier = new FeatureTokenVerifier($public); // DI

    $this->assertTrue($verifier->verify($token, $payload['site_hash']));
}
```

### 7.4 Bootstrap

`tests/bootstrap.php`'e openssl extension check:
```php
if (!extension_loaded('openssl')) {
    fwrite(STDERR, "openssl extension required for tests\n");
    exit(1);
}
```

### 7.5 PHPCS / Plugin Check

`openssl_*` fonksiyonları WP core'da yaygın kullanılıyor (REST API cookie auth, salts, etc.) — Plugin Check'te discouraged DEĞİL. `phpcs:ignore` gerekmiyor tahminen, baseline çıkarsa o noktada karar.

`base64_encode`/`base64_decode` PHP base64url helper'larında kullanılıyor — Plugin Check warning verir genelde, ancak gerekçe açık (binary RSA sig encoding) → `phpcs:ignore WordPress.PHP.DiscouragedFunctions.obfuscation_base64_decode` justification yorumu ile geçilir. Mevcut `FeatureTokenVerifier` zaten aynı pattern kullanıyor, replikasyon.

---

## 8. Acceptance Criteria

Phase tamamlanmış sayılır eğer:

### Phase A (Server v1.10.0)
- [ ] `RsaSigner` sınıfı + 12+ test (sign/verify roundtrip, missing key, invalid PEM)
- [ ] `FeatureTokenIssuer` RSA-only (HMAC kaldırıldı)
- [ ] `LicenseValidator` + `LicenseActivator` private key yoksa feature_token field'ı çıkarır + admin notice
- [ ] 143 → ~155 PHPUnit, 0 PHPCS error
- [ ] `LIVE_DEPLOYMENT_CHECKLIST.md` RSA private key üretim komutları + wp-config snippet
- [ ] CHANGELOG v1.10.0 BREAKING note (server-only deployment etkilenmez ama yeni constant şart)

### Phase B (Rentiva v4.31.0)
- [ ] `LicenseServerPublicKey` + 5+ test (PEM parse, static cache, fixture key)
- [ ] `FeatureTokenVerifier` RSA verify, DI ile testabilite
- [ ] `Mode::featureGranted()` legacy fallback kaldırıldı
- [ ] `ClientSecrets::getFeatureTokenKey()` kaldırıldı
- [ ] 781 → ~790 PHPUnit, 0 PHPCS
- [ ] readme.txt + changelog-tr.json BREAKING uyarısı (FEATURE_TOKEN_KEY deprecated, server v1.10.0+ ŞART)
- [ ] i18n regen (yeni string varsa, fuzzy check 0)

### Phase C (CS v0.6.0)
- [ ] Aynı kriterler Phase B'ye benzer (CS pattern kopyası)
- [ ] 137 → ~143 PHPUnit
- [ ] CHANGELOG.md v0.6.0 BREAKING

### Phase D (E2E)
- [ ] Local Docker happy path verified (server v1.10.0 + Rentiva v4.31.0, Pro açık)
- [ ] Local Docker token tamper test verified (1 byte flip → openssl_verify fail → Pro kapanır)
- [ ] Local Docker token forge test (test fixture private key ile imzala, prod public key ile verify → fail)
- [ ] Local Docker Mode-patch test: `featureGranted() return true` ile Pro açılır — **kasıtlı sınırı kanıtla**, future work referansı dokümante et
- [ ] Local Docker legacy compat: server v1.10.0 + Rentiva v4.30.x → mevcut çalışma sürdürülebilir
- [ ] Live mhmrentiva.com Chrome DevTools MCP smoke test (regression yok, Pro feature açık)
- [ ] Phase A deploy ÖNCESİ mhmrentiva.com wp-config'de `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` kontrol; tanımlıysa silindi mi (Section 5.1 mitigation)

### Cross-Phase
- [ ] Server v1.10.0 + Rentiva v4.31.0 + CS v0.6.0 üçü birden GH release
- [ ] mhm-rentiva-docs blog yazısı (EN + TR çift dosya)
- [ ] hot.md güncellendi
- [ ] project_license_security_v430.md → Phase E olarak refactor (veya yeni `project_license_asymmetric_v431.md`)
- [ ] wp-reflect invoke (her release sonrası — 9 release zinciri retrospektifinde "atlanan disiplin" desenleri tekrar etmesin)

---

## 9. Açık Sorular & Future Work

### 9.1 Açık Sorular (Implementation öncesi karara bağlı değil, runtime'da netleşir)

**S1:** RSA key rotation gerekecekse strateji? Şu an no-rotation kabul edildi. Eğer key compromise olursa: yeni key pair üret + tüm clients'a yeni public key embed eden release çıkar (v4.32.0 / v0.7.0 / v1.11.0) + server private key swap. Mevcut clients eski public key ile verify yapıyor → server hem eski hem yeni private key ile sign etmeli (transition period 30-60 gün) → bu durumda token format'a `kid` byte eklenir. Future work, şu an YAGNI.

**S2:** RSA-2048 vs RSA-4096? **2048 kabul edildi** (Brainstorm Q1). NIST 2048'i 2030'a kadar yeterli görüyor. 4096 marginal güvenlik kazandırır, sign/verify ~4x yavaş. Müşteri yok → 2048 kafi.

**S3:** `kid` byte gelecek için eklensin mi? Brainstorm'da **eklenmedi** (ortası seçeneği reddedildi). Gerekirse v4.32.0'da migration: token format `v1.{base64}.{sig}` olarak prefix eklenir, eski clients ignore eder, yeni clients prefix kontrol eder. Backward-compat'lı eklenebilir.

**S4:** Public key fingerprint logging — admin paneline server-side "Active public key fingerprint: SHA256:..." göster, operator key rotation sırasında doğrulayabilsin. Future work.

### 9.2 Future Work (v1.11.0 / v4.32.0 / v0.7.0 sonrası)

- Key rotation infrastructure (`kid` header + multi-key registry)
- Per-product RSA key pair (Rentiva ve CS ayrı keys → bir key compromise diğerini etkilemez) — şu an tek paylaşılan key
- Public key fingerprint admin display
- Plugin update channel: GH release ile public key rotation otomasyonu (mhm-plugin-updater entegrasyonu)
- License server admin panel'de "Issued tokens audit log" (kim, ne zaman, hangi feature seti) — debugging + abuse detection

### 9.3 Asymmetric Crypto'nun Da Kapatamadığı Saldırı

Saldırgan plugin source'unu **tamamen** rewrite ederse (artık Rentiva değil, custom plugin), embedded public key ve license verify mantığını da kaldırırsa → tabii ki çalışır. Ama bu artık "Rentiva kullanmak" olmaz — saldırganın kendi yazdığı kod olur. Bizim koruma alanımız: **Rentiva-as-distributed** binary'i over and underrun protect etmek. Rewrite saldırılarına karşı koruma yok, IP koruması (DMCA, copyright) hukuki mesele.

---

## 10. Effort Estimation

| Phase | Tahmin | Buffer | Toplam |
|---|---|---|---|
| Phase A — Server v1.10.0 | 2.5h | 0.5h | 3h |
| Phase B — Rentiva v4.31.0 | 1.5h | 0.5h | 2h |
| Phase C — CS v0.6.0 | 1h | 0.5h | 1.5h |
| Phase D — E2E | 1h | 0.5h | 1.5h |
| **Toplam** | **6h** | **2h** | **8h** |

Brainstorm'da 4-6h verilmişti — spec yazımı gerçekçi 6-8h gösteriyor (test fixture senkronu, i18n regen, docs blog, deploy validation dahil).

---

## 11. Kabul Sonrası Geçiş

Bu spec onaylandığında **superpowers:writing-plans** skill'i invoke edilir → her phase için detaylı todo-bazlı implementation plan üretilir. Plan onaylandıktan sonra TDD ile Phase A → B → C → D sırasıyla session'lar (gerekirse seans bölünerek) ilerletilir.

Her phase için disiplin gate'leri (uzun seans / zincir release disiplini, wp-conductor SKILL):
- Per-Release Gate: wp-reflect, docs blog (EN+TR), fingerprint update, runtime smoke test
- Per-Session Gate: kök neden analizi (bug zinciri yaşamamak için), feedback memory updates
