<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * The preview's sample context only knew booking keys, so a template that reads
 * anything else -- an add-on's vendor name, payout amount, report title --
 * previewed with holes: "Merhaba , …", "İlanınız  gün sonra", "Nereden: <>".
 * build_context() now hands its sample to `mhmrentiva_email_preview_context`
 * so the template's owner fills what its templates read. Real sends never pass
 * through build_context() (Mailer::getBookingContext() builds theirs).
 *
 * @covers \MHMRentiva\Admin\Emails\Core\EmailTemplates::build_context
 */
final class PreviewSampleContextTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        remove_all_filters('mhmrentiva_email_preview_context');
        parent::tearDown();
    }

    public function test_the_preview_context_goes_through_the_filter_with_its_key(): void
    {
        $seen = array();
        add_filter(
            'mhmrentiva_email_preview_context',
            static function (array $ctx, string $key, int $booking_id) use (&$seen): array {
                $seen[]               = array( $key, $booking_id );
                $ctx['vendor']['name'] = 'Ahmet Yılmaz';
                return $ctx;
            },
            10,
            3
        );

        $ctx = EmailTemplates::build_context('vendor_approved', 0);

        $this->assertSame(array( array( 'vendor_approved', 0 ) ), $seen);
        $this->assertSame('Ahmet Yılmaz', $ctx['vendor']['name']);
        $this->assertSame('John Doe', $ctx['customer']['name'], 'Lite\'s own sample stays.');
    }

    public function test_a_real_send_context_never_sees_the_preview_filter(): void
    {
        add_filter(
            'mhmrentiva_email_preview_context',
            static function (array $ctx): array {
                $ctx['vendor']['name'] = 'SAMPLE';
                return $ctx;
            }
        );

        $id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        $this->assertArrayNotHasKey('vendor', (array) Mailer::getBookingContext($id));
    }

    /**
     * Lite's own templates, rendered from Lite's sample, leave nothing unfilled.
     * The add-on's suite runs the same check over the add-on's templates.
     */
    public function test_no_lite_template_previews_with_an_unfilled_placeholder(): void
    {
        foreach (array_keys(Templates::registry()) as $key) {
            $ctx  = EmailTemplates::build_context((string) $key, 0);
            $text = self::visible_text(Templates::compile_subject((string) $key, $ctx)) + self::visible_text(Templates::render_body((string) $key, $ctx));

            foreach ($text as $line) {
                $this->assertDoesNotMatchRegularExpression(self::HOLE, $line, "{$key} previews with a hole: \"{$line}\"");
            }
        }
    }

    /**
     * What an unfilled value leaves behind once it is substituted as '':
     * an unresolved token, a space before punctuation, a doubled space, an
     * empty pair of brackets or quotes, or a colon followed by nothing.
     */
    public const HOLE = '/\{\{[^}]*\}\}|\{[a-z_]+\}| [,.;:!?)]|  |<>|\(\)|""|“”|:\s+—/u';

    /**
     * The text a reader sees, one entry per text node (trimmed), so markup
     * indentation between nodes is not mistaken for a doubled space.
     *
     * @return array<int, string>
     */
    public static function visible_text(string $html): array
    {
        $doc = new \DOMDocument();
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- partial e-mail markup.
        @$doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        foreach (iterator_to_array($doc->getElementsByTagName('style')) as $style) {
            $style->parentNode->removeChild($style);
        }

        $out   = array();
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//text()') as $node) {
            $line = trim((string) preg_replace('/[\t\r\n]+\s*/', ' ', (string) $node->nodeValue));
            if ('' !== $line) {
                $out[] = $line;
            }
        }

        return $out;
    }
}
