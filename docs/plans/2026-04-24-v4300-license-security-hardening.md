# v4.30.0 License Security Hardening — Implementation Spec

**Tarih:** 2026-04-24
**Durum:** SPEC — review bekliyor, kod başlamadı
**Tahmini efor:** 16-20 saat (buffer ile 20-24h)
**Hedef releaseler:**
- `mhm-license-server` v1.9.0
- `mhm-rentiva` v4.30.0
- `mhm-currency-switcher` patch

---

## 1. Motivasyon (Neden Şimdi?)

### Tehdit
`mhm-rentiva` GitHub PUBLIC repo. Tüm Pro kodu açık. Saldırgan:
1. `Mode::canUseVendorMarketplace()` dahil Pro gate'lerini `always-true` yaparak Pro özellikleri ücretsiz kullanır
2. `LicenseManager::isActive()` hack'ler → hiç key girmeden aktif görünür
3. Crack'lenmiş Pro ZIP'i nullified-sites ağında dağıtır

Mevcut koruma:
- ✅ HMAC-korumalı REST API (mhm-license-server)
- ✅ Per-product `product_slug` binding (v1.8.0, full enforcement)
- ✅ AES-256-CBC server-side encryption
- ❌ Client-side bypass'a karşı hiçbir savunma yok

### Zamanlama Kararı
Polar.sh üzerinden aktif satış sürüyor. WordPress.org'a Lite submission hazırlığı var. **Popülerlik artışı → Pro piracy motivasyonu artışı.** v4.30.0 hardening WP.org scale'den **önce** devrede olmalı.

### Hedef
Source-edit bypass'ı **pahalı ve kısa ömürlü** yapmak. %100 engellenemez (client-side kod her zaman hack'lenebilir), ama:
- Crack yapmak 1 günden fazla uzmanlık gerektirsin
- Crack'lenmiş ZIP 24 saatten fazla çalışmasın (token TTL)
- Nullified distribution economics'i saldırgan için verimsiz olsun

---

## 2. Mevcut Durum (Starting Point)

### License Server (`mhm-license-server` v1.8.0)
- DB: `mhm_licenses.product_slug` kolonu ✅
- `LicenseActivator::activate()` slug match enforcement ✅
- `LicenseValidator::validate()` slug match enforcement ✅
- HMAC request auth (`RequestGuard`) ✅
- AES-256-CBC key storage ✅

### Client — Rentiva (`src/Admin/Licensing/LicenseManager.php`)
```php
Line 234: 'product_slug' => 'mhm-rentiva',  // activate
Line 313: 'product_slug' => 'mhm-rentiva',  // validate
```

### Client — Currency Switcher (`src/License/LicenseManager.php`)
```php
Line 164: 'product_slug' => 'mhm-currency-switcher',  // activate
Line 258: 'product_slug' => 'mhm-currency-switcher',  // validate
```

### Missing Layer
Her iki client'ta Pro gate `Mode::canUse*()` SADECE local option + grace period kontrolü yapıyor. Server'a **Pro feature listesi için gitmiyor**. Kullanıcı `isActive()` true yapsa tüm Pro açık.

---

## 3. Saldırı Modeli

| Senaryo | Mevcut Durum | v4.30.0 Sonrası |
|---|---|---|
| `isActive() { return true; }` | ✗ Bypass → Pro full açık | ✓ Bloke (feature_token yok) |
| `Mode::canUse*() { return true; }` | ✗ Bypass | ✓ Bloke (token check) |
| Response body tamper (MITM) | ✗ Bypass | ✓ Bloke (HMAC sig) |
| Fake POST /activate from script | ✗ Bypass | ✓ Bloke (reverse validation) |
| HMAC secret steal → fake server | Kısmi bypass | ✓ Bloke (feature token farklı secret) |
| DNS hijack → localhost license server | ✗ Bypass | ✓ Bloke (token üretimi gerçek secret gerek) |
| Pirated ZIP + no network | ✗ Bypass 30sn transient süresince | ✓ 24h token TTL sonra kapan |

---

## 4. Mimari — 3 Katmanlı Savunma

### Katman 1: HMAC-İmzalı Response

Sunucu cevabı client tarafında doğrulanır. Mevcut **request** HMAC'ine ek olarak **response** HMAC'i.

