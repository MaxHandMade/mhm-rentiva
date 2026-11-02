<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\CacheManager;
use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_Query;
use WP_UnitTestCase;

/**
 * The rental clause's two NOT EXISTS / NOT IN branches join postmeta on post_id
 * alone, so with the active-vehicle clause beside it the rows multiply by every
 * meta row a vehicle has. Answering the clause first must select the same vehicles.
 */
final class VehicleQueryShapeTest extends WP_UnitTestCase
{
	/** @var array<string,int> */
	private array $vehicles = array();

	public function setUp(): void
	{
		parent::setUp();
		RequestSimulator::new_request();
		CacheManager::invalidate_type('vehicle_list');

		$fleet = array(
			'plain'          => array(),
			'active'         => array( MetaKeys::VEHICLE_LIFECYCLE_STATUS => 'active' ),
			'paused'         => array( MetaKeys::VEHICLE_LIFECYCLE_STATUS => 'paused' ),
			'legacy_active'  => array( MetaKeys::VEHICLE_STATUS => 'active' ),
			'legacy_off'     => array( MetaKeys::VEHICLE_STATUS => 'inactive' ),
			'rental'         => array( MetaKeys::VEHICLE_SERVICE_TYPE => 'rental' ),
			'both'           => array( MetaKeys::VEHICLE_SERVICE_TYPE => 'both' ),
			'transfer'       => array( MetaKeys::VEHICLE_SERVICE_TYPE => 'transfer' ),
			'blank_rental'   => array( MetaKeys::VEHICLE_SERVICE_TYPE => '' ),
			'blank_transfer' => array(
				MetaKeys::VEHICLE_SERVICE_TYPE        => '',
				MetaKeys::VEHICLE_SERVICE_TYPE_LEGACY => 'transfer',
			),
			'old_transfer'   => array( MetaKeys::VEHICLE_SERVICE_TYPE_LEGACY => 'transfer' ),
			'odd_value'      => array( MetaKeys::VEHICLE_SERVICE_TYPE => 'something' ),
		);

		$day = 1;
		foreach ($fleet as $name => $meta) {
			$id = (int) self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_vehicle',
					'post_status' => 'publish',
					'post_title'  => $name,
					'post_date'   => sprintf('2026-01-%02d 10:00:00', $day++),
				)
			);
			update_post_meta($id, MetaKeys::VEHICLE_FEATURED, '1');
			foreach ($meta as $key => $value) {
				update_post_meta($id, $key, $value);
			}
			$this->vehicles[ $name ] = $id;
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function base_args(): array
	{
		return VehicleDataHelper::restrict_query_to_service(
			array(
				'post_type'      => 'mhmrentiva_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_query'     => array(
					MetaQueryHelper::get_active_vehicle_meta_query(),
					MetaQueryHelper::get_featured_meta_query('1'),
				),
			),
			'rental'
		);
	}

	/**
	 * @param array<string,mixed> $args WP_Query arguments.
	 */
	private function assert_same_answer(array $args, string $why): void
	{
		$before = new WP_Query($args);
		$after  = new WP_Query(VehicleDataHelper::prefilter_service_clause($args));

		$this->assertSame(array_map('intval', $before->posts), array_map('intval', $after->posts), $why);
		$this->assertSame($before->found_posts, $after->found_posts, $why . ' (total)');
	}

	public function test_the_fleet_is_a_real_control(): void
	{
		$ids = array_map('intval', ( new WP_Query($this->base_args()) )->posts);

		$this->assertContains($this->vehicles['plain'], $ids);
		$this->assertContains($this->vehicles['both'], $ids);
		$this->assertContains($this->vehicles['odd_value'], $ids);
		$this->assertNotContains($this->vehicles['transfer'], $ids);
		$this->assertNotContains($this->vehicles['paused'], $ids);
	}

	public function test_same_vehicles_in_the_same_order(): void
	{
		$this->assert_same_answer($this->base_args(), 'Plain.');

		$limited                   = $this->base_args();
		$limited['posts_per_page'] = 3;
		$this->assert_same_answer($limited, 'Limit.');

		$titled            = $this->base_args();
		$titled['orderby'] = 'title';
		$titled['order']   = 'ASC';
		$this->assert_same_answer($titled, 'Title order.');
	}

	public function test_an_exclusion_still_excludes(): void
	{
		$args                 = $this->base_args();
		$args['post__not_in'] = array( $this->vehicles['plain'] );
		$this->assert_same_answer($args, 'post__not_in without post__in.');

		$filtered = VehicleDataHelper::prefilter_service_clause($args);
		$this->assertNotContains($this->vehicles['plain'], $filtered['post__in']);
	}

