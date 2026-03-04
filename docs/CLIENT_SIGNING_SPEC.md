# Client Signing Spec (Rentiva -> License Server)

## Purpose
This document defines how Rentiva signs license API requests sent to the license server.

The contract is strict and must match server-side `RequestGuard` verification.

## Required Headers
- `X-MHM-SITE`
- `X-MHM-API-KEY` (when credentials are configured)
- `X-MHM-TIMESTAMP` (when credentials are configured)
- `X-MHM-SIGNATURE` (when credentials are configured)

## Canonical Message
Format:

```text
METHOD|PATH|TIMESTAMP|BODY_SHA256
```

Rules:
- `METHOD`: uppercase HTTP method (currently `POST`)
- `PATH`: canonical route path, without `/wp-json`
- `TIMESTAMP`: unix epoch seconds string
- `BODY_SHA256`: `hash('sha256', raw_json_body)`

## Canonical Path
Client request helper receives endpoint paths such as:

```text
/licenses/activate
```

Canonical path must be:

```text
/mhm-license/v1/licenses/activate
```

Do not include `/wp-json` in canonical path.

## Body Encoding
Body must be encoded with deterministic flags:

```php
wp_json_encode(
    $body,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
)
```

## Example Signed Request
HTTP request:

```text
POST /wp-json/mhm-license/v1/licenses/activate
```

Headers:

```text
X-MHM-API-KEY: abc123
X-MHM-TIMESTAMP: 1700000000
X-MHM-SITE: 4b7c2d...
X-MHM-SIGNATURE: 8f3e...
```

Canonical message:

```text
POST|/mhm-license/v1/licenses/activate|1700000000|9f2e...
```

Signature:

```php
hash_hmac('sha256', $canonicalMessage, $hmacSecret)
```

## Credential Resolution
Priority:
1. `MHM_RENTIVA_LICENSE_API_KEY` / `MHM_RENTIVA_LICENSE_HMAC_SECRET` constants
2. Environment fallback values

If either credential is missing:
- request still sends `X-MHM-SITE`
- signing headers are omitted (backward compatibility with `off`/`monitor` modes)

## Troubleshooting
If signature validation fails:
1. Confirm canonical path excludes `/wp-json`.
2. Confirm timestamp is fresh and within server replay window.
3. Confirm body hash uses the exact raw request body.
4. Confirm API key and HMAC secret are from the same environment.
5. Confirm no proxy or middleware rewrites request body.

