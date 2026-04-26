# License Renewal & Subscription Management — Design

**Tarih:** 2026-04-26
**Durum:** Design approved (brainstorm), implementation plan'a geçiş için hazır
**Kapsam:** "C katmanı" — 3 katmanlı lisans ekosistemine renewal/subscription management özelliği eklenmesi

## Etkilenen sürümler

| Repo | Mevcut | Hedef |
|---|---|---|
| `MaxHandMade/mhm-license-server` | v1.10.1 (canlıda) | **v1.11.0** |
| `MaxHandMade/wpalemi` (mhm-polar-bridge) | v1.8.0 (canlıda) | **v1.9.0** |
| `MaxHandMade/mhm-rentiva` | v4.31.2 (canlıda) | **v4.32.0** |
| `MaxHandMade/mhm-currency-switcher` | v0.6.5 (canlıda) | **v0.7.0** |

mhm-rentiva-docs ayrı repo — her release için EN+TR blog yazıları yayınlanır.

## Bağlam ve hedef

Rentiva ve Currency Switcher Pro müşterileri Polar.sh aboneliklerini şu an plugin içinden yönetemiyor. Abonelik iptali, ödeme yöntemi güncellemesi, plan değişikliği için müşterinin ya wpalemi.com `/account/` sayfasına gitmesi ya da Polar email'lerindeki linkleri kullanması gerekiyor. Bu spec ile plugin License sayfasında doğrudan Polar customer portal'a yönlendiren tek-tıklık akış kuruluyor.

İkincil hedef: aylık aboneler için -7 gün renewal reminder email'i (Polar yıllık planlar için zaten gönderiyor; aylık planlar için göndermiyor — kaynak: `polarsource/polar:server/polar/subscription/repository.py`, "long billing cycles > 180 days" filtresi).

Üçüncül hedef (scope ekledi): tüm 6 polar-bridge lifecycle email'inde branding tutarlılığı — `From: MHM by WP Alemi <support@wpalemi.com>` + `(WP Alemi)` imzası. Sosyal medyada "maxhandmade" olarak tanınan ürün ailesi (MHM) ile global marketplace (WP Alemi) ikiliğinin email'lerde net görünmesi.

**Bu spec bilinçli olarak yapmadığı şeyler:**

- Polar'ın kendi license-keys benefit feature'ını kullanmak — biz kendi mhm-license-server'ımızı kullanıyoruz (RSA asymmetric crypto, reverse-validation, feature_token, yetenek setleri Polar native'in dışında). Polar license-keys API'sine geçmiyoruz.
- Yıllık abonelere renewal reminder göndermek (Polar zaten gönderiyor, duplicate olur)
- Past_due / dunning email göndermek (Polar zaten gönderiyor, duplicate olur)
- Subscription state'e göre buton label varyasyonları ("Resume Subscription", "Update Payment Method") — generic "Manage Subscription" + state-driven renk yeterli; v4.33.0+ enhancement bekler

## Sandbox-prod parity prensibi

Bu spec **geliştirme aşamasında** çıkıyor — gerçek müşteri yok, Polar sandbox kullanılıyor. Ancak hiçbir kod yolu sandbox'a özel kısayol içermez. Tek fark: `MHM_POLAR_SANDBOX=true` constant'ı `MHM_Polar_API::api_base()`'i `sandbox-api.polar.sh`'a çevirir. Test verisi gerçek webhook ile gelir. Zaman manipülasyonu sadece PHPUnit unit test seviyesinde (mock `time()`); runtime'da hiçbir kısa-yol yok. Production'da yapamadığımız bir şey sandbox'ta da yapılmamalı.

## Mimari

```
┌────────────────────────────────────┐
│ Customer site (örn. mhmrentiva.com)│
│                                    │
│  mhm-rentiva v4.32.0 ya da         │
│  mhm-currency-switcher v0.7.0      │
│                                    │
│  License sayfası:                  │
│  ┌────────────────────────────┐    │
│  │ License Status (state)     │    │
│  │ Pro License Active         │    │
│  │ Expires: 2026-05-26 (12d)  │    │
│  ├────────────────────────────┤    │
│  │ License Management         │    │
│  │ [Manage Subscription]      │ ←── yeni
│  │ [Re-validate Now]          │    │
│  │ [Deactivate License]       │    │
│  └────────────────────────────┘    │
└──────────────┬─────────────────────┘
               │ HTTPS, HMAC-imzalı request
               │ RSA-imzalı response
               ▼
┌────────────────────────────────────┐
│ wpalemi.com                        │
│                                    │
│  ┌──────────────────────────────┐  │
│  │ mhm-license-server v1.11.0   │  │
│  │                              │  │
│  │  POST /licenses/             │  │
│  │       customer-portal-       │  │
│  │       session                │  │
│  │  - license_key + site_hash   │  │
│  │  - polar_customer_id lookup  │  │
│  │    (yeni schema kolonu)      │  │
│  │  - RSA imzalı response       │  │
│  └────────────┬─────────────────┘  │
│               │ aynı WP, PHP        │
│               │ function call       │
│               ▼                     │
│  ┌──────────────────────────────┐  │
│  │ mhm-polar-bridge v1.9.0      │  │
│  │                              │  │
│  │  MHM_Polar_API::             │  │
│  │   create_customer_session    │  │
│  │                              │  │
│  │  +RenewalReminderCron        │  │
│  │    (daily, monthly subs only)│  │
│  │  +send_renewal_reminder_email│  │
│  │  +6 lifecycle email branding │  │
│  └────────────┬─────────────────┘  │
└───────────────┼────────────────────┘
                │ HTTPS
                │ Bearer token
                │ (MHM_POLAR_API_TOKEN)
                ▼
┌────────────────────────────────────┐
│ Polar.sh (sandbox veya prod)       │
│                                    │
│  POST /v1/customer-sessions/       │
│  → {customer_portal_url,           │
│     expires_at, token, ...}        │
└────────────────────────────────────┘
```

