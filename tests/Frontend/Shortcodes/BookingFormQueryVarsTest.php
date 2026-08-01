<?php

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use WP_UnitTestCase;

/**
 * WP.org T4 #11 follow-up (Critical 1 + Critical 2 from B-G1j review) --
 * BookingForm's "Book Now" URL params (vehicle_id, start_date, end_date,
 * pickup_time, return_time) and the vehicle-card-base.php forwarder that
 * builds that URL (pickup_location, pickup_date, pickup_time, return_date,
 * return_time) must both read from WordPress's `query_vars` whitelist, not
 * raw $_GET.
 */
class BookingFormQueryVarsTest extends WP_UnitTestCase
{
    private $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();

        BookingForm::register();
        VehiclesList::register();

        if (! post_type_exists('mhmrentiva_vehicle')) {
            register_post_type('mhmrentiva_vehicle', array(
                'public'      => true,
                'has_archive' => true,
                'supports'    => array('title', 'editor', 'thumbnail', 'excerpt'),
                'post_status' => 'publish',
            ));
        }

        $this->vehicle_id = $this->factory->post->create(array(
            'post_type'   => 'mhmrentiva_vehicle',
            'post_title'  => 'Booking Query Var Test Vehicle',
            'post_status' => 'publish',
            'meta_input'  => array(
                '_mhmrentiva_price_per_day' => '100',
                '_mhmrentiva_vehicle_status'        => 'active',
            ),
        ));

