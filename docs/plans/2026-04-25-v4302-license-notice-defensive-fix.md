# Rentiva v4.30.2 — License Notice Defensive Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `LicenseAdmin::admin_notices()` defensive against stale URL state and add friendly i18n mappings for v1.8.0+/v1.9.x server error codes (`product_mismatch`, `product_slug_required`, `site_unreachable`, `site_verification_failed`, `tampered_response`).

**Architecture:** Three-layer fix in a single switch case (`case 'error'`) of `src/Admin/Licensing/LicenseAdmin.php:417-435`:
1. **Defensive guard:** When `$error_message === ''`, skip notice render entirely (root cause of "License activation failed: " observed in production at `?license=error&message=` stale URL state).
2. **Coverage:** Add five new `match` cases for v1.8.0+ / v1.9.x server error codes with friendly Turkish-translated messages.
3. **Generic default:** Replace `default` raw error code echo with a customer-friendly generic message; the technical code is exposed via `data-error-code` HTML attribute for support/debugging without leaking jargon to end users.

**Tech Stack:** PHP 7.4+ (plugin minimum), WordPress 6.x admin notices, `__()` i18n, PHPUnit 9.6 (with WP test suite via `install-wp-tests.sh`), wp-i18n pipeline (`make-pot` → `msgmerge` → `make-mo` → `make-php`).

**Out of scope (deferred to v4.31.0):**
- Transient-based notice mechanism (replaces URL-query approach end-to-end).
- Server↔client error-code linter / sync automation.

---

## File Structure

| File | Type | Responsibility |
|---|---|---|
| `src/Admin/Licensing/LicenseAdmin.php` | Modify (lines 416-435) | Single switch case patched; no other behaviour changed. |
| `tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php` | Create | PHPUnit test class with 8 cases covering empty-error, each new code, default fallback, and the 'activated' regression case. |
| `languages/mhm-rentiva.pot` | Regen | Source extraction picks up the 5 new `__()` calls + new generic default message. |
| `languages/mhm-rentiva-tr_TR.po` | Regen + translate | `msgmerge` against new `.pot`, fill in 5 new TR translations. |
| `languages/mhm-rentiva-tr_TR.mo` | Compile | `wp i18n make-mo`. |
| `languages/mhm-rentiva-tr_TR.l10n.php` | Compile | `wp i18n make-php` (WP 6.5+ optimisation). |
| `mhm-rentiva.php` | Modify (header + define) | `Version: 4.30.1 → 4.30.2`, `MHM_RENTIVA_VERSION '4.30.1' → '4.30.2'`. |
| `readme.txt` | Modify | `Stable tag: 4.30.1 → 4.30.2` + `== Changelog ==` section gains v4.30.2 entry. |
| `changelog-tr.json` | Modify | Prepend new v4.30.2 entry. |

---

## Task 1: Failing Test for Empty error_message Defense

**Files:**
- Create: `tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php`

- [ ] **Step 1: Write the failing test class scaffold**

```php
<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use WP_UnitTestCase;

/**
 * v4.30.2+ — Defensive notice rendering. The original code would render
 * "License activation failed: " (empty trailing space) when the URL had
 * `?license=error` but no `&message=...` query param, because the match's
 * `default` case ran sprintf with an empty %s.
 *
 * @covers \MHMRentiva\Admin\Licensing\LicenseAdmin::admin_notices
 */
final class LicenseAdminAdminNoticesTest extends WP_UnitTestCase
{
    /** @var array<string,mixed> */
    private array $get_backup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->get_backup = $_GET;
        $_GET = [];

        // admin_notices() guards on current_user_can('manage_options'). The WP
        // test suite's default user is admin, but tests should be explicit.
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);
    }

    protected function tearDown(): void
    {
        $_GET = $this->get_backup;
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_empty_error_message_renders_no_notice(): void
    {
        $_GET['license'] = 'error';
        // No 'message' key — simulating stale URL state.

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertSame(
            '',
            $output,
            'Notice must NOT render when $_GET[message] is missing — defensive fix for stale URL state.'
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter test_empty_error_message_renders_no_notice"`

Expected: FAIL — output should contain `<div class="notice notice-error">` because the current `default` match arm renders "License activation failed: " (empty trailing).

