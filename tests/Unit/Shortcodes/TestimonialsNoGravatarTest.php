<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use WP_UnitTestCase;

/**
 * WP.org T4 #3 (Task B-G1b): the testimonials shortcode used to build a
 * https://www.gravatar.com/avatar/... URL from the reviewer's email -- an
 * undisclosed third-party HTTP request that contradicted readme.txt's "no
 * third-party requests" claim. It now renders a purely local initials
 * avatar instead. No Gravatar URL, and no WordPress core
 * get_avatar()/get_avatar_url() either (both default to Gravatar just like
 * the removed closure did).
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials
 */
final class TestimonialsNoGravatarTest extends WP_UnitTestCase
{
    /**
     * Source-level mutation proof: reintroduce a gravatar.com URL, or a
     * get_avatar()/get_avatar_url() call, in the template and this fails --
     * independent of whatever data happens to be seeded at render time.
     */
    public function test_template_source_has_no_gravatar_or_get_avatar_reference(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/templates/shortcodes/testimonials.php'
        );

        $this->assertStringNotContainsStringIgnoringCase(
            'gravatar',
            $source,
            'testimonials.php must not reference Gravatar any more.'
        );
        $this->assertStringNotContainsString(
            'get_avatar',
            $source,
            'testimonials.php must not call WordPress core get_avatar()/get_avatar_url() -- both default to Gravatar.'
        );
    }

    /**
     * Renders a real booking-review testimonial end to end and asserts the
     * emitted HTML carries a local initials avatar, not a Gravatar <img>.
     */
    public function test_renders_local_initials_avatar_for_booking_review(): void
    {
        $vehicle_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Test Vehicle',
        ));

        $booking_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));

        update_post_meta($booking_id, '_mhm_rentiva_customer_review', 'Great car, would rent again!');
        update_post_meta($booking_id, '_mhm_rentiva_review_approved', '1');
        update_post_meta($booking_id, '_mhm_rentiva_customer_rating', 5);
        update_post_meta($booking_id, '_mhm_rentiva_customer_name', 'Zeynep Kaya');
        update_post_meta($booking_id, '_mhm_rentiva_customer_email', 'zeynep@example.com');
        update_post_meta($booking_id, '_mhm_rentiva_vehicle_id', $vehicle_id);

        $output = Testimonials::render();

        $this->assertStringNotContainsStringIgnoringCase(
            'gravatar',
            $output,
            'Rendered testimonials markup must never reach out to Gravatar.'
        );
        $this->assertStringNotContainsString(
            'get_avatar',
            $output,
            'Rendered testimonials markup must not carry a get_avatar() call or its trace.'
        );
        $this->assertStringNotContainsString(
            '<img',
            $output,
            'No <img> avatar (Gravatar or otherwise) should be rendered for a testimonial.'
        );
        $this->assertStringContainsString(
            'rv-avatar-placeholder',
            $output,
            'A local initials placeholder must be rendered instead.'
        );
        $this->assertMatchesRegularExpression(
            '/rv-avatar-placeholder"[^>]*>\s*Z\s*<\/span>/',
            $output,
            "The initials placeholder must show the reviewer's first-name initial."
        );
    }

    /**
     * Tree-wide backstop (B-G1b review finding #3): PHPUnit's own copy of the
     * task brief's acceptance-bar grep --
     *   grep -rniE "gravatar|get_avatar" templates/ src/
     * -- so vehicle-rating-form.php, user-dashboard.php, and any future
     * template/class that reintroduces Gravatar fail a test automatically,
     * not just a manual grep someone has to remember to run. This also
     * catches a bare get_avatar() call on its own, which
     * bin/check-external-http.php's host-based deny-list cannot see -- that
     * gate matches literal host strings like "gravatar.com" and get_avatar()
     * emits none.
     */
    public function test_no_gravatar_or_get_avatar_anywhere_in_templates_or_src(): void
    {
        $root = dirname(__DIR__, 3);
        $hits = array();

        foreach (array( $root . '/templates', $root . '/src' ) as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ('php' !== $file->getExtension()) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (false !== stripos($source, 'gravatar') || false !== stripos($source, 'get_avatar')) {
                    $relative = str_replace('\\', '/', $file->getPathname());
                    $relative = str_replace(str_replace('\\', '/', $root) . '/', '', $relative);
                    $hits[]   = $relative;
                }
            }
        }

        sort($hits);

        $this->assertSame(
            array(),
            $hits,
            'gravatar/get_avatar reference(s) found in templates/ or src/: ' . implode(', ', $hits)
        );
    }
}
