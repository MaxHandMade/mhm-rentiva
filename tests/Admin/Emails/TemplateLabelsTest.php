<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Emails\Templates\EmailPreview;
use WP_UnitTestCase;

/**
 * The preview dropdown named only Lite's own eleven templates; every template an
 * extension adds to the registry fell back to its key run through ucfirst() --
 * "Booking created vendor", "Iban change approved" -- an English non-label on
 * a Turkish site. Labels are now one map with a filter, and the key fallback is
 * left only for a template nobody labelled.
 *
 * @covers \MHMRentiva\Admin\Emails\Core\Templates::labels
 * @covers \MHMRentiva\Admin\Emails\Core\Templates::label
 */
final class TemplateLabelsTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        remove_all_filters('mhmrentiva_email_template_labels');
        remove_all_filters('mhmrentiva_email_registry');
        parent::tearDown();
    }

    /**
     * Lite's own registry is fully labelled. With the add-on active its keys
     * join the registry, and its own test asserts the same for them.
     */
    public function test_every_registry_key_has_a_label(): void
    {
        $labels = Templates::labels();

        foreach (array_keys(Templates::registry()) as $key) {
            $this->assertArrayHasKey($key, $labels, "Template {$key} has no label; the dropdown would show its key.");
            $this->assertNotSame('', trim((string) $labels[ $key ]));
        }
    }

    public function test_an_extension_labels_its_templates_through_the_filter(): void
    {
        add_filter(
            'mhmrentiva_email_registry',
            static function (array $registry): array {
                $registry['payout_approved'] = array( 'subject' => 'x', 'file' => 'x' );
                return $registry;
            }
        );
        add_filter(
            'mhmrentiva_email_template_labels',
            static function (array $labels): array {
                $labels['payout_approved'] = 'Payout approved label';
                return $labels;
            }
        );

        $this->assertSame('Payout approved label', Templates::label('payout_approved'));

        ob_start();
        EmailPreview::render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<option value="payout_approved">Payout approved label</option>', $html);
        $this->assertStringNotContainsString('>Payout approved<', $html, 'The ucfirst() key fallback leaked into the dropdown.');
    }

    public function test_only_an_unlabelled_key_falls_back_to_the_key(): void
    {
        $this->assertSame('Some unknown key', Templates::label('some_unknown_key'));
        $this->assertNotSame('Booking cancelled', Templates::label('booking_cancelled'));
    }

    public function test_a_label_cannot_inject_markup_into_the_dropdown(): void
    {
        add_filter(
            'mhmrentiva_email_template_labels',
            static function (array $labels): array {
                $labels['booking_cancelled'] = '<b>x</b>';
                return $labels;
            }
        );

        ob_start();
        EmailPreview::render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }
}
