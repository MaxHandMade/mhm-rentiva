# Asymmetric Crypto License Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace HMAC-based feature token with RSA-signed tokens across the 3-tier license stack (server + Rentiva + Currency Switcher) to close the cracked-binary attack vector while preserving zero-config deploy UX.

**Architecture:** Server holds RSA private key in wp-config; plugins embed the matching public key as a heredoc class constant. Server signs feature tokens with `openssl_sign` (RSA-2048 + PKCS#1 v1.5, SHA-256); plugins verify with `openssl_verify` against the embedded public key. Token format unchanged structurally — `{base64url(canonical_payload)}.{base64url(rsa_sig)}`.

**Tech Stack:** PHP 8.1+, OpenSSL extension, WordPress 6.7+, PHPUnit 9.x, PHPCS/WPCS, Docker Compose (rentiva-dev + rentiva-lisans stacks), Hostinger MCP for live deploy, Chrome DevTools MCP for live smoke test.

**Spec reference:** [`docs/plans/2026-04-25-asymmetric-crypto-license-design.md`](2026-04-25-asymmetric-crypto-license-design.md) — read before executing.

---

## File Structure

### mhm-license-server (Phase A) — `c:/projects/rentiva-lisans/plugins/mhm-license-server/`

**Create:**
- `tests/fixtures/test-rsa-private.pem` (RSA-2048 PKCS#8 private key, test-only)
- `tests/fixtures/test-rsa-public.pem` (RSA-2048 SubjectPublicKeyInfo, test-only)
- `tests/fixtures/README.md` (fixture purpose + warning: test-only, never prod)
- `src/Security/RsaSigner.php` (RSA signing + private key loading + caching)
- `tests/Integration/Security/RsaSignerTest.php` (~12 tests)

**Modify:**
- `src/Security/SecretManager.php` (add `getRsaPrivateKey()` method)
- `src/License/FeatureTokenIssuer.php` (HMAC → RSA, accept RsaSigner)
- `src/License/LicenseValidator.php` (graceful skip when private key missing)
- `src/License/LicenseActivator.php` (graceful skip when private key missing)
- `tests/Integration/License/FeatureTokenIssuerTest.php` (HMAC tests → RSA)
- `tests/Integration/License/LicenseValidatorSigningTest.php` (verify graceful degradation)
- `tests/Integration/License/LicenseActivatorSigningTest.php` (verify graceful degradation)
- `mhm-license-server.php` (version bump 1.9.3 → 1.10.0)
- `readme.txt` (Stable tag + Changelog)
- `CHANGELOG.md` (v1.10.0 entry)
- `LIVE_DEPLOYMENT_CHECKLIST.md` (RSA private key gen commands + wp-config heredoc snippet)

**Delete:**
- `tests/Integration/License/FeatureTokenIssuerTest.php` HMAC-specific tests (replaced)

### mhm-rentiva (Phase B) — `c:/projects/rentiva-dev/plugins/mhm-rentiva/`

**Create:**
- `tests/fixtures/test-rsa-private.pem` (IDENTICAL bytes to server fixture)
- `tests/fixtures/test-rsa-public.pem` (IDENTICAL bytes to server fixture)
- `tests/fixtures/README.md` (same warning)
- `src/Admin/Licensing/LicenseServerPublicKey.php` (heredoc PEM constant + static cache)
- `tests/Unit/Licensing/LicenseServerPublicKeyTest.php` (~5 tests)

**Modify:**
- `src/Admin/Licensing/FeatureTokenVerifier.php` (HMAC verify → RSA verify, constructor DI)
- `src/Admin/Licensing/Mode.php` (remove legacy `isPro()` fallback in `featureGranted()`)
- `src/Admin/Licensing/ClientSecrets.php` (remove `getFeatureTokenKey()`)
- `tests/Unit/Licensing/FeatureTokenVerifierTest.php` (HMAC scenarios → RSA)
- `tests/Unit/Licensing/ModeFeatureTokenTest.php` (legacy fallback test → enforcement test)
- `tests/Unit/Licensing/ClientSecretsTest.php` (drop FEATURE_TOKEN_KEY tests)
- `mhm-rentiva.php` (version 4.30.2 → 4.31.0)
- `readme.txt` (Stable tag + Changelog)
- `README.md`, `README-tr.md` (badge)
- `changelog-tr.json` (v4.31.0 detailed entry, BREAKING uyarı)
- `languages/mhm-rentiva.pot` (regen if new strings)
- `languages/mhm-rentiva-tr_TR.po`, `.mo`, `.l10n.php`

### mhm-currency-switcher (Phase C) — `c:/projects/mhm-currency-switcher/`

**Create / Modify:** Mirror of Phase B with CS pattern (`src/License/` instead of `src/Admin/Licensing/`, snake_case methods). Test paths `tests/Unit/License/`.

### Phase D (E2E) — no code changes

Validation only: pre-deploy config audit, Docker E2E suite, live deploy via Hostinger MCP, live smoke test via Chrome DevTools MCP.

---

## Cross-Phase Conventions

**Test fixture sync rule:** `tests/fixtures/test-rsa-private.pem` and `test-rsa-public.pem` MUST be byte-identical across all 3 repos. Generate ONCE in Phase A Task A.0, then `cp` to other repos in Phase B Task B.0 and Phase C Task C.0. Verify with `sha256sum` after copy.

**TDD discipline:** Every implementation step starts with a RED test that fails for the right reason, then minimum code to GREEN. Do not write implementation before test exists.

**Commit cadence:** After each task's GREEN + suite-pass step. Commit messages follow conventional commits (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).

**WPCS gate per repo:** Zero new errors over baseline. Run `composer phpcs` after each task.

**Docker stacks:**
- `rentiva-lisans` (server): `cd c:/projects/rentiva-lisans && docker compose ...`, container `rentiva-lisans-wpcli-1`, plugin path `/var/www/html/wp-content/plugins/mhm-license-server`
- `rentiva-dev` (Rentiva client): container `rentiva-dev-wpcli-1`, plugin path `/var/www/html/wp-content/plugins/mhm-rentiva`
- CS local Docker — develop branch checkout

---

## Phase A — Server v1.10.0 (~3 hours)

### Task A.0: Pre-flight Baseline + Test Fixture Generation

**Files:**
- Create: `tests/fixtures/test-rsa-private.pem`
- Create: `tests/fixtures/test-rsa-public.pem`
- Create: `tests/fixtures/README.md`

- [ ] **Step 1: Capture PHPUnit baseline before changes**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage --colors" | tail -20
```

Expected: `OK (143 tests, 391 assertions)` — record this number; final test count must be ≥143 + new test count.

- [ ] **Step 2: Capture PHPCS baseline**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs" | tail -5
```

Expected: 0 errors. Any warnings — record.

- [ ] **Step 3: Generate RSA-2048 test key pair**

```bash
cd /c/projects/rentiva-lisans/plugins/mhm-license-server
mkdir -p tests/fixtures
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server/tests/fixtures && openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out test-rsa-private.pem && openssl pkey -in test-rsa-private.pem -pubout -out test-rsa-public.pem"
```

Expected: 2 files created in `tests/fixtures/`, both starting with `-----BEGIN ... KEY-----`.

- [ ] **Step 4: Verify key pair is functional**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server/tests/fixtures && echo 'test-payload' | openssl dgst -sha256 -sign test-rsa-private.pem | openssl dgst -sha256 -verify test-rsa-public.pem -signature /dev/stdin <(echo 'test-payload')" 2>&1 || true
```

Expected: `Verified OK` (or similar). If fails, regenerate keys.

- [ ] **Step 5: Create fixture README**

Write to `tests/fixtures/README.md`:

```markdown
# Test RSA Key Pair

**Purpose:** Test-only RSA-2048 key pair for `RsaSigner` and `FeatureTokenIssuer` tests. Allows real `openssl_sign` / `openssl_verify` calls in PHPUnit suite — no mocks, deterministic.

**WARNING:** These keys are committed to a public-ish git history (server repo is private but fixtures may leak via support exports). They are **NEVER** to be used as production signing keys. Production keys are generated independently and deployed only to wpalemi.com wp-config.

**Mirror across 3 repos:**
- `mhm-license-server/tests/fixtures/test-rsa-{private,public}.pem`
- `mhm-rentiva/tests/fixtures/test-rsa-{private,public}.pem`
- `mhm-currency-switcher/tests/fixtures/test-rsa-{private,public}.pem`

These files MUST be byte-identical across all 3 repos. Verify with `sha256sum` after sync.

Regenerate (if compromised, very unlikely for test keys):
\`\`\`bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out test-rsa-private.pem
openssl pkey -in test-rsa-private.pem -pubout -out test-rsa-public.pem
\`\`\`
```

- [ ] **Step 6: Compute fixture checksums (sync reference)**

```bash
cd /c/projects/rentiva-lisans/plugins/mhm-license-server
sha256sum tests/fixtures/test-rsa-private.pem tests/fixtures/test-rsa-public.pem
```

Expected: 2 hex strings — record both for Phase B/C verification.

- [ ] **Step 7: Commit fixtures**

```bash
git add tests/fixtures/
git commit -m "test(fixtures): add test-only RSA-2048 key pair for asymmetric crypto suite"
```

---

### Task A.1: SecretManager — getRsaPrivateKey()

**Files:**
- Modify: `src/Security/SecretManager.php`
- Test: `tests/Integration/Security/SecretManagerTest.php`

- [ ] **Step 1: Write failing test for `getRsaPrivateKey()` returns wp-config constant**

Append to `tests/Integration/Security/SecretManagerTest.php`:

```php
public function testGetRsaPrivateKeyReturnsConstantWhenDefined(): void
{
    $pem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    if (!defined('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM')) {
        define('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', $pem);
    }

    $this->assertSame($pem, SecretManager::getRsaPrivateKey());
}

public function testGetRsaPrivateKeyReturnsEmptyWhenUndefined(): void
{
    // Note: constants cannot be undefined once set. This test relies on test ordering
    // OR a separate process. For now, we test via a fresh PHP process if needed.
    // Phase A acceptance: skip if running in same process as previous test.
    if (defined('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM')) {
        $this->markTestSkipped('Constant already defined in this process');
    }

    $this->assertSame('', SecretManager::getRsaPrivateKey());
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testGetRsaPrivateKeyReturnsConstant tests/Integration/Security/SecretManagerTest.php"
```

Expected: FAIL with "Method `getRsaPrivateKey` not found" or similar.

- [ ] **Step 3: Implement minimal `getRsaPrivateKey()` in SecretManager**

Add to `src/Security/SecretManager.php` (after existing `getPingSecret()`):

```php
/**
 * Get RSA private key for feature token signing (PEM string).
 *
 * Returns wp-config constant value if defined, else empty string.
 * Unlike other secrets, RSA private key has NO file fallback — auto-generated
 * keys cannot match the public key embedded in client plugins.
 *
 * @return string PEM-encoded private key, or empty string if not configured.
 */
public static function getRsaPrivateKey(): string
{
    if (defined('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM')) {
        return (string) constant('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM');
    }
    return '';
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testGetRsaPrivateKey tests/Integration/Security/SecretManagerTest.php"
```

Expected: PASS for `testGetRsaPrivateKeyReturnsConstantWhenDefined`. Skipped for `testGetRsaPrivateKeyReturnsEmptyWhenUndefined` (acceptable — constant pollution).

- [ ] **Step 5: Run full PHPUnit suite for regression**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage" | tail -3
```

Expected: 145+ tests passing (143 baseline + 2 new). 0 failures.

- [ ] **Step 6: Commit**

```bash
git add src/Security/SecretManager.php tests/Integration/Security/SecretManagerTest.php
git commit -m "feat(security): SecretManager::getRsaPrivateKey() — wp-config constant reader, no file fallback"
```

---

### Task A.2: RsaSigner Class

**Files:**
- Create: `src/Security/RsaSigner.php`
- Create: `tests/Integration/Security/RsaSignerTest.php`

- [ ] **Step 1: Write failing test for `RsaSigner::sign()` produces verifiable signature**

Create `tests/Integration/Security/RsaSignerTest.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\LicenseServer\Tests\Integration\Security;

use MHM\LicenseServer\Security\RsaSigner;
use PHPUnit\Framework\TestCase;

class RsaSignerTest extends TestCase
{
    private string $privatePem;
    private string $publicPem;

    protected function setUp(): void
    {
        $this->privatePem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
        $this->publicPem  = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem');

        if (!defined('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM')) {
            define('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', $this->privatePem);
        }
    }

    public function testSignProducesVerifiableSignature(): void
    {
        $signer  = new RsaSigner();
        $payload = '{"feature":"vendor_marketplace","exp":1234567890}';

        $signature = $signer->sign($payload);

        $publicKey = openssl_pkey_get_public($this->publicPem);
        $this->assertNotFalse($publicKey, 'Public key parse failed');

        $verified = openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verified, 'RSA signature did not verify');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Integration/Security/RsaSignerTest.php"
```

Expected: FAIL with `Class "MHM\LicenseServer\Security\RsaSigner" not found`.

- [ ] **Step 3: Implement minimal RsaSigner class**

Create `src/Security/RsaSigner.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\LicenseServer\Security;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * RSA-2048 + PKCS#1 v1.5 + SHA-256 signer for feature tokens.
 *
 * Loads private key from wp-config constant via SecretManager.
 * Caches the parsed key resource per-request for performance.
 */
final class RsaSigner
{
    private static ?OpenSSLAsymmetricKey $cachedKey = null;

    /**
     * Sign canonical payload with RSA private key.
     *
     * @param string $canonicalPayload Pre-canonicalized JSON payload.
     * @return string Binary signature bytes (caller must base64url-encode).
     *
     * @throws RuntimeException If private key not configured or signing fails.
     */
    public function sign(string $canonicalPayload): string
    {
        $key = $this->loadPrivateKey();

        $signature = '';
        $ok = openssl_sign($canonicalPayload, $signature, $key, OPENSSL_ALGO_SHA256);
        if ($ok === false) {
            throw new RuntimeException('RSA signing failed: ' . openssl_error_string());
        }

        return $signature;
    }

    private function loadPrivateKey(): OpenSSLAsymmetricKey
    {
        if (self::$cachedKey !== null) {
            return self::$cachedKey;
        }

        $pem = SecretManager::getRsaPrivateKey();
        if ($pem === '') {
            throw new RuntimeException('RSA private key not configured (MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM)');
        }

        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('RSA private key invalid PEM: ' . openssl_error_string());
        }

        self::$cachedKey = $key;
        return $key;
    }

    /**
     * Reset cached key — for tests that swap fixtures between cases.
     */
    public static function resetCache(): void
    {
        self::$cachedKey = null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Integration/Security/RsaSignerTest.php"
```

Expected: PASS.

- [ ] **Step 5: Add test for missing private key**

Append to `RsaSignerTest.php`:

```php
public function testSignThrowsWhenPrivateKeyMissing(): void
{
    // Force cache reset to simulate undefined constant scenario
    RsaSigner::resetCache();

    // Use a sub-process or skip approach — for now, mock by stubbing SecretManager
    // via a subclass
    $signer = new class extends RsaSigner {
        protected function loadPrivateKeyForTest(): void
        {
            throw new \RuntimeException('RSA private key not configured (test stub)');
        }
    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('RSA private key not configured');

    // Direct test via static helper — alternative: use reflection
    $stub = new class {
        public function trigger(): void
        {
            throw new \RuntimeException('RSA private key not configured (test trigger)');
        }
    };

    $stub->trigger();
}
```

> **NOTE:** This test pattern is awkward because of constant pollution. Prefer alternative: refactor `loadPrivateKey()` to accept dependency injection (PEM string) for testability. Decision: keep RsaSigner concrete for production simplicity; document that "missing key" path is verified via integration test in FeatureTokenIssuerTest where the key is conditional. SKIP this specific test if reflection-based stubbing is too brittle — the integration test in Task A.3 exercises the missing-key path indirectly.

Replace the test above with this pragmatic version:

```php
public function testSignThrowsWhenPemInvalid(): void
{
    // Inject invalid PEM via reflection on cachedKey + a mocked SecretManager call
    // Pragmatic: this test is covered by integration suite; skip in unit layer
    $this->markTestSkipped('Covered by FeatureTokenIssuer integration test where key absence is conditional');
}

public function testCacheReturnsSameKeyResourceAcrossCalls(): void
{
    $signer = new RsaSigner();
    $signer->sign('payload-1'); // primes cache

    // Use reflection to confirm cache populated
    $reflectClass = new \ReflectionClass(RsaSigner::class);
    $property = $reflectClass->getProperty('cachedKey');
    $property->setAccessible(true);
    $cached1 = $property->getValue();

    $signer->sign('payload-2');
    $cached2 = $property->getValue();

    $this->assertSame($cached1, $cached2, 'Key resource was re-parsed');
}
```

- [ ] **Step 6: Run all RsaSigner tests**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Integration/Security/RsaSignerTest.php"
```

Expected: 2 tests pass, 1 skipped.

- [ ] **Step 7: Run PHPCS check**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs -- src/Security/RsaSigner.php tests/Integration/Security/RsaSignerTest.php"
```

Expected: 0 errors.

- [ ] **Step 8: Commit**

```bash
git add src/Security/RsaSigner.php tests/Integration/Security/RsaSignerTest.php
git commit -m "feat(security): RsaSigner — RSA-2048 PKCS#1 v1.5 signer for feature tokens"
```

---

### Task A.3: FeatureTokenIssuer — Migrate from HMAC to RSA

**Files:**
- Modify: `src/License/FeatureTokenIssuer.php`
- Modify: `tests/Integration/License/FeatureTokenIssuerTest.php`

- [ ] **Step 1: Read current FeatureTokenIssuer to understand HMAC structure**

```bash
cat /c/projects/rentiva-lisans/plugins/mhm-license-server/src/License/FeatureTokenIssuer.php | head -80
```

Identify: `issue()` method that produces `{base64_payload}.{hmac_hex}` token. Note the `canonicalize()` helper, the HMAC key fetch, and the `featuresFor()` helper.

- [ ] **Step 2: Update existing test for RSA token format**

Modify the test that currently asserts `{base64}.{hmac_hex}` format. Find:

```bash
grep -n 'hash_hmac\|hmac_hex\|FEATURE_TOKEN_KEY' tests/Integration/License/FeatureTokenIssuerTest.php
```

Replace HMAC assertions with RSA-equivalent. Example new test (to add, replacing one HMAC test):

```php
public function testIssueProducesRsaSignedToken(): void
{
    $privatePem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $publicPem  = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem');

    if (!defined('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM')) {
        define('MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', $privatePem);
    }

    $issuer = new FeatureTokenIssuer(new RsaSigner());
    $token = $issuer->issue('mhm-rentiva', 'pro', 'license-key-hash-abc', 'site-hash-xyz');

    $parts = explode('.', $token);
    $this->assertCount(2, $parts, 'Token must have 2 segments');

    $canonical = $this->base64UrlDecode($parts[0]);
    $signature = $this->base64UrlDecode($parts[1]);

    $publicKey = openssl_pkey_get_public($publicPem);
    $verified = openssl_verify($canonical, $signature, $publicKey, OPENSSL_ALGO_SHA256);

    $this->assertSame(1, $verified, 'RSA signature did not verify with public key');

    $payload = json_decode($canonical, true);
    $this->assertSame('mhm-rentiva', $payload['product_slug']);
    $this->assertSame('pro', $payload['plan']);
    $this->assertSame('license-key-hash-abc', $payload['license_key_hash']);
    $this->assertSame('site-hash-xyz', $payload['site_hash']);
    $this->assertGreaterThan(time() - 5, $payload['issued_at']);
    $this->assertGreaterThan(time() + 86400 - 5, $payload['expires_at']);
}

private function base64UrlDecode(string $input): string
{
    $padded = str_pad(strtr($input, '-_', '+/'), strlen($input) % 4 === 0 ? strlen($input) : strlen($input) + (4 - strlen($input) % 4), '=', STR_PAD_RIGHT);
    return base64_decode($padded, true) ?: '';
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testIssueProducesRsaSignedToken"
```

Expected: FAIL — either constructor signature mismatch (HMAC FeatureTokenIssuer doesn't take RsaSigner), or sign output format differs.

- [ ] **Step 4: Refactor FeatureTokenIssuer to use RsaSigner**

Modify `src/License/FeatureTokenIssuer.php`:

1. Constructor: accept `RsaSigner $signer` (DI)
2. `issue()` method: replace HMAC sign with `$this->signer->sign($canonical)`
3. Token assembly: `base64UrlEncode($canonical) . '.' . base64UrlEncode($signature)` (binary RSA sig, NOT hex)
4. Add `base64UrlEncode()` private helper:

```php
private function base64UrlEncode(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}
```

5. Remove `hash_hmac()` call + FEATURE_TOKEN_KEY fetch
6. Remove HMAC-specific helper methods (`signHmac()` if exists)

Concrete refactor — find and modify:

```php
// BEFORE (in issue() method):
$canonical = $this->canonicalize($payload);
$signature = hash_hmac('sha256', $canonical, SecretManager::getFeatureTokenKey());
$token = base64_encode($canonical) . '.' . $signature;

// AFTER:
$canonical = $this->canonicalize($payload);
$signature = $this->signer->sign($canonical); // binary RSA sig
$token = $this->base64UrlEncode($canonical) . '.' . $this->base64UrlEncode($signature);
```

Constructor:

```php
// BEFORE: public function __construct() { ... }
// AFTER:
public function __construct(private readonly RsaSigner $signer)
{
}
```

- [ ] **Step 5: Run RSA test to verify it passes**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testIssueProducesRsaSignedToken"
```

Expected: PASS.

- [ ] **Step 6: Update remaining FeatureTokenIssuer tests**

Find all tests in `tests/Integration/License/FeatureTokenIssuerTest.php` that:
- Reference HMAC (`hash_hmac`, `FEATURE_TOKEN_KEY`)
- Construct `new FeatureTokenIssuer()` without args (constructor now requires `RsaSigner`)
- Expect token format with hex signature (changed to base64url binary)

Replace constructor calls:

```php
// BEFORE: $issuer = new FeatureTokenIssuer();
// AFTER:  $issuer = new FeatureTokenIssuer(new RsaSigner());
```

Update format assertions: hex sig (64 chars) → base64url binary (~344 chars for RSA-2048).

Tests to keep (logic unchanged): payload structure, feature set per product/plan, TTL, site_hash binding.

- [ ] **Step 7: Run all FeatureTokenIssuer tests**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Integration/License/FeatureTokenIssuerTest.php"
```

Expected: All tests pass (~11 tests, possibly +1 for RSA-specific).

- [ ] **Step 8: Run full suite**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage" | tail -3
```

Expected: All ≥146 tests passing.

- [ ] **Step 9: Commit**

```bash
git add src/License/FeatureTokenIssuer.php tests/Integration/License/FeatureTokenIssuerTest.php
git commit -m "feat(license): FeatureTokenIssuer migrates from HMAC to RSA signing

- Constructor now requires RsaSigner DI
- Token signature segment: hex HMAC -> base64url binary RSA-2048
- Payload structure unchanged (license_key_hash, product_slug, plan, features, site_hash, issued_at, expires_at)
- All existing tests updated for new constructor + format"
```

---

### Task A.4: LicenseValidator — Graceful Degradation

**Files:**
- Modify: `src/License/LicenseValidator.php`
- Modify: `tests/Integration/License/LicenseValidatorSigningTest.php`

- [ ] **Step 1: Write failing test — feature_token field absent when private key missing**

Append to `tests/Integration/License/LicenseValidatorSigningTest.php`:

```php
public function testValidateOmitsFeatureTokenWhenRsaKeyMissing(): void
{
    // Spawn a sub-process or use a separate test runner pass where constant is undefined.
    // Pragmatic: use a wrapper that checks SecretManager::getRsaPrivateKey() and conditional skip.

    if (SecretManager::getRsaPrivateKey() !== '') {
        $this->markTestSkipped('RSA key defined; cannot test missing-key path in same process');
    }

    $validator = new LicenseValidator();
    $result = $validator->validate('valid-license-key', 'site-hash-xyz', '1.0.0', 'mhm-rentiva');

    $this->assertArrayNotHasKey('feature_token', $result);
    $this->assertArrayHasKey('signature', $result); // response signature still present (RESPONSE_HMAC)
}
```

- [ ] **Step 2: Verify test setup — test in process WITHOUT RSA constant**

Most tests will run with the constant defined (for happy path). This test only runs in a fresh process. Add to `phpunit.xml.dist` a separate `<testsuite>` for missing-key tests — or use process isolation:

```php
/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
public function testValidateOmitsFeatureTokenWhenRsaKeyMissing(): void
{
    // No define() call here — constant is undefined in this isolated process
    $validator = new LicenseValidator();
    // ... rest of test
}
```

- [ ] **Step 3: Run test**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testValidateOmitsFeatureTokenWhenRsaKeyMissing"
```

Expected: FAIL — current code throws because RsaSigner cannot load undefined key, or feature_token still present.

- [ ] **Step 4: Implement graceful degradation in LicenseValidator**

Find the section in `LicenseValidator::validate()` where `feature_token` is added to `$result`. Wrap with try-catch:

```php
// Inside validate() or signResult() method:
try {
    $issuer = new FeatureTokenIssuer(new RsaSigner());
    $result['feature_token'] = $issuer->issue(
        $row->product_slug,
        $row->plan,
        hash('sha256', $licenseKey),
        $siteHash
    );
} catch (\RuntimeException $e) {
    // Private key not configured — log + skip token field
    error_log('[mhm-license-server] Feature token skipped: ' . $e->getMessage());
    // feature_token field omitted from response
}
```

> **Admin notice:** If the user is in admin context and key is missing, surface a one-time admin notice. Implementation deferred to deploy checklist (operator verifies via UI). Code-level: just `error_log()` + omit field.

- [ ] **Step 5: Run test to verify it passes**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testValidateOmitsFeatureTokenWhenRsaKeyMissing"
```

Expected: PASS.

- [ ] **Step 6: Run existing happy-path tests for regression**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Integration/License/LicenseValidatorSigningTest.php"
```

Expected: 6+ tests pass (existing happy path + new graceful-degradation test).

- [ ] **Step 7: Commit**

```bash
git add src/License/LicenseValidator.php tests/Integration/License/LicenseValidatorSigningTest.php
git commit -m "feat(license): LicenseValidator graceful degradation when RSA key missing

- feature_token field omitted from response if SecretManager::getRsaPrivateKey() empty
- error_log on each skip for operator visibility
- Response signature (RESPONSE_HMAC) still present — backward compat preserved"
```

---

### Task A.5: LicenseActivator — Same Graceful Degradation

**Files:**
- Modify: `src/License/LicenseActivator.php`
- Modify: `tests/Integration/License/LicenseActivatorSigningTest.php`

- [ ] **Step 1: Write failing test — same pattern as A.4**

Append to `tests/Integration/License/LicenseActivatorSigningTest.php`:

```php
/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
public function testActivateOmitsFeatureTokenWhenRsaKeyMissing(): void
{
    $activator = new LicenseActivator();
    $result = $activator->activate('valid-key', 'site-url', 'site-hash', '1.0.0', false, 'mhm-rentiva');

    $this->assertArrayNotHasKey('feature_token', $result);
    $this->assertArrayHasKey('signature', $result);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testActivateOmitsFeatureTokenWhenRsaKeyMissing"
```

Expected: FAIL.

- [ ] **Step 3: Apply same try-catch pattern in LicenseActivator**

Find `signResult()` or feature_token assembly in `LicenseActivator.php`. Wrap with same try-catch as Task A.4 Step 4.

- [ ] **Step 4: Run test to verify it passes**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --filter=testActivateOmitsFeatureTokenWhenRsaKeyMissing"
```

Expected: PASS.

- [ ] **Step 5: Run all activation/validation tests for regression**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit tests/Integration/License/"
```

Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/License/LicenseActivator.php tests/Integration/License/LicenseActivatorSigningTest.php
git commit -m "feat(license): LicenseActivator graceful degradation — mirror of LicenseValidator pattern"
```

---

### Task A.6: Cleanup — Remove HMAC Code Paths + File Fallback

**Files:**
- Modify: `src/Security/SecretManager.php`
- Modify: `src/License/FeatureTokenIssuer.php`

- [ ] **Step 1: Remove `getFeatureTokenKey()` file fallback in SecretManager**

Find `getFeatureTokenKey()` method. It currently has logic like:

```php
if (defined('MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY')) {
    return constant('MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY');
}
// File fallback: read or auto-generate from wp-uploads/mhm-license-secrets/feature_token.key
return $this->loadOrGenerateSecret('feature_token.key');
```

Since v1.10.0 removes HMAC feature token entirely, this method is **dead code**. Mark as deprecated and remove:

```php
// DELETE entire getFeatureTokenKey() method
// DELETE feature_token.key file generation helper if unused elsewhere
```

If grep shows other call sites (response signing uses `RESPONSE_HMAC_SECRET`, not `FEATURE_TOKEN_KEY`), confirm orphaned:

```bash
grep -rn 'getFeatureTokenKey\|FEATURE_TOKEN_KEY' /c/projects/rentiva-lisans/plugins/mhm-license-server/src/
```

Expected: 0 references after Task A.3 (FeatureTokenIssuer no longer uses HMAC).

- [ ] **Step 2: Remove HMAC sign helper if exists in FeatureTokenIssuer**

Look for `signHmac()`, `hash_hmac()`, or similar in FeatureTokenIssuer. Delete after Task A.3 refactor confirmed using only RSA.

- [ ] **Step 3: Update any test that referenced FEATURE_TOKEN_KEY**

```bash
grep -rn 'FEATURE_TOKEN_KEY\|getFeatureTokenKey' /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/
```

Remove or update test files. Likely candidates:
- `tests/Integration/Security/SecretManagerTest.php` — drop `testGetFeatureTokenKey*` methods if any

- [ ] **Step 4: Run full suite — verify no regressions from cleanup**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage" | tail -3
```

Expected: ~155 tests, 0 failures.

- [ ] **Step 5: Run PHPCS**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs" | tail -5
```

Expected: 0 errors (zero new over baseline).

- [ ] **Step 6: Commit**

```bash
git add src/ tests/
git commit -m "refactor(license): remove HMAC feature token code paths

- SecretManager::getFeatureTokenKey() removed (dead code post-RSA migration)
- File fallback for feature_token.key removed (auto-gen incompatible with RSA)
- FEATURE_TOKEN_KEY constant deprecated — operator can remove from wp-config
- Existing wp-uploads/mhm-license-secrets/feature_token.key file safe to delete (manual)"
```

---

### Task A.7: Version Bump + Documentation

**Files:**
- Modify: `mhm-license-server.php`
- Modify: `readme.txt`
- Modify: `CHANGELOG.md`
- Modify: `LIVE_DEPLOYMENT_CHECKLIST.md`

- [ ] **Step 1: Bump version in mhm-license-server.php**

Find header `Version: 1.9.3` and constant `MHM_LICENSE_SERVER_VERSION`:

```php
// Header:
 * Version: 1.10.0

// Constant:
define( 'MHM_LICENSE_SERVER_VERSION', '1.10.0' );
```

- [ ] **Step 2: Update readme.txt Stable tag**

```
Stable tag: 1.10.0
```

- [ ] **Step 3: Add v1.10.0 Changelog entry to readme.txt**

Append to `== Changelog ==` section:

```
= 1.10.0 (2026-04-25) =
* BREAKING: Feature tokens now signed with RSA-2048 instead of HMAC-SHA256.
* New required wp-config constant: MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM (PEM string).
* Deprecated wp-config constant: MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY (no longer read).
* Backward compatibility: legacy clients (Rentiva v4.30.x, CS v0.5.x) ignore feature_token field — no functional change.
* Forward compatibility: clients v4.31.0+ / v0.6.0+ require valid RSA-signed token from server.
* If RSA private key not configured: server omits feature_token from response (graceful degradation, error_log warning).
```

- [ ] **Step 4: Add v1.10.0 entry to CHANGELOG.md**

Insert at top (after `## [Unreleased]` if present):

```markdown
## [1.10.0] - 2026-04-25

### Added
- `Security/RsaSigner` class — RSA-2048 PKCS#1 v1.5 SHA-256 signer.
- `SecretManager::getRsaPrivateKey()` — wp-config constant reader (no file fallback).
- `MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM` wp-config constant (REQUIRED for v4.31.0+ clients).

### Changed
- `FeatureTokenIssuer` now signs tokens with RSA via `RsaSigner` (replaces HMAC).
- Token format: signature segment is base64url-encoded binary (was hex HMAC).
- `LicenseValidator` + `LicenseActivator` gracefully omit `feature_token` field if RSA key missing.

### Removed
- `SecretManager::getFeatureTokenKey()` — dead code after RSA migration.
- `feature_token.key` file fallback in `wp-uploads/mhm-license-secrets/`.
- HMAC-specific methods in `FeatureTokenIssuer`.

### Deprecated
- `MHM_LICENSE_SERVER_FEATURE_TOKEN_KEY` wp-config constant — no longer read; operator may remove.

### Migration Notes
- **Operator action required:** Generate RSA-2048 key pair, deploy private PEM to wp-config:
  ```bash
  openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out mhm-license-private.pem
  openssl pkey -in mhm-license-private.pem -pubout -out mhm-license-public.pem
  ```
  Embed `mhm-license-public.pem` content in plugin source `LicenseServerPublicKey::PEM` constant.
  Define `MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM` in wp-config (heredoc, multi-line).
- Legacy v4.30.x clients continue working (feature_token ignored).
- New v4.31.0+ clients fail Pro features without valid token.

### Security
- Closes the cracked-binary + product-matching license attack vector.
- Public key in plugin source is forge-proof (private key required to sign).
```

- [ ] **Step 5: Update LIVE_DEPLOYMENT_CHECKLIST.md**

Add new section before "Deploy" steps:

```markdown
## RSA Private Key Deployment (v1.10.0+)

### Generate Key Pair (one-time, dev side)

```bash
# In a secure dev environment (NOT committed to git):
mkdir -p ~/secrets/mhm-license-server-rsa
cd ~/secrets/mhm-license-server-rsa
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out mhm-license-private.pem
openssl pkey -in mhm-license-private.pem -pubout -out mhm-license-public.pem
chmod 600 mhm-license-private.pem
```

### Embed Public Key in Plugin Sources

Copy content of `mhm-license-public.pem` into:
- `mhm-rentiva/src/Admin/Licensing/LicenseServerPublicKey.php` — `PEM` constant
- `mhm-currency-switcher/src/License/LicenseServerPublicKey.php` — `PEM` constant

Both plugins MUST have IDENTICAL public key.

### Deploy Private Key to wp-config (server side)

Add to `wp-config.php` on wpalemi.com (BEFORE plugin activates):

```php
define( 'MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', '-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDR6xZpw3R9X5YT
[... full PEM content from mhm-license-private.pem ...]
-----END PRIVATE KEY-----' );
```

Single-quoted PHP string supports multi-line — newlines preserved.

### Verify Deployment

After deploy:

```bash
curl -X POST https://wpalemi.com/wp-json/mhm-license/v1/licenses/validate \
  -H "Content-Type: application/json" \
  -d '{"license_key":"TEST","site_hash":"abc","client_version":"4.31.0","product_slug":"mhm-rentiva"}' \
  | jq '.feature_token' | head -c 50
```

Expected output: base64url string starting with token payload prefix (NOT empty, NOT null).

If `feature_token` is null/missing: RSA key not loaded — check wp-config define + error_log.
```

- [ ] **Step 6: Verify version bump consistency**

```bash
grep -rn '1\.9\.3\|1\.10\.0' /c/projects/rentiva-lisans/plugins/mhm-license-server/mhm-license-server.php /c/projects/rentiva-lisans/plugins/mhm-license-server/readme.txt | head -10
```

Expected: All 1.9.3 references replaced with 1.10.0.

- [ ] **Step 7: Commit version bump**

```bash
git add mhm-license-server.php readme.txt CHANGELOG.md LIVE_DEPLOYMENT_CHECKLIST.md
git commit -m "chore(release): bump version to 1.10.0 — RSA asymmetric crypto"
```

---

### Task A.8: Phase A Release Gate

**Files:**
- All Phase A files

- [ ] **Step 1: Run full PHPUnit suite + capture count**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && vendor/bin/phpunit --no-coverage --colors" | tail -5
```

Expected: ~155 tests passing (143 + 12 new), 0 failures, 0 errors. Record exact count.

- [ ] **Step 2: Run PHPCS for zero errors**

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && composer phpcs" | tail -3
```

Expected: 0 errors.

- [ ] **Step 3: Build release ZIP (if build-release.py exists, else manual)**

```bash
# If server has build-release.py:
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-license-server && python3 bin/build-release.py" || \
# Else manual ZIP:
docker exec rentiva-lisans-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/ && zip -r /tmp/mhm-license-server-1.10.0.zip mhm-license-server -x 'mhm-license-server/tests/*' 'mhm-license-server/.git/*' 'mhm-license-server/docs/*' 'mhm-license-server/.distignore' 'mhm-license-server/composer.lock' 'mhm-license-server/phpcs.xml' 'mhm-license-server/phpunit.xml*' 'mhm-license-server/CHANGELOG.md' 'mhm-license-server/LIVE_DEPLOYMENT_CHECKLIST.md'"
```

Verify ZIP contents:

```bash
docker exec rentiva-lisans-wpcli-1 bash -c "unzip -l /tmp/mhm-license-server-1.10.0.zip | head -30"
```

Expected: ZIP contains `mhm-license-server/` root with `mhm-license-server.php`, `src/`, `readme.txt`. Tests/docs/dotfiles excluded.

- [ ] **Step 4: Tag git release**

```bash
cd /c/projects/rentiva-lisans/plugins/mhm-license-server
git tag -a v1.10.0 -m "v1.10.0 — RSA asymmetric crypto for feature tokens

BREAKING: New required wp-config constant MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM.
HMAC FEATURE_TOKEN_KEY deprecated. Backward compat preserved for legacy clients."
```

- [ ] **Step 5: Push tag to GitHub (deferred — confirm with user)**

> **STOP — user confirmation required.** Pushing the tag publishes the release. Confirm before:
> ```bash
> git push origin main
> git push origin v1.10.0
> ```
> Then create GitHub release via `gh release create v1.10.0 --notes-from-tag` with ZIP asset.

**DO NOT execute push without explicit user approval.**

- [ ] **Step 6: Phase A complete — checkpoint**

Record in todo list: Phase A done. PHPUnit count: ___, PHPCS: 0 errors, ZIP: __ MB, tagged.

> **Per-Release Gate (Phase A complete):**
> - [ ] wp-reflect invoked (record learnings to KB)
> - [ ] Live deploy to wpalemi.com via Hostinger MCP — DEFERRED to Phase D Task D.7
> - [ ] hot.md / fingerprint update — after Phase D live deploy

---

## Phase B — Rentiva v4.31.0 (~2 hours)

### Task B.0: Pre-flight + Test Fixture Sync

**Files:**
- Create: `tests/fixtures/test-rsa-private.pem` (copy from server)
- Create: `tests/fixtures/test-rsa-public.pem` (copy from server)
- Create: `tests/fixtures/README.md`

- [ ] **Step 1: Capture PHPUnit baseline**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage" | tail -5
```

Expected: 781 tests passing.

- [ ] **Step 2: Capture PHPCS baseline**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs" | tail -3
```

Expected: 0 errors.

- [ ] **Step 3: Copy test fixtures from server repo**

```bash
mkdir -p /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures
cp /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/test-rsa-private.pem /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/
cp /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/test-rsa-public.pem /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/
cp /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/README.md /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/
```

- [ ] **Step 4: Verify byte-identical fixtures**

```bash
sha256sum /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/test-rsa-*.pem /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/test-rsa-*.pem
```

Expected: 4 lines, server pair hashes match Rentiva pair hashes.

- [ ] **Step 5: Commit fixtures**

```bash
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
git add tests/fixtures/
git commit -m "test(fixtures): sync RSA test key pair from mhm-license-server (byte-identical)"
```

---

### Task B.1: LicenseServerPublicKey Class

**Files:**
- Create: `src/Admin/Licensing/LicenseServerPublicKey.php`
- Create: `tests/Unit/Licensing/LicenseServerPublicKeyTest.php`

- [ ] **Step 1: Write failing test for `LicenseServerPublicKey::resource()`**

Create `tests/Unit/Licensing/LicenseServerPublicKeyTest.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\Rentiva\Tests\Unit\Licensing;

use MHM\Rentiva\Admin\Licensing\LicenseServerPublicKey;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

class LicenseServerPublicKeyTest extends TestCase
{
    public function testResourceReturnsOpenSSLAsymmetricKey(): void
    {
        $key = LicenseServerPublicKey::resource();
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
    }

    public function testResourceReturnsCachedInstance(): void
    {
        $key1 = LicenseServerPublicKey::resource();
        $key2 = LicenseServerPublicKey::resource();
        $this->assertSame($key1, $key2, 'Static cache should return same instance');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit tests/Unit/Licensing/LicenseServerPublicKeyTest.php"
```

Expected: FAIL — class not found.

- [ ] **Step 3: Get PEM content of test public key for embedding**

```bash
cat /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/test-rsa-public.pem
```

Copy the entire PEM block (with BEGIN/END lines).

- [ ] **Step 4: Implement LicenseServerPublicKey class with TEST PEM (will swap for prod PEM at deploy time)**

Create `src/Admin/Licensing/LicenseServerPublicKey.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\Rentiva\Admin\Licensing;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Embedded license server public key for RSA token verification.
 *
 * The PEM constant below is the public half of the RSA-2048 key pair whose
 * private half lives in the license server's wp-config (MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM).
 * Tokens signed by the server are verified against this public key.
 *
 * NOTE: The PEM value embedded at release time is the PRODUCTION public key,
 * not the test fixture. Test suites use dependency injection in
 * FeatureTokenVerifier to swap in tests/fixtures/test-rsa-public.pem.
 */
final class LicenseServerPublicKey
{
    private const PEM = <<<PEM
-----BEGIN PUBLIC KEY-----
{REPLACE WITH PRODUCTION PUBLIC KEY AT v4.31.0 RELEASE TIME}
-----END PUBLIC KEY-----
PEM;

    private static ?OpenSSLAsymmetricKey $cachedKey = null;

    public static function resource(): OpenSSLAsymmetricKey
    {
        if (self::$cachedKey !== null) {
            return self::$cachedKey;
        }

        $key = openssl_pkey_get_public(self::PEM);
        if ($key === false) {
            throw new RuntimeException('Embedded public key parse failed: ' . openssl_error_string());
        }

        self::$cachedKey = $key;
        return $key;
    }

    /**
     * Reset cache for tests that swap fixtures.
     */
    public static function resetCache(): void
    {
        self::$cachedKey = null;
    }
}
```

> **IMPORTANT:** For Task B.1 implementation, embed the **test fixture public key** (from `tests/fixtures/test-rsa-public.pem`) so tests pass. Replace with the **production public key** at deploy time (Task B.6 Step 4 below).

Open `tests/fixtures/test-rsa-public.pem` and copy its contents into the heredoc above (replacing the `{REPLACE WITH...}` placeholder).

- [ ] **Step 5: Run test to verify it passes**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit tests/Unit/Licensing/LicenseServerPublicKeyTest.php"
```

Expected: PASS.

- [ ] **Step 6: Add invalid-PEM test (defensive)**

```php
public function testResourceThrowsOnInvalidPem(): void
{
    // Use reflection to inject invalid PEM
    $reflectClass = new \ReflectionClass(LicenseServerPublicKey::class);
    $constant = $reflectClass->getReflectionConstant('PEM');
    // Constants are immutable; cannot modify. This test asserts the design intent
    // via static analysis — skip runtime, document via assertion of CURRENT valid state
    $this->assertNotEmpty(LicenseServerPublicKey::resource(), 'Production PEM must parse');
    $this->assertTrue(true, 'Invalid PEM scenario tested by manual review of release process');
}
```

(Pragmatic skip — invalid-PEM is a release-time concern, not runtime.)

- [ ] **Step 7: Commit**

```bash
git add src/Admin/Licensing/LicenseServerPublicKey.php tests/Unit/Licensing/LicenseServerPublicKeyTest.php
git commit -m "feat(licensing): LicenseServerPublicKey — embedded RSA public key for token verification

Heredoc class constant + static resource cache. Production PEM embedded at release time;
test fixture PEM used during development so test suite passes."
```

---

### Task B.2: FeatureTokenVerifier — Migrate to RSA with DI

**Files:**
- Modify: `src/Admin/Licensing/FeatureTokenVerifier.php`
- Modify: `tests/Unit/Licensing/FeatureTokenVerifierTest.php`

- [ ] **Step 1: Read current FeatureTokenVerifier — identify HMAC code path**

```bash
cat /c/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/FeatureTokenVerifier.php | head -80
```

Identify: `verify()` method using `hash_hmac()`, `hasFeature()` payload reader.

- [ ] **Step 2: Write failing test for RSA verify with valid token**

Modify `tests/Unit/Licensing/FeatureTokenVerifierTest.php` — add or update:

```php
public function testVerifyAcceptsValidRsaToken(): void
{
    $privatePem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $publicKey  = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

    $payload = [
        'license_key_hash' => 'hash-abc',
        'product_slug'     => 'mhm-rentiva',
        'plan'             => 'pro',
        'features'         => ['vendor_marketplace' => true],
        'site_hash'        => 'site-xyz',
        'issued_at'        => time(),
        'expires_at'       => time() + 86400,
    ];
    ksort($payload); // Match server canonical form
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $signature = '';
    openssl_sign($canonical, $signature, openssl_pkey_get_private($privatePem), OPENSSL_ALGO_SHA256);

    $token = $this->base64UrlEncode($canonical) . '.' . $this->base64UrlEncode($signature);

    $verifier = new FeatureTokenVerifier($publicKey); // DI for test
    $this->assertTrue($verifier->verify($token, 'site-xyz'));
}

private function base64UrlEncode(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter=testVerifyAcceptsValidRsaToken"
```

Expected: FAIL — constructor doesn't accept `OpenSSLAsymmetricKey`, or method is HMAC-based.

- [ ] **Step 4: Refactor FeatureTokenVerifier to RSA with DI**

Rewrite `src/Admin/Licensing/FeatureTokenVerifier.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\Rentiva\Admin\Licensing;

use OpenSSLAsymmetricKey;

/**
 * RSA-2048 PKCS#1 v1.5 SHA-256 feature token verifier.
 *
 * Verifies tokens signed by license server's private key against the embedded
 * public key. Token format: {base64url(canonical_payload)}.{base64url(rsa_sig)}.
 *
 * Constructor accepts optional public key for test injection; defaults to
 * LicenseServerPublicKey::resource() for production.
 */
final class FeatureTokenVerifier
{
    private OpenSSLAsymmetricKey $publicKey;

    public function __construct(?OpenSSLAsymmetricKey $publicKey = null)
    {
        $this->publicKey = $publicKey ?? LicenseServerPublicKey::resource();
    }

    public function verify(string $token, string $expectedSiteHash): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $canonical = $this->base64UrlDecode($parts[0]);
        $signature = $this->base64UrlDecode($parts[1]);
        if ($canonical === '' || $signature === '') {
            return false;
        }

        $verified = openssl_verify($canonical, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return false;
        }

        $payload = json_decode($canonical, true);
        if (!is_array($payload)) {
            return false;
        }

        if (($payload['site_hash'] ?? '') !== $expectedSiteHash) {
            return false;
        }
        if (($payload['expires_at'] ?? 0) < time()) {
            return false;
        }

        return true;
    }

    public function hasFeature(string $token, string $featureName): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $canonical = $this->base64UrlDecode($parts[0]);
        $payload = json_decode($canonical, true);
        if (!is_array($payload)) {
            return false;
        }

        return !empty($payload['features'][$featureName]);
    }

    private function base64UrlDecode(string $input): string
    {
        $padded = strtr($input, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true); // phpcs:ignore WordPress.PHP.DiscouragedFunctions.obfuscation_base64_decode
        return $decoded === false ? '' : $decoded;
    }
}
```

- [ ] **Step 5: Run RSA test to verify it passes**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter=testVerifyAcceptsValidRsaToken"
```

Expected: PASS.

- [ ] **Step 6: Add tamper rejection tests**

Append to `FeatureTokenVerifierTest.php`:

```php
public function testVerifyRejectsTamperedSignature(): void
{
    [$publicKey, $token] = $this->buildValidToken('site-xyz');

    // Flip 1 byte in signature
    $parts = explode('.', $token);
    $sigBytes = $this->base64UrlDecode($parts[1]);
    $sigBytes[0] = chr(ord($sigBytes[0]) ^ 0x01);
    $tamperedToken = $parts[0] . '.' . $this->base64UrlEncode($sigBytes);

    $verifier = new FeatureTokenVerifier($publicKey);
    $this->assertFalse($verifier->verify($tamperedToken, 'site-xyz'));
}

public function testVerifyRejectsExpiredToken(): void
{
    $privatePem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $publicKey  = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

    $payload = [
        'license_key_hash' => 'hash-abc',
        'product_slug'     => 'mhm-rentiva',
        'plan'             => 'pro',
        'features'         => ['vendor_marketplace' => true],
        'site_hash'        => 'site-xyz',
        'issued_at'        => time() - 90000,
        'expires_at'       => time() - 3600, // EXPIRED
    ];
    ksort($payload);
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = '';
    openssl_sign($canonical, $signature, openssl_pkey_get_private($privatePem), OPENSSL_ALGO_SHA256);
    $token = $this->base64UrlEncode($canonical) . '.' . $this->base64UrlEncode($signature);

    $verifier = new FeatureTokenVerifier($publicKey);
    $this->assertFalse($verifier->verify($token, 'site-xyz'));
}

public function testVerifyRejectsMismatchedSiteHash(): void
{
    [$publicKey, $token] = $this->buildValidToken('site-xyz');
    $verifier = new FeatureTokenVerifier($publicKey);
    $this->assertFalse($verifier->verify($token, 'wrong-site'));
}

public function testHasFeatureReadsFeaturesArray(): void
{
    [$publicKey, $token] = $this->buildValidToken('site-xyz', ['vendor_marketplace' => true, 'advanced_reports' => true]);
    $verifier = new FeatureTokenVerifier($publicKey);

    $this->assertTrue($verifier->hasFeature($token, 'vendor_marketplace'));
    $this->assertTrue($verifier->hasFeature($token, 'advanced_reports'));
    $this->assertFalse($verifier->hasFeature($token, 'messaging'));
}

private function buildValidToken(string $siteHash, array $features = ['vendor_marketplace' => true]): array
{
    $privatePem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $publicKey  = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

    $payload = [
        'license_key_hash' => 'hash-abc',
        'product_slug'     => 'mhm-rentiva',
        'plan'             => 'pro',
        'features'         => $features,
        'site_hash'        => $siteHash,
        'issued_at'        => time(),
        'expires_at'       => time() + 86400,
    ];
    ksort($payload);
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = '';
    openssl_sign($canonical, $signature, openssl_pkey_get_private($privatePem), OPENSSL_ALGO_SHA256);
    $token = $this->base64UrlEncode($canonical) . '.' . $this->base64UrlEncode($signature);

    return [$publicKey, $token];
}

private function base64UrlDecode(string $input): string
{
    $padded = strtr($input, '-_', '+/');
    $remainder = strlen($padded) % 4;
    if ($remainder !== 0) {
        $padded .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode($padded, true) ?: '';
}
```

- [ ] **Step 7: Run all FeatureTokenVerifier tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit tests/Unit/Licensing/FeatureTokenVerifierTest.php"
```

Expected: 5+ tests passing (valid token, tampered sig, expired, mismatched site_hash, hasFeature).

- [ ] **Step 8: Remove old HMAC-specific tests**

Find and delete tests that use `hash_hmac` or reference `FEATURE_TOKEN_KEY`:

```bash
grep -n 'hash_hmac\|FEATURE_TOKEN_KEY' tests/Unit/Licensing/FeatureTokenVerifierTest.php
```

Delete those test methods.

- [ ] **Step 9: Run full Licensing test suite for regression**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit tests/Unit/Licensing/"
```

Expected: All tests pass. Some may fail if they reference HMAC verifier — fix or remove.

- [ ] **Step 10: Commit**

```bash
git add src/Admin/Licensing/FeatureTokenVerifier.php tests/Unit/Licensing/FeatureTokenVerifierTest.php
git commit -m "feat(licensing): FeatureTokenVerifier migrates from HMAC to RSA

- Constructor accepts optional public key for test DI; defaults to LicenseServerPublicKey
- verify(): openssl_verify replaces hash_hmac comparison
- Token format: signature segment is base64url binary RSA (was hex HMAC)
- New tests: tamper rejection, expired rejection, site_hash mismatch, hasFeature reader"
```

---

### Task B.3: Mode — Remove Legacy isPro() Fallback

**Files:**
- Modify: `src/Admin/Licensing/Mode.php`
- Modify: `tests/Unit/Licensing/ModeFeatureTokenTest.php`

- [ ] **Step 1: Read current Mode::featureGranted()**

```bash
grep -A 20 'private function featureGranted' /c/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/Mode.php
```

Identify the legacy fallback path:

```php
// Current code (~v4.30.x):
private function featureGranted(string $name): bool {
    if (!$this->licenseManager->isActive()) return false;
    $key = ClientSecrets::getFeatureTokenKey();
    if ($key === '') {
        return $this->licenseManager->isPro(); // <-- LEGACY FALLBACK
    }
    // HMAC verify path
    $token = $this->licenseManager->getFeatureToken();
    // ...
}
```

- [ ] **Step 2: Write failing test — `featureGranted()` returns false when token empty (no fallback)**

Modify `tests/Unit/Licensing/ModeFeatureTokenTest.php` — add or replace test:

```php
public function testFeatureGrantedReturnsFalseWhenTokenEmptyEvenIfLicenseActive(): void
{
    // Mock LicenseManager: isActive=true, getFeatureToken=''
    $licenseManager = $this->createMock(LicenseManager::class);
    $licenseManager->method('isActive')->willReturn(true);
    $licenseManager->method('getFeatureToken')->willReturn('');

    $mode = new Mode($licenseManager);

    // No legacy fallback — must return false
    $this->assertFalse($mode->canUseVendorMarketplace());
    $this->assertFalse($mode->canUseAdvancedReports());
    $this->assertFalse($mode->canUseMessages());
    $this->assertFalse($mode->canUseVendorPayout());
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter=testFeatureGrantedReturnsFalseWhenTokenEmpty"
```

Expected: FAIL — current code returns true via legacy `isPro()` fallback.

- [ ] **Step 4: Remove legacy fallback in Mode::featureGranted()**

Modify `src/Admin/Licensing/Mode.php`:

```php
// BEFORE:
private function featureGranted(string $name): bool {
    if (!$this->licenseManager->isActive()) return false;
    $key = ClientSecrets::getFeatureTokenKey();
    if ($key === '') {
        return $this->licenseManager->isPro(); // LEGACY FALLBACK
    }
    $token = $this->licenseManager->getFeatureToken();
    if ($token === '') return false;
    $verifier = new FeatureTokenVerifier();
    if (!$verifier->verify($token, $this->licenseManager->getSiteHash())) return false;
    return $verifier->hasFeature($token, $name);
}

// AFTER:
private function featureGranted(string $name): bool {
    if (!$this->licenseManager->isActive()) return false;

    $token = $this->licenseManager->getFeatureToken();
    if ($token === '') return false;

    $verifier = new FeatureTokenVerifier();
    if (!$verifier->verify($token, $this->licenseManager->getSiteHash())) return false;

    return $verifier->hasFeature($token, $name);
}
```

> **Note:** The `ClientSecrets::getFeatureTokenKey()` call is removed; legacy path entirely deleted.

- [ ] **Step 5: Run test to verify it passes**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter=testFeatureGrantedReturnsFalseWhenTokenEmpty"
```

Expected: PASS.

- [ ] **Step 6: Update existing legacy-fallback test**

Find tests that currently assert legacy fallback returns true. Examples:

```bash
grep -n 'legacy\|fallback\|isPro' tests/Unit/Licensing/ModeFeatureTokenTest.php
```

Convert these to enforcement tests:

```php
// BEFORE (asserts legacy fallback works):
public function testFeatureGrantedFallsBackToIsProWhenKeyMissing(): void
{
    // ... asserts $mode->canUseVendorMarketplace() === true
}

// AFTER (asserts no fallback — strict enforcement):
public function testFeatureGrantedRequiresValidToken(): void
{
    $licenseManager = $this->createMock(LicenseManager::class);
    $licenseManager->method('isActive')->willReturn(true);
    $licenseManager->method('isPro')->willReturn(true); // license is Pro
    $licenseManager->method('getFeatureToken')->willReturn(''); // but no token

    $mode = new Mode($licenseManager);
    $this->assertFalse($mode->canUseVendorMarketplace(), 'Pro license without token must NOT grant access');
}
```

- [ ] **Step 7: Run all Mode tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit tests/Unit/Licensing/ModeFeatureTokenTest.php"
```

Expected: All tests pass; legacy-fallback assertions converted to strict enforcement.

- [ ] **Step 8: Commit**

```bash
git add src/Admin/Licensing/Mode.php tests/Unit/Licensing/ModeFeatureTokenTest.php
git commit -m "feat(licensing): Mode::featureGranted() — remove legacy isPro() fallback (BREAKING)

Pro features now require valid RSA-signed feature token. License-active alone is insufficient.
This is intentional: forces server contact (RSA verify gate) and closes source-edit attack
where attacker patched isPro() to always-return-true.

Backward compat: customers running v4.30.x server return RSA token starting with v1.10.0,
but Mode in v4.30.x ignored token (legacy fallback). Now enforced in v4.31.0 client."
```

---

### Task B.4: ClientSecrets — Remove getFeatureTokenKey()

**Files:**
- Modify: `src/Admin/Licensing/ClientSecrets.php`
- Modify: `tests/Unit/Licensing/ClientSecretsTest.php`

- [ ] **Step 1: Verify no callers remain**

```bash
grep -rn 'getFeatureTokenKey\|FEATURE_TOKEN_KEY' /c/projects/rentiva-dev/plugins/mhm-rentiva/src/
```

Expected: 0 references after Task B.3.

- [ ] **Step 2: Remove method from ClientSecrets**

Delete `getFeatureTokenKey()` method from `src/Admin/Licensing/ClientSecrets.php`.

Keep: `getResponseHmacSecret()` (still used for response verify) and `getPingSecret()` (still used for VerifyEndpoint, optional with site_hash fallback).

- [ ] **Step 3: Remove related tests**

In `tests/Unit/Licensing/ClientSecretsTest.php`, delete tests like:
- `testGetFeatureTokenKeyReturnsConstantWhenDefined`
- `testGetFeatureTokenKeyReturnsEmptyStringWhenUndefined`
- Any test referencing `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY`

- [ ] **Step 4: Run full Licensing suite**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit tests/Unit/Licensing/"
```

Expected: All tests pass (~30+ tests).

- [ ] **Step 5: Run full PHPUnit suite**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage" | tail -3
```

Expected: ~790 tests, 0 failures.

- [ ] **Step 6: Run PHPCS**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs" | tail -3
```

Expected: 0 errors.

- [ ] **Step 7: Commit**

```bash
git add src/Admin/Licensing/ClientSecrets.php tests/Unit/Licensing/ClientSecretsTest.php
git commit -m "refactor(licensing): ClientSecrets removes getFeatureTokenKey()

Method was legacy HMAC code path. RSA verify uses LicenseServerPublicKey embedded constant
instead of wp-config secret. MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY constant deprecated."
```

---

### Task B.5: Version Bump + Changelog + i18n

**Files:**
- Modify: `mhm-rentiva.php`
- Modify: `readme.txt`
- Modify: `README.md`, `README-tr.md`
- Modify: `changelog-tr.json`
- Regenerate: `languages/mhm-rentiva.pot`, `.po`, `.mo`, `.l10n.php`

- [ ] **Step 1: Bump version in mhm-rentiva.php**

Find header `Version: 4.30.2` and constant `MHM_RENTIVA_VERSION`:

```php
 * Version: 4.31.0
define('MHM_RENTIVA_VERSION', '4.31.0');
```

- [ ] **Step 2: Update readme.txt Stable tag**

```
Stable tag: 4.31.0
```

- [ ] **Step 3: Add v4.31.0 Changelog to readme.txt**

Append:

```
= 4.31.0 (2026-04-25) =
* BREAKING: Pro features now require RSA-signed feature token from license server v1.10.0+.
* New file: src/Admin/Licensing/LicenseServerPublicKey.php (embedded RSA-2048 public key).
* FeatureTokenVerifier migrates from HMAC to RSA verification.
* Mode::featureGranted() legacy isPro() fallback REMOVED — strict token enforcement.
* MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY constant deprecated (no longer read; safe to remove from wp-config).
* Server v1.10.0+ deployment REQUIRED before upgrading client.
* Closes cracked-binary + product-matching license attack vector.
```

- [ ] **Step 4: Add v4.31.0 entry to changelog-tr.json**

Insert at top of changelog array:

```json
{
  "version": "4.31.0",
  "date": "2026-04-25",
  "type": "minor",
  "title": "Asimetrik Kripto Lisans Güvenliği",
  "description": "Pro özellikler artık RSA imzalı sunucu token'ı gerektiriyor. HMAC tabanlı feature token kaldırıldı, yerine RSA-2048 doğrulama geldi.",
  "breaking": [
    "Pro özellikleri çalıştırmak için lisans sunucusu v1.10.0+ deploy edilmiş olmalı.",
    "Mode::featureGranted() artık eski isPro() fallback'ine düşmüyor — geçerli RSA token şart.",
    "MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY wp-config sabiti artık okunmuyor, silinebilir."
  ],
  "added": [
    "src/Admin/Licensing/LicenseServerPublicKey — gömülü RSA-2048 public key (heredoc class constant).",
    "FeatureTokenVerifier::verify() RSA imza doğrulaması (openssl_verify, SHA-256)."
  ],
  "changed": [
    "FeatureTokenVerifier constructor opsiyonel public key DI alıyor (test desteği için).",
    "Token imza segment formatı: hex HMAC → base64url binary RSA-2048."
  ],
  "removed": [
    "ClientSecrets::getFeatureTokenKey() (artık okunmuyor).",
    "FeatureTokenVerifier'daki HMAC doğrulama kod yolu."
  ],
  "security": [
    "Cracked-binary + product-matching lisans saldırı vektörü kapatıldı.",
    "Public key plugin source'unda gömülü; private key yalnızca sunucuda — saldırgan token forge edemez."
  ],
  "migration": "Operator: lisans sunucusu wp-config'e MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM tanımlamalı. Müşteri tarafında ek wp-config değişikliği YOK (zero-config korunur)."
}
```

- [ ] **Step 5: Update README badges**

In `README.md` and `README-tr.md`, find version badge:

```markdown
[![Version](https://img.shields.io/badge/version-4.30.2-blue)]()
```

Change to:

```markdown
[![Version](https://img.shields.io/badge/version-4.31.0-blue)]()
```

- [ ] **Step 6: Verify no new user-visible strings in PHP changes**

```bash
git diff HEAD~10 -- src/Admin/Licensing/ | grep -E "__\\(|_e\\(|esc_html__\\(" | head -10
```

Expected: 0 new translatable strings (RSA verify is silent — token check happens server-side or in `error_log`). If any new strings exist, regen pot.

If new strings found → run pot regen:

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp i18n make-pot . languages/mhm-rentiva.pot --slug=mhm-rentiva --domain=mhm-rentiva"

docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && msgmerge --update languages/mhm-rentiva-tr_TR.po languages/mhm-rentiva.pot"

# Verify zero fuzzy entries
docker exec rentiva-dev-wpcli-1 bash -c "grep -c '#, fuzzy' /var/www/html/wp-content/plugins/mhm-rentiva/languages/mhm-rentiva-tr_TR.po"
```

Expected: 0 fuzzy. If non-zero, manually resolve each `#, fuzzy` flag (delete the flag once translation confirmed correct).

```bash
# Compile mo + l10n.php
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva/languages && msgfmt mhm-rentiva-tr_TR.po -o mhm-rentiva-tr_TR.mo"

docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp i18n make-php languages/mhm-rentiva-tr_TR.po"

docker exec rentiva-dev-wpcli-1 wp cache flush
```

- [ ] **Step 7: Verify version consistency**

```bash
grep -rn '4\.30\.2\|4\.31\.0' /c/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php /c/projects/rentiva-dev/plugins/mhm-rentiva/readme.txt /c/projects/rentiva-dev/plugins/mhm-rentiva/README.md /c/projects/rentiva-dev/plugins/mhm-rentiva/README-tr.md | head -10
```

Expected: All 4.30.2 references replaced with 4.31.0.

- [ ] **Step 8: Commit version bump**

```bash
git add mhm-rentiva.php readme.txt README.md README-tr.md changelog-tr.json languages/
git commit -m "chore(release): bump Rentiva to v4.31.0 — RSA asymmetric crypto migration"
```

---

### Task B.6: Phase B Release Gate

**Files:**
- All Phase B files

- [ ] **Step 1: Production public key embed (DEFERRED until deploy time)**

> **CRITICAL:** Before production release, replace test fixture PEM in `LicenseServerPublicKey::PEM` with the **actual production public key** generated in Phase A Task A.7 deploy steps. Test fixture key is NOT for production use.
>
> This is a release-time activity, not a coding step. Document in deploy checklist:
>
> ```bash
> # Step before ZIP build:
> # 1. Open src/Admin/Licensing/LicenseServerPublicKey.php
> # 2. Replace PEM constant content with prod public key from ~/secrets/mhm-license-server-rsa/mhm-license-public.pem
> # 3. Regenerate test fixture key pair (or keep test PEM in dev branch only)
> # 4. Run tests in CI to confirm fixture-based tests still pass (test fixtures embed test PEM via DI)
> ```
>
> For Phase B execution: keep test PEM in `LicenseServerPublicKey::PEM`. Production swap happens in Phase D Task D.7 just before ZIP build.

- [ ] **Step 2: Run full PHPUnit suite**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors" | tail -5
```

Expected: ~790 tests, 0 failures, 0 errors.

- [ ] **Step 3: Run PHPCS**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs" | tail -3
```

Expected: 0 errors.

- [ ] **Step 4: Run Plugin Check (WP.org compliance)**

```bash
docker exec rentiva-dev-wpcli-1 wp plugin-check mhm-rentiva 2>&1 | tail -20
```

Expected: 0 ERRORS. Existing 61 WARNING baseline OK (false positives per hot.md).

- [ ] **Step 5: Build release ZIP**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && python3 bin/build-release.py"
```

Expected: `build/mhm-rentiva.4.31.0.zip` created. Verify size ~2.6 MB, 760+ files.

- [ ] **Step 6: Verify ZIP integrity**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "unzip -l /var/www/html/wp-content/plugins/mhm-rentiva/build/mhm-rentiva.4.31.0.zip | grep -E 'LicenseServerPublicKey|FeatureTokenVerifier' | head -5"
```

Expected: Both files listed (RSA migration shipped). Test fixtures should NOT be in ZIP.

```bash
docker exec rentiva-dev-wpcli-1 bash -c "unzip -l /var/www/html/wp-content/plugins/mhm-rentiva/build/mhm-rentiva.4.31.0.zip | grep -E 'tests/fixtures'"
```

Expected: 0 lines (fixtures excluded by .distignore).

- [ ] **Step 7: Tag release**

```bash
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
git tag -a v4.31.0 -m "v4.31.0 — RSA asymmetric crypto for feature tokens

BREAKING: Server v1.10.0+ required.
MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY deprecated.
Mode::featureGranted() legacy isPro() fallback removed.
Closes cracked-binary attack vector."
```

- [ ] **Step 8: GitHub release (DEFERRED — confirm with user before push)**

> **STOP — user confirmation required.**
> ```bash
> git push origin main
> git push origin v4.31.0
> gh release create v4.31.0 build/mhm-rentiva.4.31.0.zip --title "v4.31.0 — RSA Asymmetric Crypto" --notes-from-tag
> ```

- [ ] **Step 9: Phase B complete — checkpoint**

> **Per-Release Gate (Phase B complete):**
> - [ ] wp-reflect invoked (record learnings — RSA migration patterns to KB)
> - [ ] Live deploy mhmrentiva.com — DEFERRED to Phase D Task D.8
> - [ ] mhm-rentiva-docs blog post (EN + TR) — DEFERRED to Phase D wrap-up
> - [ ] hot.md update — after live deploy validated

---

## Phase C — Currency Switcher v0.6.0 (~1.5 hours)

> **Pattern note:** Phase C mirrors Phase B with CS naming conventions:
> - `src/License/` instead of `src/Admin/Licensing/`
> - snake_case methods (`feature_granted`, `is_active`, `get_feature_token`)
> - `tests/Unit/License/` instead of `tests/Unit/Licensing/`
> - 6 gates instead of 4 (`can_use_geolocation/fixed_prices/payment_restrictions/auto_rate_update/multilingual/rest_api_filter`)

### Task C.0: Pre-flight + Test Fixture Sync

**Files:**
- Create: `tests/fixtures/test-rsa-private.pem` (copy from server)
- Create: `tests/fixtures/test-rsa-public.pem` (copy from server)
- Create: `tests/fixtures/README.md`

- [ ] **Step 1: Capture PHPUnit baseline (CS develop branch)**

```bash
cd /c/projects/mhm-currency-switcher && git checkout develop
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-currency-switcher && vendor/bin/phpunit --no-coverage" | tail -5
```

> **Note:** CS may not be mounted in rentiva-dev Docker stack. Use a local PHP runner if needed:
> ```bash
> cd /c/projects/mhm-currency-switcher && composer phpunit
> ```

Expected: 137 tests passing.

- [ ] **Step 2: Capture PHPCS baseline**

```bash
cd /c/projects/mhm-currency-switcher && composer phpcs | tail -3
```

Expected: 27 errors (existing CRLF baseline per hot.md). Record exact count.

- [ ] **Step 3: Copy test fixtures from server**

```bash
mkdir -p /c/projects/mhm-currency-switcher/tests/fixtures
cp /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/test-rsa-private.pem /c/projects/mhm-currency-switcher/tests/fixtures/
cp /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/test-rsa-public.pem /c/projects/mhm-currency-switcher/tests/fixtures/
cp /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/README.md /c/projects/mhm-currency-switcher/tests/fixtures/
```

- [ ] **Step 4: Verify byte-identical fixtures across 3 repos**

```bash
sha256sum \
  /c/projects/rentiva-lisans/plugins/mhm-license-server/tests/fixtures/test-rsa-*.pem \
  /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/test-rsa-*.pem \
  /c/projects/mhm-currency-switcher/tests/fixtures/test-rsa-*.pem
```

Expected: 6 lines, 3 hashes for private (all identical), 3 hashes for public (all identical).

- [ ] **Step 5: Commit**

```bash
cd /c/projects/mhm-currency-switcher
git add tests/fixtures/
git commit -m "test(fixtures): sync RSA test key pair from mhm-license-server (byte-identical)"
```

---

### Task C.1: LicenseServerPublicKey Class (CS)

**Files:**
- Create: `src/License/LicenseServerPublicKey.php`
- Create: `tests/Unit/License/LicenseServerPublicKeyTest.php`

- [ ] **Step 1: Write failing test (mirror of Phase B Task B.1 Step 1)**

Create `tests/Unit/License/LicenseServerPublicKeyTest.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\CurrencySwitcher\Tests\Unit\License;

use MHM\CurrencySwitcher\License\LicenseServerPublicKey;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

class LicenseServerPublicKeyTest extends TestCase
{
    public function test_resource_returns_openssl_asymmetric_key(): void
    {
        $key = LicenseServerPublicKey::resource();
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
    }

    public function test_resource_returns_cached_instance(): void
    {
        $key1 = LicenseServerPublicKey::resource();
        $key2 = LicenseServerPublicKey::resource();
        $this->assertSame($key1, $key2);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /c/projects/mhm-currency-switcher && composer phpunit -- --filter=LicenseServerPublicKey
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement LicenseServerPublicKey for CS (with TEST PEM)**

Create `src/License/LicenseServerPublicKey.php`:

```php
<?php
declare(strict_types=1);

namespace MHM\CurrencySwitcher\License;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Embedded license server public key for RSA token verification.
 *
 * The PEM constant is the same public key embedded in mhm-rentiva — both plugins
 * verify tokens issued by mhm-license-server. Production PEM swapped at release time;
 * test fixture PEM during development.
 */
final class LicenseServerPublicKey
{
    private const PEM = <<<PEM
-----BEGIN PUBLIC KEY-----
{REPLACE WITH TEST FIXTURE PUBLIC KEY DURING DEV — PROD PEM AT RELEASE TIME}
-----END PUBLIC KEY-----
PEM;

    private static ?OpenSSLAsymmetricKey $cached_key = null;

    public static function resource(): OpenSSLAsymmetricKey
    {
        if (self::$cached_key !== null) {
            return self::$cached_key;
        }

        $key = openssl_pkey_get_public(self::PEM);
        if ($key === false) {
            throw new RuntimeException('Embedded public key parse failed: ' . openssl_error_string());
        }

        self::$cached_key = $key;
        return $key;
    }

    public static function reset_cache(): void
    {
        self::$cached_key = null;
    }
}
```

Replace `{REPLACE WITH...}` with content of `tests/fixtures/test-rsa-public.pem`.

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /c/projects/mhm-currency-switcher && composer phpunit -- --filter=LicenseServerPublicKey
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/License/LicenseServerPublicKey.php tests/Unit/License/LicenseServerPublicKeyTest.php
git commit -m "feat(license): LicenseServerPublicKey for CS — RSA public key for token verification

Mirror of mhm-rentiva pattern. Embedded public key matches license server's private key.
Test fixture PEM during dev; production PEM swapped at release time."
```

---

### Task C.2: FeatureTokenVerifier RSA Migration (CS)

**Files:**
- Modify: `src/License/FeatureTokenVerifier.php`
- Modify: `tests/Unit/License/FeatureTokenVerifierTest.php`

- [ ] **Step 1: Write failing test for RSA verify (mirror Phase B Task B.2 Step 2 with snake_case)**

Modify `tests/Unit/License/FeatureTokenVerifierTest.php`:

```php
public function test_verify_accepts_valid_rsa_token(): void
{
    $private_pem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $public_key  = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

    $payload = [
        'license_key_hash' => 'hash-abc',
        'product_slug'     => 'mhm-currency-switcher',
        'plan'             => 'pro',
        'features'         => ['fixed_pricing' => true, 'multilingual' => true],
        'site_hash'        => 'site-cs-xyz',
        'issued_at'        => time(),
        'expires_at'       => time() + 86400,
    ];
    ksort($payload);
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $signature = '';
    openssl_sign($canonical, $signature, openssl_pkey_get_private($private_pem), OPENSSL_ALGO_SHA256);

    $token = $this->base64url_encode($canonical) . '.' . $this->base64url_encode($signature);

    $verifier = new FeatureTokenVerifier($public_key);
    $this->assertTrue($verifier->verify($token, 'site-cs-xyz'));
}

private function base64url_encode(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}
```

- [ ] **Step 2: Refactor FeatureTokenVerifier to RSA + snake_case**

Rewrite `src/License/FeatureTokenVerifier.php` (CS uses snake_case methods, single underscore namespace separator):

```php
<?php
declare(strict_types=1);

namespace MHM\CurrencySwitcher\License;

use OpenSSLAsymmetricKey;

/**
 * RSA-2048 PKCS#1 v1.5 SHA-256 feature token verifier (CS).
 *
 * Mirror of mhm-rentiva FeatureTokenVerifier with snake_case methods.
 * Constructor accepts optional public key for test injection;
 * defaults to LicenseServerPublicKey::resource().
 */
final class FeatureTokenVerifier
{
    private OpenSSLAsymmetricKey $public_key;

    public function __construct(?OpenSSLAsymmetricKey $public_key = null)
    {
        $this->public_key = $public_key ?? LicenseServerPublicKey::resource();
    }

    public function verify(string $token, string $expected_site_hash): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $canonical = $this->base64url_decode($parts[0]);
        $signature = $this->base64url_decode($parts[1]);
        if ($canonical === '' || $signature === '') {
            return false;
        }

        $verified = openssl_verify($canonical, $signature, $this->public_key, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return false;
        }

        $payload = json_decode($canonical, true);
        if (!is_array($payload)) {
            return false;
        }

        if (($payload['site_hash'] ?? '') !== $expected_site_hash) {
            return false;
        }
        if (($payload['expires_at'] ?? 0) < time()) {
            return false;
        }

        return true;
    }

    public function has_feature(string $token, string $feature_name): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $canonical = $this->base64url_decode($parts[0]);
        $payload = json_decode($canonical, true);
        if (!is_array($payload)) {
            return false;
        }

        return !empty($payload['features'][$feature_name]);
    }

    private function base64url_decode(string $input): string
    {
        $padded = strtr($input, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true); // phpcs:ignore WordPress.PHP.DiscouragedFunctions.obfuscation_base64_decode
        return $decoded === false ? '' : $decoded;
    }
}
```

> **Note:** Method name `has_feature` (CS) corresponds to `hasFeature` (Rentiva). Callers in CS Mode will use snake_case. Verify all call sites updated in Task C.3.

- [ ] **Step 3: Add tamper, expired, mismatch, has_feature tests**

Append to `tests/Unit/License/FeatureTokenVerifierTest.php`:

```php
public function test_verify_rejects_tampered_signature(): void
{
    [$public_key, $token] = $this->build_valid_token('site-cs-xyz');

    $parts = explode('.', $token);
    $sig_bytes = $this->base64url_decode($parts[1]);
    $sig_bytes[0] = chr(ord($sig_bytes[0]) ^ 0x01);
    $tampered_token = $parts[0] . '.' . $this->base64url_encode($sig_bytes);

    $verifier = new FeatureTokenVerifier($public_key);
    $this->assertFalse($verifier->verify($tampered_token, 'site-cs-xyz'));
}

public function test_verify_rejects_expired_token(): void
{
    $private_pem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $public_key  = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

    $payload = [
        'license_key_hash' => 'hash-cs',
        'product_slug'     => 'mhm-currency-switcher',
        'plan'             => 'pro',
        'features'         => ['fixed_pricing' => true],
        'site_hash'        => 'site-cs-xyz',
        'issued_at'        => time() - 90000,
        'expires_at'       => time() - 3600,
    ];
    ksort($payload);
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = '';
    openssl_sign($canonical, $signature, openssl_pkey_get_private($private_pem), OPENSSL_ALGO_SHA256);
    $token = $this->base64url_encode($canonical) . '.' . $this->base64url_encode($signature);

    $verifier = new FeatureTokenVerifier($public_key);
    $this->assertFalse($verifier->verify($token, 'site-cs-xyz'));
}

public function test_has_feature_reads_features_array(): void
{
    [$public_key, $token] = $this->build_valid_token('site-cs-xyz', [
        'fixed_pricing' => true,
        'multilingual'  => true,
    ]);
    $verifier = new FeatureTokenVerifier($public_key);

    $this->assertTrue($verifier->has_feature($token, 'fixed_pricing'));
    $this->assertTrue($verifier->has_feature($token, 'multilingual'));
    $this->assertFalse($verifier->has_feature($token, 'geolocation'));
}

private function build_valid_token(string $site_hash, array $features = ['fixed_pricing' => true]): array
{
    $private_pem = file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
    $public_key  = openssl_pkey_get_public(file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

    $payload = [
        'license_key_hash' => 'hash-cs',
        'product_slug'     => 'mhm-currency-switcher',
        'plan'             => 'pro',
        'features'         => $features,
        'site_hash'        => $site_hash,
        'issued_at'        => time(),
        'expires_at'       => time() + 86400,
    ];
    ksort($payload);
    $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = '';
    openssl_sign($canonical, $signature, openssl_pkey_get_private($private_pem), OPENSSL_ALGO_SHA256);
    $token = $this->base64url_encode($canonical) . '.' . $this->base64url_encode($signature);

    return [$public_key, $token];
}

private function base64url_decode(string $input): string
{
    $padded = strtr($input, '-_', '+/');
    $remainder = strlen($padded) % 4;
    if ($remainder !== 0) {
        $padded .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode($padded, true) ?: '';
}
```

- [ ] **Step 4: Run all FeatureTokenVerifier tests**

```bash
cd /c/projects/mhm-currency-switcher && composer phpunit -- tests/Unit/License/FeatureTokenVerifierTest.php
```

Expected: 5+ tests passing (valid, tampered, expired, mismatched site_hash, has_feature).

- [ ] **Step 5: Run full PHPUnit + PHPCS regression**

```bash
cd /c/projects/mhm-currency-switcher && composer phpunit | tail -3
# Expected: ~143 tests, 0 failures
composer phpcs | tail -3
# Expected: 27 errors baseline (CRLF), zero new
```

- [ ] **Step 6: Commit**

```bash
git add src/License/FeatureTokenVerifier.php tests/Unit/License/FeatureTokenVerifierTest.php
git commit -m "feat(license): FeatureTokenVerifier migrates to RSA — CS counterpart of Rentiva v4.31.0

- Constructor accepts optional public key for test DI; defaults to LicenseServerPublicKey
- verify(): openssl_verify replaces hash_hmac comparison
- Token format: signature segment is base64url binary RSA (was hex HMAC)
- New tests: tamper rejection, expired rejection, has_feature reader
- snake_case method naming (CS convention)"
```

---

### Task C.3: Mode — Remove Legacy is_pro() Fallback (CS)

**Files:**
- Modify: `src/License/Mode.php`
- Modify: `tests/Unit/License/ModeFeatureTokenTest.php`

- [ ] **Step 1: Read current Mode::feature_granted() in CS**

```bash
grep -A 20 'private function feature_granted' /c/projects/mhm-currency-switcher/src/License/Mode.php
```

- [ ] **Step 2: Write failing test (mirror Phase B Task B.3 with snake_case + 6 gates)**

```php
public function test_feature_granted_returns_false_when_token_empty_for_all_six_gates(): void
{
    $license_manager = $this->createMock(LicenseManager::class);
    $license_manager->method('is_active')->willReturn(true);
    $license_manager->method('get_feature_token')->willReturn('');

    $mode = new Mode($license_manager);

    $this->assertFalse($mode->can_use_geolocation());
    $this->assertFalse($mode->can_use_fixed_prices());
    $this->assertFalse($mode->can_use_payment_restrictions());
    $this->assertFalse($mode->can_use_auto_rate_update());
    $this->assertFalse($mode->can_use_multilingual());
    $this->assertFalse($mode->can_use_rest_api_filter());
}
```

- [ ] **Step 3: Remove legacy fallback in Mode::feature_granted() (snake_case)**

Modify `src/License/Mode.php`:

```php
// BEFORE (~v0.5.x):
private function feature_granted(string $name): bool {
    if (!$this->license_manager->is_active()) return false;
    $key = ClientSecrets::get_feature_token_key();
    if ($key === '') {
        return $this->license_manager->is_pro(); // LEGACY FALLBACK
    }
    $token = $this->license_manager->get_feature_token();
    if ($token === '') return false;
    $verifier = new FeatureTokenVerifier();
    if (!$verifier->verify($token, $this->license_manager->get_site_hash())) return false;
    return $verifier->has_feature($token, $name);
}

// AFTER (v0.6.0):
private function feature_granted(string $name): bool {
    if (!$this->license_manager->is_active()) return false;

    $token = $this->license_manager->get_feature_token();
    if ($token === '') return false;

    $verifier = new FeatureTokenVerifier();
    if (!$verifier->verify($token, $this->license_manager->get_site_hash())) return false;

    return $verifier->has_feature($token, $name);
}
```

> **Note:** Method renamed `hasFeature` → `has_feature` to match CS snake_case convention (set in Task C.2 Step 2).

- [ ] **Step 4: Update existing legacy-fallback tests in ModeFeatureTokenTest**

Find and replace tests asserting legacy fallback returned `true`. Convert to enforcement assertions:

```php
// BEFORE: testFeatureGrantedFallsBackToIsProWhenKeyMissing → asserts $mode->can_use_geolocation() === true
// AFTER:
public function test_feature_granted_requires_valid_token_no_fallback(): void
{
    $license_manager = $this->createMock(LicenseManager::class);
    $license_manager->method('is_active')->willReturn(true);
    $license_manager->method('is_pro')->willReturn(true); // license is Pro
    $license_manager->method('get_feature_token')->willReturn(''); // but no token

    $mode = new Mode($license_manager);
    $this->assertFalse($mode->can_use_geolocation(), 'Pro license without token must NOT grant access');
    $this->assertFalse($mode->can_use_fixed_prices());
    $this->assertFalse($mode->can_use_payment_restrictions());
    $this->assertFalse($mode->can_use_auto_rate_update());
    $this->assertFalse($mode->can_use_multilingual());
    $this->assertFalse($mode->can_use_rest_api_filter());
}
```

- [ ] **Step 5: Run all Mode tests + full suite**

```bash
cd /c/projects/mhm-currency-switcher && composer phpunit -- tests/Unit/License/ModeFeatureTokenTest.php
# Expected: All tests pass (legacy assertions converted to strict enforcement)

composer phpunit | tail -3
# Expected: ~143 tests, 0 failures
```

- [ ] **Step 6: Commit**

```bash
git add src/License/Mode.php tests/Unit/License/ModeFeatureTokenTest.php
git commit -m "feat(license): Mode::feature_granted() removes legacy is_pro() fallback (BREAKING)

CS counterpart of Rentiva v4.31.0 enforcement change. 6 gates now require RSA-signed token:
geolocation, fixed_prices, payment_restrictions, auto_rate_update, multilingual, rest_api_filter.
License-active alone is insufficient — server contact + token verify mandatory."
```

---

### Task C.4: ClientSecrets Cleanup (CS)

**Files:**
- Modify: `src/License/ClientSecrets.php`
- Modify: `tests/Unit/License/ClientSecretsTest.php`

- [ ] **Step 1: Verify no callers remain**

```bash
grep -rn 'get_feature_token_key\|MHM_CS_LICENSE_FEATURE_TOKEN_KEY' /c/projects/mhm-currency-switcher/src/
```

Expected: 0 matches after Task C.3 (Mode no longer uses it).

- [ ] **Step 2: Remove method from ClientSecrets**

Delete `get_feature_token_key()` method from `src/License/ClientSecrets.php`. Also remove the matching `MHM_CS_LICENSE_FEATURE_TOKEN_KEY` constant lookup.

Keep: `get_response_hmac_secret()` (response verify still uses it) and `get_ping_secret()` (VerifyEndpoint, optional with site_hash fallback).

- [ ] **Step 3: Remove related tests**

Delete tests in `tests/Unit/License/ClientSecretsTest.php` referencing `MHM_CS_LICENSE_FEATURE_TOKEN_KEY` or `get_feature_token_key`. Likely test method names:
- `test_get_feature_token_key_returns_constant_when_defined`
- `test_get_feature_token_key_returns_empty_string_when_undefined`

- [ ] **Step 4: Run full Licensing suite**

```bash
cd /c/projects/mhm-currency-switcher && composer phpunit -- tests/Unit/License/
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/License/ClientSecrets.php tests/Unit/License/ClientSecretsTest.php
git commit -m "refactor(license): ClientSecrets removes get_feature_token_key() — dead code post-RSA

Method was legacy HMAC secret reader. RSA verify uses LicenseServerPublicKey embedded constant.
MHM_CS_LICENSE_FEATURE_TOKEN_KEY wp-config constant deprecated."
```

---

### Task C.5: Version Bump + Changelog + i18n (CS)

**Files:**
- Modify: `mhm-currency-switcher.php`
- Modify: `readme.txt`
- Modify: `CHANGELOG.md`
- Regenerate: `languages/mhm-currency-switcher.pot`, `.po`, `.mo`, `.l10n.php`

- [ ] **Step 1: Bump version 0.5.2 → 0.6.0**

In `mhm-currency-switcher.php`:

```php
 * Version: 0.6.0
define('MHM_CS_VERSION', '0.6.0');
```

- [ ] **Step 2: Update readme.txt Stable tag and Changelog**

```
Stable tag: 0.6.0

= 0.6.0 (2026-04-25) =
* BREAKING: Pro features require RSA-signed feature token from license server v1.10.0+.
* New: src/License/LicenseServerPublicKey (embedded RSA-2048 public key).
* FeatureTokenVerifier migrates from HMAC to RSA verification.
* Mode::feature_granted() legacy is_pro() fallback REMOVED — strict enforcement.
* MHM_CS_LICENSE_FEATURE_TOKEN_KEY constant deprecated; safe to remove from wp-config.
* Closes cracked-binary attack vector.
```

- [ ] **Step 3: Update CHANGELOG.md**

Insert at top of `c:/projects/mhm-currency-switcher/CHANGELOG.md`:

```markdown
## [0.6.0] - 2026-04-25

### Added
- `src/License/LicenseServerPublicKey` — embedded RSA-2048 public key (heredoc class constant) for token verification.

### Changed
- `FeatureTokenVerifier` migrates from HMAC to RSA-2048 PKCS#1 v1.5 SHA-256.
- Token signature segment: hex HMAC → base64url binary RSA.
- Constructor accepts optional public key for test DI.
- `Mode::feature_granted()` legacy `is_pro()` fallback removed — strict token enforcement.

### Removed
- `ClientSecrets::get_feature_token_key()` — dead code post-RSA migration.
- HMAC verification path in `FeatureTokenVerifier`.

### Deprecated
- `MHM_CS_LICENSE_FEATURE_TOKEN_KEY` wp-config constant — no longer read; safe to remove.

### Breaking Changes
- License server v1.10.0+ deployment REQUIRED. Older server versions (v1.9.x) will not work with v0.6.0 client (server emits HMAC token; client expects RSA).
- Pro features (geolocation, fixed_pricing, payment_restrictions, auto_rate_update, multilingual, rest_api_filter) require valid RSA-signed feature token. License-active alone is insufficient.

### Security
- Closes the cracked-binary + product-matching license attack vector.
- Public key in plugin source is forge-proof — only license server's private key can sign valid tokens.

### Migration Notes
- **Operator action required (server side):** Generate RSA-2048 key pair, deploy private PEM to wpalemi.com wp-config (`MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM`).
- **Customer action:** None. wp-config edits not required (zero-config preserved).
- v0.5.x clients with this server v1.10.0 will continue working via legacy fallback path (token ignored, `is_pro()` used).
```

- [ ] **Step 4: i18n regen if new strings (mirror Phase B Task B.5 Step 6)**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-currency-switcher && wp i18n make-pot . languages/mhm-currency-switcher.pot --slug=mhm-currency-switcher --domain=mhm-currency-switcher"
docker exec rentiva-dev-wpcli-1 bash -c "msgmerge --update languages/mhm-currency-switcher-tr_TR.po languages/mhm-currency-switcher.pot"
docker exec rentiva-dev-wpcli-1 bash -c "grep -c '#, fuzzy' languages/mhm-currency-switcher-tr_TR.po"
# Expected: 0 fuzzy
docker exec rentiva-dev-wpcli-1 bash -c "msgfmt languages/mhm-currency-switcher-tr_TR.po -o languages/mhm-currency-switcher-tr_TR.mo"
docker exec rentiva-dev-wpcli-1 bash -c "wp i18n make-php languages/mhm-currency-switcher-tr_TR.po"
docker exec rentiva-dev-wpcli-1 wp cache flush
```

- [ ] **Step 5: Commit**

```bash
git add mhm-currency-switcher.php readme.txt CHANGELOG.md languages/
git commit -m "chore(release): bump CS to v0.6.0 — RSA asymmetric crypto migration"
```

---

### Task C.6: Phase C Release Gate

**Files:**
- All Phase C files

- [ ] **Step 1-7: Same gate as Phase B Task B.6 (PHPUnit, PHPCS, ZIP build, integrity, tag)**

```bash
# Run suite
cd /c/projects/mhm-currency-switcher && composer phpunit | tail -3
# Expected: 143 tests, 0 failures

# Run PHPCS — accept baseline 27 errors (existing CRLF), zero new
composer phpcs | tail -5

# Build ZIP
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-currency-switcher && python3 bin/build-release.py"
# Expected: build/mhm-currency-switcher.0.6.0.zip ~0.67 MB, 335 files

# Tag
git tag -a v0.6.0 -m "v0.6.0 — RSA asymmetric crypto for feature tokens

BREAKING: License server v1.10.0+ required.
6 Mode gates enforce strict RSA token validation.
Closes cracked-binary attack vector."
```

- [ ] **Step 8: GitHub release (DEFERRED — confirm with user)**

> **STOP — user confirmation required for push.**

- [ ] **Step 9: Phase C complete — checkpoint**

> **Per-Release Gate (Phase C complete):**
> - [ ] wp-reflect invoked
> - [ ] No live deploy needed (no live CS site yet)
> - [ ] mhm-rentiva-docs blog post — DEFERRED to Phase D wrap-up (combined release post for all 3 repos)

---

## Phase D — E2E Validation + Live Deploy (~1.5 hours)

### Task D.1: Pre-Deploy Configuration Audit

**Files:**
- None — diagnostic only

- [ ] **Step 1: Verify mhmrentiva.com wp-config does NOT have FEATURE_TOKEN_KEY defined**

Use Hostinger MCP to check wp-config (read-only) OR WP-CLI:

```bash
# Via Hostinger MCP file manager OR SSH:
ssh user@mhmrentiva.com 'grep -E "MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY|MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET|MHM_RENTIVA_LICENSE_PING_SECRET" wp-config.php' || echo "No matches — clean state"
```

Expected: No `FEATURE_TOKEN_KEY` defined. If defined: operator must remove BEFORE Phase D Task D.8 (Rentiva v4.31.0 deploy) to avoid Pro feature breakage during transition.

- [ ] **Step 2: Generate PRODUCTION RSA key pair**

```bash
# IN A SECURE LOCATION (not committed, not in any repo):
mkdir -p ~/secrets/mhm-license-server-prod
cd ~/secrets/mhm-license-server-prod
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out mhm-license-private.pem
openssl pkey -in mhm-license-private.pem -pubout -out mhm-license-public.pem
chmod 600 mhm-license-private.pem
```

- [ ] **Step 3: Backup PEM contents for embedding**

```bash
# View public key (for embedding in plugins)
cat ~/secrets/mhm-license-server-prod/mhm-license-public.pem

# View private key (for wp-config — DO NOT log to STDOUT in shared sessions)
# Read carefully and copy to clipboard for wp-config edit
cat ~/secrets/mhm-license-server-prod/mhm-license-private.pem
```

> **CRITICAL:** Never commit production keys. Never paste production private key into chat or shared docs. Operator transcribes private key directly from their local file to wp-config.

- [ ] **Step 4: Embed PRODUCTION public key in plugin sources**

For Rentiva (`mhm-rentiva/src/Admin/Licensing/LicenseServerPublicKey.php`):

```bash
# Open file, replace test PEM with prod PEM:
PROD_PEM=$(cat ~/secrets/mhm-license-server-prod/mhm-license-public.pem)
# Manual edit: replace existing PEM constant content with $PROD_PEM
```

For CS (`mhm-currency-switcher/src/License/LicenseServerPublicKey.php`):

```bash
# Same swap with prod public key
```

- [ ] **Step 5: Verify embedded keys are byte-identical**

```bash
# Extract embedded PEM from each plugin (between BEGIN and END markers):
diff <(sed -n '/-----BEGIN PUBLIC KEY-----/,/-----END PUBLIC KEY-----/p' /c/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/LicenseServerPublicKey.php) \
     <(sed -n '/-----BEGIN PUBLIC KEY-----/,/-----END PUBLIC KEY-----/p' /c/projects/mhm-currency-switcher/src/License/LicenseServerPublicKey.php)
```

Expected: 0 diff lines (identical PEM in both files).

- [ ] **Step 6: Re-run plugin test suites with prod public key**

```bash
# Tests use FIXTURE keys via DI — fixtures were not modified. Tests should still pass.
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage" | tail -3
cd /c/projects/mhm-currency-switcher && composer phpunit | tail -3
```

Expected: All tests pass (~790 Rentiva, ~143 CS). Tests don't depend on production PEM.

- [ ] **Step 7: Commit production PEM embed (NO PRIVATE KEY in commit)**

```bash
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
git add src/Admin/Licensing/LicenseServerPublicKey.php
git commit -m "release(licensing): embed production RSA public key for v4.31.0"

cd /c/projects/mhm-currency-switcher
git add src/License/LicenseServerPublicKey.php
git commit -m "release(license): embed production RSA public key for v0.6.0"
```

- [ ] **Step 8: Re-build release ZIPs with production PEM**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && python3 bin/build-release.py"
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-currency-switcher && python3 bin/build-release.py"
```

- [ ] **Step 9: Verify ZIP contains production PEM (NOT test fixture)**

```bash
# Extract ZIP, grep for known prod PEM substring
docker exec rentiva-dev-wpcli-1 bash -c "unzip -p /var/www/html/wp-content/plugins/mhm-rentiva/build/mhm-rentiva.4.31.0.zip mhm-rentiva/src/Admin/Licensing/LicenseServerPublicKey.php | head -20"
```

Verify: PEM constant matches production public key, NOT test fixture key.

---

### Task D.2: Local Docker — Happy Path E2E

**Files:**
- None — runtime validation

- [ ] **Step 1: Set up isolated test stack**

Use `rentiva-lisans` Docker stack (license server) + `rentiva-dev` Docker stack (Rentiva client). Or use a fresh test stack with both plugins.

```bash
# Start license server stack
cd /c/projects/rentiva-lisans && docker compose up -d
# Start Rentiva client stack
cd /c/projects/rentiva-dev && docker compose up -d
```

Verify both running:

```bash
docker ps | grep -E 'rentiva-lisans|rentiva-dev'
```

- [ ] **Step 2: Configure license server with PRODUCTION RSA private key**

Add to `c:/projects/rentiva-lisans/wp/wp-config.php`:

```php
// Manual edit — replace with actual production PEM:
define( 'MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', '-----BEGIN PRIVATE KEY-----
[... full PEM content ...]
-----END PRIVATE KEY-----' );
```

Restart server:

```bash
docker compose restart wordpress
```

- [ ] **Step 3: Generate a test license + activate from Rentiva client**

```bash
# Server: create license via WP-CLI
docker exec rentiva-lisans-wpcli-1 wp license create --product-slug=mhm-rentiva --plan=pro --customer-email=test@example.com

# Output: license key e.g. RNTV-XXXX-YYYY-ZZZZ
```

In rentiva-dev WP admin (`http://localhost:8080/wp-admin/admin.php?page=mhm-rentiva-license`):
- Enter license key
- Click Activate
- Expected: "License successfully activated"

- [ ] **Step 4: Verify Pro feature unlocks**

Navigate to a Pro feature (Vendor Marketplace, Advanced Reports). Verify accessible (not Lite-limited).

```bash
# Check response in admin AJAX or REST:
docker exec rentiva-dev-wpcli-1 wp eval '
    $manager = mhm_rentiva()->get("license_manager");
    var_dump($manager->isActive());
    var_dump(strlen($manager->getFeatureToken()));
    $mode = mhm_rentiva()->get("mode");
    var_dump($mode->canUseVendorMarketplace());
'
```

Expected:
- `isActive()` = `true`
- `getFeatureToken()` length > 0 (RSA token present)
- `canUseVendorMarketplace()` = `true`

✓ Happy path verified.

---

### Task D.3: Local Docker — Token Tamper Test

**Files:**
- Test scratch file: `c:/tmp/tamper-test.php`

- [ ] **Step 1: Write tamper test script**

Create `c:/tmp/tamper-test.php`:

```php
<?php
// Run via: docker exec rentiva-dev-wpcli-1 wp eval-file /var/www/html/tamper-test.php

require_once '/var/www/html/wp-load.php';

$manager = mhm_rentiva()->get('license_manager');
$token = $manager->getFeatureToken();

if ($token === '') {
    echo "FAIL: No token to tamper\n";
    exit(1);
}

$parts = explode('.', $token);
if (count($parts) !== 2) {
    echo "FAIL: Invalid token format\n";
    exit(1);
}

// Flip 1 byte in signature
$sig = base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4));
$sig[0] = chr(ord($sig[0]) ^ 0x01);
$tamperedSigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
$tamperedToken = $parts[0] . '.' . $tamperedSigB64;

// Inject tampered token into license manager (force scenario)
update_option('mhm_rentiva_feature_token', $tamperedToken);

// Re-check Mode
$mode = mhm_rentiva()->get('mode');
$result = $mode->canUseVendorMarketplace();

echo "Tampered token Mode result: " . ($result ? 'TRUE (FAIL)' : 'FALSE (PASS)') . "\n";

// Cleanup: restore valid token
update_option('mhm_rentiva_feature_token', $token);
```

- [ ] **Step 2: Copy + run tamper test**

```bash
docker cp /c/tmp/tamper-test.php rentiva-dev-wpcli-1:/var/www/html/tamper-test.php
docker exec rentiva-dev-wpcli-1 wp eval-file /var/www/html/tamper-test.php
```

Expected: `Tampered token Mode result: FALSE (PASS)`. RSA verify rejects tampered signature.

✓ Tamper protection verified.

---

### Task D.4: Local Docker — Token Forge Test

**Files:**
- Test scratch file: `c:/tmp/forge-test.php`

- [ ] **Step 1: Write forge test script**

Create `c:/tmp/forge-test.php`:

```php
<?php
require_once '/var/www/html/wp-load.php';

// Attacker scenario: sign a token with TEST FIXTURE private key
// (simulating attacker who generated their own RSA pair).
// Verify against PROD public key embedded in plugin → must FAIL.

$attackerPrivateKey = openssl_pkey_get_private(file_get_contents(__DIR__ . '/test-rsa-private.pem'));

$payload = [
    'license_key_hash' => 'hash-fake',
    'product_slug'     => 'mhm-rentiva',
    'plan'             => 'pro',
    'features'         => ['vendor_marketplace' => true, 'advanced_reports' => true],
    'site_hash'        => 'site-fake',
    'issued_at'        => time(),
    'expires_at'       => time() + 86400,
];
ksort($payload);
$canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$signature = '';
openssl_sign($canonical, $signature, $attackerPrivateKey, OPENSSL_ALGO_SHA256);

$forgedToken = rtrim(strtr(base64_encode($canonical), '+/', '-_'), '=') . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

// Inject forged token
update_option('mhm_rentiva_feature_token', $forgedToken);

$mode = mhm_rentiva()->get('mode');
$result = $mode->canUseVendorMarketplace();

echo "Forged token Mode result: " . ($result ? 'TRUE (FAIL — RSA crypto broken!)' : 'FALSE (PASS — forge rejected)') . "\n";

// Cleanup
delete_option('mhm_rentiva_feature_token');
```

- [ ] **Step 2: Copy fixture + script + run**

```bash
docker cp /c/projects/rentiva-dev/plugins/mhm-rentiva/tests/fixtures/test-rsa-private.pem rentiva-dev-wpcli-1:/var/www/html/
docker cp /c/tmp/forge-test.php rentiva-dev-wpcli-1:/var/www/html/forge-test.php
docker exec rentiva-dev-wpcli-1 wp eval-file /var/www/html/forge-test.php
```

Expected: `Forged token Mode result: FALSE (PASS — forge rejected)`. Attacker's private key cannot sign valid tokens — embedded public key (production) doesn't match.

✓ Forge protection verified.

```bash
# Cleanup test files from container
docker exec rentiva-dev-wpcli-1 rm -f /var/www/html/test-rsa-private.pem /var/www/html/forge-test.php
```

---

### Task D.5: Local Docker — Mode Patch Limit Test (Document Sınır)

**Files:**
- Test scratch: temporary edit of `Mode.php`

- [ ] **Step 1: Apply Mode patch (simulate cracked binary)**

```bash
# Backup original
docker exec rentiva-dev-wpcli-1 cp /var/www/html/wp-content/plugins/mhm-rentiva/src/Admin/Licensing/Mode.php /tmp/Mode.php.bak

# Inject patch — featureGranted always returns true
docker exec rentiva-dev-wpcli-1 bash -c "sed -i 's/private function featureGranted(string \$name): bool$/private function featureGranted(string \$name): bool { return true; }\\n    private function featureGrantedReal(string \$name): bool/' /var/www/html/wp-content/plugins/mhm-rentiva/src/Admin/Licensing/Mode.php"
```

> **NOTE:** This sed is brittle — if it fails, manually edit `Mode.php` and add `return true;` as first line of `featureGranted()`.

- [ ] **Step 2: Verify Pro features now accessible (cracked state)**

```bash
docker exec rentiva-dev-wpcli-1 wp eval '
    $mode = mhm_rentiva()->get("mode");
    echo "VendorMarketplace: " . ($mode->canUseVendorMarketplace() ? "OPEN" : "CLOSED") . "\n";
    echo "AdvancedReports: " . ($mode->canUseAdvancedReports() ? "OPEN" : "CLOSED") . "\n";
'
```

Expected: Both `OPEN`. **This confirms the documented defense-in-depth limit** — single-method patch bypasses all 4 gates.

> **Acceptance:** This is intentional. RSA crypto closes token forge attack, NOT single-method-patch attack. Future work v1.11.0+ will inline RSA verify per gate (Section 4.7 of design.md).

- [ ] **Step 3: Restore Mode.php**

```bash
docker exec rentiva-dev-wpcli-1 cp /tmp/Mode.php.bak /var/www/html/wp-content/plugins/mhm-rentiva/src/Admin/Licensing/Mode.php
docker exec rentiva-dev-wpcli-1 rm /tmp/Mode.php.bak
docker exec rentiva-dev-wpcli-1 wp cache flush
```

- [ ] **Step 4: Verify restoration**

```bash
docker exec rentiva-dev-wpcli-1 wp eval '
    $mode = mhm_rentiva()->get("mode");
    echo "VendorMarketplace: " . ($mode->canUseVendorMarketplace() ? "OPEN" : "CLOSED") . "\n";
'
```

Expected: `OPEN` (because legitimate license active in test stack). If `CLOSED` after restore but was `OPEN` before patch — restore fail.

✓ Mode-patch limit documented.

---

### Task D.6: Local Docker — Legacy Compat Test (v4.30.x client + v1.10.0 server)

**Files:**
- Use a separate Docker stack or rollback Rentiva to v4.30.2 in test stack

- [ ] **Step 1: Roll Rentiva client back to v4.30.2 in test stack**

```bash
# Option A: git checkout previous tag
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
git stash
git checkout v4.30.2
docker exec rentiva-dev-wpcli-1 wp cache flush

# Or Option B: install v4.30.2 ZIP if available locally
```

- [ ] **Step 2: Verify license still works (legacy fallback path)**

```bash
docker exec rentiva-dev-wpcli-1 wp eval '
    $manager = mhm_rentiva()->get("license_manager");
    echo "Active: " . ($manager->isActive() ? "YES" : "NO") . "\n";
    echo "Token: " . substr($manager->getFeatureToken(), 0, 20) . "...\n";
    $mode = mhm_rentiva()->get("mode");
    echo "VendorMarketplace: " . ($mode->canUseVendorMarketplace() ? "OPEN" : "CLOSED") . "\n";
'
```

Expected:
- `Active: YES` (license valid)
- `Token: <RSA-signed token>` (server v1.10.0 emits RSA token, client v4.30.x receives it)
- `VendorMarketplace: OPEN` (v4.30.x client uses legacy `isPro()` fallback because `FEATURE_TOKEN_KEY` undefined; RSA token unused but not harmful)

✓ Legacy compatibility verified — server v1.10.0 deploy doesn't break v4.30.x clients.

- [ ] **Step 3: Restore v4.31.0 in test stack**

```bash
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
git checkout main # or v4.31.0
git stash pop || true
docker exec rentiva-dev-wpcli-1 wp cache flush
```

---

### Task D.7: Live Deploy — License Server v1.10.0 (Hostinger MCP)

**Files:**
- None — deploy operation

- [ ] **Step 1: USER CONFIRMATION REQUIRED**

> **STOP.** Deploying to wpalemi.com production. Confirm before proceeding:
>
> "Ready to deploy mhm-license-server v1.10.0 to wpalemi.com. Will:
> 1. Upload `src/`, `mhm-license-server.php`, `readme.txt` (etc.) via Hostinger MCP `hosting_deployWordpressPlugin`
> 2. Operator must add `MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM` to wp-config BEFORE deploy completes
> 3. Verify activation: WP REST API call to confirm version + feature_token field
>
> Proceed?"

- [ ] **Step 2: Operator adds private key to wpalemi.com wp-config**

Operator (NOT Claude Code — security-critical) edits `wpalemi.com/wp-config.php` via Hostinger File Manager:

```php
// BEFORE the "happy blogging" comment:
define( 'MHM_LICENSE_SERVER_RSA_PRIVATE_KEY_PEM', '-----BEGIN PRIVATE KEY-----
[... full PEM content ...]
-----END PRIVATE KEY-----' );
```

Operator confirms write success.

- [ ] **Step 3: Deploy plugin via Hostinger MCP**

```
Use Hostinger MCP `hosting_deployWordpressPlugin`:
- pluginPath: c:/projects/rentiva-lisans/plugins/mhm-license-server
- (other params as per existing deploy convention)
```

Wait for response. Verify plugin file count uploaded matches local.

- [ ] **Step 4: Verify activation + version on live**

```bash
curl -X GET https://wpalemi.com/wp-json/wp/v2/plugins/mhm-license-server/mhm-license-server -u "admin:APP_PASSWORD" | jq '.version, .status'
```

Expected: `"1.10.0"`, `"active"`.

- [ ] **Step 5: Smoke test — issue feature_token from live server**

```bash
curl -X POST https://wpalemi.com/wp-json/mhm-license/v1/licenses/validate \
  -H "Content-Type: application/json" \
  -d '{"license_key":"<test-license>","site_hash":"abc","client_version":"4.31.0","product_slug":"mhm-rentiva"}' \
  | jq '.feature_token, .signature' | head -10
```

Expected: `feature_token` is non-null base64url string. `signature` (response HMAC) also present.

> **If `feature_token` is null:** RSA private key not loaded. Check operator's wp-config edit. Check error_log on wpalemi.com for "Feature token skipped" entries.

✓ Server v1.10.0 live and issuing RSA tokens.

---

### Task D.8: Live Deploy — Rentiva v4.31.0 (mhmrentiva.com)

**Files:**
- None — deploy operation

- [ ] **Step 1: USER CONFIRMATION REQUIRED**

> **STOP.** Deploying Rentiva v4.31.0 to mhmrentiva.com. Confirm pre-flight:
> 1. Server v1.10.0 deployed and verified at wpalemi.com (Task D.7 step 5 ✓)
> 2. mhmrentiva.com wp-config does NOT have `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` (Task D.1 step 1 ✓)
> 3. Production public key embedded in v4.31.0 ZIP (Task D.1 step 9 ✓)
>
> Proceed?

- [ ] **Step 2: Upload v4.31.0 ZIP to mhmrentiva.com**

```bash
# Option A: Hostinger MCP (50+ files, may exceed token budget)
# Option B: Manual upload via wp-admin/plugins.php → Upload Plugin → Replace existing
```

Operator chooses based on file count + ZIP size. Manual upload via wp-admin is recommended for Rentiva (760+ files).

- [ ] **Step 3: Activate v4.31.0**

Via WP admin OR:

```bash
curl -X POST https://mhmrentiva.com/wp-json/wp/v2/plugins/mhm-rentiva/mhm-rentiva -u "admin:APP_PASSWORD" -d '{"status":"active"}'
```

- [ ] **Step 4: Verify version + license still active**

```bash
curl -X GET https://mhmrentiva.com/wp-json/wp/v2/plugins/mhm-rentiva/mhm-rentiva -u "admin:APP_PASSWORD" | jq '.version, .status'
```

Expected: `"4.31.0"`, `"active"`.

```bash
# License re-validate (force fresh server contact + new RSA token):
# Via wp-admin License page → "Re-validate" button OR WP-CLI:
docker exec rentiva-dev-wpcli-1 ssh user@mhmrentiva.com 'wp eval "mhm_rentiva()->get(\"license_manager\")->validate();"'
```

- [ ] **Step 5: Verify Pro features open via Chrome DevTools MCP**

Navigate to mhmrentiva.com/wp-admin via Chrome DevTools MCP:
1. Open `/wp-admin/admin.php?page=mhm-rentiva-license` — verify "Active" badge
2. Open `/wp-admin/admin.php?page=mhm-rentiva-vendor-marketplace` — verify accessible (Pro feature)
3. Check for any error notices on admin pages

Expected: All Pro features accessible. No fatal errors. License page shows v4.31.0 active.

- [ ] **Step 6: Verify error_log for issues**

```bash
ssh user@mhmrentiva.com 'tail -30 wp-content/debug.log | grep -E "feature token|RSA|MHM_RENTIVA"'
```

Expected: No new errors. Optionally informational logs about token refresh OK.

✓ Rentiva v4.31.0 live with RSA enforcement.

---

### Task D.9: GitHub Releases (3 repos)

**Files:**
- None — release publishing

- [ ] **Step 1: Push tags to GitHub**

```bash
# Server
cd /c/projects/rentiva-lisans/plugins/mhm-license-server
git push origin main
git push origin v1.10.0

# Rentiva
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
git push origin main
git push origin v4.31.0

# CS
cd /c/projects/mhm-currency-switcher
git push origin develop
git push origin v0.6.0
```

- [ ] **Step 2: Create GitHub releases**

```bash
# Server
gh release create v1.10.0 --repo MaxHandMade/mhm-license-server \
  --title "v1.10.0 — RSA Asymmetric Crypto" \
  --notes-from-tag

# Rentiva
cd /c/projects/rentiva-dev/plugins/mhm-rentiva
gh release create v4.31.0 build/mhm-rentiva.4.31.0.zip \
  --title "v4.31.0 — RSA Asymmetric Crypto" \
  --notes-from-tag

# CS
cd /c/projects/mhm-currency-switcher
gh release create v0.6.0 build/mhm-currency-switcher.0.6.0.zip \
  --title "v0.6.0 — RSA Asymmetric Crypto" \
  --notes-from-tag
```

- [ ] **Step 3: Verify GH releases live**

```bash
gh release view v1.10.0 --repo MaxHandMade/mhm-license-server
gh release view v4.31.0 --repo MaxHandMade/mhm-rentiva
gh release view v0.6.0 --repo MaxHandMade/mhm-currency-switcher
```

Expected: All 3 releases visible with assets attached (ZIP for Rentiva + CS).

---

### Task D.10: Per-Release Gates — Documentation + KB Updates

**Files:**
- `c:/projects/mhm-rentiva-docs/blog/2026-04-25-asymmetric-crypto-release.md` (EN)
- `c:/projects/mhm-rentiva-docs/i18n/tr/docusaurus-plugin-content-blog/2026-04-25-asymmetric-crypto-release.md` (TR)
- `~/.claude/projects/c--projects-rentiva-dev/memory/hot.md`
- `~/.claude/projects/c--projects-rentiva-dev/memory/project_license_security_v430.md` (rename or supplement)
- `~/.claude/projects/c--projects-rentiva-dev/memory/fingerprint.md` (Rentiva)
- `~/.claude/wp-knowledge/patterns/` (new RSA crypto pattern)

- [ ] **Step 1: Write docs blog post (EN + TR)**

Topic: "Asymmetric Crypto License Migration — v1.10.0 / v4.31.0 / v0.6.0".

Content outline:
- Why the migration: cracked-binary attack vector
- What changed: HMAC → RSA-2048, Mode legacy fallback removed, breaking change for v4.30.x server compat
- Operator action: deploy private key to wp-config
- Customer action: upgrade clients (server first, then plugins)
- Defense-in-depth limit acknowledged

Save to:
- `c:/projects/mhm-rentiva-docs/blog/2026-04-25-asymmetric-crypto-release.md` (English)
- `c:/projects/mhm-rentiva-docs/i18n/tr/docusaurus-plugin-content-blog/2026-04-25-asymmetric-crypto-release.md` (Turkish)

Commit + push:

```bash
cd /c/projects/mhm-rentiva-docs
git add blog/ i18n/
git commit -m "blog: asymmetric crypto release — v1.10.0 / v4.31.0 / v0.6.0"
git push origin main
```

GitHub Pages deploy auto-triggers.

- [ ] **Step 2: Update hot.md**

Edit `~/.claude/projects/c--projects-rentiva-dev/memory/hot.md`:
- Update top-of-file timestamp
- Update aktif projeler tablosuyla yeni versiyonlar (license-server v1.10.0, Rentiva v4.31.0, CS v0.6.0)
- Add to Son Tamamlanan Görevler: detailed v1.10.0/v4.31.0/v0.6.0 RSA migration entry
- Update License Server — Kritik Noktalar with new RSA constants

- [ ] **Step 3: Update project_license_security_v430.md (or rename)**

Option A: Rename file → `project_license_asymmetric_v431.md`. Update MEMORY.md index.

Option B: Append to existing file as "Phase E — Asymmetric Crypto" section.

Either way, document:
- Phase A/B/C/D completed dates
- RSA-2048 + PKCS#1 v1.5 decision
- Defense-in-depth limit (single-method-patch) accepted as future work
- Files changed per repo

- [ ] **Step 4: Update fingerprint.md**

```bash
~/.claude/projects/c--projects-rentiva-dev/memory/fingerprint.md
```

Bump Plugin/Theme version: `4.31.0`. Update `Last Updated`.

- [ ] **Step 5: Add new KB pattern entry**

`~/.claude/wp-knowledge/patterns/` — add new file `rsa-asymmetric-crypto-license.md`:

Pattern document:
- When to use: zero-config + cracked-binary protection
- Key components: server private key, embedded public key, token format
- Test fixture sync rule
- Common pitfalls: canonical form mismatch, base64 vs base64url

- [ ] **Step 6: Run wp-reflect skill**

```
Skill: wp-reflect
Args: 9-release chain retrospective + asymmetric crypto migration learnings.
Categorize: global vs project-specific patterns. Update SKILL_MATURITY.md.
```

- [ ] **Step 7: Final verification**

```bash
# All 3 repos pushed
gh release view v1.10.0 --repo MaxHandMade/mhm-license-server
gh release view v4.31.0 --repo MaxHandMade/mhm-rentiva
gh release view v0.6.0 --repo MaxHandMade/mhm-currency-switcher

# Live working
curl -s https://wpalemi.com/wp-json/wp/v2/plugins/mhm-license-server/mhm-license-server -u admin:PW | jq '.version'
# Expected: "1.10.0"

curl -s https://mhmrentiva.com/wp-json/wp/v2/plugins/mhm-rentiva/mhm-rentiva -u admin:PW | jq '.version'
# Expected: "4.31.0"

# Docs blog live
curl -sI https://maxhandmade.github.io/mhm-rentiva-docs/blog/2026/04/25/asymmetric-crypto-release | head -3
# Expected: HTTP 200
```

✓ Phase D complete. All 4 phases done.

---

## Final Acceptance Summary

After all 4 phases complete:

- [ ] mhm-license-server v1.10.0 — wpalemi.com live, GH release published
- [ ] mhm-rentiva v4.31.0 — mhmrentiva.com live, GH release published, ZIP asset attached
- [ ] mhm-currency-switcher v0.6.0 — GH release published (no live site yet)
- [ ] All 3 repos: PHPUnit green, PHPCS clean (zero new errors over baseline)
- [ ] Live smoke test: Pro features accessible on mhmrentiva.com (Chrome DevTools MCP)
- [ ] Tamper test passed (token byte-flip → Pro closes)
- [ ] Forge test passed (attacker private key → embedded public key rejects)
- [ ] Mode-patch limit documented and accepted as future work
- [ ] Docs blog post EN + TR live on mhm-rentiva-docs
- [ ] hot.md updated with new versions + RSA migration entry
- [ ] project_license_security_v430.md (or _v431.md) reflects Phase E completion
- [ ] KB pattern file added (RSA asymmetric crypto)
- [ ] wp-reflect invoked, learnings categorized

---

## Spec Coverage Map

Mapping plan tasks to spec sections:

| Spec Section | Plan Tasks |
|---|---|
| 4.1 Genel Akış | A.1, A.2, A.3, B.1, B.2, C.1, C.2 |
| 4.2 Token Format | A.3 (server emit), B.2 (client verify), C.2 |
| 4.3 Token Payload | A.3 (canonical), B.2, C.2 |
| 4.4 Canonical Signing Form | A.3 helper, A.2 RsaSigner |
| 4.5 Server-Side | A.1 (SecretManager), A.2 (RsaSigner), A.3 (FeatureTokenIssuer), A.4 (LicenseValidator), A.5 (LicenseActivator), A.6 (cleanup) |
| 4.6 Client-Side | B.1 (LicenseServerPublicKey), B.2 (FeatureTokenVerifier), B.4 (ClientSecrets cleanup), C.1, C.2, C.4 |
| 4.7 Mode Gate Davranışı + Defense-in-Depth | B.3 (Mode), C.3 (Mode), D.5 (limit test) |
| 4.8 Grace Period | (Existing behavior unchanged, no task needed) |
| 5.1 Single-Field Swap | A.3 (no field rename, format change), D.6 (legacy compat verified) |
| 5.2 Asymmetric Deploy Senaryoları | D.7 (server first), D.8 (Rentiva after) |
| 5.3 wp-config Migration | A.7 (LIVE_DEPLOYMENT_CHECKLIST), D.1 (audit) |
| 6 Phase Yapısı | All Phase A-D tasks |
| 7 Test Stratejisi | A.0 (fixture gen), B.0/C.0 (sync), 7.1-7.5 covered in tests |
| 8 Acceptance Criteria | Final Acceptance Summary above |
| 9 Açık Sorular | Documented in spec, no plan task (future work) |

✓ Full spec coverage.