**Server (`mhm-license-server` v1.9.0):**
```php
// src/License/LicenseValidator.php::validate()
$data = [
    'success'       => true,
    'status'        => 'active',
    'plan'          => 'pro',
    'product_slug'  => $license->product_slug,
    'expires_at'    => $license->expires_at,
    'activation_id' => $activation_id,
    'feature_token' => $feature_token,  // Layer 3'te üretilecek
    'issued_at'     => time(),
    'nonce'         => wp_generate_uuid4(),
];

$canonical = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$data['signature'] = hash_hmac('sha256', $canonical, self::RESPONSE_HMAC_SECRET);

return rest_ensure_response($data);
```

**Client (`LicenseManager`):**
```php
private function verifyResponseSignature(array $response): bool {
    $signature = $response['signature'] ?? '';
    if (empty($signature)) return false;

    unset($response['signature']);
    ksort($response); // deterministic key order
    $canonical = wp_json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $expected = hash_hmac('sha256', $canonical, self::RESPONSE_HMAC_SECRET);

    return hash_equals($expected, $signature);
}

public function validate(bool $silent = false): bool|\WP_Error {
    $response = $this->request('/licenses/validate', [...]);
    if (is_wp_error($response)) return $response;

    if (!$this->verifyResponseSignature($response)) {
        return new \WP_Error('tampered_response', __('License response tampered.', 'mhm-rentiva'));
    }
    // continue
}
```

**Effort:** ~4-5h
- Server: +2h (validator + activator response signing)
- Client (Rentiva): +1.5h (verify + tests)
- Client (CS): +1h (copy-paste)

---

### Katman 2: Reverse Site Validation

Sunucu, activate sırasında client site'ine **geri** HTTP atar. Site gerçekten orada mı + challenge'a doğru cevap veriyor mu?

**Client — Yeni Public REST Endpoint:**
```php
// src/Admin/Licensing/VerifyEndpoint.php
add_action('rest_api_init', function () {
    register_rest_route('mhm-rentiva-verify/v1', '/ping', [
        'methods'             => 'GET',
        'callback'            => [self::class, 'handle_ping'],
        'permission_callback' => '__return_true',
    ]);
});

public static function handle_ping(\WP_REST_Request $request): array {
    $challenge = $request->get_header('x-mhm-challenge');
    if (empty($challenge) || !is_string($challenge)) {
        return ['error' => 'challenge_missing'];
    }

    return [
        'challenge_response' => hash_hmac('sha256', $challenge, self::PING_SECRET),
        'site_url'           => home_url(),
        'product_slug'       => 'mhm-rentiva',
        'version'            => MHM_RENTIVA_VERSION,
    ];
}
```

**Server — Activate Akışı:**
```php
// src/License/LicenseActivator.php::activate()
$challenge = wp_generate_uuid4();

// Client'a HTTP GET at
$response = wp_remote_get(
    trailingslashit($request->site_url) . 'wp-json/mhm-rentiva-verify/v1/ping',
    [
        'headers' => ['X-MHM-Challenge' => $challenge],
        'timeout' => 10,
        'sslverify' => true,
    ]
);

if (is_wp_error($response)) {
    return new \WP_Error('site_unreachable', __('Your site is not reachable from our server.', 'mhm-license-server'));
}

$body = json_decode(wp_remote_retrieve_body($response), true);
$expected = hash_hmac('sha256', $challenge, self::PING_SECRET);

if (!isset($body['challenge_response']) || !hash_equals($expected, $body['challenge_response'])) {
    return new \WP_Error('site_verification_failed', __('Site challenge failed.', 'mhm-license-server'));
}

// Site doğrulandı, aktivasyon devam
```

