<?php
/**
 * Tests for LicenseAdmin::admin_notices defensive rendering.
 *
 * @package MHMRentiva
 * @since   4.30.2
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use WP_UnitTestCase;

/**
 * v4.30.2+ — Defensive notice rendering. The original code rendered
 * "License activation failed: " (with empty trailing space) whenever the
 * URL had `?license=error` but no `&message=...` query param, because the
 * `match` default arm passed an empty string into sprintf("...: %s").
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
        $_GET             = [];

        // admin_notices() guards on current_user_can('manage_options'); the
        // WP test suite default user lacks role context, so create + sign in
        // an explicit admin so the guard does not short-circuit our cases.
        $admin_id = self::factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($admin_id);
    }

    protected function tearDown(): void
    {
        $_GET = $this->get_backup;
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * v4.30.2+ — Stale URL state (e.g. browser back/forward, bookmark, or
     * truncated copy-paste) leaves $_GET[license]=error without a matching
     * &message=. The defensive guard must skip the notice entirely instead
     * of rendering "License activation failed: " with an empty trailing.
     */
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

    /**
     * v4.30.2+ — site_unreachable is one of five v1.8.0+/v1.9.x server error
     * codes that previously fell through to the raw "License activation
     * failed: site_unreachable" default. Customers must see a friendly,
     * actionable message; localised string is checked via regex so the
     * test passes whether the .mo loaded yet or not.
     */
    public function test_site_unreachable_renders_friendly_message(): void
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
        $this->assertMatchesRegularExpression(
            '/(could not reach your site|sitenize.*ulaşamadı)/u',
            $output,
            'Friendly EN/TR message expected; raw error code must NOT leak through.'
        );
        $this->assertStringNotContainsString(
            'License activation failed: site_unreachable',
            $output,
            'Default raw-code render path must NOT be used; explicit case required.'
        );
    }

    /**
     * v4.30.2+ — Unknown error codes (future server releases) must not leak
     * raw technical strings into the customer-facing message; the default
     * branch renders a generic "Please try again." while exposing the code
     * via the `data-error-code` HTML attribute for support / debugging.
     */
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
            'Default branch must expose the technical code via HTML data attribute, not as inline text.'
        );
        $this->assertStringNotContainsString(
            'License activation failed: made_up_future_code_42',
            $output,
            'Raw "License activation failed: <code>" inline text must NOT be shown to customers anymore.'
        );
        $this->assertMatchesRegularExpression(
            '/(License activation failed\. Please try again|Lisans etkinleştirme başarısız oldu\. Lütfen tekrar deneyin)/u',
            $output,
            'Generic friendly EN/TR message expected.'
        );
    }

    /**
     * Regression — the happy-path 'activated' notice must keep rendering.
     */
    public function test_activated_path_still_renders_success_notice(): void
    {
        $_GET['license'] = 'activated';

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-success', $output);
        $this->assertStringContainsString('successfully activated', $output);
    }

    /**
     * Regression — the top-level guard `if ( ! isset($_GET[license]) ) return;`
     * must keep short-circuiting before any notice work happens.
     */
    public function test_no_license_query_renders_nothing(): void
    {
        // No $_GET[license] at all.

        ob_start();
        LicenseAdmin::admin_notices();
        $output = ob_get_clean();

        $this->assertSame('', $output, 'Top-level guard must short-circuit when license query is absent.');
    }
}
