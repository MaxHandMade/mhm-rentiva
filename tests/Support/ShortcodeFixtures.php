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
				'post_title'  => 'Fixture Vehicle ' . wp_generate_password(4, false),
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
	public static function booking(int $user_id, int $vehicle_id): int
	{
		$id = (int) wp_insert_post(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_title'  => 'Fixture Booking',
			)
		);

		$pickup = gmdate('Y-m-d', time() + 7 * DAY_IN_SECONDS);
		$return = gmdate('Y-m-d', time() + 9 * DAY_IN_SECONDS);

		$metas = array(
			MetaKeys::BOOKING_CUSTOMER_USER_ID => $user_id,
			MetaKeys::BOOKING_VEHICLE_ID       => $vehicle_id,
			MetaKeys::BOOKING_STATUS           => 'confirmed',
			MetaKeys::BOOKING_PICKUP_DATE      => $pickup,
			MetaKeys::BOOKING_RETURN_DATE      => $return,
			MetaKeys::BOOKING_DROPOFF_DATE     => $return,
			MetaKeys::BOOKING_PICKUP_TIME      => '10:00',
			MetaKeys::BOOKING_RETURN_TIME      => '10:00',
			MetaKeys::BOOKING_START_TS         => strtotime($pickup . ' 10:00:00 UTC'),
			MetaKeys::BOOKING_END_TS           => strtotime($return . ' 10:00:00 UTC'),
			MetaKeys::BOOKING_TOTAL_PRICE      => '200',
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
}