Polar üzerinde değişiklik yok. license-server ve polar-bridge aynı WordPress instance'ında (wpalemi.com) çalıştığı için aralarında HTTP hop yok — license-server doğrudan `MHM_Polar_API::create_customer_session()` PHP fonksiyonunu çağırır.

## Component değişiklikleri

### mhm-license-server v1.11.0

#### 1. Schema migration

`wp_mhm_licenses` tablosuna yeni kolon:

```sql
ALTER TABLE wp_mhm_licenses
  ADD COLUMN polar_customer_id varchar(64) NULL AFTER external_subscription_id,
  ADD INDEX polar_customer_id (polar_customer_id);
```

`DatabaseManager::create_tables()` (fresh install) ve `DatabaseManager::migrate()` (upgrade) güncellenir. Migration mevcut pattern'i kopyalar:

```php
if (!isset($column_map['polar_customer_id'])) {
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN polar_customer_id varchar(64) NULL AFTER external_subscription_id");
    $wpdb->query("ALTER TABLE {$table} ADD INDEX polar_customer_id (polar_customer_id)");
}
```

**Backfill yok.** Geliştirme aşamasında müşteri verisi olmadığı için retroactive backfill gereksiz; production'a açıldığında tüm yeni satırlar zaten yeni provisioner üzerinden gelecek.

#### 2. Yeni REST endpoint: `POST /licenses/customer-portal-session`

`src/REST/RESTController.php` içinde `register_routes()`'a eklenir.

**Auth:** Mevcut `RequestGuard::verify_request()` (HMAC + nonce + replay) + `RateLimitPolicy` 10 req/dk per license_key.

**Request body:**

```json
{
  "license_key": "XXXX-XXXX-XXXX-XXXX",
  "site_hash": "abc123...",
  "return_url": "https://customer-site.com/wp-admin/admin.php?page=mhm-rentiva-license"
}
```

**Validasyon zinciri:**

1. License key hash ile row bulunur. Yoksa → 404 `license_not_found`
2. `row.status === 'active'` değilse → 403 `license_not_active`
3. `(license_id, site_hash)` çifti `license_activations` tablosunda yoksa → 403 `site_not_activated`
4. `row.polar_customer_id` NULL ise → 422 `license_not_subscription`
5. `MHM_Polar_API::is_configured()` false ise → 503 `polar_api_unavailable`
6. `MHM_Polar_API::create_customer_session($polar_customer_id, $return_url)` çağrılır. Boş array dönerse → 503 `polar_api_unavailable`

**Response (200, RSA-signed):**

```json
{
  "success": true,
  "data": {
    "customer_portal_url": "https://polar.sh/...?token=...",
    "expires_at": "2026-04-26T15:42:00Z"
  },
  "signature": "base64url(rsa_signature)",
  "issued_at": 1745678520,
  "nonce": "..."
}
```

İmzalama mevcut `RsaSigner` ve `LicenseValidator::signResult()` pattern'i.

**Error response (4xx/5xx, yine RSA-signed):**

```json
{
  "success": false,
  "error": "license_not_subscription",
  "message": "This license has no Polar subscription linked.",
  "signature": "...",
  "issued_at": ...,
  "nonce": "..."
}
```

Tüm endpoint çağrıları `APILogger` üzerinden loglanır.

#### 3. `MHM_Polar_API::create_customer_session()` imza güncellemesi

Mevcut imza tek değer döndürüyor (string URL). Yeni endpoint hem URL hem expires_at istediği için array dönecek:

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

`BillingPortalRedirect::handle()` çağrısı güncellenir:

```php
$session = MHM_Polar_API::create_customer_session($customer_id);
if (empty($session['customer_portal_url'])) {
    wp_safe_redirect(home_url('/account/?billing=unavailable'));
    exit;
}
wp_redirect($session['customer_portal_url']);
exit;
```

#### 4. PHPUnit kapsamı

Yeni test dosyaları (`tests/` altında):

- `REST/CustomerPortalSessionTest.php` — endpoint behavior
  - Happy path (active + activated site + polar_customer_id present + Polar 200)
  - 404 license_not_found
  - 403 license_not_active
  - 403 site_not_activated
  - 422 license_not_subscription (polar_customer_id NULL)
  - 503 polar_api_unavailable (Polar 500)
  - 503 polar_api_unavailable (token unset)
  - 429 rate limit (>10 req/dk)
  - 401 HMAC signature mismatch
  - Response RSA-imzalı doğrulanabilir
