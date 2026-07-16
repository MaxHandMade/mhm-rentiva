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
     * Guards the premise of every assertion below.
     */
    public function test_transfer_shortcode_class_is_absent_from_lite(): void
    {
        $this->assertFalse(class_exists('MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes'));
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
