<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Services\FavoritesService;

/**
 * Minimal content the shortcodes need before they render their real markup.
 */
final class ShortcodeFixtures
{
	/** Sequence for vehicle() titles. */
	private static int $vehicle_seq = 0;

	/**
	 * A published vehicle the comparison shortcode counts as comparable:
	 * VehicleComparison::get_vehicle_data() skips anything that is not a
	 * published mhmrentiva_vehicle with status 'active'.
	 */
	public static function vehicle(): int
	{
		$id = wp_insert_post(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				// A counter, not a random suffix: titles sort, and a list sorted by title
				// must pick the same vehicle on every run.
				'post_title'  => sprintf('Fixture Vehicle %03d', ++self::$vehicle_seq),
			)
		);

		update_post_meta($id, MetaKeys::VEHICLE_STATUS, 'active');
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');

		return (int) $id;
	}

	/**
	 * A logged-in-capable customer account (WooCommerce's role when it is loaded).
	 */
	public static function customer(): int
	{
		$role = null !== get_role('customer') ? 'customer' : 'subscriber';

		return (int) wp_insert_user(
			array(
				'user_login' => 'fixture_customer_' . wp_generate_password(6, false, false),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'fixture-' . wp_generate_password(6, false, false) . '@example.test',
				'role'       => $role,
			)
		);
	}

	/**
	 * A paid booking of $vehicle_id owned by $user_id.
	 *
	 * The account surfaces find bookings by the customer user id meta
	 * (AccountRenderer::get_user_bookings()); payment history reads the payment
	 * metas of those same bookings, so one row feeds both.
	 */
	public static function booking(int $user_id, int $vehicle_id, string $status = 'confirmed', string $total = '200', int $offset_days = 7): int
	{
		$id = (int) wp_insert_post(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_title'  => 'Fixture Booking',
			)
		);

		$pickup = gmdate('Y-m-d', time() + $offset_days * DAY_IN_SECONDS);
		$return = gmdate('Y-m-d', time() + ( $offset_days + 2 ) * DAY_IN_SECONDS);

		$metas = array(
			MetaKeys::BOOKING_CUSTOMER_USER_ID => $user_id,
			MetaKeys::BOOKING_VEHICLE_ID       => $vehicle_id,
			MetaKeys::BOOKING_STATUS           => $status,
			MetaKeys::BOOKING_PICKUP_DATE      => $pickup,
			MetaKeys::BOOKING_RETURN_DATE      => $return,
			MetaKeys::BOOKING_DROPOFF_DATE     => $return,
			MetaKeys::BOOKING_PICKUP_TIME      => '10:00',
			MetaKeys::BOOKING_RETURN_TIME      => '10:00',
			MetaKeys::BOOKING_START_TS         => strtotime($pickup . ' 10:00:00 UTC'),
			MetaKeys::BOOKING_END_TS           => strtotime($return . ' 10:00:00 UTC'),
			MetaKeys::BOOKING_TOTAL_PRICE      => $total,
			MetaKeys::BOOKING_PAYMENT_STATUS   => 'paid',
			MetaKeys::BOOKING_PAYMENT_METHOD   => 'offline',
			MetaKeys::BOOKING_PAYMENT_GATEWAY  => 'offline',
			MetaKeys::BOOKING_PAYMENT_TYPE     => 'full',
		);
		foreach ($metas as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	/**
	 * Everything one Lite shortcode tag needs to render its real markup.
	 *
	 * `user` is the account to log in as (null = guest), `atts` the shortcode
	 * attributes, `marker` a string the real markup always carries and the empty
	 * or error paths never do, `min_elements` a floor on the element count so an
	 * empty render can not pass a "nothing was lost" comparison by having nothing.
	 *
	 * @return array{user:int|null, atts:array<string,mixed>, marker:string, min_elements:int}
	 */
	public static function for_tag(string $tag): array
	{
		$row = array(
			'user'         => null,
			'atts'         => array(),
			'marker'       => '',
			'min_elements' => 1,
		);

		switch ($tag) {
			case 'rentiva_booking_form':
				$row['atts']         = array( 'vehicle_id' => (string) self::vehicle() );
				$row['marker']       = 'rv-booking-form-content';
				$row['min_elements'] = 80;
				break;

			case 'rentiva_availability_calendar':
				$row['atts']         = array( 'vehicle_id' => (string) self::vehicle() );
				$row['marker']       = 'rv-availability-grid';
				$row['min_elements'] = 100;
				break;

			case 'rentiva_vehicle_details':
				$row['atts']         = array( 'vehicle_id' => (string) self::vehicle() );
				$row['marker']       = 'rv-vd2-gallery-card';
				$row['min_elements'] = 80;
				break;

			case 'rentiva_vehicle_rating_form':
				$row['atts']         = array( 'vehicle_id' => (string) self::vehicle() );
				$row['marker']       = 'rv-rating-summary';
				$row['min_elements'] = 30;
				break;

			case 'rentiva_vehicles_list':
			case 'rentiva_vehicles_grid':
				// One vehicle card (~47 elements); the empty state names no vehicle.
				$row['marker']       = get_the_title(self::vehicle());
				$row['min_elements'] = 30;
				break;

			case 'rentiva_search_results':
				$row['marker']       = get_the_title(self::vehicle());
				$row['min_elements'] = 80;
				break;

			case 'rentiva_featured_vehicles':
				$vehicle = self::vehicle();
				update_post_meta($vehicle, MetaKeys::VEHICLE_FEATURED, '1');
				$row['marker']       = get_the_title($vehicle);
				$row['min_elements'] = 20;
				break;

			case 'rentiva_vehicle_comparison':
				$row['atts']         = array( 'vehicle_ids' => self::vehicle() . ',' . self::vehicle() );
				$row['marker']       = 'rv-comparison-table';
				$row['min_elements'] = 40;
				break;

			case 'rentiva_my_bookings':
				$row['user'] = self::customer();
				$vehicle     = self::vehicle();
				self::booking($row['user'], $vehicle);
				// The booking row names its vehicle; the empty state can not.
				$row['marker']       = get_the_title($vehicle);
				$row['min_elements'] = 25;
				break;

			case 'rentiva_payment_history':
				$row['user'] = self::customer();
				self::booking($row['user'], self::vehicle());
				// One row per paid booking; the empty state renders .empty-state instead.
				$row['marker']       = 'data-testid="payment-item"';
				$row['min_elements'] = 15;
				break;

			case 'rentiva_my_favorites':
				$row['user'] = self::customer();
				$vehicle     = self::vehicle();
				FavoritesService::add($row['user'], $vehicle);
				$row['marker']       = get_the_title($vehicle);
				$row['min_elements'] = 20;
				break;

			case 'rentiva_user_dashboard':
				// A guest gets '' from the retired stub; the logged-in notice is the markup.
				$row['user']         = self::customer();
				$row['marker']       = 'mhm-rentiva-retired-dashboard';
				$row['min_elements'] = 2;
				break;

			case 'rentiva_unified_search':
				$row['marker']       = 'rv-unified-search__form';
				$row['min_elements'] = 70;
				break;

			case 'rentiva_contact':
				$row['marker']       = 'rv-contact-form-container';
				$row['min_elements'] = 50;
				break;

			case 'rentiva_testimonials':
				// An approved, rated review on a published vehicle (source 2 of
				// Testimonials::get_testimonials()); without one only the empty state renders.
				$comment = (int) wp_insert_comment(
					array(
						'comment_post_ID'  => self::vehicle(),
						'comment_author'   => 'Fixture Reviewer',
						'comment_content'  => 'Fixture review text.',
						'comment_approved' => 1,
					)
				);
				update_comment_meta($comment, 'mhmrentiva_rating', 5);
				$row['marker']       = 'rv-testimonial-card';
				$row['min_elements'] = 20;
				break;

			default:
				// An unknown tag would return an empty marker that every render "contains".
				throw new \InvalidArgumentException( 'ShortcodeFixtures::for_tag(): unknown tag ' . $tag );
		}

		return $row;
	}

	/**
	 * Three catalog vehicles, built once per test (the ids are checked, because
	 * WP_UnitTestCase rolls the rows back after the test that built them).
	 *
	 * @var list<int>
	 */
	private static array $catalog = array();

	/**
	 * A catalog like the dev site's: three published, active, featured vehicles
	 * that differ in every field a card or a list can show or sort by -- title,
	 * price, brand, category, the four default card details, rating, thumbnail,
	 * excerpt, publish date. With one bare vehicle a sort, a limit or a "show
	 * brand" toggle has nothing to change, and the settings gate would call a live
	 * setting dead (spec 2026-10-10-elementor-kopru-dilim-3a §3 G1, R4).
	 *
	 * @return list<int> Vehicle ids, oldest first.
	 */
	public static function catalog(): array
	{
		if (3 === count(self::$catalog) && 'mhmrentiva_vehicle' === get_post_type(self::$catalog[0])) {
			return self::$catalog;
		}

		// Title, price, rating, rating count and publish date each sort the three in
		// a different order, so every sort key moves the cards; Bravo sits under
		// a minimum rating of 3 and a minimum review count of 4.
		$specs = array(
			array( 'Zz Alpha Sedan', '250', 'Volkswagen', 'Sedan', 'petrol', 'manual', '4', '45250', '4.5', '12', 1 ),
			array( 'Zz Bravo Coupe', '400', 'Audi', 'Coupe', 'diesel', 'auto', '2', '12000', '2', '3', 3 ),
			array( 'Zz Charlie Van', '100', 'Ford', 'Van', 'electric', 'auto', '9', '800', '5', '30', 2 ),
		);

		self::$catalog = array();
		foreach ($specs as $spec) {
			list( $title, $price, $brand, $category, $fuel, $transmission, $seats, $mileage, $rating, $count, $days_ago ) = $spec;

			$id = (int) wp_insert_post(
				array(
					'post_type'    => 'mhmrentiva_vehicle',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_excerpt' => $title . ' excerpt for the catalog fixture.',
					'post_content' => $title . ' description for the catalog fixture.',
					'post_date'    => gmdate('Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS),
				)
			);

			$metas = array(
				MetaKeys::VEHICLE_STATUS         => 'active',
				MetaKeys::VEHICLE_PRICE_PER_DAY  => $price,
				MetaKeys::VEHICLE_BRAND          => $brand,
				MetaKeys::VEHICLE_MODEL          => $category . ' Model',
				MetaKeys::VEHICLE_YEAR           => '2022',
				MetaKeys::VEHICLE_SEATS          => $seats,
				MetaKeys::VEHICLE_TRANSMISSION   => $transmission,
				MetaKeys::VEHICLE_FUEL_TYPE      => $fuel,
				MetaKeys::VEHICLE_MILEAGE        => $mileage,
				MetaKeys::VEHICLE_RATING_AVERAGE => $rating,
				MetaKeys::VEHICLE_RATING_COUNT   => $count,
				MetaKeys::VEHICLE_FEATURED       => '1',
				'_mhmrentiva_doors'              => '4',
				'_mhmrentiva_engine_size'        => '1.6',
				'_mhmrentiva_deposit'            => '10',
			);
			foreach ($metas as $key => $value) {
				update_post_meta($id, $key, $value);
			}
			update_post_meta($id, MetaKeys::VEHICLE_FEATURES_LIST, array( 'air_conditioning', 'abs_brakes', 'bluetooth' ));
			update_post_meta($id, '_mhmrentiva_equipment', array( 'spare_tire', 'first_aid_kit' ));

			$term = term_exists($category, 'mhmrentiva_vehicle_category');
			if (! $term) {
				$term = wp_insert_term($category, 'mhmrentiva_vehicle_category');
			}
			if (is_array($term)) {
				wp_set_object_terms($id, array( (int) $term['term_id'] ), 'mhmrentiva_vehicle_category');
			}

			set_post_thumbnail($id, self::image(sanitize_title($title)));

			self::$catalog[] = $id;
		}

		// One published additional service, as every dev site has.
		$addon = (int) wp_insert_post(
			array(
				'post_type'    => 'mhmrentiva_addon',
				'post_status'  => 'publish',
				'post_title'   => 'Zz Child Seat',
				'post_excerpt' => 'Fixture additional service.',
			)
		);
		update_post_meta($addon, 'mhmrentiva_addon_price', '15');

		return self::$catalog;
	}

	/**
	 * An image attachment with the four core sizes in its metadata, so each
	 * image-size setting resolves to its own URL. No file is written: URLs are
	 * built from the metadata alone.
	 */
	public static function image(string $name): int
	{
		$file = '2026/10/' . $name . '.jpg';
		$id   = (int) wp_insert_attachment(
			array(
				'post_title'     => $name,
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			$file
		);

		$sizes = array();
		foreach (array(
			'thumbnail'    => array( 150, 150 ),
			'medium'       => array( 300, 200 ),
			'medium_large' => array( 768, 512 ),
			'large'        => array( 1024, 683 ),
		) as $size => $dims) {
			$sizes[ $size ] = array(
				'file'      => $name . '-' . $dims[0] . 'x' . $dims[1] . '.jpg',
				'width'     => $dims[0],
				'height'    => $dims[1],
				'mime-type' => 'image/jpeg',
			);
		}
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 1200,
				'height' => 800,
				'file'   => $file,
				'sizes'  => $sizes,
			)
		);

		return $id;
	}

	/**
	 * What the settings gate (SettingProbe) renders a tag on: the catalog plus
	 * whatever the tag's own markup needs, or -- for a data-dependent setting --
	 * a named fixture that adds the data that setting acts on
	 * (tests/Integration/setting-effect-data-dependent.php names one per row).
	 *
	 * @return array{user:int|null, atts:array<string,mixed>, pairs?:array<string,array{0:mixed,1:mixed}>}
	 */
	public static function setting_probe(string $tag, ?string $fixture = null): array
	{
		$catalog = self::catalog();
		$row     = array(
			'user' => null,
			'atts' => array(),
		);

		switch ($tag) {
			case 'rentiva_booking_form':
			case 'rentiva_availability_calendar':
			case 'rentiva_vehicle_details':
			case 'rentiva_vehicle_rating_form':
				$row['atts'] = array( 'vehicle_id' => (string) $catalog[0] );
				break;

			case 'rentiva_vehicle_comparison':
				$row['atts'] = array( 'vehicle_ids' => $catalog[0] . ',' . $catalog[1] );
				break;

			case 'rentiva_my_bookings':
			case 'rentiva_payment_history':
				$row['user'] = self::customer();
				// Created on three different days, out of step with price and status.
				foreach (array( array( 'confirmed', '700', 2 ), array( 'pending', '1200', 9 ), array( 'completed', '300', 5 ) ) as $i => $booking) {
					$id   = self::booking($row['user'], $catalog[ $i ], $booking[0], $booking[1], 7 + 10 * $i);
					$date = gmdate('Y-m-d H:i:s', time() - $booking[2] * DAY_IN_SECONDS);
					wp_update_post(
						array(
							'ID'            => $id,
							'post_date'     => $date,
							'post_date_gmt' => $date,
						)
					);
				}
				break;

			case 'rentiva_my_favorites':
				$row['user'] = self::customer();
				foreach ($catalog as $vehicle) {
					FavoritesService::add($row['user'], $vehicle);
				}
				break;

			case 'rentiva_testimonials':
				// Three rated reviews (5, 4, 2) on three vehicles of their own, a day
				// apart: a review recalculates its vehicle's rating, and the catalog's
				// ratings must stay as the rating filters of the lists expect them.
				$reviewed = array();
				foreach (array( 5, 4, 2 ) as $i => $rating) {
					$reviewed[] = self::vehicle();
					$comment    = (int) wp_insert_comment(
						array(
							'comment_post_ID'  => $reviewed[ $i ],
							'comment_author'   => 'Fixture Reviewer ' . chr(65 + $i),
							'comment_content'  => 'Fixture review text ' . chr(65 + $i) . '.',
							'comment_approved' => 1,
							'comment_date'     => gmdate('Y-m-d H:i:s', time() - ( $i + 1 ) * DAY_IN_SECONDS),
						)
					);
					update_comment_meta($comment, 'mhmrentiva_rating', $rating);
				}
				// The vehicle filter has to name vehicles that have reviews.
				$row['pairs'] = array( 'vehicle_id' => array( $reviewed[0], $reviewed[2] ) );
				break;

			case 'rentiva_user_dashboard':
				$row['user'] = self::customer();
				break;

			case 'rentiva_vehicles_list':
				// The vehicle card widget renders this tag for one chosen vehicle; the
				// list widget and the block have no vehicle key and ignore it.
				$row['atts'] = array( 'vehicle_id' => (string) $catalog[0] );
				break;

			case 'rentiva_vehicles_grid':
			case 'rentiva_search_results':
			case 'rentiva_featured_vehicles':
			case 'rentiva_unified_search':
				break;

			case 'rentiva_contact':
				// email_to: the address only ever reaches the page as an encrypted payload
				// with a random nonce, so two addresses differ on every render (noisy); the
				// observable effect is the payload field appearing at all (spec 3A §2.5 b).
				// redirect_url: only a same-site URL survives wp_validate_redirect().
				$row['pairs'] = array(
					'email_to'     => array( '', 'zza@example.com' ),
					'redirect_url' => array( '', home_url('/zz-thanks/') ),
				);
				break;

			default:
				throw new \InvalidArgumentException( 'ShortcodeFixtures::setting_probe(): unknown tag ' . $tag );
		}

		if (null !== $fixture) {
			$row = self::with_named_fixture($row, $fixture, $tag);
		}

		return $row;
	}

	/**
	 * The named fixtures of setting-effect-data-dependent.php. Each adds exactly
	 * the data (or the companion setting) the row's setting acts on.
	 *
	 * `filters` are added by SettingProbe around each render and removed after,
	 * so a fixture never leaks into another surface's renders. `unavailable`
	 * names a precondition this environment can not meet; SettingProbe then
	 * reports the row instead of rendering it.
	 *
	 * @param array{user:int|null, atts:array<string,mixed>} $row Base fixture.
	 * @return array{user:int|null, atts:array<string,mixed>, pairs?:array<string,list<mixed>>, filters?:list<array{0:string,1:callable,2?:int,3?:int}>, unavailable?:string}
	 */
	private static function with_named_fixture(array $row, string $fixture, string $tag): array
	{
		$catalog = self::catalog();

		switch ($fixture) {
			case 'catalog':
				// The base fixture already carries the data (the catalog's vehicles, two
				// of them compared); named so the row says what it was measured on.
				return $row;

			case 'no_vehicle_preselected':
				// The selector only shows when the form is not bound to a vehicle.
				unset($row['atts']['vehicle_id']);
				return $row;

			case 'category_slugs':
				// The catalog's category terms (Sedan, Coupe, Van) by slug.
				$row['pairs'] = array( 'category' => array( 'sedan', 'van', 'coupe' ) );
				return $row;

			case 'card_feature_items':
				// Card items of the "feature" type (not the default detail fields):
				// show_features toggles exactly those.
				$row['filters'] = array(
					array(
						'option_' . \MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME,
						static function ($settings) {
							$settings = is_array($settings) ? $settings : array();
							$settings['mhmrentiva_vehicle_card_fields'] = array(
								array(
									'type' => 'detail',
									'key'  => 'fuel_type',
								),
								array(
									'type' => 'feature',
									'key'  => 'air_conditioning',
								),
								array(
									'type' => 'feature',
									'key'  => 'bluetooth',
								),
							);
							return $settings;
						},
					),
				);
				return $row;

			case 'without_woocommerce':
				// AccountRenderer::get_navigation() returns [] whenever WooCommerce is
				// active, so there is no navigation for hide_nav to hide.
				if (class_exists('WooCommerce')) {
					$row['unavailable'] = 'WooCommerce is loaded (AccountRenderer::get_navigation() returns [] when it is)';
				}
				return $row;

			case 'one_result_per_page':
				// Pagination renders only when there is more than one page.
				$row['atts']['results_per_page'] = '1';
				return $row;

			case 'search_locations':
				// Rental locations come from an add-on through the filter; without one
				// the pickers have nothing to list and are not rendered.
				$row['filters'] = array(
					array(
						'mhmrentiva_search_locations',
						static function () {
							return array(
								(object) array(
									'id'   => 9101,
									'name' => 'Zz Airport',
								),
								(object) array(
									'id'   => 9102,
									'name' => 'Zz Harbour',
								),
							);
						},
					),
				);
				return $row;

			case 'vehicle_id_lists':
				// A comparison needs two vehicles; one id renders the same empty table.
				$row['pairs'] = array(
					'vehicle_ids' => array(
						$catalog[0] . ',' . $catalog[1],
						$catalog[0] . ',' . $catalog[2],
					),
				);
				return $row;

			case 'one_vehicle_not_featured':
				// The catalog is all featured; "featured only" needs one that is not.
				// The vehicle card filters only when no vehicle is chosen.
				$vehicle = self::vehicle();
				update_post_meta($vehicle, MetaKeys::VEHICLE_FEATURED, '0');
				unset($row['atts']['vehicle_id']);
				return $row;

			case 'card_without_vehicle':
				// The vehicle card widget maps its chosen vehicle to `ids` with limit 1
				// (VehicleCardWidget::prepare_shortcode_attributes()), so the list's
				// filters and sort pick the card's vehicle only when none is chosen.
				// The card shows the first vehicle by the list's sort (orderby title,
				// vehicles-list/block.json). Every other fixture vehicle of the process
				// would compete for that first place, so the candidates are pinned to
				// the catalog (`ids`) and the sort to title: Alpha (4.5 from 12 reviews)
				// comes first, Bravo (2 from 3) second, Charlie (5 from 30) last. A
				// minimum of 1 keeps Alpha, 5 (rating) or 20 (reviews) leaves Charlie;
				// asc / desc puts Alpha / Charlie first.
				unset($row['atts']['vehicle_id']);
				$row['atts']['ids']     = implode(',', $catalog);
				$row['atts']['orderby'] = 'title';
				$row['pairs']           = array(
					'min_rating'  => array( '1', '5' ),
					'min_reviews' => array( '1', '20' ),
				);
				return $row;

			case 'view_all_url':
				// The link text renders only with a link.
				$row['atts']['view_all_url'] = 'https://example.test/all-vehicles';
				return $row;

			case 'favorites_sorted_by_title':
				// MyFavorites applies `order` only when `orderby` is not 'date'
				// (MyFavorites.php:80), and the widget's default is 'date'.
				$row['atts']['orderby'] = 'title';
				return $row;

			default:
				throw new \InvalidArgumentException( 'ShortcodeFixtures::setting_probe(): unknown fixture ' . $fixture . ' for ' . $tag );
		}
	}
}