- `Database/PolarCustomerIdMigrationTest.php` — schema migration
  - Fresh install kolonu ekler
  - Eski tablo (kolon olmadan) → migration kolonu ekler
  - İdempotent (çift run → hata yok)
- `Polar/PolarAPISignatureChangeTest.php` — wrapper imza değişikliği
  - Başarıda `customer_portal_url` + `expires_at` döner
  - Hata durumlarında boş array döner
  - `return_url` non-empty ise Polar'a iletilir
  - Backward-compat: `BillingPortalRedirect` yeni dönüş tipini doğru tüketir

Mevcut: 153 test → hedef: ~180 (+27).

#### 5. Versioning

- `mhm-license-server.php` header: 1.10.1 → 1.11.0
- `readme.txt` Stable tag güncellenir
- `CHANGELOG.md` detaylı entry
- `LIVE_DEPLOYMENT_CHECKLIST.md` migration check eklenir (`polar_customer_id` kolonu)

---

### mhm-polar-bridge v1.9.0

#### 1. LicenseProvisioner — `polar_customer_id` payload alanı

`includes/LicenseProvisioner.php::generate()` 7. parametre olarak `$polar_customer_id` alır. Boş değilse payload'a eklenir. `WebhookHandler::handle_subscription_active()` çağrısında `$customer_id` (zaten line 129 civarında parse ediliyor) iletilir.

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
    // ... mevcut payload ...
    if ($polar_customer_id !== '') {
        $payload['polar_customer_id'] = $polar_customer_id;
    }
    // ... mevcut HTTP çağrı ...
}
```

**Backward-compat:** Boş ise payload'a eklenmez → eski license-server (v1.10.x) tolerant kalır (NULL kolonu kabul ederdi zaten); yeni license-server (v1.11.0) yeni alanı saklar.

#### 2. RenewalReminderCron — yeni dosya

`includes/RenewalReminderCron.php`:

```php
class MHM_Polar_RenewalReminderCron {
    public const HOOK = 'mhm_polar_renewal_reminder_daily';

    public function register(): void {
        add_action('init', [$this, 'schedule_event']);
        add_action(self::HOOK, [$this, 'run']);
    }

    public function schedule_event(): void {
        if (!wp_next_scheduled(self::HOOK)) {
            // UTC 06:00 → Türkiye saati 09:00
            $next = strtotime('tomorrow 06:00 UTC');
            wp_schedule_event($next, 'daily', self::HOOK);
        }
    }

    public function run(): void {
        $now           = time();
        $window_start  = $now + (int) (6.5 * DAY_IN_SECONDS);
        $window_end    = $now + (int) (7.5 * DAY_IN_SECONDS);

        foreach ($this->find_eligible_subscribers($window_start, $window_end) as $candidate) {
            $this->send_and_mark($candidate);
        }
    }

    /**
     * @return array<int, array{user_id:int, license_index:int, license:array}>
     */
    private function find_eligible_subscribers(int $window_start, int $window_end): array {
        $eligible = [];
        $users = get_users([
            'meta_key'     => 'wpalemi_licenses',
            'meta_compare' => 'EXISTS',
        ]);

        foreach ($users as $user) {
            $licenses = get_user_meta($user->ID, 'wpalemi_licenses', true);
            if (!is_array($licenses)) {
                continue;
            }

            foreach ($licenses as $idx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (($row['plan'] ?? '') !== 'monthly') {
                    continue;
                }
                if (($row['status'] ?? '') !== 'active') {
                    continue;
                }
                if (!empty($row['cancel_at_period_end'])) {
                    continue;
                }
                if (empty($row['polar_subscription_id'])) {
                    continue;
                }
                if (empty($row['expires_at'])) {
                    continue;
                }

                $expires_ts = strtotime((string) $row['expires_at']);
                if ($expires_ts === false || $expires_ts < $window_start || $expires_ts >= $window_end) {
                    continue;
                }

                if (!empty($row['renewal_reminder_sent_at'])) {
                    continue;
                }

                $eligible[] = [
                    'user_id'       => $user->ID,
                    'license_index' => $idx,
                    'license'       => $row,
                ];
            }
        }

        return $eligible;
    }

    private function send_and_mark(array $candidate): void {
        // CustomerManager → send_renewal_reminder_email
        // Mark renewal_reminder_sent_at via CustomerManager::update_license
    }
}
```

`mhm-polar-bridge.php` ana dosyada `register()` çağrılır (mevcut bootstrap pattern).

#### 3. WebhookHandler — `subscription.updated`'da reminder flag clear

`handle_subscription_updated()` içinde `expires_at` yenilendiğinde `renewal_reminder_sent_at` temizlenir:

```php
$customer_manager->update_license($user_id, $subscription_id, [
    'product_label'            => $product_label,
    'plan'                     => $plan,
    'expires_at'               => $expires_at,
    'renews_at'                => $renews_at,
    'renewal_reminder_sent_at' => '',
]);
```

Bu sayede subscription her yenilendiğinde -7 gün cron yeni dönem için tekrar tetiklenir.

#### 4. CustomerManager — yeni email + 6 mevcut email branding refresh

**Yeni metod `send_renewal_reminder_email()`:** Locale-aware (TR + EN), `home_url('/account/')` linki gömülü. From header `MHM by WP Alemi`. İmza `(WP Alemi)` parantez ile.

**TR şablon:**

```
Konu: {product_name} aboneliğiniz 7 gün içinde yenilenecek

