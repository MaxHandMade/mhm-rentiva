<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use WP_UnitTestCase;

/**
 * The rating summary's bars print each star value's share; the template glued
 * `$percent . '%'`, so a Turkish vehicle page read "100%" where Turkish
 * writes "%100". Same class as the booking form's tax label.
 *
 * @covers \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage
 */
final class RatingDistributionPercentLocaleTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	private int $vehicle_id;

	public function set_up(): void
	{
		parent::set_up();
		RenderContext::reset();
		PreviewSamples::reset();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Rated Vehicle',
			)
		);
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->vehicle_id,
				'comment_type'     => 'review',
				'comment_approved' => 1,
			)
		);
		update_comment_meta($comment_id, 'mhmrentiva_rating', '5');
	}

	public function tear_down(): void
	{
		remove_all_filters('gettext_with_context');
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->reset_context_and_tear_down();
	}

	private function render(): string
	{
		RequestSimulator::new_request();

		return do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle_id . '"]');
	}

	public function test_an_english_page_puts_the_sign_after_the_share(): void
	{
		$this->assertStringContainsString('<span class="rv-rating-dist-percent">100%</span>', $this->render());
	}

	public function test_a_turkish_page_puts_the_sign_before_the_share(): void
	{
		add_filter(
			'gettext_with_context',
			static function ($translation, $text, $context, $domain) {
				return 'mhm-rentiva' === $domain && '%s%%' === $text && str_starts_with((string) $context, 'A percentage')
					? '%%%s'
					: $translation;
			},
			10,
			4
		);

		$this->assertStringContainsString('<span class="rv-rating-dist-percent">%100</span>', $this->render());
	}
}
