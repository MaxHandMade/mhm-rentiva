<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use WP_UnitTestCase;

/**
 * Preview infrastructure: sample data, wrapper, badge, nonce helpers, cache skip.
 */
class PreviewInfrastructureTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	public function set_up(): void
	{
		parent::set_up();
		RenderContext::reset();
		PreviewSamples::reset();
		PreviewCacheProbeShortcode::$renders = 0;
		PreviewCacheProbeShortcode::$reads   = 0;
		PreviewCacheProbeShortcode::$writes  = 0;
	}

	public function tear_down(): void
	{
		$this->set_preview(false);
		remove_all_filters('mhmrentiva_shortcode_html_cache_enabled');
		$this->reset_context_and_tear_down();
	}

	private function set_preview(bool $on): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode($on);
	}

	private function make_vehicle(string $lifecycle, string $password = ''): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'     => 'mhmrentiva_vehicle',
				'post_status'   => 'publish',
				'post_password' => $password,
			)
		);
		update_post_meta($id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, $lifecycle);
		return $id;
	}

	public function test_vehicle_id_lowest_active_public(): void
	{
		$this->make_vehicle('inactive');
		$this->make_vehicle('active', 'secret');
		$active = array();
		for ($i = 0; $i < 4; $i++) {
			$active[] = $this->make_vehicle('active');
		}
		$this->assertSame($active[0], PreviewSamples::vehicle_id());
		$this->assertSame(array( $active[0], $active[1] ), PreviewSamples::vehicle_ids(2));
		$this->assertSame($active, PreviewSamples::vehicle_ids(10));
	}

	public function test_no_published_vehicle_returns_zero_and_empty_state(): void
	{
		$this->make_vehicle('inactive');
		$this->assertSame(0, PreviewSamples::vehicle_id());
		$this->assertSame(array(), PreviewSamples::vehicle_ids(3));
	}

	public function test_samples_not_persisted(): void
	{
		global $wpdb;
		$this->make_vehicle('active');
		$count = static function () use ($wpdb): array {
			return array(
				(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
				(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}"),
				(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"),
			);
		};
		$before = $count();
		PreviewSamples::vehicle_id();
		PreviewSamples::vehicle_ids(3);
		PreviewSamples::bookings_rows();
		PreviewSamples::payment_rows();
		$this->assertSame($before, $count());
	}

	public function test_sample_rows_are_deterministic_plain_arrays(): void
	{
		$this->assertSame(PreviewSamples::bookings_rows(), PreviewSamples::bookings_rows());
		$this->assertNotEmpty(PreviewSamples::bookings_rows());
		foreach (PreviewSamples::bookings_rows() as $row) {
			$this->assertIsArray($row);
			foreach (array( 'id', 'vehicle_title', 'status', 'pickup_date', 'pickup_time', 'dropoff_date', 'total_price' ) as $key) {
				$this->assertArrayHasKey($key, $row);
			}
		}
		foreach (PreviewSamples::payment_rows() as $row) {
			foreach (array( 'booking_id', 'booking_title', 'date', 'sort_ts', 'status', 'status_label', 'method', 'amount', 'has_amount', 'total', 'type', 'receipt' ) as $key) {
				$this->assertArrayHasKey($key, $row);
			}
			$this->assertSame(array( 'attachment_id', 'status', 'url' ), array_keys($row['receipt']));
		}
	}

	public function test_wrap_preview_only_in_preview(): void
	{
		$this->assertSame('<p>x</p>', RenderContext::wrap_preview('<p>x</p>'));
		$this->set_preview(true);
		$this->assertSame(
			'<div class="mhm-rv-preview" data-rv-render="preview"><p>x</p></div>',
			RenderContext::wrap_preview('<p>x</p>')
		);
	}

	public function test_render_scope_front_is_passthrough(): void
	{
		$this->assertSame('<i>a</i>', RenderContext::render_preview_scope(static fn () => '<i>a</i>'));
	}

	public function test_wrap_outermost_only(): void
	{
		$this->set_preview(true);
		$html = RenderContext::render_preview_scope(
			static fn () => 'A' . RenderContext::render_preview_scope(static fn () => 'B') . 'C'
		);
		$this->assertSame('<div class="mhm-rv-preview" data-rv-render="preview">ABC</div>', $html);
		$this->assertSame(1, substr_count($html, 'data-rv-render'));
		// A second, sequential scope wraps again: the depth returned to zero.
		$this->assertSame(1, substr_count(RenderContext::render_preview_scope(static fn () => 'D'), 'data-rv-render'));
	}

	public function test_depth_restored_after_exception(): void
	{
		$this->set_preview(true);
		try {
			RenderContext::render_preview_scope(
				static function (): string {
					throw new \RuntimeException('boom');
				}
			);
			$this->fail('exception expected');
		} catch (\RuntimeException $e) {
			$this->assertSame('boom', $e->getMessage());
		}
		$this->assertSame(
			'<div class="mhm-rv-preview" data-rv-render="preview">ok</div>',
			RenderContext::render_preview_scope(static fn () => 'ok'),
			'a throw inside a scope must not leave the counter raised'
		);
	}

	public function test_nonce_field_front_byte_identical(): void
	{
		$this->assertSame(
			wp_nonce_field('act_x', 'fld_x', true, false),
			RenderContext::nonce_field('act_x', 'fld_x')
		);
		$this->assertSame(
			wp_nonce_field('act_x', 'fld_x', true, false),
			RenderContext::nonce_field('act_x', 'fld_x', true)
		);
		// $referer = false: the id-less plain input the rating form prints today.
		$this->assertSame(
			'<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('mhmrentiva_rating_nonce')) . '">',
			RenderContext::nonce_field('mhmrentiva_rating_nonce', 'nonce', false)
		);
		$this->assertSame(
			' data-nonce="' . esc_attr(wp_create_nonce('act_y')) . '"',
			RenderContext::nonce_attr('act_y')
		);
	}

	public function test_nonce_helpers_empty_in_preview(): void
	{
		$this->set_preview(true);
		$this->assertSame('', RenderContext::nonce_field('a', 'n'));
		$this->assertSame('', RenderContext::nonce_field('a', 'n', false));
		$this->assertSame('', RenderContext::nonce_attr('a'));
	}

	/**
	 * The printing template tags emit exactly what the templates echoed
	 * before (`echo nonce_field(..)`, `echo nonce_field(.., false) . "\n"`,
	 * `echo ltrim( nonce_attr(..) ) . "\n"`), on the front end and in a preview.
	 */
	public function test_printing_nonce_helpers_match_the_returning_ones(): void
	{
		foreach (array( false, true ) as $preview) {
			$this->set_preview($preview);
			$label = $preview ? 'preview' : 'front';

			$this->assertSame(RenderContext::nonce_field('act_x', 'fld_x'), $this->printed(static fn () => RenderContext::the_nonce_field('act_x', 'fld_x')), $label);
			$this->assertSame(RenderContext::nonce_field('act_x', 'fld_x', false) . "\n", $this->printed(static fn () => RenderContext::the_nonce_input_line('act_x', 'fld_x')), $label);
			$this->assertSame(ltrim(RenderContext::nonce_attr('act_y')) . "\n", $this->printed(static fn () => RenderContext::the_nonce_attr_line('act_y')), $label);
		}
		$this->assertSame("\n", $this->printed(static fn () => RenderContext::the_nonce_attr_line('act_y')), 'preview prints the newline alone');
		$this->assertSame("\n", $this->printed(static fn () => RenderContext::the_nonce_input_line('act_x', 'fld_x')), 'preview prints the newline alone');
		$this->assertSame('', $this->printed(static fn () => RenderContext::the_nonce_field('act_x', 'fld_x')), 'preview prints no field');
	}

	/**
	 * @param callable(): void $print
	 */
	private function printed(callable $print): string
	{
		ob_start();
		$print();
		return (string) ob_get_clean();
	}

	public function test_badge_empty_on_front(): void
	{
		$this->assertSame('', RenderContext::badge('Preview: x'));
	}

	public function test_badge_renders_escaped_in_preview(): void
	{
		$this->set_preview(true);
		$html = RenderContext::badge('Preview: <b>x</b>');
		$this->assertStringContainsString('class="mhm-rv-preview-badge"', $html);
		$this->assertStringContainsString('role="note"', $html);
		$this->assertStringContainsString('Preview: &lt;b&gt;x&lt;/b&gt;', $html);
		$this->assertStringNotContainsString('<b>', $html);
	}

	public function test_preview_never_reads_or_writes_cache(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);

		// Front render populates the cache and is served from it afterwards.
		$front1 = PreviewCacheProbeShortcode::render(array());
		$front2 = PreviewCacheProbeShortcode::render(array());
		$this->assertSame($front1, $front2, 'front cache hit');
		$this->assertSame(1, PreviewCacheProbeShortcode::$renders);
		$this->assertSame(1, PreviewCacheProbeShortcode::$writes);

		// Preview neither reads the front entry nor writes one.
		$reads  = PreviewCacheProbeShortcode::$reads;
		$writes = PreviewCacheProbeShortcode::$writes;
		$this->set_preview(true);
		// The version is removed to see that a preview does not recreate it, and
		// put back afterwards: a recreated version is unique, so the front check
		// at the end would otherwise miss its own cached entry.
		$front_version = get_transient('mhmrentiva_rv_cache_v_rentiva_preview_cache_probe');
		delete_transient('mhmrentiva_rv_cache_v_rentiva_preview_cache_probe');
		$p1 = PreviewCacheProbeShortcode::render(array());
		$this->assertFalse(
			get_transient('mhmrentiva_rv_cache_v_rentiva_preview_cache_probe'),
			'a preview must not recreate the cache version transient'
		);
		$p2 = PreviewCacheProbeShortcode::render(array());
		$this->assertSame($reads, PreviewCacheProbeShortcode::$reads);
		$this->assertSame($writes, PreviewCacheProbeShortcode::$writes);
		$this->assertSame(3, PreviewCacheProbeShortcode::$renders, 'each preview render is fresh');
		$this->assertNotSame($p1, $p2);
		$this->assertStringStartsWith('<div class="mhm-rv-preview" data-rv-render="preview">', $p1);
		$this->assertStringNotContainsString('data-rv-render', $front1);

		// Back on the front, the cached front entry is not a preview wrapper.
		set_transient('mhmrentiva_rv_cache_v_rentiva_preview_cache_probe', $front_version, DAY_IN_SECONDS);
		$this->set_preview(false);
		$this->assertSame($front1, PreviewCacheProbeShortcode::render(array()));
	}

	public function test_preview_wraps_once_for_nested_shortcode(): void
	{
		$this->set_preview(true);
		$html = RenderContext::render_preview_scope(static fn () => PreviewCacheProbeShortcode::render(array()));
		$this->assertSame(1, substr_count($html, 'data-rv-render'));
	}
}

/**
 * Minimal AbstractShortcode subclass that counts renders and cache traffic.
 */
class PreviewCacheProbeShortcode extends AbstractShortcode
{
	public static int $renders = 0;
	public static int $reads   = 0;
	public static int $writes  = 0;

	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_preview_cache_probe';
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/does-not-exist';
	}

	protected static function get_default_attributes(): array
	{
		return array();
	}

	protected static function prepare_template_data(array $atts): array
	{
		return array( 'atts' => $atts );
	}

	protected static function render_template(array $template_data): string
	{
		++self::$renders;
		return '<p>render-' . self::$renders . '</p>';
	}

	protected static function get_cached_html(string $cache_key): ?string
	{
		++self::$reads;
		return parent::get_cached_html($cache_key);
	}

	protected static function cache_html(string $cache_key, string $html): void
	{
		++self::$writes;
		parent::cache_html($cache_key, $html);
	}
}
