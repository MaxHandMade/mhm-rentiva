<?php

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use WP_UnitTestCase;

/**
 * Unified Search must not offer a Transfer surface Lite cannot serve.
 *
 * The transfer form posts to the `rentiva_transfer_results` page -- a shortcode
 * Lite carves out -- so submitting it landed the visitor on a page printing the
 * tag's own literal text. Neither the tab nor the panel may render.
 *
 * @package MHMRentiva\Tests\Frontend
 */
class UnifiedSearchTransferSeamTest extends WP_UnitTestCase
{

    /**
     * Guards the premise of every assertion below: Lite does not SHIP the class.
     *
     * This asserted class_exists() === false until 2026-07-17. That is a runtime
     * question, and it was the wrong one twice over:
     *
     *  1. It is false in the field. Lite and Pro share the MHMRentiva\ namespace and
     *     a chained autoloader, so on any site with the Pro add-on installed --
     *     including the owner's own dev box -- class_exists() is TRUE. The test only
     *     held because this suite runs against a Lite-only tree. It was effectively
     *     asserting "Pro is never installed", which Lite cannot guarantee.
     *  2. Because the class is absent HERE, the tab could not render for the wrong
     *     reason, and the assertions below passed with the licence gate reverted.
     *     Presence-only gating then shipped, and an unlicensed visitor really did
     *     get the Transfer tab (see UnifiedSearchTransferGateTest, which reproduces
     *     class-present + licence-absent).
     *
     * What this file actually needs to know is a PACKAGING fact -- Lite's src/ does
     * not contain the file -- and that is what it now checks. Stated this way the
     * premise stays true under both real configurations (Lite alone, and Lite with
     * an unlicensed Pro), instead of only the one the CI happens to run.
     */
    public function test_transfer_shortcode_class_is_not_shipped_by_lite(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2) . '/src/Admin/Transfer/Frontend/TransferShortcodes.php',
            'Lite must not ship the Pro Transfer shortcode class.'
        );
    }

    /**
     * The attribute default, the settings fallback and the service_type master
     * switch each independently force the transfer tab on. The seam gate must beat
     * all three, so every one is exercised here -- including the explicit
     * `show_transfer_tab="1"` and `service_type="transfer"` opt-ins, which are the
     * cases a naive default-only fix would miss.
     *
     * @dataProvider transfer_forcing_atts_provider
     */
    public function test_transfer_tab_and_panel_never_render_in_lite(array $atts, string $case): void
    {
        $html = UnifiedSearch::render($atts);

        $this->assertNotSame('', $html, "Premise: nothing rendered for {$case}, so the assertions would be vacuous.");
        $this->assertStringNotContainsString('data-testid="tab-transfer"', $html, "Transfer tab rendered for {$case}.");
        $this->assertStringNotContainsString('data-testid="transfer-search-form"', $html, "Transfer panel rendered for {$case}.");
        $this->assertStringNotContainsString('data-testid="search-submit-transfer"', $html, "Transfer submit rendered for {$case}.");
        $this->assertStringNotContainsString('_panel_transfer', $html, "Transfer panel markup rendered for {$case}.");
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public function transfer_forcing_atts_provider(): array
    {
        return array(
            'defaults'                 => array( array(), 'defaults' ),
            'explicit show_transfer'   => array( array( 'show_transfer_tab' => '1' ), 'show_transfer_tab="1"' ),
            'service_type=transfer'    => array( array( 'service_type' => 'transfer' ), 'service_type="transfer"' ),
            'default_tab=transfer'     => array( array( 'default_tab' => 'transfer' ), 'default_tab="transfer"' ),
            'transfer forced together' => array(
                array( 'service_type' => 'transfer', 'show_transfer_tab' => '1', 'default_tab' => 'transfer' ),
                'all transfer opt-ins at once',
            ),
        );
    }

    /**
     * The rental side must keep working -- including when the shortcode was asked
     * for transfer only, which would otherwise leave a widget with no panel at all.
     */
    public function test_rental_surface_still_renders_in_lite(): void
    {
        foreach (array( array(), array( 'service_type' => 'transfer' ) ) as $atts) {
            $html = UnifiedSearch::render($atts);

            $this->assertStringContainsString('data-testid="search-submit-rental"', $html);
        }
    }
}