Merhaba {name},

{product_name} aylık aboneliğiniz {renews_at} tarihinde otomatik
yenilenecek.

Otomatik yenilemeyi iptal etmek, ödeme yönteminizi güncellemek veya
plan değiştirmek isterseniz aboneliğinizi yönetin:

{manage_url}

Hiçbir şey yapmanıza gerek yok — yenileme otomatik gerçekleşecek.

— MHM Ekibi (WP Alemi)
```

**EN şablon:**

```
Subject: Your {product_name} subscription renews in 7 days

Hi {name},

Your {product_name} monthly subscription renews on {renews_at} and
we'll charge your saved payment method automatically.

Need to cancel auto-renewal, update your card, or switch plans?
Manage your subscription here:

{manage_url}

Nothing to do otherwise — your renewal will go through automatically.

— MHM Team (WP Alemi)
```

Locale çözümlemesi `get_user_locale($user_id)`. Türkçe (`tr_TR`) varsa TR, aksi halde EN.

**Tarih formatı `format_email_date()` helper'a locale parametresi eklenir:**

```php
private function format_email_date(string $raw, string $locale = 'en'): string {
    if ($raw === '') return '';
    $ts = strtotime($raw);
    if ($ts === false) return '';
    if ($locale === 'tr') {
        return wp_date('j F Y', $ts);   // 26 Nisan 2026
    }
    return gmdate('F j, Y', $ts);        // April 26, 2026
}
```

**6 mevcut email branding refresh:** `send_welcome_email`, `send_canceled_email`, `send_uncanceled_email`, `send_refunded_email`, `send_expired_email` + yeni `send_renewal_reminder_email`. Tüm `wp_mail()` çağrılarında:

- `From: WP Alemi <support@wpalemi.com>` → `From: MHM by WP Alemi <support@wpalemi.com>`
- İmza `— WP Alemi Team` → `— MHM Team (WP Alemi)`

**Locale tutarsızlığı (kabul edilen):** Mevcut 5 lifecycle email İngilizce-only kalır; renewal reminder ilk locale-aware email. TR varyantları 5 mevcut email için ayrı bir polar-bridge v1.10.0 release'inde eklenir. Branding sözcüğü (MHM by WP Alemi) tüm 6'sında tutarlı.

#### 5. PHPUnit kapsamı

Yeni test dosyaları:

- `tests/RenewalReminderCronTest.php` — eligibility kuralları, send + mark, idempotency, schedule register
- `tests/CustomerManager/RenewalReminderEmailTest.php` — TR/EN locale, From header, imza, tarih formatı
- `tests/CustomerManager/BrandingRefreshTest.php` — 6 email'in From header + imza güncellemesi
- `tests/WebhookHandler/RenewalReminderResetTest.php` — subscription.updated flag clear
- `tests/LicenseProvisioner/PolarCustomerIdTest.php` — payload backward-compat

Toplam ~30 yeni test.

`wp_mail` mock pattern olarak `pre_wp_mail` filter veya WP_UnitTestCase MockMailer — mevcut polar-bridge test infrastructure'ında ne varsa onunla tutarlı.

#### 6. Versioning

- `mhm-polar-bridge.php` header: 1.8.0 → 1.9.0
- `readme.txt` Stable tag
- `CHANGELOG.md` detaylı entry (RenewalReminderCron + branding + polar_customer_id provisioning)

---

### mhm-rentiva v4.32.0

#### 1. LicenseManager — `createCustomerPortalSession()` public method

`src/Admin/Licensing/LicenseManager.php`:

```php
/**
 * Mint a Polar customer portal session via license-server.
 *
 * @param string $return_url Optional return URL passed to Polar.
 * @return array{success:true, customer_portal_url:string, expires_at:string}
 *               | array{success:false, error_code:string}
 */
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
        'customer_portal_url' => (string) $response['data']['customer_portal_url'],
        'expires_at'          => (string) ($response['data']['expires_at'] ?? ''),
    ];
}
```

Mevcut `request()` private metodu RSA imza doğrulamasını v4.30.0+ pattern'iyle yapıyor — bu metod için ekstra imza kodu yok.

#### 2. LicenseAdmin — admin-post handler

`src/Admin/Licensing/LicenseAdmin.php` `init()` veya constructor'da:

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

#### 3. License sayfası — buton render

Mevcut "License Management" h2 bloku (line 308-345) güncellenir. Üç buton soldan sağa: Manage Subscription (state-driven primary) → Re-validate Now (gri) → Deactivate License (gri, confirm).

**Helper metodlar:**

```php
private static function compute_days_remaining(array $license_data): ?int {
    $expires_raw = $license_data['expires_at'] ?? $license_data['expires'] ?? '';
    if (empty($expires_raw)) return null;
    $ts = is_numeric($expires_raw) ? (int) $expires_raw : strtotime($expires_raw);
    if ($ts === false) return null;
    $diff = $ts - time();
    return $diff <= 0 ? 0 : (int) ceil($diff / DAY_IN_SECONDS);
}