        wp_cache_delete($this->vehicle_id, 'post_meta');
    }

    /**
     * The `query_vars` filter callback must RETURN the modified array and
     * include every BookingForm-specific public param.
     */
    public function test_register_query_vars_returns_modified_array_with_bookingform_params(): void
    {
        $result = BookingForm::register_query_vars(array('some_other_plugins_var'));

        $this->assertIsArray($result);
        $this->assertContains('some_other_plugins_var', $result);

        foreach (array('vehicle_id', 'start_date', 'end_date', 'pickup_time', 'return_time') as $var) {
            $this->assertContains($var, $result, "query_vars must include '{$var}'.");
        }
    }

    /**
     * Live whitelist check: BookingForm's own params AND the params it
     * reuses from SearchResults (pickup_date, return_date, pickup_location)
     * must all be present on the actual live `query_vars` filter chain.
     */
    public function test_query_vars_filter_is_wired_up_on_the_live_whitelist(): void
    {
        $vars = apply_filters('query_vars', array());

        foreach (array('vehicle_id', 'start_date', 'end_date', 'pickup_time', 'return_time') as $var) {
            $this->assertContains($var, $vars, "'{$var}' should be registered by BookingForm.");
        }

        // Reused from SearchResults::PUBLIC_QUERY_VARS -- not re-registered here.
        foreach (array('pickup_date', 'return_date', 'pickup_location') as $var) {
            $this->assertContains($var, $vars, "'{$var}' should already be registered by SearchResults.");
        }
    }

    /**
     * A registered BookingForm param, present on the request URL, must be
     * readable via get_query_var().
     */
    public function test_registered_params_are_readable_via_get_query_var_after_request(): void
    {
        $this->go_to('/?vehicle_id=42&start_date=2026-08-01&end_date=2026-08-05&pickup_time=10:00&return_time=18:00');

        $this->assertSame('42', get_query_var('vehicle_id'));
        $this->assertSame('2026-08-01', get_query_var('start_date'));
        $this->assertSame('2026-08-05', get_query_var('end_date'));
        $this->assertSame('10:00', get_query_var('pickup_time'));
        $this->assertSame('18:00', get_query_var('return_time'));
    }

    /**
     * Negative proof: the read path is the whitelist, NOT raw $_GET. Set
     * $_GET directly (bypassing go_to()/WP's request parsing), so query_vars
     * is never populated for this key. If BookingForm::get_text() still read
     * raw $_GET, the value would leak through here.
     */
    public function test_get_text_does_not_fall_back_to_raw_get_for_a_registered_param(): void
    {
        $_GET['vehicle_id'] = 'LeakedRawGetValue';

        $method = new \ReflectionMethod(BookingForm::class, 'get_text');
        $method->setAccessible(true);
        $value = $method->invoke(null, 'vehicle_id');

        unset($_GET['vehicle_id']);

        $this->assertSame(
            '',
            $value,
            'get_text() must read from the query_vars whitelist, not fall back to raw $_GET.'
        );
    }

    /**
     * prepare_template_data() must reflect the registered query vars end to
     * end, across both BookingForm's own params and the ones it shares with
     * SearchResults (pickup_date/return_date/pickup_location).
     */
    public function test_prepare_template_data_reflects_registered_query_vars(): void
    {
        $this->go_to('/?vehicle_id=' . $this->vehicle_id . '&pickup_date=2026-09-01&return_date=2026-09-04&pickup_time=09:00&return_time=17:00&pickup_location=3');

        $data = BookingForm::get_data(array());

        // start_date/end_date are re-formatted for display (WP `date_format` option)
        // once past the query_vars read -- mirror that step here rather than
        // asserting the raw ISO string, since the formatting itself is unrelated
        // to this task (behavior-preserving $_GET -> query_vars swap only).
        $wp_date_format = get_option('date_format', 'd/m/Y');
        $expected_start = (new \DateTime('2026-09-01'))->format($wp_date_format);
        $expected_end   = (new \DateTime('2026-09-04'))->format($wp_date_format);

        $this->assertSame((string) $this->vehicle_id, (string) $data['atts']['vehicle_id']);
        $this->assertSame($expected_start, $data['atts']['start_date']);
        $this->assertSame($expected_end, $data['atts']['end_date']);
        $this->assertSame('09:00', $data['atts']['pickup_time']);
        $this->assertSame('17:00', $data['atts']['return_time']);
        $this->assertSame(3, $data['atts']['pickup_location_id']);
    }

    /**
     * Critical 2 (template forwarding): vehicle-card-base.php reads
     * pickup_location/pickup_date/pickup_time/return_date/return_time via
     * get_query_var() and forwards them into the rendered "Book Now" link.
     * Proves link-building (template) and link-reading (BookingForm) still
     * agree after the query_vars conversion.
     */
    public function test_vehicle_card_book_now_link_forwards_registered_query_vars(): void
    {
        $this->go_to('/?pickup_location=7&pickup_date=2026-08-10&pickup_time=11:30&return_date=2026-08-15&return_time=19:45');

        $output = do_shortcode('[rentiva_vehicles_list limit="1"]');

        $this->assertStringContainsString('data-testid="vehicle-book-btn"', $output);

        $this->assertMatchesRegularExpression('/href="[^"]*vehicle_id=' . preg_quote((string) $this->vehicle_id, '/') . '[^"]*"/', $output);
        $this->assertStringContainsString('pickup_location=7', $output);
        $this->assertStringContainsString('pickup_date=2026-08-10', $output);
        $this->assertStringContainsString('pickup_time=11', $output);
        $this->assertStringContainsString('return_date=2026-08-15', $output);
        $this->assertStringContainsString('return_time=19', $output);
    }

    /**
     * When none of the forwarding params are present on the request, the
     * Book Now link must fall back to just `vehicle_id` (plus the vehicle's
     * own location, if any) -- i.e. the negative case: nothing leaks in from
     * a stale raw $_GET left over by another code path.
     */
    public function test_vehicle_card_book_now_link_omits_unset_forward_params(): void
    {
        $this->go_to('/');

        $output = do_shortcode('[rentiva_vehicles_list limit="1"]');

        $this->assertStringNotContainsString('pickup_time=', $output);
        $this->assertStringNotContainsString('return_time=', $output);
        $this->assertStringNotContainsString('pickup_date=', $output);
        $this->assertStringNotContainsString('return_date=', $output);
    }
}
