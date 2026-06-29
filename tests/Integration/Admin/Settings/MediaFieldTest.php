<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsHelper;
use WP_UnitTestCase;

/**
 * @group settings
 */
final class MediaFieldTest extends WP_UnitTestCase {

	public function test_html_has_hidden_input_and_picker(): void {
		update_option('mhm_rentiva_settings', array('statement_logo_id' => 0));
		$html = SettingsHelper::render_media_field_html('statement_logo_id');

		$this->assertStringContainsString('name="mhm_rentiva_settings[statement_logo_id]"', $html);
		$this->assertStringContainsString('type="hidden"', $html);
		$this->assertStringContainsString('wp.media', $html);
		$this->assertStringContainsString('data-mhm-media-select', $html);
	}

	public function test_html_shows_preview_when_attachment_set(): void {
		$att = (int) $this->factory->post->create(array(
			'post_type' => 'attachment',
			'guid'      => 'http://example.org/wp-content/uploads/logo.png',
		));
		update_option('mhm_rentiva_settings', array('statement_logo_id' => $att));
		$html = SettingsHelper::render_media_field_html('statement_logo_id');

		$this->assertStringContainsString('http://example.org/wp-content/uploads/logo.png', $html);
		$this->assertStringContainsString('value="' . $att . '"', $html);
	}
}
