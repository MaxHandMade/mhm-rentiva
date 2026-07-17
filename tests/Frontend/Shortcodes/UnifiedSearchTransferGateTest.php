<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use MHMRentiva\Admin\Licensing\Mode;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The public front page must not offer Transfer to an unlicensed visitor.
 *
 * THE BUG THIS PINS
 * -----------------
 * UnifiedSearch gated its Transfer tab and its location dropdown on
 * class_exists() alone. That asks whether the class SHIPPED, not whether the
 * customer PAID -- and on a Pro-installed-but-unlicensed site it shipped. Found
 * by loading the front page at isPro=false, where an anonymous visitor got:
 *
 *   - a rendered "Transfer" tab with a full origin/destination/date form, posting
 *     to rentiva_transfer_results -- a shortcode the licence had just closed to a
 *     silent no-op, so the search always landed on a blank page;
 *   - pickup/dropoff selects populated with REAL rows from the Pro locations table
 *     (Istanbul Havalimani, Sabiha Gokcen, Antalya, Taksim, ...), even though the
 *     owner's rule is that Lite has NO location search at all;
 *   - the Pro transfer CSS/JS and its localized transfer_vars.
 *
 * WHY THE STAND-IN CLASSES
 * ------------------------
 * This suite runs against a Lite tree where the Transfer classes are genuinely
 * absent, so asserting "no transfer tab" passes with the fix REVERTED --
 * class_exists() is already false and the assertion proves nothing. class_alias()
 * makes real classes answer to the Transfer FQNs, reproducing "class present,
 * licence absent" -- the only shape that fails when the Mode gate is removed.
 *
 * THE STAND-IN IS PROCESS-WIDE, AND THAT IS ACCEPTED
 * --------------------------------------------------
 * class_alias() cannot be undone, so from here on class_exists(TransferShortcodes)
 * is true for the rest of the PHPUnit process. PHPUnit's separate-process
 * annotation was tried and did not contain it (the alias is created in
 * set_up_before_class), while costing ~17s a run, so it was dropped. Do NOT name
 * that annotation literally in this docblock: PHPUnit reads annotations anywhere in
 * a docblock, prose included, so merely mentioning it switches isolation back on --
 * which then fails the run outright with "Serialization of 'Closure' is not
 * allowed".
 *
 * That is safe because nothing may depend on the class being ABSENT at runtime:
 * Lite and Pro share the MHMRentiva\ namespace, so on any site with the Pro add-on
 * installed the class legitimately exists. One test did assert that
 * (UnifiedSearchTransferSeamTest) and was asserting "Pro is never installed" --
 * untrue in the field, and the very blind spot that let this defect ship. It now
 * checks the packaging fact it actually meant. If a future test needs "Transfer is
 * not shipped", assert on the FILE, not on class_exists().
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch::prepare_template_data
 */
final class UnifiedSearchTransferGateTest extends WP_UnitTestCase
{

    private const TRANSFER_SHORTCODES = '\MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes';
    private const LOCATION_PROVIDER   = '\MHMRentiva\Admin\Transfer\Engine\LocationProvider';

