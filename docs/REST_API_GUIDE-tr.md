# REST API Oluşturma Rehberi

## 📁 REST API Dosya Yapısı

REST API endpoint'leri şu klasörlerde organize edilir:

```
src/Admin/REST/
├── APIKeyManager.php        # API anahtarı yönetimi
├── Availability.php          # Müsaitlik endpoint'i
├── EndpointListHelper.php   # Endpoint listesi helper'ı
├── ErrorHandler.php          # Hata yönetimi
├── Helpers/
│   ├── AuthHelper.php        # ✅ RESTSettings kullanıyor
│   ├── ValidationHelper.php  # Validasyon helper'ları
│   └── SecureToken.php       # Token yönetimi
└── Settings/
    └── RESTSettings.php      # ✅ REST Settings (ayarlar)

src/Admin/Payment/REST/
├── Payments.php              # Genel ödeme endpoint'leri (wrapper)
├── Payments/                 # Ödeme endpoint'leri
│   ├── Payments.php
│   ├── CreateIntent.php
│   ├── Refund.php
│   └── Helpers/
│       ├── ResponseHelper.php
│       └── Validation.php
├── PayPal.php               # PayPal endpoint'leri (wrapper)
├── PayPal/                   # PayPal endpoint'leri
│   ├── PayPal.php
│   ├── CreateOrder.php
│   ├── CapturePayment.php
│   ├── Refund.php
│   ├── Webhook.php
│   └── Helpers/
│       ├── Auth.php
│       ├── RateLimit.php
│       └── Validation.php
├── PayTR.php                # PayTR endpoint'leri (wrapper)
├── PayTR/                   # PayTR endpoint'leri
│   ├── CreateToken.php
│   ├── Callback.php
│   └── Helpers/
│       ├── Auth.php
│       ├── BookingQuery.php
│       ├── RateLimit.php
│       └── Validation.php
├── StripeWebhook.php        # Stripe webhook (wrapper)
└── StripeWebhook/          # Stripe webhook
    ├── Webhook.php
    └── Helpers/
        ├── BookingQuery.php
        ├── EventProcessor.php
        └── SignatureVerifier.php

src/Admin/Messages/REST/
├── Messages.php             # Mesaj endpoint'leri (wrapper)
├── Admin/                   # Admin mesaj endpoint'leri
│   ├── GetMessages.php
│   ├── GetMessage.php
│   ├── UpdateStatus.php
│   └── ReplyToMessage.php
├── Customer/               # Müşteri mesaj endpoint'leri
│   ├── GetMessages.php
│   ├── SendMessage.php
│   ├── GetThread.php
│   ├── SendReply.php
│   ├── GetBookings.php
│   └── CloseMessage.php
└── Helpers/
    ├── Auth.php
    ├── MessageFormatter.php
    └── MessageQuery.php
```

---

## 🔨 Yeni REST API Endpoint Oluşturma

### 1. Yeni Endpoint Dosyası Oluştur

Örnek: `src/Admin/REST/Vehicles.php`

```php
<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\REST\Settings\RESTSettings;
use MHMRentiva\Admin\REST\Helpers\AuthHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class Vehicles
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        // ✅ RESTSettings kullanarak güvenlik kontrolü
        register_rest_route('mhm-rentiva/v1', '/vehicles', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_vehicles'],
            'permission_callback' => [self::class, 'permission_check'], // ✅ Güvenlik kontrolü
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);
    }

    /**
     * Permission callback - RESTSettings kullanarak güvenlik kontrolü
     */
    public static function permission_check(\WP_REST_Request $request): bool|\WP_Error
    {
        // ✅ 1. Security check (HTTPS, IP, User Agent)
        if (!RESTSettings::check_security($request)) {
            return new \WP_Error(
                'rest_forbidden',
                __('Access forbidden by security settings.', 'mhm-rentiva'),
                ['status' => 403]
            );
        }

        // ✅ 2. Rate limiting check
        // Not: get_client_ip() private olduğu için AuthHelper kullanın
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $client_ip; // veya user_id, token, vb.
        
        if (!RESTSettings::check_rate_limit($identifier, 'default')) {
            return new \WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.', 'mhm-rentiva'),
                ['status' => 429]
            );
        }

        return true;
    }

    /**
     * Get vehicles endpoint
     */
    public static function get_vehicles(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');

        // Vehicle query logic here
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ]);

        $data = array_map(function($vehicle) {
            return [
                'id' => $vehicle->ID,
                'title' => $vehicle->post_title,
                'content' => $vehicle->post_content,
                // ... more fields
            ];
        }, $vehicles);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
            'total' => count($vehicles),
        ], 200);
    }
}
```

