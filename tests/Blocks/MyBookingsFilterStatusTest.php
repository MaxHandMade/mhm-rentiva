<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Tests\Support\BlockAttributeGroups;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The my-bookings block's status filter offers booking statuses.
 *
 * It offered `active` and `upcoming`, which no booking ever carries, while the
 * real statuses were missing from block.json's enum -- so the block-renderer
 * preview rejected them (HTTP 400). The options now match the Elementor widget
 * (MyBookingsWidget.php:62-74). A legacy saved `active`/`upcoming` is invalid
 * under the new enum, so WP falls back to the default `all` = every booking,
 * which is what such a block showed before.
 */
class MyBookingsFilterStatusTest extends WP_UnitTestCase
{
	private const OPTIONS = array( 'all', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled' );

	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
	}

	public function option_provider(): array
	{
		$out = array();
		foreach (self::OPTIONS as $option) {
			$out[ $option ] = array( $option, 'all' === $option ? '' : $option );
		}
		return $out;
	}

	/**
	 * @dataProvider option_provider
	 */
	public function test_each_editor_option_previews(string $option): void
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$request = new WP_REST_Request('GET', '/wp/v2/block-renderer/mhm-rentiva/my-bookings');
		$request->set_query_params(
			array(
				'context'    => 'edit',
				'attributes' => array( 'filterStatus' => $option ),
			)
		);

		$this->assertSame(200, rest_do_request($request)->get_status());
	}

	/**
	 * @dataProvider option_provider
	 */
	public function test_each_option_reaches_status(string $option, string $expected): void
	{
		$atts = BlockAttributeGroups::captured_atts('my-bookings', array( 'filterStatus' => $option ));
		$this->assertSame($expected, $atts['status']);
	}

	/** Render path only: under the new enum these values are invalid by design. */
	public function test_legacy_values_mean_all(): void
	{
		foreach (array( 'active', 'upcoming' ) as $legacy) {
			$atts = BlockAttributeGroups::captured_atts('my-bookings', array( 'filterStatus' => $legacy ));
			$this->assertSame('', $atts['status'], "Legacy filterStatus \"{$legacy}\" must keep listing every booking.");
		}
	}

	public function test_editor_options_match_block_enum(): void
	{
		$dir   = dirname(__DIR__, 2) . '/assets/blocks/my-bookings';
		$json  = json_decode((string) file_get_contents($dir . '/block.json'), true);
		$this->assertSame(self::OPTIONS, $json['attributes']['filterStatus']['enum']);
		$this->assertSame('all', $json['attributes']['filterStatus']['default']);

		$js = (string) file_get_contents($dir . '/index.js');
		$this->assertSame(1, preg_match('/value:\s*filterStatus,\s*options:\s*\[(.*?)\]/s', $js, $m), 'filterStatus SelectControl not found.');
		preg_match_all("/value:\s*'([^']+)'/", $m[1], $values);
		$this->assertSame(self::OPTIONS, $values[1]);
	}
}
