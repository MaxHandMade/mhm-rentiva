<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use WP_UnitTestCase;

/**
 * debug_search() must see all three embedding forms Render Parity allows —
 * classic shortcode text, the matching Gutenberg block, and the matching
 * Elementor widget — and must scan every published page, not just the
 * bracket-containing subset (the old behaviour that read a block-built demo
 * page as "not found" while the component rendered on it).
 *
 * @covers \MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions::debug_search
 */
final class ShortcodeDebugSearchTest extends WP_UnitTestCase {

	private function result_for( array $payload, string $slug ): array {
		foreach ( $payload['results'] as $row ) {
			if ( $slug === $row['slug'] ) {
				return $row;
			}
		}
		$this->fail( "No result row for $slug" );
	}

	/** @return array<int, list<string>> page_id => via list */
	private function hits_by_page( array $row ): array {
		$hits = array();
		foreach ( $row['found_in'] as $hit ) {
			$hits[ $hit['page_id'] ] = $hit['via'];
		}
		return $hits;
	}

	public function test_finds_shortcode_block_and_widget_embeddings(): void {
		$shortcode_page = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Intro [rentiva_booking_form vehicle_id="7"] outro',
			)
		);
		$block_page     = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				// Block-built page: no bracket anywhere in the content.
				'post_content' => '<!-- wp:mhm-rentiva/booking-form {"vehicleId":"7"} /-->',
			)
		);
		$widget_page    = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		update_post_meta( $widget_page, '_elementor_data', '[{"widgetType":"rv-booking-form","settings":{}}]' );

		$payload = ShortcodePageActions::debug_search();
		$row     = $this->result_for( $payload, 'rentiva_booking_form' );
		$hits    = $this->hits_by_page( $row );

		$this->assertSame( array( 'shortcode' ), $hits[ $shortcode_page ] ?? null, 'shortcode text page' );
		$this->assertSame( array( 'block' ), $hits[ $block_page ] ?? null, 'block-built page' );
		$this->assertSame( array( 'widget' ), $hits[ $widget_page ] ?? null, 'Elementor widget page' );
	}

	public function test_shortcode_embedded_inside_elementor_data_counts_as_widget_use(): void {
		$page = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		update_post_meta( $page, '_elementor_data', '[{"widgetType":"shortcode","settings":{"shortcode":"[rentiva_testimonials limit=3]"}}]' );

		$row  = $this->result_for( ShortcodePageActions::debug_search(), 'rentiva_testimonials' );
		$hits = $this->hits_by_page( $row );

		$this->assertSame( array( 'widget' ), $hits[ $page ] ?? null );
	}

	public function test_scanned_pages_counts_every_published_page(): void {
		// A page with no bracket at all — the old pre-filter dropped these.
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Plain page without any embed.',
			)
		);
		$published = (int) ( wp_count_posts( 'page' )->publish ?? 0 );

		$payload = ShortcodePageActions::debug_search();

		$this->assertSame( $published, $payload['scanned_pages'] );
	}

	public function test_similar_prefix_does_not_false_positive(): void {
		// [rentiva_vehicles_list] must not match rentiva_vehicles_grid (or the
		// block/widget needles of a sibling with a shared prefix).
		$page = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[rentiva_vehicles_list limit="6"]',
			)
		);

		$payload = ShortcodePageActions::debug_search();
		$grid    = $this->result_for( $payload, 'rentiva_vehicles_grid' );

		$this->assertArrayNotHasKey( $page, $this->hits_by_page( $grid ) );
	}
}
