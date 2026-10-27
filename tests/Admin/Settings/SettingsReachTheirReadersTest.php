<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\BookingUrl;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use MHMRentiva\Admin\Settings\Comments\CommentsSettings;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * A setting an administrator saves must be the setting the plugin reads.
 *
 * Class sweep 2026-10-20, class 2 ("read but never written, or written under
 * another key"), items H1, H2, H4, M1, M2, M3, M5. Every test here does what an
 * administrator does -- posts the real settings form through the option's own
 * sanitizer (or the email-templates screen through its real save handler) --
 * and then asks the code that USES the value, never the option row itself. A
 * value that is stored but read under another name passes a "was it stored"
 * test and fails this one.
 *
 * The stored option is held in memory by a `pre_option_mhmrentiva_settings`
 * filter (removed by WP_UnitTestCase's hook restore), so the sanitizer's own
 * read of the current values and every reader after it see the same array and
 * nothing is written to the shared test database.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 */
final class SettingsReachTheirReadersTest extends WP_UnitTestCase
{
	/** @var array<string, mixed> */
	private array $stored = array();

	/** Standalone options the email-templates save handler writes. */
	private const CANCELLED_OPTIONS = array(
		'mhmrentiva_booking_cancelled_enabled',
		'mhmrentiva_booking_cancelled_subject',
		'mhmrentiva_booking_cancelled_body',
	);

	public function setUp(): void
	{
		parent::setUp();
		$this->stored = array();
		add_filter('pre_option_mhmrentiva_settings', fn () => $this->stored);
		$this->reset_comments_cache();
	}

	public function tearDown(): void
	{
		foreach (self::CANCELLED_OPTIONS as $option) {
			delete_option($option);
		}
		delete_option('mhmrentiva_support_email');
		$this->reset_comments_cache();
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');
		wp_set_current_user(0);
		parent::tearDown();
	}

	/**
	 * Posts one settings tab the way the browser does and keeps the result as
	 * the stored option.
	 *
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private function save_tab(string $tab, array $fields): array
	{
		$this->stored = SettingsSanitizer::sanitize(array( 'current_active_tab' => $tab ) + $fields);
		return $this->stored;
	}

	private function reset_comments_cache(): void
	{
		$cache = new \ReflectionProperty(CommentsSettings::class, 'runtime_cache');
		$cache->setAccessible(true);
		$cache->setValue(null, null);
	}

	/**
	 * A location provider (in production: the add-on) offering one rental
	 * location, id 7. Priority 20 so it wins over any provider already hooked.
	 */
	private function provide_location(): void
	{
		$airport = (object) array(
			'id'   => 7,
			'name' => 'Airport',
		);
		add_filter('mhmrentiva_locations', static fn (): array => array( $airport ), 20);
		add_filter(
			'mhmrentiva_location_by_id',
			static fn ($location, int $id) => 7 === $id ? $airport : null,
			20,
			2
		);
	}

	// ---------------------------------------------------------------- H1

	public function test_h1_the_default_rental_location_saved_on_the_vehicle_tab_reaches_the_cars_pickup_point(): void
	{
		$this->provide_location();
		$this->stored = array( 'mhmrentiva_booking_url' => 'https://example.test/book' );
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');

		$out = $this->save_tab('vehicle', array( 'mhmrentiva_default_rental_location' => '7' ));
		$this->assertSame(7, $out['mhmrentiva_default_rental_location'] ?? null, 'The Vehicle tab dropped the default rental location.');

		// A vehicle with no location of its own, owned by a vendor with none.
		$vendor  = (int) self::factory()->user->create();
		$vehicle = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => $vendor,
			)
		);

		$this->assertSame(7, BookingUrl::location_for($vehicle));
	}

	public function test_h1_a_location_the_provider_does_not_know_is_not_stored(): void
	{
		$this->provide_location();
		$this->stored = array( 'mhmrentiva_default_rental_location' => 7 );

		$out = $this->save_tab('vehicle', array( 'mhmrentiva_default_rental_location' => '99' ));

		$this->assertSame('', $out['mhmrentiva_default_rental_location']);
	}

	public function test_h1_default_none_clears_the_location(): void
	{
		$this->provide_location();
		$this->stored = array( 'mhmrentiva_default_rental_location' => 7 );

		$out = $this->save_tab('vehicle', array( 'mhmrentiva_default_rental_location' => '' ));

		$this->assertSame('', $out['mhmrentiva_default_rental_location']);
	}

	/**
	 * Without a provider the field is not drawn, so the POST has no such key: a
	 * Vehicle-tab save must not wipe what was chosen while the provider was on.
	 */
	public function test_h1_a_save_without_the_field_keeps_the_stored_location(): void
	{
		$this->stored = array( 'mhmrentiva_default_rental_location' => 7 );

		$out = $this->save_tab('vehicle', array( 'mhmrentiva_vehicle_min_rental_days' => '2' ));

		$this->assertSame(7, $out['mhmrentiva_default_rental_location']);
	}

	// ---------------------------------------------------------------- H2

	/**
	 * @param array<string, string> $fields
	 */
	private function post_booking_notifications(array $fields): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

		$keys  = array( 'mhmrentiva_email_templates_nonce', 'current_tab', 'email_templates_action' );
		$keys  = array_merge($keys, array_keys($fields));
		$_POST = array(
			'mhmrentiva_email_templates_nonce' => wp_create_nonce('mhmrentiva_save_email_templates'),
			'current_tab'                      => 'booking_notifications',
			'email_templates_action'           => 'save',
		) + $fields;

		try {
			EmailTemplates::handle_save_templates();
		} finally {
			foreach ($keys as $key) {
				unset($_POST[ $key ]);
			}
		}
	}

	public function test_h2_the_manual_cancellation_email_saves_and_sends_what_was_typed(): void
	{
		$this->post_booking_notifications(
			array(
				// "Enabled" left unticked: an unticked checkbox posts nothing.
				'mhmrentiva_booking_cancelled_subject' => 'Your booking is off',
				'mhmrentiva_booking_cancelled_body'    => '<p>Custom cancellation body</p><script>x</script>',
			)
		);

		$this->assertSame('0', get_option('mhmrentiva_booking_cancelled_enabled'), 'Unticking "Enabled" did not persist.');

		$context = array(
			'site'    => array( 'name' => 'Test Site' ),
			'booking' => array( 'id' => 1 ),
		);
		$this->assertSame('Your booking is off', Templates::compile_subject('booking_cancelled', $context));

		$body = Templates::render_body('booking_cancelled', $context);
		$this->assertStringContainsString('Custom cancellation body', $body);
		$this->assertStringNotContainsString('<script>', (string) get_option('mhmrentiva_booking_cancelled_body'), 'The body must get the same wp_kses_post() as its siblings.');
	}

	public function test_h2_ticking_enabled_persists(): void
	{
		$this->post_booking_notifications(array( 'mhmrentiva_booking_cancelled_enabled' => '1' ));

		$this->assertSame('1', get_option('mhmrentiva_booking_cancelled_enabled'));
	}

	// ---------------------------------------------------------------- H4

	public function test_h4_the_comment_notification_boxes_show_what_was_saved(): void
	{
		$this->save_tab(
			'comments',
			array(
				'mhmrentiva_comments_settings' => array(
					'notifications' => array(
						'admin_new_comment'     => '0',
						'user_comment_approved' => '0',
					),
				),
			)
		);
		$this->reset_comments_cache();

		$this->assertFalse(CommentsSettings::get_setting('notifications.admin_new_comment'));
		$this->assertFalse(CommentsSettings::get_setting('notifications.user_comment_approved'));

		ob_start();
		CommentsSettingsGroup::render_group_notifications();
		$html = (string) ob_get_clean();

		$this->assertDoesNotMatchRegularExpression('/id="mhmrentiva_notifications_admin_new_comment"[^>]*checked/', $html);
		$this->assertDoesNotMatchRegularExpression('/id="mhmrentiva_notifications_user_comment_approved"[^>]*checked/', $html);
	}

	// ---------------------------------------------------------------- M1

	public function test_m1_unified_search_hands_the_vehicle_tabs_minimum_to_the_script(): void
	{
		$this->save_tab('vehicle', array( 'mhmrentiva_vehicle_min_rental_days' => '3' ));

		$previous              = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_scripts'] = new \WP_Scripts();
		try {
			$enqueue = new \ReflectionMethod(UnifiedSearch::class, 'enqueue_assets');
			$enqueue->setAccessible(true);
			$enqueue->invoke(null, array());
			$data = (string) wp_scripts()->get_data('mhm-rentiva-unified-search', 'data');
		} finally {
			$GLOBALS['wp_scripts'] = $previous;
		}

		$this->assertStringContainsString('"minRentalDays":3', $data);
	}

	public function test_m1_search_results_apply_the_vehicle_tabs_minimum(): void
	{
		ShortcodeFixtures::catalog();
		$this->save_tab('vehicle', array( 'mhmrentiva_vehicle_min_rental_days' => '3' ));

		$defaults = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
		$defaults->setAccessible(true);
		$search = new \ReflectionMethod(SearchResults::class, 'perform_search');
		$search->setAccessible(true);

		// The full parameter shape the shortcode builds from the request, with a
		// two-day range on top.
		$from_url = new \ReflectionMethod(SearchResults::class, 'get_search_params_from_url');
		$from_url->setAccessible(true);
		$pickup = gmdate('Y-m-d', strtotime('+30 days'));
		$params = array(
			'pickup_date' => $pickup,
			'return_date' => gmdate('Y-m-d', strtotime($pickup . ' +2 days')),
		) + $from_url->invoke(null);
		$result = $search->invoke(null, $params, $defaults->invoke(null));

		$this->assertSame(0, $result['total'], 'A two-day search passed a three-day minimum.');
	}

	// ---------------------------------------------------------------- M2

	public function test_m2_the_booking_list_falls_back_to_the_stores_currency(): void
	{
		add_filter('woocommerce_currency', static fn (): string => 'EUR');
		$this->save_tab('general', array( 'mhmrentiva_currency' => 'EUR' ));

		$booking = (int) self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_booking',
				'meta_input' => array(
					'_mhmrentiva_payment_status' => 'paid',
					'_mhmrentiva_total_price'    => '150',
				),
			)
		);

		ob_start();
		BookingColumns::render('mhmrentiva_booking_payment', $booking);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('EUR', $html);
		$this->assertStringNotContainsString('USD', $html);
	}

	// ---------------------------------------------------------------- M3

	/**
	 * @return array<int, string>
	 */
	private function support_recipients(): array
	{
		$method = new \ReflectionMethod(ContactForm::class, 'get_email_recipients');
		$method->setAccessible(true);
		return $method->invoke(null, 'support');
	}

	public function test_m3_contact_form_support_mail_goes_to_the_general_tabs_address(): void
	{
		$this->save_tab('general', array( 'mhmrentiva_support_email' => 'help@example.test' ));

		$this->assertSame(array( 'help@example.test' ), $this->support_recipients());
	}

	/**
	 * Before 4.5.3 the field was its own option; the 6.0.0 prefix migration
	 * renamed that row to `mhmrentiva_support_email`. A site that has never saved
	 * the General tab since still has only that row.
	 */
	public function test_m3_an_old_standalone_address_is_used_until_the_general_tab_is_saved(): void
	{
		update_option('mhmrentiva_support_email', 'legacy@example.test');

		$this->assertSame(array( 'legacy@example.test' ), $this->support_recipients());

		$this->save_tab('general', array( 'mhmrentiva_support_email' => 'help@example.test' ));
		$this->assertSame(array( 'help@example.test' ), $this->support_recipients());
	}

	// ---------------------------------------------------------------- M5

	public function test_m5_the_header_image_field_is_drawn_in_the_email_section(): void
	{
		global $wp_settings_fields;
		$saved              = $wp_settings_fields;
		$wp_settings_fields = array();
		try {
			EmailSettings::register();
			$fields = $wp_settings_fields[ SettingsCore::PAGE ][ EmailSettings::SECTION_ID ] ?? array();
		} finally {
			$wp_settings_fields = $saved;
		}

		$this->assertArrayHasKey('mhmrentiva_email_header_image', $fields);
	}

	public function test_m5_the_saved_header_image_reaches_the_email_layout(): void
	{
		$this->save_tab('email', array( 'mhmrentiva_email_header_image' => 'https://example.test/logo.png' ));

		$html = Templates::wrapWithLayout(array( 'site' => array( 'name' => 'Test Site' ) ), 'Subject', '<p>Body</p>');

		$this->assertStringContainsString('src="https://example.test/logo.png"', $html);
	}
}