- [ ] **Step 3: Commit failing test**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php
git commit -m "test(license): RED — empty error_message must not render notice"
```

---

## Task 2: Defensive Guard — Skip Notice on Empty error_message

**Files:**
- Modify: `src/Admin/Licensing/LicenseAdmin.php:416-435` (the `case 'error'` block)

- [ ] **Step 1: Apply defensive guard**

Replace:

```php
			case 'error':
				$error_text = match ($error_message) {
					'empty_key' => __('License key cannot be empty.', 'mhm-rentiva'),
```

With:

```php
			case 'error':
				// v4.30.2+ — Defensive: when stale URL state leaves $_GET[message]
				// unset (e.g. browser back/forward, bookmark, copy-paste of a
				// truncated URL), the original `default` match arm rendered
				// "License activation failed: " with an empty trailing %s.
				// Skip the notice entirely if there's no actual error code.
				if ('' === $error_message) {
					break;
				}

				$error_text = match ($error_message) {
					'empty_key' => __('License key cannot be empty.', 'mhm-rentiva'),
```

- [ ] **Step 2: Run test to verify it passes**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter test_empty_error_message_renders_no_notice"`

Expected: PASS.

- [ ] **Step 3: Commit GREEN**

```bash
git add src/Admin/Licensing/LicenseAdmin.php
git commit -m "fix(license): GREEN — skip notice render when error_message empty (stale URL guard)"
```

---

## Task 3: Failing Test for v1.9.x site_unreachable Mapping

**Files:**
- Modify: `tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php`

- [ ] **Step 1: Add failing test for site_unreachable**

Append inside the test class (before the closing brace):

```php
    public function test_site_unreachable_renders_friendly_turkish_message(): void
    {
        $_GET['license'] = 'error';
        $_GET['message'] = 'site_unreachable';

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'notice-error',
            $output,
            'Notice block must render for site_unreachable.'
        );
        $this->assertStringContainsString(
            'sitenize ulaşamıyor',
            $output,
            'Friendly Turkish message expected, NOT raw error code.'
        );
        $this->assertStringNotContainsString(
            'License activation failed: site_unreachable',
            $output,
            'Default raw-code render must NOT be used; explicit case required.'
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter test_site_unreachable_renders_friendly_turkish_message"`

Expected: FAIL — string `'License activation failed: site_unreachable'` appears in output (default match path).

- [ ] **Step 3: Add `site_unreachable` case to the match**

In `src/Admin/Licensing/LicenseAdmin.php`, replace:

```php
					'license_http' => __('License server error. Please try again later.', 'mhm-rentiva'),
					'missing_parameters' => __('Missing required parameters.', 'mhm-rentiva'),
```

With:

```php
					'license_http' => __('License server error. Please try again later.', 'mhm-rentiva'),
					'missing_parameters' => __('Missing required parameters.', 'mhm-rentiva'),
					// v4.30.2+ — server v1.9.0+ reverse-validation error codes
					'site_unreachable' => __('License server could not reach your site for verification. A firewall or CDN may be blocking inbound HTTP. Please contact support.', 'mhm-rentiva'),
					'site_verification_failed' => __('Site verification failed. Please try again or contact support.', 'mhm-rentiva'),
					'tampered_response' => __('License server response could not be verified (tampered or out-of-sync). Please contact support.', 'mhm-rentiva'),
					// v4.30.2+ — server v1.8.0+ / v1.9.3+ product binding error codes
					'product_mismatch' => __('This license key was issued for a different product and cannot be activated here.', 'mhm-rentiva'),
					'product_slug_required' => __('Your plugin version is outdated or the request is malformed. Please update to the latest plugin release and try again.', 'mhm-rentiva'),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter test_site_unreachable_renders_friendly_turkish_message"`

Expected: PASS.

NOTE: The Turkish test assertion (`'sitenize ulaşamıyor'`) will still match against the EN source string at this stage because i18n .po regen comes in Task 6 — TR translation has not been compiled yet. To keep the test deterministic regardless of locale, change the assertion to look for an EN-or-TR substring:

```php
$this->assertMatchesRegularExpression(
    '/(could not reach your site|sitenize ulaşamıyor)/u',
    $output
);
```

If you implemented the assertion that way in Step 1, no change needed; otherwise, update the assertion before re-running.

- [ ] **Step 5: Commit GREEN**

```bash
git add src/Admin/Licensing/LicenseAdmin.php tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php
git commit -m "feat(license): GREEN — friendly notices for site_unreachable + 4 other v1.8/v1.9 codes"
```

---

## Task 4: Failing Test for Generic Default + data-error-code

**Files:**
- Modify: `tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php`

- [ ] **Step 1: Add failing test for unknown code default fallback**

Append inside the test class:

```php
    public function test_unknown_error_code_renders_generic_message_and_data_attribute(): void
    {
        $_GET['license'] = 'error';
        $_GET['message'] = 'made_up_future_code_42';

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-error', $output);
        $this->assertStringContainsString(
            'data-error-code="made_up_future_code_42"',
            $output,
            'Default branch must expose technical code via HTML data attribute for support/debugging, not as inline text.'
        );
        $this->assertStringNotContainsString(
            'License activation failed: made_up_future_code_42',
            $output,
            'Raw "License activation failed: <code>" inline text must NOT be shown to customers anymore.'
        );
        $this->assertMatchesRegularExpression(
            '/(License activation failed\. Please try again|Lisans etkinleştirme başarısız oldu\. Lütfen tekrar deneyin)/u',
            $output,
            'Generic friendly message expected.'
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter test_unknown_error_code_renders_generic_message_and_data_attribute"`

Expected: FAIL on `data-error-code` substring (current default uses inline raw code).

- [ ] **Step 3: Replace default branch with generic message + data attribute**

In `src/Admin/Licensing/LicenseAdmin.php`, replace:

```php
					/* translators: %s: error message */
					default => sprintf(esc_html__('License activation failed: %s', 'mhm-rentiva'), esc_html($error_message)),
				};
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>' . esc_html($error_text) . '</p>';
				echo '</div>';
				break;
```

With:

```php
					default => __('License activation failed. Please try again.', 'mhm-rentiva'),
				};

				// v4.30.2+ — Expose technical code via data attribute (debug/support)
				// instead of leaking it into the customer-facing message text.
				printf(
					'<div class="notice notice-error is-dismissible" data-error-code="%s"><p>%s</p></div>',
					esc_attr($error_message),
					esc_html($error_text)
				);
				break;
```

- [ ] **Step 4: Run all admin_notices tests to verify pass + no regression**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter LicenseAdminAdminNoticesTest"`

Expected: 3 tests pass (empty defense, site_unreachable, unknown default).

- [ ] **Step 5: Commit**

```bash
git add src/Admin/Licensing/LicenseAdmin.php tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php
git commit -m "fix(license): generic default message + data-error-code attribute (no raw code leakage)"
```

---

## Task 5: Regression Test for Existing 'activated' Path

**Files:**
- Modify: `tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php`

- [ ] **Step 1: Add regression test for happy-path activation notice**

Append inside the test class:

```php
    public function test_activated_path_still_renders_success_notice(): void
    {
        $_GET['license'] = 'activated';

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-success', $output);
        $this->assertStringContainsString('successfully activated', $output);
    }

    public function test_no_license_query_renders_nothing(): void
    {
        // No $_GET[license] at all.

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertSame('', $output, 'Top-level guard `if (! isset($_GET[license])) return;` must keep working.');
    }
```

- [ ] **Step 2: Run all admin_notices tests**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter LicenseAdminAdminNoticesTest"`

Expected: 5 tests pass.

- [ ] **Step 3: Commit regression coverage**

```bash
git add tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php
git commit -m "test(license): regression coverage for 'activated' + no-query paths"
```

---

## Task 6: Full Suite + PHPCS Validation

- [ ] **Step 1: Run full PHPUnit suite**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"`

Expected: 776 + 5 new = 781 tests, all green (skipped count ≤ 6 unchanged).

- [ ] **Step 2: Run PHPCS**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --runtime-set ignore_warnings_on_exit 1; echo EXIT=\$?"`

Expected: `EXIT=0` (no new errors over baseline 0).

- [ ] **Step 3: BOM check on touched files**

Run:
```bash
for f in c:/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php c:/projects/rentiva-dev/plugins/mhm-rentiva/tests/Unit/Licensing/LicenseAdminAdminNoticesTest.php; do
  head -c 3 "$f" | xxd | grep -q "ef bb bf" && echo "BOM in $f"
done
echo "(no output = no BOM)"
```

Expected: no output.

- [ ] **Step 4: If anything fails — STOP, return to Phase 1 of systematic-debugging. Do not move on.**

---

## Task 7: i18n Pipeline (5 New TR Strings)

- [ ] **Step 1: Regenerate .pot**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp i18n make-pot . languages/mhm-rentiva.pot --slug=mhm-rentiva --allow-root"`

Expected: `Success: POT file successfully generated.`

- [ ] **Step 2: msgmerge TR**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva/languages && msgmerge --update --backup=none mhm-rentiva-tr_TR.po mhm-rentiva.pot"`

Expected: `done.` with no fatal errors.

- [ ] **Step 3: Translate the 6 new strings (5 error codes + 1 generic default)**

Edit `c:/projects/rentiva-dev/plugins/mhm-rentiva/languages/mhm-rentiva-tr_TR.po`. Find each new `msgid` (the ones with empty `msgstr ""`) and translate:

| msgid | msgstr |
|---|---|
| `License server could not reach your site for verification. A firewall or CDN may be blocking inbound HTTP. Please contact support.` | `Lisans sunucusu sitenize doğrulama için ulaşamadı. Bir güvenlik duvarı veya CDN gelen HTTP isteklerini engelliyor olabilir. Lütfen destek ile iletişime geçin.` |
| `Site verification failed. Please try again or contact support.` | `Site doğrulaması başarısız oldu. Lütfen tekrar deneyin veya destek ile iletişime geçin.` |
| `License server response could not be verified (tampered or out-of-sync). Please contact support.` | `Lisans sunucusu yanıtı doğrulanamadı (kurcalanmış veya senkron dışı). Lütfen destek ile iletişime geçin.` |
| `This license key was issued for a different product and cannot be activated here.` | `Bu lisans anahtarı farklı bir ürün için verilmiş ve burada etkinleştirilemez.` |
| `Your plugin version is outdated or the request is malformed. Please update to the latest plugin release and try again.` | `Eklenti sürümünüz güncel değil veya istek bozuk. Lütfen en son eklenti sürümüne güncelleyin ve tekrar deneyin.` |
| `License activation failed. Please try again.` | `Lisans etkinleştirme başarısız oldu. Lütfen tekrar deneyin.` |

- [ ] **Step 4: Verify no empty msgstr remains for these 6 keys**

Run:
```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva/languages && for k in 'License server could not reach' 'Site verification failed' 'License server response could not be verified' 'This license key was issued for a different product' 'Your plugin version is outdated' 'License activation failed. Please try again'; do
  grep -A1 \"\$k\" mhm-rentiva-tr_TR.po | grep '^msgstr ' | head -1
done"
```

Expected: each `msgstr` line is non-empty (contains Turkish text).

- [ ] **Step 5: Compile .mo + .l10n.php**

Run:
```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp i18n make-mo languages --allow-root && wp i18n make-php languages --allow-root"
```

Expected: two `Success: Created 1 file.` lines.

- [ ] **Step 6: Re-run full PHPUnit suite (verify TR translations did not break tests)**

Run: `docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage 2>&1 | tail -5"`

Expected: 781 / 781 OK.

- [ ] **Step 7: Commit i18n**

```bash
git add languages/
git commit -m "i18n(rentiva): TR translations for v4.30.2 license notice messages (6 new strings)"
```

---

## Task 8: Version Bump to 4.30.2

**Files:**
- Modify: `mhm-rentiva.php` (Version header + `MHM_RENTIVA_VERSION` define)
- Modify: `readme.txt` (Stable tag + Changelog section)
- Modify: `changelog-tr.json` (prepend new entry)

- [ ] **Step 1: Bump version constants**

Edit `c:/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php`:
- Plugin header line (search for ` * Version:           4.30.1`) → ` * Version:           4.30.2`
- `define('MHM_RENTIVA_VERSION', '4.30.1');` → `define('MHM_RENTIVA_VERSION', '4.30.2');`

Edit `c:/projects/rentiva-dev/plugins/mhm-rentiva/readme.txt`:
- `Stable tag: 4.30.1` → `Stable tag: 4.30.2`

- [ ] **Step 2: Add v4.30.2 readme.txt changelog entry**

In `c:/projects/rentiva-dev/plugins/mhm-rentiva/readme.txt`, after `== Changelog ==` and before `= 4.30.1 =`, insert:

```
= 4.30.2 =
* **License notice rendering — defensive fix:** When the License page URL had `?license=error` but no `&message=...` (stale URL state from browser back/forward, bookmark, or truncated copy-paste), the notice rendered "License activation failed: " with an empty trailing space. v4.30.2 skips the notice entirely when the error code is missing.
* **License notice — friendly mappings for v1.8.0+/v1.9.x server error codes:** `site_unreachable`, `site_verification_failed`, `tampered_response`, `product_mismatch`, `product_slug_required` now produce customer-friendly Turkish-translated messages instead of falling through to the raw "License activation failed: <technical_code>" default.
* **Notice default — generic message + data attribute:** Unknown future error codes render a generic "License activation failed. Please try again." with the technical code exposed via `data-error-code` HTML attribute (debug/support only — not shown to end users).
* **Tests:** 5 new unit tests under `LicenseAdminAdminNoticesTest`. 776 → 781 PHPUnit, PHPCS clean.
```

- [ ] **Step 3: Add changelog-tr.json entry (prepend after `[`)**

Edit `c:/projects/rentiva-dev/plugins/mhm-rentiva/changelog-tr.json`. After the opening `[` and before the first `{`, insert:

```json
    {
        "version": "4.30.2",
        "date": "2026-04-25",
        "type": "patch",
        "changes": [
            "🛠 LİSANS BİLDİRİMİ — Defansif düzeltme: License sayfası URL'sinde `?license=error` varken `&message=...` yoksa (tarayıcı geri/ileri, bookmark veya kırpılmış kopyala-yapıştır), notice 'License activation failed: ' boş kuyrukla render oluyordu. v4.30.2 ile error kodu yoksa notice tamamen render edilmiyor.",
            "🌐 LİSANS BİLDİRİMİ — v1.8.0+/v1.9.x sunucu hata kodları için Türkçe mesajlar: `site_unreachable`, `site_verification_failed`, `tampered_response`, `product_mismatch`, `product_slug_required` artık ham 'License activation failed: <teknik_kod>' yerine müşteri-dostu Türkçe çevrilmiş mesaj veriyor.",
            "🔧 NOTICE DEFAULT — Bilinmeyen gelecek error kodları için generic 'Lisans etkinleştirme başarısız oldu. Lütfen tekrar deneyin.' mesajı + teknik kod `data-error-code` HTML attribute'unda (sadece debug/support için, son kullanıcıya yansımaz).",
            "🧪 5 yeni unit test (`LicenseAdminAdminNoticesTest`). 776 → 781 PHPUnit, 0 PHPCS."
        ],
        "notice": "v4.30.2 — Lisans bildirimi defansif düzeltme + v1.9.x sunucu hata kodları için Türkçe friendly mesajlar."
    },
```

- [ ] **Step 4: Verify version coherence**

Run:
```bash
grep -E "Version:|MHM_RENTIVA_VERSION|Stable tag" \
  c:/projects/rentiva-dev/plugins/mhm-rentiva/mhm-rentiva.php \
  c:/projects/rentiva-dev/plugins/mhm-rentiva/readme.txt | head -5
```

Expected: all three lines show `4.30.2`.

- [ ] **Step 5: Commit version bump**

```bash
git add mhm-rentiva.php readme.txt changelog-tr.json
git commit -m "chore(release): v4.30.2 — license notice defensive fix + v1.9.x error code mappings"
```

---

## Task 9: ZIP Build with Pre-Release Audit

- [ ] **Step 1: Snapshot v4.30.1 ZIP file list (for diff)**

Run:
```bash
docker exec rentiva-dev-wordpress-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && unzip -l build/mhm-rentiva.4.30.1.zip 2>/dev/null | awk '{print \$4}' | grep -v '^$' | sort > /tmp/zip-v4301.txt; wc -l /tmp/zip-v4301.txt"
```

If v4.30.1 ZIP isn't on disk anymore, skip diff in Step 4 and rely on standalone audit in Step 3.

- [ ] **Step 2: Build v4.30.2 ZIP**

Run:
```bash
docker exec rentiva-dev-wordpress-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && rm -rf build/zip-staging build/mhm-rentiva*.zip 2>/dev/null; python3 bin/build-release.py 2>&1 | tail -5"
```

Expected: `[build] SUCCESS  : .../build/mhm-rentiva.4.30.2.zip`, single root `mhm-rentiva/`.

- [ ] **Step 3: Standalone audit — top-level dirs + suspicious patterns**

Run:
```bash
docker exec rentiva-dev-wordpress-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && unzip -l build/mhm-rentiva.4.30.2.zip | awk '{print \$4}' | grep -v '^$' > /tmp/zip-v4302.txt; wc -l /tmp/zip-v4302.txt; echo '--- top-level ---'; awk -F/ '{print \$1\"/\"\$2}' /tmp/zip-v4302.txt | sort -u; echo '--- suspicious ---'; grep -iE 'drafts|tests/|/docs/|wporg|debug_|DEV_NOTES|\\.md\$' /tmp/zip-v4302.txt | head -10"
```

Expected:
- Top-level: same 12 entries as v4.30.1 (CHANGELOG.json, LICENSE, README.md, assets, changelog-tr.json, composer.json, languages, mhm-rentiva.php, readme.txt, src, templates, uninstall.php).
- Suspicious patterns: only `README.md` (legitimate, ships intentionally).

- [ ] **Step 4: Diff against v4.30.1 ZIP (if snapshot taken)**

Run:
```bash
docker exec rentiva-dev-wordpress-1 bash -c "comm -23 /tmp/zip-v4301.txt /tmp/zip-v4302.txt | head; echo '---'; comm -13 /tmp/zip-v4301.txt /tmp/zip-v4302.txt | head"
```

Expected:
- Removed: empty (nothing was deleted between releases).
- Added: empty (no new files; only modifications to existing).

If anything unexpected appears in either side — STOP and investigate.

- [ ] **Step 5: Copy ZIP out + size check**

Run:
```bash
docker cp rentiva-dev-wordpress-1:/var/www/html/wp-content/plugins/mhm-rentiva/build/mhm-rentiva.4.30.2.zip /tmp/mhm-rentiva.4.30.2.zip
ls -la /tmp/mhm-rentiva.4.30.2.zip
```

Expected: ~2.94 MB (similar to v4.30.1's 2.80 MB).

---

## Task 10: Tag + Push + GitHub Release

- [ ] **Step 1: Push commits to origin/main**

Run:
```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva && git push origin main 2>&1 | tail -3
```

Expected: `main -> main` line confirming push succeeded.

- [ ] **Step 2: Tag v4.30.2 + push**

Run:
```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva && \
  git tag -a v4.30.2 -m "v4.30.2 — license notice defensive fix + v1.9.x error code mappings" && \
  git push origin v4.30.2 2>&1 | tail -3
```

Expected: `[new tag] v4.30.2 -> v4.30.2`.

- [ ] **Step 3: Create GitHub Release with notes**

Run:
```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva && \
gh release create v4.30.2 /tmp/mhm-rentiva.4.30.2.zip \
  --repo MaxHandMade/mhm-rentiva \
  --title "v4.30.2 — license notice defensive fix" \
  --notes "$(cat <<'EOF'
## License notice rendering hardened

### Fixed

- **Defensive: skip notice when error code missing.** When the License page URL had \`?license=error\` but no \`&message=...\` query param (stale URL state from browser back/forward, bookmark, or truncated copy-paste), the notice rendered "License activation failed: " with an empty trailing space. v4.30.2 skips the notice entirely when the error code is missing.

- **Friendly i18n for v1.8.0+/v1.9.x server error codes.** \`site_unreachable\`, \`site_verification_failed\`, \`tampered_response\`, \`product_mismatch\`, and \`product_slug_required\` now produce customer-friendly Turkish-translated messages instead of falling through to the raw "License activation failed: <technical_code>" default.

- **Generic default + data-error-code attribute.** Unknown future error codes render a generic "License activation failed. Please try again." with the technical code exposed via the \`data-error-code\` HTML attribute (debug / support only — never shown inline to end users).

### Test coverage

5 new unit tests under \`LicenseAdminAdminNoticesTest\` covering: empty error_message defense, site_unreachable friendly mapping, generic default + data attribute, regression for the 'activated' success path, and regression for no-license-query-at-all. Total suite: **781 tests, 2720 assertions, 6 skipped**, PHPCS clean.

### Compatibility

Drop-in replacement for v4.30.1. No wp-config changes. No server compatibility implications. Works against \`mhm-license-server\` v1.8.0+ and v1.9.x.
EOF
)" 2>&1 | tail -3
```

Expected: GitHub release URL printed.

---

## Task 11: Live DOM Verification on mhmrentiva.com

> **Per skill: superpowers:verification-before-completion + feedback_empirical_dom_verification.md** — runtime smoke test before claiming done.

- [ ] **Step 1: Update plugin on mhmrentiva.com**

This step is **manual on the operator side** (the conductor cannot push plugin files to live without operator action):
- wp-admin → Plugins → MHM Rentiva → Deactivate + Delete
- Upload `/tmp/mhm-rentiva.4.30.2.zip` from the GitHub release
- Activate
- Confirm header version reads `v4.30.2`

When operator confirms upload complete, proceed.

- [ ] **Step 2: Verify Bug B is closed (empty error_message → no notice)**

Run via Chrome DevTools MCP:
- Navigate to `https://mhmrentiva.com/wp-admin/admin.php?page=mhm-rentiva-license&license=error`
- Take snapshot of the License Management page DOM
- Search for substring `License activation failed`

Expected: substring NOT found. The empty notice that was visible at the top of the page in v4.30.1 must be GONE.

- [ ] **Step 3: Verify Bug A is closed (friendly TR mapping)**

Navigate to `https://mhmrentiva.com/wp-admin/admin.php?page=mhm-rentiva-license&license=error&message=site_unreachable`

Take snapshot. Search the DOM for:
- `Lisans sunucusu sitenize` — must be present (friendly TR mapping is rendered)
- `License activation failed: site_unreachable` — must NOT be present (raw code is suppressed)

- [ ] **Step 4: Verify default branch (unknown code)**

Navigate to `https://mhmrentiva.com/wp-admin/admin.php?page=mhm-rentiva-license&license=error&message=imaginary_future_code`

Search DOM for:
- `data-error-code="imaginary_future_code"` — must be present in the `<div class="notice notice-error">` element
- `Lisans etkinleştirme başarısız oldu. Lütfen tekrar deneyin.` — must be the visible message text
- `License activation failed: imaginary_future_code` — must NOT appear inline

- [ ] **Step 5: Regression — happy path notice still renders**

Activate / deactivate the QMLZ test license (or simulate via URL `?license=activated`), confirm "✅ License successfully activated!" or its TR equivalent renders correctly.

If any of Steps 2–5 fails — STOP, return to systematic-debugging Phase 1, do not claim done.

---

## Self-Review Checklist

- [ ] All 11 tasks have explicit code blocks for code steps (no "implement appropriate handling" placeholders).
- [ ] All file paths are absolute or fully relative from plugin root.
- [ ] Type / function / property names used in later tasks match earlier ones (`admin_notices` static method everywhere, never `render_admin_notices`).
- [ ] Spec coverage:
  - Defensive guard → Task 1+2.
  - 5 new error code mappings → Task 3.
  - Generic default + data attribute → Task 4.
  - Regression coverage → Task 5.
  - Validation gates → Task 6.
  - i18n pipeline → Task 7.
  - Version + changelog → Task 8.
  - ZIP audit → Task 9.
  - Tag + GH release → Task 10.
  - Live DOM verification → Task 11.
- [ ] Each commit is small enough to revert cleanly.
- [ ] TDD discipline preserved: every implementation step is preceded by a failing test step.
- [ ] No `--no-verify`, `--force`, or amend operations anywhere.

---

**Out-of-scope reminders (do NOT do during this release):**
- Do NOT migrate to transient-based notices (deferred to v4.31.0).
- Do NOT add server↔client error-code linter (deferred to v4.31.0).
- Do NOT touch CS or server in this release — Rentiva-only patch.
