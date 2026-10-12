<?php

declare(strict_types=1);

namespace MHMRentiva\Frontend;

/**
 * Tells a widget whether it is being drawn for a visitor (`front`) or for an
 * editor preview: Elementor (`elementor`) or the block editor (`block`).
 *
 * Nothing here is memoized. Elementor's edit mode is open only for the
 * duration of `ajax_render_widget`, and the block-renderer stack changes inside
 * one request, so every current() call recomputes from live state.
 *
 * Ordinary WordPress post previews (`is_preview()`) and
 * `is_editor_or_preview()` are deliberately NOT consulted: they resolve to
 * `front`.
 *
 * Known limit: if a REST callback throws, `rest_request_after_callbacks` does
 * not run and the block stack stays populated for the rest of that PHP process
 * (`rest_post_dispatch` does not fire on the internal rest_do_request() path,
 * so it cannot serve as a fallback). Over HTTP the process ends with the
 * request; tests clear the stack with reset(). A stale entry can share its
 * spl_object_id with a later request object, which then pops it: harmless
 * self-heal.
 *
 * @package MHMRentiva\Frontend
 */
final class RenderContext {

	public const SOURCE_FRONT     = 'front';
	public const SOURCE_ELEMENTOR = 'elementor';
	public const SOURCE_BLOCK     = 'block';

	private const BLOCK_RENDERER_ROUTE = '/wp/v2/block-renderer/';
	private const BLOCK_NAMESPACE      = 'mhm-rentiva/';

	/**
	 * Object ids of the block-renderer requests currently being dispatched.
	 *
	 * @var array<int, int>
	 */
	private static array $block_stack = array();

	private string $source;

	private function __construct(string $source)
	{
		$this->source = $source;
	}

	/**
	 * Hook the REST callbacks that track block-editor server-side rendering.
	 * Idempotent.
	 */
	public static function register(): void
	{
		if (false === has_filter('rest_request_before_callbacks', array( self::class, 'enter_request' ))) {
			add_filter('rest_request_before_callbacks', array( self::class, 'enter_request' ), 10, 3);
		}
		if (false === has_filter('rest_request_after_callbacks', array( self::class, 'leave_request' ))) {
			add_filter('rest_request_after_callbacks', array( self::class, 'leave_request' ), 10, 3);
		}
	}

	/**
	 * The context of the render happening right now. Recomputed on every call.
	 */
	public static function current(): self
	{
		$source = self::detect();

		if (self::SOURCE_FRONT !== $source) {
			/**
			 * Lets a site change the source of an already detected preview.
			 * Never consulted on the front end, so it cannot switch a visitor
			 * render into a preview. A value other than `front`, `elementor`
			 * or `block` is ignored.
			 *
			 * @param array{source:string} $context Detected context.
			 */
			$filtered = apply_filters('mhmrentiva_render_context', array( 'source' => $source ));
			if (
				is_array($filtered)
				&& isset($filtered['source'])
				&& is_string($filtered['source'])
				&& in_array($filtered['source'], array( self::SOURCE_FRONT, self::SOURCE_ELEMENTOR, self::SOURCE_BLOCK ), true)
			) {
				$source = $filtered['source'];
			}
		}

		return new self($source);
	}

	public function source(): string
	{
		return $this->source;
	}

	public function is_preview(): bool
	{
		return self::SOURCE_FRONT !== $this->source;
	}

	public function allows_sample(): bool
	{
		return $this->is_preview();
	}

	public function is_interactive(): bool
	{
		return ! $this->is_preview();
	}

	/**
	 * Empty the block stack. For tests only: see the class note on exceptions.
	 *
	 * @internal
	 */
	public static function reset(): void
	{
		self::$block_stack = array();
	}

	/**
	 * `rest_request_before_callbacks` handler. Always returns $response as is.
	 *
	 * @param mixed $response Pass-through.
	 * @param mixed $handler  Unused.
	 * @param mixed $request  The REST request.
	 * @return mixed
	 */
	public static function enter_request($response, $handler = null, $request = null)
	{
		if (self::is_block_edit_request($request)) {
			self::$block_stack[] = spl_object_id($request);
		}
		return $response;
	}

	/**
	 * `rest_request_after_callbacks` handler. Always returns $response as is.
	 *
	 * @param mixed $response Pass-through.
	 * @param mixed $handler  Unused.
	 * @param mixed $request  The REST request.
	 * @return mixed
	 */
	public static function leave_request($response, $handler = null, $request = null)
	{
		if (is_object($request) && array() !== self::$block_stack) {
			$id  = spl_object_id($request);
			$pos = array_search($id, self::$block_stack, true);
			if (false !== $pos) {
				array_splice(self::$block_stack, (int) $pos, 1);
			}
		}
		return $response;
	}

	private static function detect(): string
	{
		if (array() !== self::$block_stack) {
			return self::SOURCE_BLOCK;
		}
		if (self::elementor_is_previewing()) {
			return self::SOURCE_ELEMENTOR;
		}
		return self::SOURCE_FRONT;
	}

	/**
	 * Elementor edit mode (AJAX re-render) or preview iframe. False whenever
	 * Elementor is not loaded or has no instance yet.
	 */
	private static function elementor_is_previewing(): bool
	{
		if (! did_action('elementor/loaded') || ! class_exists('\Elementor\Plugin')) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if (! is_object($plugin)) {
			return false;
		}
		if ($plugin->editor->is_edit_mode()) {
			return true;
		}
		return $plugin->preview->is_preview_mode();
	}

	/**
	 * @param mixed $request
	 */
	private static function is_block_edit_request($request): bool
	{
		if (! $request instanceof \WP_REST_Request) {
			return false;
		}
		if (! str_starts_with($request->get_route(), self::BLOCK_RENDERER_ROUTE)) {
			return false;
		}
		$name = $request->get_param('name');
		if (! is_string($name) || ! str_starts_with($name, self::BLOCK_NAMESPACE)) {
			return false;
		}
		return 'edit' === $request->get_param('context');
	}
}