private static function compute_emphasis_class(?int $days_remaining): string {
    if ($days_remaining === null) return '';
    if ($days_remaining <= 7)  return 'mhm-rentiva-license-urgent';
    if ($days_remaining <= 30) return 'mhm-rentiva-license-warning';
    return '';
}
```

**Buton render:**

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

Mevcut "Re-validate Now" ve "Deactivate License" markup'ı korunur.

#### 4. CSS — state-driven emphasis

Yeni dosya: `assets/css/license-admin.css`. Enqueue `LicenseAdmin::enqueue_admin_styles()` mevcut hook'una eklenir; cache-busting için `MHM_RENTIVA_VERSION . '.' . filemtime($path)` (v4.26.5 pattern).

```css
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

#### 5. Notice akışı — `?license=manage_unavailable&reason=...`

`display_admin_notices()` switch'ine yeni case eklenir. 5 hata kodu için kısa açıklama label'ı (`get_manage_unavailable_label()` helper). Notice text:

> ℹ️ Subscription management is not available right now (service unavailable). Please try again later or contact support@wpalemi.com.

#### 6. PHPUnit kapsamı

- `tests/Admin/Licensing/LicenseManagerCustomerPortalSessionTest.php` — happy/error path'leri
- `tests/Admin/Licensing/LicenseAdminManageSubscriptionTest.php` — capability + nonce + redirect
- `tests/Admin/Licensing/EmphasisClassTest.php` — threshold mantığı (60d → '', 25d → warning, 5d → urgent, 0d → urgent)

Toplam ~17 yeni test. 793 → ~810.

#### 7. i18n

Yaklaşık 6 yeni string (TR + EN). `wp-i18n` skill invoke edilir — `.pot` regen + `msgmerge` (fuzzy temizleme — v4.30.0 öğrenisi) + `.mo` + `.l10n.php` + `wp cache flush`.

#### 8. Versioning

- `mhm-rentiva.php` header + `MHM_RENTIVA_VERSION`: 4.31.2 → 4.32.0
- `readme.txt` Stable tag + Changelog
- `README.md` + `README-tr.md` badge
- `changelog.json` + `changelog-tr.json`
- ZIP build: `python bin/build-release.py`
- GitHub release + ZIP asset
- Docs blog: `mhm-rentiva-docs/blog/2026-04-26-v4.32.0-release.md` + `mhm-rentiva-docs/i18n/tr/docusaurus-plugin-content-blog/2026-04-26-v4.32.0-release.md`

---

### mhm-currency-switcher v0.7.0

CS React tabanlı (admin-app/), Rentiva PHP echo. Pattern paritesi.

#### 1. PHP — LicenseManager metodu

`src/License/LicenseManager.php`'e snake_case kopya:

```php
public function create_customer_portal_session(string $return_url = ''): array {
    $license = $this->get_license();
    if (empty($license['key']) || !$this->is_active()) {
        return ['success' => false, 'error_code' => 'license_not_active'];
    }

    $response = $this->request(
        'licenses/customer-portal-session',
        [
            'license_key' => $license['key'],
            'site_hash'   => $this->get_site_hash(),
            'return_url'  => $return_url,
        ]
    );

    if (is_wp_error($response) || empty($response['success'])) {
        return ['success' => false, 'error_code' => $response['error'] ?? 'unknown_error'];
    }

    return [
        'success'             => true,
        'customer_portal_url' => (string) $response['data']['customer_portal_url'],
        'expires_at'          => (string) ($response['data']['expires_at'] ?? ''),
    ];
}
```

`request()` v0.6.0+ RSA verify yapısı — ekstra kod yok. CS plugin için doğrulama: `get_site_hash()` zaten public mi (Rentiva v4.31.0'da private→public yapıldı; CS v0.6.0'da paritede yapılmış olmalı — implementation öncesi dosyada teyit).

#### 2. PHP — admin REST endpoint

CS namespace'i `mhm-currency/v1`. Yeni route:

```php
register_rest_route('mhm-currency/v1', '/license/manage-subscription', [
    'methods'             => 'POST',
    'callback'            => [$this, 'create_manage_subscription_url'],
    'permission_callback' => fn() => current_user_can('manage_options'),
]);
```

Handler:

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