---

### 2. Plugin.php'ye Kaydet

`src/Plugin.php` dosyasında `initialize_rest_services()` veya benzer bir fonksiyona ekleyin:

```php
if (class_exists('MHMRentiva\Admin\REST\Vehicles')) {
    \MHMRentiva\Admin\REST\Vehicles::register();
}
```

---

## 🔐 RESTSettings Entegrasyonu

### Mevcut Endpoint'leri RESTSettings'e Geçirme

**Örnek:** `Availability.php` dosyasını güncelle:

```php
// ❌ ESKİ (RateLimiter kullanıyor):
public static function permission_check(): bool
{
    $client_ip = \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP();
    return \MHMRentiva\Admin\Core\Utilities\RateLimiter::check($client_ip, 'general');
}

// ✅ YENİ (RESTSettings kullanıyor):
public static function permission_check(\WP_REST_Request $request): bool|\WP_Error
{
    // 1. Security check
    if (!RESTSettings::check_security($request)) {
        return new \WP_Error('rest_forbidden', 'Access forbidden', ['status' => 403]);
    }

    // 2. Rate limiting check
    // Not: get_client_ip() private olduğu için doğrudan $_SERVER kullanın veya AuthHelper kullanın
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RESTSettings::check_rate_limit($client_ip, 'default')) {
        return new \WP_Error('rate_limit_exceeded', 'Too many requests', ['status' => 429]);
    }

    return true;
}
```

---

## 📍 Mevcut REST API Endpoint'leri

### Aktif Endpoint'ler:

1. **Availability**
   - `GET/POST /wp-json/mhm-rentiva/v1/availability`
   - `GET/POST /wp-json/mhm-rentiva/v1/availability/with-alternatives`
   - **Not:** Bu endpoint henüz RESTSettings kullanmıyor (eski RateLimiter kullanıyor)

2. **Messages** (Admin)
   - `GET /wp-json/mhm-rentiva/v1/messages`
   - `GET /wp-json/mhm-rentiva/v1/messages/{id}`
   - `POST /wp-json/mhm-rentiva/v1/messages/{id}/status`
   - `POST /wp-json/mhm-rentiva/v1/messages/{id}/reply`

3. **Messages** (Customer)
   - `GET /wp-json/mhm-rentiva/v1/customer/messages`
   - `POST /wp-json/mhm-rentiva/v1/customer/messages`
   - `GET /wp-json/mhm-rentiva/v1/customer/messages/thread/{thread_id}`
   - `POST /wp-json/mhm-rentiva/v1/customer/messages/reply`
   - `GET /wp-json/mhm-rentiva/v1/customer/bookings`
   - `POST /wp-json/mhm-rentiva/v1/customer/messages/close`

4. **Payments**
   - `POST /wp-json/mhm-rentiva/v1/payments/create-intent`
   - `POST /wp-json/mhm-rentiva/v1/payments/refund`

5. **PayPal**
   - `POST /wp-json/mhm-rentiva/v1/paypal/create-order`
   - `POST /wp-json/mhm-rentiva/v1/paypal/capture-payment`
   - `POST /wp-json/mhm-rentiva/v1/paypal/webhook`
   - `POST /wp-json/mhm-rentiva/v1/paypal/refund`

6. **PayTR**
   - `POST /wp-json/mhm-rentiva/v1/paytr/create-token`
   - `POST /wp-json/mhm-rentiva/v1/paytr/callback`

7. **Stripe**
   - `POST /wp-json/mhm-rentiva/v1/stripe/webhook`

---

## ✅ RESTSettings Entegrasyon Kontrol Listesi

Yeni endpoint oluştururken:

- [ ] `RESTSettings::check_security($request)` kullanılıyor mu?
- [ ] `RESTSettings::check_rate_limit($identifier, $type)` kullanılıyor mu?
- [ ] `permission_callback` doğru implement edildi mi?
- [ ] Error handling var mı?
- [ ] Response format standart mı?

**Önemli Notlar:**
- `RESTSettings::get_client_ip()` metodu **private** olduğu için doğrudan çağrılamaz
- IP adresini almak için `$_SERVER['REMOTE_ADDR']` kullanın veya `AuthHelper` sınıfını kullanın
- `AuthHelper::verifyAuth()` metodu hem güvenlik hem rate limiting kontrolü yapar

---

## 🎯 Örnek: Yeni Vehicles Endpoint'i

Tam çalışan örnek:

```php
// src/Admin/REST/Vehicles.php
```

Bu dosyayı oluşturmak ister misiniz?