**Edge case'ler:**
- **Shared hosting outbound block:** Bazı shared host'lar outbound HTTP'yi blokluyor. Timeout 10sn + graceful degradation: Katman 1 + 3 geçerse aktive et (Katman 2 WARNING log'la)
- **Localhost / staging:** `is_staging` flag client tarafından gönderiliyor; staging için reverse validation bypass (dev ortam)
- **Rate limit:** Aynı site'ye 1 dakikada 1 activate denemesi

**Effort:** ~5-6h
- Server: +3h (HTTP call + challenge/response logic + edge cases)
- Client (Rentiva): +1.5h (VerifyEndpoint class + tests)
- Client (CS): +1h (copy-paste)

---

### Katman 3: Server-Issued Feature Token

Pro özellikler **sunucu tarafından verilen kriptolu token** olmadan çalışmaz. `Mode::canUse*()` local flag DEĞİL, server-issued token'dan okur.

**Server — Token Üretimi:**
```php
// src/License/FeatureTokenIssuer.php
class FeatureTokenIssuer {
    public function issue(License $license, string $site_hash): string {
        $feature_set = $this->resolveFeatureSet($license->plan, $license->product_slug);

        $payload = [
            'license_key_hash' => hash('sha256', $license->key),
            'product_slug'     => $license->product_slug,
            'features'         => $feature_set, // ['vendor_marketplace' => true, ...]
            'site_hash'        => $site_hash,
            'issued_at'        => time(),
            'expires_at'       => time() + 86400, // 24h
        ];

        return $this->crypto->encryptAndSign($payload, self::FEATURE_TOKEN_KEY);
    }

    private function resolveFeatureSet(string $plan, string $product_slug): array {
        if ($product_slug === 'mhm-rentiva' && $plan === 'pro') {
            return [
                'vendor_marketplace' => true,
                'advanced_reports'   => true,
                'messaging'          => true,
                'full_rest_api'      => true,
                'gdpr_tools'         => true,
                'custom_emails'      => true,
            ];
        }
        // diğer ürün/plan kombinasyonları
        return [];
    }
}
```

**Client — Mode.php Refactor:**
```php
// src/Admin/Licensing/Mode.php
public function canUseVendorMarketplace(): bool {
    // 1. Basic license check
    if (!$this->licenseManager->isActive()) {
        return false;
    }

    // 2. Feature token decrypt + verify
    $token = $this->licenseManager->getFeatureToken();
    if (empty($token)) {
        return false;
    }

    $payload = $this->crypto->decryptAndVerify($token, self::FEATURE_TOKEN_KEY);
    if ($payload === null) {
        return false; // Tampered OR expired
    }

    // 3. Token freshness (24h TTL + grace)
    if ($payload['expires_at'] < time() - (7 * DAY_IN_SECONDS)) {
        return false; // Hard expire after token TTL + grace
    }

    // 4. Feature flag check
    return !empty($payload['features']['vendor_marketplace']);
}
```

**Token lifecycle:**
- Activate/validate → server yeni token döner
- Client options'a kaydet (transient değil, permanent store — her 24h'de validate ile yenilenir)
- Daily cron `validate()` → fresh token
- Server offline → old token 7 gün kullanılabilir (grace), sonra Pro kapanır

**Crypto:**
- AES-256-GCM encrypt + HMAC-SHA256 (authenticated encryption)
- Server `FEATURE_TOKEN_KEY` secret, client'a göndermez
- Client decrypt edemez, sadece verify + opaque field'ları okur

**Bekle — Client decrypt etmeden nasıl feature check yapacak?** İki seçenek:

**Option A: Server readable, client readable (HMAC signed only)**
- Token JSON base64 + HMAC sig
- Client decode edip okuyabilir
- Bypass riski: Client değiştirirse HMAC bozulur, verify fail

**Option B: Server encrypted (AES-GCM), client blind (expected_features pre-known)**
- Client token'ı sadece HMAC verify eder, içini okuyamaz
- Feature check için "challenge" pattern: Pro özellik çalıştırırken token'ı server'a geri yollar, "bu token bu feature için geçerli mi" sorar
- Daha güvenli ama her Pro feature'da network call → UX kötü

**Seçim:** **Option A** — pragmatik. HMAC tampering'i bloklar, plaintext verification hızlı, UX iyi.

**Effort:** ~7-9h
- Server: +4h (FeatureTokenIssuer + crypto wrapper + validator integration)
- Client (Rentiva): +3h (Mode refactor, token store, decrypt helper)
- Client (CS): +2h (Mode refactor copy-paste)

---

## 5. Implementation Phases

### Phase A — Server v1.9.0 (Standalone)
1. Add `FeatureTokenIssuer` class
2. Add response signing to `LicenseValidator` + `LicenseActivator`
3. Add reverse site validation to `LicenseActivator`
4. Add `RESPONSE_HMAC_SECRET` + `FEATURE_TOKEN_KEY` + `PING_SECRET` constants (wp-config.php)
5. PHPUnit tests: response signing, reverse validation, token issuance/verification
6. E2E test with test-client (port 8087)