Status 200 + `success:false` (mevcut License.jsx error handling pattern'i) — JS tarafında daha basit branching.

#### 3. JSX — License.jsx içinde Manage Subscription butonu

`admin-app/src/components/tabs/License.jsx`. Yeni callback `handleManageSubscription`, yeni state `manageLoading`, yeni helper `emphasisClass()`.

Buton render Deactivate butonunun **soluna** eklenir (Q3 placement I):

```jsx
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
```

`handleManageSubscription`:

```jsx
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

Lite kullanıcı için: render path `isActive && license.key` koşulunda olduğundan buton gizli.

#### 4. CSS — emphasis class

`admin-app/src/style.css`'e ekleme. WP admin global button stilleri yüksek specificity'de olduğu için `!important` kullanılır (kasıtlı, yorum eşliğinde):

```css
/* WP admin button styles take high specificity — !important is intentional. */
.mhm-cs-license-warning {
    background-color: #fbbf24 !important;
    border-color: #d97706 !important;
    color: #78350f !important;
}
.mhm-cs-license-warning:hover {
    background-color: #f59e0b !important;
    border-color: #b45309 !important;
    color: #451a03 !important;
}
.mhm-cs-license-urgent {
    background-color: #f97316 !important;
    border-color: #c2410c !important;
    color: #fff !important;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.18) !important;
}
.mhm-cs-license-urgent:hover {
    background-color: #ea580c !important;
    border-color: #9a3412 !important;
    box-shadow: 0 0 0 4px rgba(234, 88, 12, 0.24) !important;
}
```

#### 5. Build pipeline

```bash
cd admin-app && npm run build
```

PHPCS / PHPUnit / PHPStan koşmadan önce build zorunlu (admin-app/dist çıktısı pre-push CI parity'de olmalı).

#### 6. PHPUnit kapsamı

- `tests/License/LicenseManagerCustomerPortalSessionTest.php` (Rentiva test'in snake_case kopyası)
- `tests/REST/ManageSubscriptionEndpointTest.php` — capability, success, error path

Toplam ~10 yeni test. 148 → ~158.

JS tarafı için CS'in mevcut admin-app/ test infrastructure'ı varsa pattern devam ettirilir; yoksa scope dışı (E2E senaryosu manuel kapatır).

#### 7. i18n

Yaklaşık 4 yeni string (TR + EN). `wp-i18n` skill invoke edilir.

#### 8. Versioning

- `mhm-currency-switcher.php` header: 0.6.5 → 0.7.0
- Version constant (implementation öncesi tam adı dosyadan teyit; muhtemelen `MHM_CURRENCY_SWITCHER_VERSION`)
- `readme.txt` Stable tag + Changelog
- README badge
- ZIP build: `bin/build-release.py`
- GitHub release + ZIP asset
- Docs blog: `mhm-rentiva-docs/blog/2026-04-26-cs-v0.7.0-release.md` + i18n TR varyantı (CS kategorisi altında, paylaşılan docs site)
- CI: PHPUnit matrisi (PHP 7.4/8.1/8.2/8.3 × WP 6.0/6.4/latest), PHPCS (baseline 27 korunmalı), PHPStan level 6

---

## Test stratejisi

### Test piramidi

```
              ▲ E2E (sandbox real Polar)        ~5 senaryo
              │ — gerçek webhook + gerçek email
              │
        ▲ Integration (Docker WP + license-server)  ~15 senaryo
        │ — gerçek HTTP, sadece Polar API mocked
        │
   ▲ Unit (PHPUnit, mocked everything)  ~50 yeni test
   │ — encapsulation kontrolleri
```

### Unit (PHPUnit) — TDD

Her component'in PHPUnit kapsamı yukarıda detaylanmıştı. Toplam ~50 yeni test.

**Mock pattern (Polar API):** `pre_http_request` filter (v4.30.0 öğrenisi). `tearDown()`'da `remove_all_filters('pre_http_request')` zorunlu.

**Mock pattern (zaman):** RenewalReminderCron'un zaman pencerelerinde mock'lanabilir `time()` — sandbox'ta gerçek 7 gün beklemek pratik değil; runtime'da hiç mock yok.

**Mock pattern (wp_mail):** `pre_wp_mail` filter veya WP_UnitTestCase MockMailer.

### Integration — Docker WP stack

`c:/projects/rentiva-dev` Docker stack'inde:

1. `/customer-portal-session` happy path — `wp eval` ile license seed + curl ile imzalı POST
2. RenewalReminderCron eligibility — `wp user create` + `update_user_meta wpalemi_licenses` fixture'lar + `wp cron event run` + Mailpit (port 8025) inbox doğrulama
3. Schema migration upgrade — eski tablo seed et, plugin reactivate, kolon kontrol
4. Provisioner backward-compat — eski payload'la (polar_customer_id yok) license create
5. Branding refresh — 6 lifecycle email tetikle, Mailpit From header doğrula

### E2E (sandbox real Polar) — manuel

Polar Sandbox org + 4 test product (Rentiva Monthly/Yearly + CS Monthly/Yearly) + sandbox webhook + test customer card.

**5 senaryo:**

1. **Happy path — Manage Subscription tıklama:** sandbox subscription aktif → plugin'de buton tıkla → yeni sekme Polar customer portal açılır → portal'da gerçek subscription görünür → URL Polar TTL süresi sonunda expire eder
2. **Emphasis state geçişleri:** sandbox subscription period_end manipüle (Polar UI veya yeni kısa-vadeli test product) → plugin sayfasını refresh → ≤30 sarı → ≤7 amber → expired Lite UI
3. **Renewal reminder — aylık abone:** sandbox monthly subscription, expires_at 7 gün sonra → `wp cron event run mhm_polar_renewal_reminder_daily` → Mailpit/Gmail inbox'ta email (From `MHM by WP Alemi`, subject `... renews in 7 days`)
4. **Renewal reminder idempotency:** senaryo 3'ten sonra cron tekrar çalıştır → ikinci email **gelmez**
5. **Renewal reminder reset:** subscription.updated webhook simulate (`c:/tmp/polar-refund-simulate.py` paterni) → expires_at güncellenir → `renewal_reminder_sent_at` clear → sonraki dönem cron yeniden tetiklenir

### CI parity disiplini

Her push öncesi 4-yönlü kontrol (lokal, manuel) — `feedback_pre_push_ci_parity.md` (hot.md 2026-04-25 öğrenisi):

- license-server: `composer phpcs` + `vendor/bin/phpstan --memory-limit=2G` + `vendor/bin/phpunit` (PHP 8.x lokal + Docker PHP 7.4)
- polar-bridge: `composer phpcs --filter=plugins/mhm-polar-bridge/` + phpstan + phpunit
- rentiva: phpcs + phpstan + phpunit
- CS: `npm run build` (admin-app değiştiyse) + phpcs (baseline 27 korumalı) + phpstan + phpunit

Bu disiplin atlanırsa hot.md'deki "8-release zinciri" senaryosu tekrarlanır.

### wp-conductor zorunlu görev TodoList

Her release için:

- [ ] Geliştirme tamamlandı + testler geçiyor
- [ ] WPCS PASS (0 error)
- [ ] i18n güncel (fuzzy temizleme dahil)
- [ ] superpowers:verification-before-completion uygulandı
- [ ] wp-reflect çalıştırıldı
- [ ] Docs güncellendi
- [ ] ZIP oluşturuldu
- [ ] GitHub release + asset
- [ ] Docs blog yazısı yayınlandı (EN + TR)
- [ ] GitHub push edildi
- [ ] Per-Session Cleanup Gate

---

## Deploy ordering

### Sıra (sandbox + production aynı disiplin)

```
1. license-server v1.11.0  →  2. polar-bridge v1.9.0  →  3. plugins paralel
                                                            (rentiva v4.32.0 + CS v0.7.0)
```

**Server first** çünkü yeni endpoint ve schema kolonu önce hazır olmalı.

**Bridge second** çünkü provisioner `polar_customer_id` artık gönderiyor — server bu alanı kabul ettikten sonra anlamlı.

**Plugins paralel** çünkü birbirine bağımlı değil.

### Backward-compat matrisi

| Server | Bridge | Plugin | Davranış |
|---|---|---|---|
| Eski | Eski | Eski | Mevcut akış, değişiklik yok |
| **Yeni** | Eski | Eski | Endpoint hazır, plugin bilmiyor — sorun yok |
| Yeni | **Yeni** | Eski | Yeni satırlar polar_customer_id ile, eski plugin kullanmıyor — sorun yok |
| Yeni | Yeni | **Yeni** | Tam akış canlı |
| Eski | Yeni | Yeni | **Kırık** — plugin endpoint çağırıyor, server 404. Sıra disiplinli takip edildiğinde gerçekleşmez. |

### Sandbox deploy

- Server: Hostinger MCP `hosting_deployWordpressPlugin` veya manuel WP Admin upload (büyük ZIP'ler için MCP token limit aşar — hot.md 2026-04-23 notu)
- Bridge: Hostinger MCP normal çalışır (küçük plugin)
- Plugins: müşteri-vari sandbox (rentiva-dev Docker veya ayrı sandbox WP), GitHub release ZIP veya mhm-plugin-updater auto-update

Her deploy sonrası schema migration + cron register kontrolü:

```bash
# Server migration
wp eval 'MHMLicenseServer\Database\DatabaseManager::migrate();'

# Bridge cron register
wp cron event list | grep mhm_polar_renewal_reminder_daily
```

### Production'a açılma checklist

1. Polar API token swap: `MHM_POLAR_API_TOKEN` sandbox → prod
2. `MHM_POLAR_SANDBOX` constant remove (default false)
3. Polar webhook URL prod org panelinde wpalemi.com'a yönlendir (kontrol — sandbox webhook'tan farklı endpoint mi?)
4. Production smoke test: gerçek Polar prod hesabıyla küçük tutar test subscription buy → webhook → license-server → plugin Manage Subscription tıkla → portal_url 200
5. Email deliverability: `MHM by WP Alemi <support@wpalemi.com>` From header DKIM/SPF imzasını etkilemez (domain aynı)

### Rollback planı

| Aşama | Sorun | Rollback |
|---|---|---|
| Server v1.11.0 | Endpoint patlıyor / migration başarısız | git checkout v1.10.1 + plugin reactivate; `polar_customer_id` kolonu kalır (zararsız) |
| Bridge v1.9.0 | Cron infinite loop / email storm | Plugin deactivate, git checkout v1.8.0, reactivate; user_meta flag'leri kalır (zararsız) |
| Plugin v4.32.0 / v0.7.0 | Manage Subscription buton 500 hatası | Plugin downgrade (manuel ZIP swap); CSS ile buton gizle (hızlı patch) |

---

## Riskler ve azaltma

| Risk | Olasılık | Etki | Azaltma |
|---|---|---|---|
| Polar customer-sessions TTL belirsiz | Düşük | Orta | On-demand minting (server endpoint + redirect aynı request cycle) |
| Polar webhook payload schema değişimi (customer_id field name) | Düşük | Yüksek | WebhookHandler defansif parse: `$payload['customer']['id'] ?? $payload['customer_id'] ?? ''` |
| Polar customer-sessions API rate limit (docs'ta belirtilmiyor) | Düşük | Düşük | License-server tarafında 10 req/dk per license Polar'a ulaşmadan önce kapı kapatır |
| Cron büyük user table'da yavaş | Orta (production'da büyürken) | Düşük | Sandbox <100 user; production'da 1000+ olursa meta_query optimization v1.10.x'te |
| Email deliverability — From name değişikliği spam filter heuristic | Düşük | Düşük | Domain wpalemi.com aynı, DKIM imzası aynı; DMARC From name'e bakmaz |
| Pop-up blocker yeni sekmeyi engellemesi | Düşük | Düşük | Form `target="_blank"` + `rel="noopener"`; engellenirse fallback yok (YAGNI) |
| Sandbox webhook URL'i prod'a sızıntısı | Düşük (kullanıcı kontrolü) | Yüksek | wp-config'de `MHM_POLAR_SANDBOX` branch; production checklist explicit teyit |
| Test pollution sandbox/prod arası | Sıfır | Sıfır | Sandbox tamamen ayrı org/DB/API base — çapraz değme yok |

---

## Out of scope (YAGNI / future)

- Yıllık abonelere kendi reminder email'imizi göndermek (Polar zaten gönderiyor)
- Past_due / dunning email gönderimi (Polar zaten gönderiyor)
- Past_due özel UI state (`Update Payment Method` label) — v4.33.0+ enhancement
- Cancel_at_period_end için "Resume Subscription" özel label — generic "Manage Subscription" yeterli
- 5 mevcut lifecycle email'in TR locale varyantı — ayrı polar-bridge v1.10.0 spec
- License-server tarafında reminder log tablosu (audit trail) — user_meta flag yeterli, production büyüdükçe gözden geçirilir
- Buton render'ında subscription state'inin (cancel_at_period_end, past_due) detaylı görünürlüğü — license-server validate response bu alanları döndürmüyor; v4.33.0+
- Rate limit 429'a UX-friendly geri sayım — error_log + generic toast yeterli
- Polar's native license-keys benefit feature kullanımı — kendi mhm-license-server'ımızı kullanıyoruz, geçiş bu spec'in kapsamı dışında

---

## Spec yazımı sırasında doğrulanacak detaylar

Implementation aşamasında dosyadan teyit edilecek noktalar (placeholder değil; sadece dosya okunarak teyit edilecek mevcut konvansiyonlar):

1. `MHM_RENTIVA_VERSION` constant adı (mhm-rentiva.php header)
2. CS version constant adı (`MHM_CURRENCY_SWITCHER_VERSION` muhtemel)
3. CS `LicenseManager::get_site_hash()` public mi (Rentiva v4.31.0 paritede yapılmış olmalı)
4. CS REST namespace tam yolu (`mhm-currency/v1` muhtemel; License.jsx mevcut çağrılarından okunur)
5. mhm-rentiva-docs CS kategorisi konvansiyonu (frontmatter tags veya prefix)
6. `wp_mail` test mock pattern'i polar-bridge mevcut testlerde nasıl yapılıyor

WordPress.org submission disclosure: Plugin doğrudan Polar'ı çağırmıyor — yalnızca license-server'ı çağırıyor (mevcut, disclosed). Plugin tarafında ekstra disclosure gerekmez.

---

## Acceptance kriterleri

Spec başarılı sayılır eğer:

1. **Sandbox happy path:** Test subscription buy → license activate → 7 gün önce reminder email Mailpit'te yakalanır → Manage Subscription tıkla → Polar customer portal yeni sekmede 200
2. **Sandbox edge cases:** Yıllık abone reminder almaz; cancel_at_period_end abone reminder almaz; idempotency (ikinci cron run email göndermez); subscription.updated reminder flag clear
3. **Backward-compat:** Eski plugin (v4.31.x) yeni server (v1.11.0) ile çalışır; eski server (v1.10.x) yeni plugin (v4.32.0) ile graceful fail (buton "Service unavailable")
4. **CI parity:** 4 plugin'in tamamında PHPCS 0 error (CS baseline 27 korunur), PHPStan level 6 (varsa) 0 error, PHPUnit PHP 7.4/8.x matrisi yeşil
5. **Brand tutarlılığı:** 6 lifecycle email'in tamamı `From: MHM by WP Alemi <support@wpalemi.com>` + `(WP Alemi)` imzası
6. **Production parity:** Sandbox'ta yapılan herhangi bir senaryo, production geçişi sonrasında aynı kod yolu ile çalışır

---

## Sıradaki adım

Bu design doc onaylandıktan sonra `superpowers:writing-plans` skill'i invoke edilerek somut implementation plan dosyası `2026-04-26-license-renewal-plan.md` üretilir. Plan dosyası TDD adımlarını, dosya bazlı değişiklik listesini, commit boundary'lerini ve review checkpoint'leri içerir.
