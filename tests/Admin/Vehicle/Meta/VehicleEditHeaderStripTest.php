<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * The line under the core H1 on the vehicle screen (canvas Yon-Arac-Duzenle
 * v144, user approval 2026-11-09): back to the list, then the vehicle's name.
 * Links and text only -- it runs inside form#post.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::render_header_strip
 */
final class VehicleEditHeaderStripTest extends WP_UnitTestCase
{
	private function strip(\WP_Post $post): string
	{
		ob_start();
		VehicleMeta::render_header_strip($post);
		return (string) ob_get_clean();
	}

	public function test_strip_links_back_to_vehicles_and_names_the_vehicle(): void
	{
		$post = self::factory()->post->create_and_get(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Toyota <b>Corolla</b> 1.6',
		));

		$html = $this->strip($post);

		$this->assertStringContainsString('edit.php?post_type=mhmrentiva_vehicle', $html);
		$this->assertStringContainsString('Vehicles', $html);
		$this->assertStringContainsString('<span>Toyota Corolla 1.6</span>', $html, 'the name is plain text, tags stripped and escaped');
		$this->assertDoesNotMatchRegularExpression('/<input|<select|<textarea|name=/', $html);
	}

	public function test_strip_on_a_new_vehicle_has_the_back_link_only(): void
	{
		$post = self::factory()->post->create_and_get(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'auto-draft',
			'post_title'  => 'Auto Draft',
		));

		$html = $this->strip($post);

		$this->assertStringContainsString('edit.php?post_type=mhmrentiva_vehicle', $html);
		$this->assertStringNotContainsString('<span>', $html);
	}

	public function test_strip_is_silent_on_another_post_type(): void
	{
		$post = self::factory()->post->create_and_get(array( 'post_type' => 'post' ));

		$this->assertSame('', $this->strip($post));
	}

	public function test_strip_is_hooked_on_edit_form_top(): void
	{
		// register() runs only under is_admin() at boot; the test asks it directly.
		VehicleMeta::register();

		$this->assertNotFalse(has_action('edit_form_top', array( VehicleMeta::class, 'render_header_strip' )));
	}
}