**Duration:** 8-9h
**Deliverable:** Server v1.9.0 ready, eski Rentiva clients (v4.27.x, v4.29.x) hala çalışıyor (legacy pass — token ignored, response sig ignored)

### Phase B — Rentiva v4.30.0
1. Add `VerifyEndpoint` REST route
2. Add response signature verification to `LicenseManager::request()`
3. Add feature token store + retrieve
4. Refactor `Mode::canUse*()` to check feature token
5. Add `PING_SECRET` + `RESPONSE_HMAC_SECRET` + `FEATURE_TOKEN_SECRET` to wp-config.php requirements
6. PHPUnit tests: response verify, token store, Mode with/without token
7. Clean WP smoke test in `rentiva-release`

**Duration:** 6-7h
**Deliverable:** Rentiva v4.30.0 ZIP + GitHub release + docs blog

### Phase C — Currency Switcher patch
1. Copy response verify + feature token store + Mode refactor from Rentiva
2. Update `PRODUCT_SLUG` + constant adları
3. PHPUnit tests
4. Smoke test
5. Release

**Duration:** 3-4h

### Phase D — E2E Validation
1. Sandbox test: Polar checkout → webhook → lisans + feature token → Rentiva activate (sig + reverse + token OK) → Pro açık
2. Tamper test: response sig bozulmuş → client reject
3. Source edit test: `isActive() return true` → Mode hala token için kontrol ediyor, Pro kapalı ✓
4. Grace test: server offline → 7 gün çalışır, 8. gün kapanır
5. Legacy client test: eski Rentiva (v4.29.x) yeni server'la — eski davranış korunuyor (legacy pass)

**Duration:** 2-3h

**Toplam:** 19-23h

---

## 6. Backward Compatibility