	public function test_a_given_id_list_keeps_its_order_and_its_members(): void
	{
		$args             = $this->base_args();
		$args['post__in'] = array( $this->vehicles['both'], $this->vehicles['transfer'], $this->vehicles['plain'] );
		$args['orderby']  = 'post__in';
		$this->assert_same_answer($args, 'post__in order.');

		$filtered = VehicleDataHelper::prefilter_service_clause($args);
		$this->assertSame(array( $this->vehicles['both'], $this->vehicles['plain'] ), $filtered['post__in']);

		// WP_Query ignores post__not_in beside post__in: that stays so.
		$args['post__not_in'] = array( $this->vehicles['plain'] );
		$this->assert_same_answer($args, 'post__not_in ignored beside post__in, as before.');
	}

	public function test_nothing_admitted_matches_nothing_rather_than_everything(): void
	{
		$args             = $this->base_args();
		$args['post__in'] = array( $this->vehicles['transfer'] );

		$filtered = VehicleDataHelper::prefilter_service_clause($args);
		$this->assertSame(array( 0 ), $filtered['post__in']);
		$this->assertSame(array(), ( new WP_Query($filtered) )->posts);

		$guard             = $this->base_args();
		$guard['post__in'] = array( 0 );
		$this->assert_same_answer($guard, 'The minimum rental guard.');
	}

	public function test_the_clause_is_gone_and_other_clauses_stay(): void
	{
		$args = $this->base_args();
		$this->assertArrayHasKey('mhmrentiva_service_type', $args['meta_query']);

		$filtered = VehicleDataHelper::prefilter_service_clause($args);
		$this->assertArrayNotHasKey('mhmrentiva_service_type', $filtered['meta_query']);
		$this->assertSame($args['meta_query'][0], $filtered['meta_query'][0]);
		$this->assertSame($args['meta_query'][1], $filtered['meta_query'][1]);
	}

	public function test_a_query_without_the_clause_or_with_an_or_top_is_left_alone(): void
	{
		$none = array( 'post_type' => 'mhmrentiva_vehicle', 'meta_query' => array( MetaQueryHelper::get_featured_meta_query('1') ) );
		$this->assertSame($none, VehicleDataHelper::prefilter_service_clause($none));

		// A filter that turned the top into OR: the clause no longer narrows, so it cannot move.
		$or                           = $this->base_args();
		$or['meta_query']['relation'] = 'OR';
		$this->assertSame($or, VehicleDataHelper::prefilter_service_clause($or));
	}

	public function test_the_joined_rows_no_longer_multiply(): void
	{
		$sql = array();
		$capture = static function (string $query) use (&$sql): string {
			if (str_contains($query, 'mhmrentiva_vehicle') && str_contains($query, 'posts.ID')) {
				$sql[] = $query;
			}
			return $query;
		};
		add_filter('query', $capture);
		new WP_Query(VehicleDataHelper::prefilter_service_clause($this->base_args()));
		remove_filter('query', $capture);

		$this->assertNotEmpty($sql);
		foreach ($sql as $one) {
			$this->assertLessThanOrEqual(7, substr_count($one, 'JOIN'), 'No query joins both clauses at once: ' . $one);
		}
	}

	/**
	 * @return list<string> The vehicle list queries of one render.
	 */
	private function render_and_capture(string $shortcode): array
	{
		$sql     = array();
		$capture = static function (string $query) use (&$sql): string {
			if (str_contains($query, "post_type = 'mhmrentiva_vehicle'") && str_contains($query, 'SELECT')) {
				$sql[] = $query;
			}
			return $query;
		};
		add_filter('query', $capture);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		do_shortcode($shortcode);
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		remove_filter('query', $capture);

		return $sql;
	}

	public function test_featured_counts_no_total(): void
	{
		$sql = $this->render_and_capture('[rentiva_featured_vehicles layout="grid" limit="3"]');

		$this->assertNotEmpty($sql);
		foreach ($sql as $one) {
			$this->assertStringNotContainsString('SQL_CALC_FOUND_ROWS', $one);
		}
	}

	public function test_favorites_count_no_total(): void
	{
		$user = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		update_user_meta($user, 'mhmrentiva_favorites', array( $this->vehicles['plain'] ));
		wp_set_current_user($user);

		$sql = $this->render_and_capture('[rentiva_my_favorites]');
		wp_set_current_user(0);

		foreach ($sql as $one) {
			$this->assertStringNotContainsString('SQL_CALC_FOUND_ROWS', $one);
		}
	}

	public function test_an_empty_answer_is_cached_too(): void
	{
		$shortcode = '[rentiva_featured_vehicles layout="grid" limit="3" category="no-such-category"]';

		$this->assertNotEmpty($this->render_and_capture($shortcode), 'The miss asks the database.');
		$second = array_filter(
			$this->render_and_capture($shortcode),
			static fn(string $query): bool => str_contains($query, MetaKeys::VEHICLE_FEATURED)
		);
		$this->assertSame(array(), $second, 'The empty answer is answered from the cache.');
	}

	/**
	 * @return list<int> What the shortcode's own query selects.
	 */
	private function featured_ids(string $atts = 'limit="50"'): array
	{
		$method = new \ReflectionMethod(FeaturedVehicles::class, 'prepare_template_data');
		$method->setAccessible(true);
		$data = $method->invoke(null, shortcode_parse_atts($atts));

		return array_map(static fn(array $vehicle): int => (int) $vehicle['id'], (array) $data['vehicles']);
	}

