<?php

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use WP_UnitTestCase;

/**
 * The "Shortcode Pages" tool must not offer pages Lite cannot render.
 *
 * Same defect class as BlockRegistryLiteSeamTest, in the second registry. An
 * unregistered shortcode does not vanish -- it degrades to its own literal
 * source text -- and create_page() publishes the raw shortcode as the page's
 * content. Offering a dropped Pro shortcode here was therefore one click away
 * from a live public page reading "[rentiva_vendor_apply]".
 *
 * The descriptions were a second violation: "Vendor Ledger -- commission history
 * and net payout balance" names a Pro feature in Lite's admin UI, which the
 * silent-degrade rule forbids.
 *
 * This suite runs against the real Lite tree, where the Pro shortcodes are
 * genuinely unregistered, so it exercises the seam rather than a simulation.
 *
 * @package MHMRentiva\Tests\Admin\Settings
 */
class ShortcodePagesLiteSeamTest extends WP_UnitTestCase
{

    /**
     * The Pro shortcodes Lite carves out. Each was offered by this tool.
     *
     * @return array<string, array{0: string}>
     */
    public function pro_shortcode_provider(): array
    {
        return array(
            'messages'         => array( 'rentiva_messages' ),
            'transfer_search'  => array( 'rentiva_transfer_search' ),
            'transfer_results' => array( 'rentiva_transfer_results' ),
            'vendor_apply'     => array( 'rentiva_vendor_apply' ),
            'vehicle_submit'   => array( 'rentiva_vehicle_submit' ),
            'vendor_directory' => array( 'rentiva_vendor_directory' ),
            'vendor_profile'   => array( 'rentiva_vendor_profile' ),
            'vendor_bookings'  => array( 'rentiva_vendor_bookings' ),
            'vendor_ledger'    => array( 'rentiva_vendor_ledger' ),
            'popular_routes'   => array( 'rentiva_popular_routes' ),
        );
    }

    /**
     * Guards the premise of every assertion below: if one of these shortcodes were
     * actually registered in Lite, the "absent" expectations would pass for the
     * wrong reason.
     *
     * @dataProvider pro_shortcode_provider
     */
    public function test_pro_shortcodes_are_not_registered_in_lite(string $shortcode): void
    {
        $this->assertFalse(
            shortcode_exists($shortcode),
            sprintf('Lite unexpectedly registers the Pro shortcode "%s".', $shortcode)
        );
    }

    /**
     * The end-user-visible outcome: the tool cannot offer to create the page.
     *
     * @dataProvider pro_shortcode_provider
     */
    public function test_the_tool_does_not_offer_pro_shortcode_pages(string $shortcode): void
    {
        $this->assertArrayNotHasKey(
            $shortcode,
            ( new ShortcodePageActions() )->get_config(),
            sprintf(
                'The Shortcode Pages tool offers "%s", which Lite cannot render. '
                . 'Creating that page would publish the literal shortcode text to visitors.',
                $shortcode
            )
        );
    }

    /**
     * Defence in depth: even a hand-crafted request naming a dropped shortcode
     * cannot publish a page, because create_page() resolves against the same
     * filtered config.
     *
     * @dataProvider pro_shortcode_provider
     */
    public function test_creating_a_pro_shortcode_page_is_refused(string $shortcode): void
    {
        $this->assertNull(
            ( new ShortcodePageActions() )->create_page($shortcode),
            sprintf('create_page() published a page for the carved-out shortcode "%s".', $shortcode)
        );
    }

    /**
     * The filter must be surgical: shortcodes Lite really ships keep their entries.
     * A filter that dropped everything would satisfy the assertions above while
     * breaking the tool -- and would make them vacuous.
     */
    public function test_core_shortcode_pages_are_still_offered(): void
    {
        $config = ( new ShortcodePageActions() )->get_config();

        foreach (
            array(
                'rentiva_my_bookings',
                'rentiva_my_favorites',
                'rentiva_booking_form',
                'rentiva_unified_search',
                'rentiva_search_results',
                'rentiva_vehicle_comparison',
            ) as $shortcode
        ) {
            $this->assertArrayHasKey(
                $shortcode,
                $config,
                sprintf('Core shortcode "%s" must still be offered in Lite.', $shortcode)
            );
        }
    }

    /**
     * The config keys off the live shortcode registry, not a hardcoded list: every
     * offered entry must be renderable, and nothing renderable is dropped.
     */
    public function test_every_offered_shortcode_is_actually_registered(): void
    {
        $config = ( new ShortcodePageActions() )->get_config();

        $this->assertNotSame(array(), $config, 'Premise failed: the tool offers nothing at all.');

        foreach (array_keys($config) as $shortcode) {
            $this->assertTrue(
                shortcode_exists((string) $shortcode),
                sprintf('The tool offers "%s" but no such shortcode is registered.', $shortcode)
            );
        }
    }

    /**
     * Lite's admin UI must not name Pro features. The dropped entries carried
     * descriptions like "commission history and net payout balance", which
     * described functionality this build does not have.
     */
    public function test_no_offered_description_names_a_carved_out_feature(): void
    {
        $offenders = array();

        foreach (( new ShortcodePageActions() )->get_config() as $shortcode => $info) {
            $text = strtolower(( $info['title'] ?? '' ) . ' ' . ( $info['description'] ?? '' ));

            foreach (array( 'vendor', 'commission', 'payout', 'transfer' ) as $term) {
                if (false !== strpos($text, $term)) {
                    $offenders[] = sprintf('%s mentions "%s"', $shortcode, $term);
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "Lite's Shortcode Pages tool describes features it does not ship:\n"
            . implode("\n", $offenders)
        );
    }
}