### Server v1.9.0 — Eski Client'lara Davranış
- Eski client (v4.27.x, v4.29.x) `feature_token` field'ını görür ama ignore eder
- Response signature field'ını görür ama verify etmez
- Reverse site validation başarılı olursa aktivasyon devam, fail olursa HARD REJECT (eski client'larda fail durumu yok aslında, ama bu yeni gate)
- **Öneri:** Reverse validation SADECE v4.30.0+ client'larda aktif. Detect için request payload'a `client_version` field'ı + v4.30.0+ için reverse zorla, eski için skip

### v4.30.0+ Client — Eski Server'a Davranış
- Eski server `signature` dönmez → client tampered_response hatası
- Bu problem yapmaz çünkü server v1.9.0 ile eşzamanlı deploy

### Legacy Pass Deprecation Timeline
- **v4.30.0 release (T+0):** Server legacy pass aktif, eski client'lar çalışır
- **T+90 gün (Temmuz 2026):** Blog post + email campaign — "v4.30.0+'a upgrade edin, Ağustos'ta eski sürümler kesilecek"
- **T+120 gün (Ağustos 2026):** Server v1.9.1 legacy pass kapat. v4.29.x'ten önce olan Rentiva sürümleri artık license validate edemez
- **v4.30.0+ müşterileri:** Etkilenmez, zaten yeni mimari

---

## 7. Test Strateji

### Unit Tests (Server)
- `FeatureTokenIssuer::issue()` — doğru feature set, 24h TTL, valid crypto
- Response signature — deterministic, tamper detected
- Reverse validation — success/fail/timeout

### Unit Tests (Client)
- `LicenseManager::verifyResponseSignature()` — tamper detection
- `Mode::canUse*()` — token missing/expired/tampered → false
- Feature token parse — malformed JSON handled

### Integration Tests
- Full flow: Activate → reverse validation → token issued → Mode.canUseVendorMarketplace() = true
- Tampered token → Mode returns false
- Expired token → Mode returns false
- Network failure during validation → grace mode works 7 days

### E2E (rentiva-release'de)
- Clean WP install
- ZIP deploy
- Activate with test key
- Verify Pro features appear
- Simulate source-edit bypass → verify still gated
- Simulate network failure → verify grace works

---

## 8. Release Strategy

### Sıralama (Kritik — Sunucu ÖNCE)
1. **Server v1.9.0** deploy to wpalemi.com — eski client'lar hala çalışır (legacy pass)
2. **Rentiva v4.30.0** GitHub release — yeni müşteriler buna upgrade eder
3. **Currency Switcher patch** release — aynı
4. **Monitoring** — 1 hafta server log'u izle (tampered_response error rate, reverse validation fail rate)
5. **T+90 blog post** — v4.31.0 announcement with legacy deprecation warning
6. **T+120 Server v1.9.1** — legacy pass kesilir

### Rollback Planı
- Server v1.9.0 deploy sonrası sorun çıkarsa (response sig bug gibi): `REVOKE_RESPONSE_SIGNING=true` wp-config.php flag'ı → legacy behavior'a döner
- Rentiva v4.30.0 post-release bug: hotfix patch (v4.30.1) gönder, müşterilere emergency update anonsu

---

## 9. Success Criteria

v4.30.0 "başarılı" sayılır eğer:

- ✅ Source-edit bypass test'i başarısız (Mode::canUse* true yapılsa Pro hala kapalı)
- ✅ Tampered response test'i başarısız (client reject ediyor)
- ✅ Fake POST /activate engelleniyor (reverse validation fail)
- ✅ Pirated ZIP + no network → 24h sonra Pro kapanıyor
- ✅ 1 hafta canlı monitoring: tampered_response error rate %0.1'den düşük (false positive yok)
- ✅ Mevcut Pro müşteri hiç aksaklık yaşamadı (grace period mantığı korundu)
- ✅ PHPUnit: 740/740 → +N (yeni test) yeşil
- ✅ WPCS: 0 error

---

## 10. Açık Sorular / Kararlar

- [ ] **Response signature secret rotation:** HMAC secret kaç ayda bir değişmeli? Yılda 1 yeterli mi?
- [ ] **Token TTL:** 24h doğru mu yoksa 12h daha mı güvenli? Daha kısa = daha çok network call, UX etkilenir
- [ ] **Feature granularity:** Şu an ürün+plan → feature set hardcoded. İleride per-customer override? (örn. "bu müşteri messaging yok ama gdpr_tools var")
- [ ] **Audit logging:** Tampered response, failed reverse validation, expired token kullanımı — DB log? Alert?
- [ ] **Rate limit on reverse validation:** Activate endpoint'i DDoS ederse sunucu fake site'lara çok HTTP atar. Rate limit per-IP?
- [ ] **Legacy pass cutoff date:** T+120 gerçekçi mi? Bazı müşteriler nadiren plugin güncellemiyor — 6 ay daha geniş mi?

---

## 11. İlgili Dosyalar

### Server (`c:/projects/rentiva-lisans/plugins/mhm-license-server/`)
- `src/License/LicenseActivator.php` — response signing + reverse validation
- `src/License/LicenseValidator.php` — response signing + feature token include
- `src/License/FeatureTokenIssuer.php` (YENİ)
- `src/Security/Crypto.php` — AES-GCM + HMAC helper (varsa genişlet)
- `src/REST/RESTController.php` — wp-config.php constant'ları load
- `tests/License/` — yeni unit/integration testler

### Client — Rentiva (`c:/projects/rentiva-dev/plugins/mhm-rentiva/`)
- `src/Admin/Licensing/LicenseManager.php` — response verify + token store
- `src/Admin/Licensing/VerifyEndpoint.php` (YENİ)
- `src/Admin/Licensing/Mode.php` — tüm `canUse*()` refactor
- `src/Admin/Licensing/FeatureTokenVerifier.php` (YENİ)
- `tests/Licensing/` — unit + integration testler

### Client — Currency Switcher (`c:/projects/mhm-currency-switcher/`)
- `src/License/LicenseManager.php` — response verify
- `src/License/VerifyEndpoint.php` (YENİ)
- `src/License/Mode.php` (varsa) — Pro gate refactor
- `tests/License/` — unit testler

---

## 12. Next Step (Spec Approval Sonrası)

**Eğer bu spec onaylanırsa:**

1. Session'ı bitir, bu spec'i git commit et (`rentiva-dev`)
2. Yeni bir session aç (büyük iş, fresh context gerekir)
3. Phase A — server v1.9.0 implementation (8-9h) — belki 2 session
4. Phase B — Rentiva v4.30.0 (6-7h) — 1-2 session
5. Phase C — CS patch (3-4h) — 1 session
6. Phase D — E2E validation (2-3h) — 1 session
7. Final release + blog post + T+90 campaign planning

Toplam 4-6 session bekleniyor. Her birinde concrete delivery (tek katman, testlenmiş, yeşil).

**Spec'te değişiklik/ekleme istersen şimdi söyle.** Onay sonrası implementation başlar.
