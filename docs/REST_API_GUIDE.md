# REST API Development Guide

## 📁 REST API File Structure

REST API endpoints are organized in the following folders:

```
src/Admin/REST/
├── APIKeyManager.php        # API key management
├── Availability.php          # Availability endpoint
├── EndpointListHelper.php   # Endpoint list helper
├── ErrorHandler.php          # Error handling
├── Helpers/
│   ├── AuthHelper.php        # ✅ Uses RESTSettings
│   ├── ValidationHelper.php  # Validation helpers
│   └── SecureToken.php       # Token management
└── Settings/
    └── RESTSettings.php      # ✅ REST Settings (configuration)

src/Admin/Payment/REST/
├── Payments.php              # General payment endpoints (wrapper)
├── Payments/                 # Payment endpoints
│   ├── Payments.php
│   ├── CreateIntent.php
│   ├── Refund.php
│   └── Helpers/
│       ├── ResponseHelper.php
│       └── Validation.php
├── PayPal.php               # PayPal endpoints (wrapper)
├── PayPal/                   # PayPal endpoints
│   ├── PayPal.php
│   ├── CreateOrder.php
│   ├── CapturePayment.php
│   ├── Refund.php
│   ├── Webhook.php
│   └── Helpers/
│       ├── Auth.php
│       ├── RateLimit.php
│       └── Validation.php
├── PayTR.php                # PayTR endpoints (wrapper)
├── PayTR/                   # PayTR endpoints
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
├── Messages.php             # Message endpoints (wrapper)
├── Admin/                   # Admin message endpoints
│   ├── GetMessages.php
│   ├── GetMessage.php
│   ├── UpdateStatus.php
│   └── ReplyToMessage.php
├── Customer/               # Customer message endpoints
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

## 🔨 Creating New REST API Endpoints

### 1. Create New Endpoint File

Example: `src/Admin/REST/Vehicles.php`

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
        // ✅ Security check using RESTSettings
        register_rest_route('mhm-rentiva/v1', '/vehicles', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_vehicles'],
            'permission_callback' => [self::class, 'permission_check'], // ✅ Security check
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
     * Permission callback - Security check using RESTSettings
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
        // Note: get_client_ip() is private, so use AuthHelper or $_SERVER
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $client_ip; // or user_id, token, etc.
        
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

### 2. Register in Plugin.php

Add to `src/Plugin.php` in `initialize_rest_services()` or similar function:

```php
if (class_exists('MHMRentiva\Admin\REST\Vehicles')) {
    \MHMRentiva\Admin\REST\Vehicles::register();
}
```

---

## 🔐 RESTSettings Integration

### Migrating Existing Endpoints to RESTSettings

**Example:** Update `Availability.php` file:

```php
// ❌ OLD (uses RateLimiter):
public static function permission_check(): bool
{
    $client_ip = \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP();
    return \MHMRentiva\Admin\Core\Utilities\RateLimiter::check($client_ip, 'general');
}

// ✅ NEW (uses RESTSettings):
public static function permission_check(\WP_REST_Request $request): bool|\WP_Error
{
    // 1. Security check
    if (!RESTSettings::check_security($request)) {
        return new \WP_Error('rest_forbidden', 'Access forbidden', ['status' => 403]);
    }

    // 2. Rate limiting check
    // Note: get_client_ip() is private, so use $_SERVER directly or AuthHelper
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RESTSettings::check_rate_limit($client_ip, 'default')) {
        return new \WP_Error('rate_limit_exceeded', 'Too many requests', ['status' => 429]);
    }

    return true;
}
```

---

## 📍 Available REST API Endpoints

### Active Endpoints:

1. **Availability**
   - `GET/POST /wp-json/mhm-rentiva/v1/availability`
   - `GET/POST /wp-json/mhm-rentiva/v1/availability/with-alternatives`
   - **Note:** This endpoint does not yet use RESTSettings (uses old RateLimiter)

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

## ✅ RESTSettings Integration Checklist

When creating new endpoints:

- [ ] Is `RESTSettings::check_security($request)` being used?
- [ ] Is `RESTSettings::check_rate_limit($identifier, $type)` being used?
- [ ] Is `permission_callback` properly implemented?
- [ ] Is error handling present?
- [ ] Is response format standardized?

**Important Notes:**
- `RESTSettings::get_client_ip()` method is **private** and cannot be called directly
- Use `$_SERVER['REMOTE_ADDR']` to get IP address or use `AuthHelper` class
- `AuthHelper::verifyAuth()` method performs both security and rate limiting checks

---

## 🎯 Example: New Vehicles Endpoint

Complete working example:

```php
// src/Admin/REST/Vehicles.php
```

Would you like to create this file?