	public function test_a_cached_list_follows_every_change_that_decides_membership(): void
	{
		$id = $this->vehicles['plain'];
		$this->assertContains($id, $this->featured_ids());

		update_post_meta($id, MetaKeys::VEHICLE_FEATURED, '0');
		$this->assertNotContains($id, $this->featured_ids(), 'Featured off.');

		update_post_meta($id, MetaKeys::VEHICLE_FEATURED, '1');
		$this->assertContains($id, $this->featured_ids(), 'Featured on.');

		update_post_meta($id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, 'paused');
		$this->assertNotContains($id, $this->featured_ids(), 'Lifecycle.');

		delete_post_meta($id, MetaKeys::VEHICLE_LIFECYCLE_STATUS);
		$this->assertContains($id, $this->featured_ids(), 'Lifecycle removed.');

		update_post_meta($id, MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');
		$this->assertNotContains($id, $this->featured_ids(), 'Service.');

		delete_post_meta($id, MetaKeys::VEHICLE_SERVICE_TYPE);
		$this->assertContains($id, $this->featured_ids());

		wp_trash_post($id);
		$this->assertNotContains($id, $this->featured_ids(), 'Trashed.');

		wp_untrash_post($id);
		wp_publish_post($id);
		$this->assertContains($id, $this->featured_ids(), 'Back.');
	}

	public function test_an_empty_list_ends_when_the_first_vehicle_qualifies(): void
	{
		foreach ($this->vehicles as $id) {
			update_post_meta($id, MetaKeys::VEHICLE_FEATURED, '0');
		}
		$this->assertSame(array(), $this->featured_ids());

		$new = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		update_post_meta($new, MetaKeys::VEHICLE_FEATURED, '1');

		$this->assertSame(array( $new ), $this->featured_ids());
	}

	/**
	 * Featured HTML of the shortcode, with the HTML cache on.
	 */
	private function featured_html(): string
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_featured_vehicles layout="grid" limit="50"]');
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);

		return $html;
	}

	public function test_the_html_cache_really_answers(): void
	{
		$first = $this->featured_html();
		$this->assertStringContainsString('>plain<', $first);

		// Written behind every hook: only the cache can still show the old title.
		global $wpdb;
		$wpdb->update($wpdb->posts, array( 'post_title' => 'renamed' ), array( 'ID' => $this->vehicles['plain'] ));
		clean_post_cache($this->vehicles['plain']);
		$this->assertStringContainsString('>plain<', $this->featured_html(), 'Negative control: the HTML came from the cache.');
	}

	public function test_the_html_cache_follows_a_meta_change_from_any_session(): void
	{
		$this->assertStringContainsString('>plain<', $this->featured_html());

		// An administrator's request, not the guest's: the cache read conditions differ.
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		update_post_meta($this->vehicles['plain'], MetaKeys::VEHICLE_FEATURED, '0');
		wp_set_current_user(0);

		$this->assertStringNotContainsString('>plain<', $this->featured_html());
	}

	public function test_the_html_cache_follows_a_status_change(): void
	{
		$this->assertStringContainsString('>plain<', $this->featured_html());
		wp_update_post(array( 'ID' => $this->vehicles['plain'], 'post_status' => 'draft' ));
		$this->assertStringNotContainsString('>plain<', $this->featured_html());
	}

	public function test_the_html_cache_follows_a_delete(): void
	{
		$this->assertStringContainsString('>plain<', $this->featured_html());
		wp_delete_post($this->vehicles['plain'], true);
		$this->assertStringNotContainsString('>plain<', $this->featured_html());
	}

	public function test_a_rename_and_a_category_change_refresh_the_list(): void
	{
		$this->assertStringContainsString('>plain<', $this->featured_html());
		wp_update_post(array( 'ID' => $this->vehicles['plain'], 'post_title' => 'renamed' ));
		$this->assertStringContainsString('>renamed<', $this->featured_html());

		$term = self::factory()->term->create(array( 'taxonomy' => 'mhmrentiva_vehicle_category', 'slug' => 'vans' ));
		$this->assertSame(array(), $this->featured_ids('limit="50" category="vans"'));
		wp_set_object_terms($this->vehicles['plain'], array( (int) $term ), 'mhmrentiva_vehicle_category');
		$this->assertSame(array( $this->vehicles['plain'] ), $this->featured_ids('limit="50" category="vans"'));
	}

	public function test_another_meta_key_does_not_invalidate(): void
	{
		$id = $this->vehicles['plain'];
		$this->featured_ids();

		$sql     = array();
		$capture = static function (string $query) use (&$sql): string {
			if (str_contains($query, MetaKeys::VEHICLE_FEATURED)) {
				$sql[] = $query;
			}
			return $query;
		};
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');
		add_filter('query', $capture);
		$this->featured_ids();
		remove_filter('query', $capture);

		$this->assertSame(array(), $sql, 'A price change leaves the list alone.');
	}
}