    public static function set_up_before_class(): void
    {
        parent::set_up_before_class();

        if (! class_exists(self::TRANSFER_SHORTCODES)) {
            class_alias(TransferShortcodesStandIn::class, 'MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes');
        }
        if (! class_exists(self::LOCATION_PROVIDER)) {
            class_alias(LocationProviderStandIn::class, 'MHMRentiva\Admin\Transfer\Engine\LocationProvider');
        }
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function template_data(array $atts = array()): array
    {
        $method = new ReflectionMethod(UnifiedSearch::class, 'prepare_template_data');
        $method->setAccessible(true);

        $defaults = array(
            'default_tab'           => 'rental',
            'service_type'          => 'both',
            'show_rental_tab'       => '1',
            'show_transfer_tab'     => '1',
            'show_location_select'  => '1',
            'show_time_select'      => '1',
            'show_date_picker'      => '1',
            'show_dropoff_location' => '1',
            'location_required'     => '1',
            'fields_required'       => '1',
            'show_pax'              => '1',
            'show_luggage'          => '1',
            'filter_categories'     => '',
            'redirect_page'         => '',
            'layout'                => 'horizontal',
            'search_layout'         => '',
            'style'                 => 'glass',
        );

        return (array) $method->invoke(null, array_merge($defaults, $atts));
    }

    /**
     * Guards the KEY NAMES, not just the values.
     *
     * Written after this suite's first run asserted on 'show_transfer' -- a key that
     * does not exist. The lookup yielded null, null is falsy, and "the tab is
     * hidden" passed while testing absolutely nothing. Only the rental positive
     * control caught it. The real keys are *_tab.
     */
    public function test_premise_the_visibility_keys_exist(): void
    {
        $data = $this->template_data();

        foreach (array( 'show_rental_tab', 'show_transfer_tab', 'show_location_select', 'locations' ) as $key) {
            $this->assertArrayHasKey($key, $data, sprintf('Key "%s" is gone -- assertions on it would pass vacuously.', $key));
        }
    }

    /**
     * Guards the premise. Without both of these every assertion below is vacuous.
     */
    public function test_premise_transfer_classes_are_present_and_the_tree_is_unlicensed(): void
    {
        $this->assertTrue(class_exists(self::TRANSFER_SHORTCODES), 'Stand-in failed for TransferShortcodes.');
        $this->assertTrue(class_exists(self::LOCATION_PROVIDER), 'Stand-in failed for LocationProvider.');
        $this->assertFalse(Mode::isPro(), 'Premise failed: this tree reports a Pro licence.');
    }

    /**
     * Mutation proof: drop `&& Mode::isPro()` from the TransferShortcodes gate in
     * prepare_template_data() and this fails -- the stand-in class exists, so the
     * tab would render.
     */
    public function test_transfer_tab_is_hidden_without_a_licence(): void
    {
        $data = $this->template_data();

        $this->assertFalse(
            (bool) $data['show_transfer_tab'],
            'The Transfer tab rendered on a public page with isPro=false.'
        );
    }

    /**
     * The master switch force-enables the transfer tab for service_type="transfer",
     * so the licence gate must still be the last word -- this is the attack shape
     * the comment in the source calls out.
     */
    public function test_transfer_tab_stays_hidden_even_when_forced_by_service_type(): void
    {
        $data = $this->template_data(array( 'service_type' => 'transfer', 'default_tab' => 'transfer' ));

        $this->assertFalse((bool) $data['show_transfer_tab'], 'service_type="transfer" overrode the licence gate.');
        $this->assertTrue((bool) $data['show_rental_tab'], 'Hiding transfer must leave the rental tab usable.');
    }

    /**
     * Mutation proof: drop `&& Mode::isPro()` from the LocationProvider gate and
     * this fails -- the stand-in returns rows, which is exactly what leaked.
     */
    public function test_no_locations_are_offered_without_a_licence(): void
    {
        $data = $this->template_data();

        $this->assertSame(
            array(),
            (array) $data['locations'],
            'Location rows were served to an unlicensed visitor. Lite has no location search.'
        );
    }

    /**
     * Positive control: the core rental side must survive the gate. A fix that hid
     * everything would satisfy the assertions above while breaking the plugin.
     */
    public function test_the_rental_tab_still_renders_without_a_licence(): void
    {
        $data = $this->template_data();

        $this->assertTrue((bool) $data['show_rental_tab'], 'The core rental tab must always render.');
    }
}

/**
 * Stands in for the carved-out Pro class so class_exists() answers true.
 * Only PRESENCE is under test.
 */
final class TransferShortcodesStandIn
{
    public static function enqueue_assets(): void
    {
    }
}

/**
 * Returns rows on purpose: a gate that leaks must be caught leaking real data,
 * not an empty array that would pass either way.
 */
final class LocationProviderStandIn
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_locations(string $service_type = 'rental'): array
    {
        return array(
            array( 'id' => 3, 'name' => 'Istanbul Havalimani' ),
            array( 'id' => 7, 'name' => 'Taksim Meydan' ),
        );
    }
}
