<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * RenderContext: front / elementor / block detection, recomputed on every call.
 */
class RenderContextTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	private const BLOCK_ROUTE = '/wp/v2/block-renderer/mhm-rentiva/vehicles-grid';

	/** @var array<string, mixed> */
	private array $saved_get = array();

	public function set_up(): void
	{
		parent::set_up();
		$this->saved_get = $_GET;
		RenderContext::reset();
	}

	public function tear_down(): void
	{
		$_GET = $this->saved_get;
		unset($GLOBALS['post']);
		if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance) {
			\Elementor\Plugin::$instance->editor->set_edit_mode(false);
		}
		remove_all_filters('mhmrentiva_render_context');
		remove_all_filters('pre_render_block');
		wp_set_current_user(0);
		$this->reset_context_and_tear_down();
	}

	private function login_admin(): int
	{
		$id = self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($id);
		return $id;
	}

	/**
	 * Dispatch a block-renderer request; $probe runs during the render and its
	 * return values are collected.
	 *
	 * @param callable(): mixed $probe
	 * @return array<int, mixed>
	 */
	private function dispatch_block(string $route, string $method, string $context, callable $probe): array
	{
		$seen = array();
		add_filter(
			'pre_render_block',
			static function ($pre) use (&$seen, $probe) {
				$seen[] = $probe();
				return $pre;
			}
		);
		$request = new WP_REST_Request($method, $route);
		$request->set_param('context', $context);
		rest_do_request($request);
		remove_all_filters('pre_render_block');
		return $seen;
	}

	public function test_front_by_default(): void
	{
		$ctx = RenderContext::current();
		$this->assertSame('front', $ctx->source());
		$this->assertFalse($ctx->is_preview());
		$this->assertFalse($ctx->allows_sample());
		$this->assertTrue($ctx->is_interactive());
	}

	public function test_elementor_ajax_edit_mode(): void
	{
		$editor = \Elementor\Plugin::$instance->editor;
		$editor->set_edit_mode(true);
		$ctx = RenderContext::current();
		$this->assertSame('elementor', $ctx->source());
		$this->assertTrue($ctx->is_preview());
		$this->assertTrue($ctx->allows_sample());
		$this->assertFalse($ctx->is_interactive());

		$editor->set_edit_mode(false);
		$this->assertSame('front', RenderContext::current()->source(), 'no memoization within one request');
	}

	public function test_elementor_preview_mode(): void
	{
		$this->login_admin();
		$post_id                   = self::factory()->post->create();
		$GLOBALS['post']           = get_post($post_id);
		$_GET['elementor-preview'] = (string) $post_id;

		$this->assertSame('elementor', RenderContext::current()->source());
	}

	/**
	 * Theme Builder previews a template over a sample page: the queried post is
	 * not the edited document. The preview id must be passed explicitly.
	 */
	public function test_preview_id_differs_from_queried_post_is_elementor(): void
	{
		$this->login_admin();
		$edited                    = self::factory()->post->create();
		$sample                    = self::factory()->post->create();
		$GLOBALS['post']           = get_post($sample);
		$_GET['elementor-preview'] = (string) $edited;

		$this->assertNotSame($edited, get_the_ID());
		$this->assertSame('elementor', RenderContext::current()->source());
	}

	public function test_preview_id_without_edit_capability_on_that_post_is_front(): void
	{
		$admin  = self::factory()->user->create(array( 'role' => 'administrator' ));
		$edited = self::factory()->post->create(array( 'post_author' => $admin ));
		$sample = self::factory()->post->create();

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'author' )));
		$GLOBALS['post']           = get_post($sample);
		$_GET['elementor-preview'] = (string) $edited;

		$this->assertSame('front', RenderContext::current()->source());
	}

	public function test_preview_query_arg_without_capability_is_front(): void
	{
		$post_id                   = self::factory()->post->create();
		$GLOBALS['post']           = get_post($post_id);
		$_GET['elementor-preview'] = (string) $post_id;

		wp_set_current_user(0);
		$this->assertSame('front', RenderContext::current()->source());

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'subscriber' )));
		$this->assertSame('front', RenderContext::current()->source());
	}

	public function test_block_renderer_edit_context(): void
	{
		$this->login_admin();
		$probe = static fn () => RenderContext::current()->source();

		$get  = $this->dispatch_block(self::BLOCK_ROUTE, 'GET', 'edit', $probe);
		$post = $this->dispatch_block(self::BLOCK_ROUTE, 'POST', 'edit', $probe);

		$this->assertContains('block', $get);
		$this->assertContains('block', $post);
		$this->assertSame('front', RenderContext::current()->source(), 'stack is empty after dispatch');
	}

	public function test_block_renderer_view_context_is_front(): void
	{
		$this->login_admin();
		$seen = $this->dispatch_block(self::BLOCK_ROUTE, 'GET', 'view', static fn () => RenderContext::current()->source());
		$this->assertSame(array(), $seen, 'core rejects context=view at validation, before any block renders');

		// Whatever the controller does with a non-edit context, the hook itself must not push.
		$request = new WP_REST_Request('GET', self::BLOCK_ROUTE);
		$request->set_param('context', 'view');
		RenderContext::enter_request(null, array(), $request);
		$this->assertSame('front', RenderContext::current()->source());
	}

	public function test_foreign_block_is_front(): void
	{
		$this->login_admin();
		$seen = $this->dispatch_block('/wp/v2/block-renderer/core/archives', 'GET', 'edit', static fn () => RenderContext::current()->source());
		$this->assertNotEmpty($seen, 'core/archives actually rendered');
		foreach ($seen as $source) {
			$this->assertSame('front', $source);
		}
	}

	public function test_nested_dispatch_stack(): void
	{
		$this->login_admin();
		$after = null;
		$guard = false;

		add_filter(
			'pre_render_block',
			function ($pre) use (&$after, &$guard) {
				if ($guard) {
					return $pre;
				}
				$guard = true;
				$inner = new WP_REST_Request('GET', self::BLOCK_ROUTE);
				$inner->set_param('context', 'edit');
				rest_do_request($inner);
				$after = RenderContext::current()->source();
				$guard = false;
				return $pre;
			}
		);

		$outer = new WP_REST_Request('GET', self::BLOCK_ROUTE);
		$outer->set_param('context', 'edit');
		rest_do_request($outer);
		remove_all_filters('pre_render_block');

		$this->assertSame('block', $after, 'outer request still on the stack after the inner one finished');
		$this->assertSame('front', RenderContext::current()->source(), 'both popped');
	}

	public function test_exception_leaves_block_until_reset(): void
	{
		$this->login_admin();
		add_filter(
			'pre_render_block',
			static function () {
				throw new \RuntimeException('boom');
			}
		);
		$request = new WP_REST_Request('GET', self::BLOCK_ROUTE);
		$request->set_param('context', 'edit');

		try {
			rest_do_request($request);
			$this->fail('exception expected');
		} catch (\RuntimeException $e) {
			$this->assertSame('boom', $e->getMessage());
		}
		remove_all_filters('pre_render_block');

		$this->assertSame('block', RenderContext::current()->source(), 'documented limit');
		RenderContext::reset();
		$this->assertSame('front', RenderContext::current()->source());
	}

	public function test_wp_post_preview_is_front(): void
	{
		$post_id = self::factory()->post->create();
		$this->go_to(home_url('/?p=' . $post_id . '&preview=true'));
		$this->assertTrue(is_preview());
		$this->assertSame('front', RenderContext::current()->source());
	}

	public function test_filter_cannot_turn_front_into_preview(): void
	{
		add_filter('mhmrentiva_render_context', static fn () => array( 'source' => 'elementor' ));
		$ctx = RenderContext::current();
		$this->assertSame('front', $ctx->source());
		$this->assertFalse($ctx->is_preview());
	}

	public function test_filter_can_change_detected_preview_source(): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode(true);
		add_filter('mhmrentiva_render_context', static fn () => array( 'source' => 'block' ));
		$this->assertSame('block', RenderContext::current()->source());

		remove_all_filters('mhmrentiva_render_context');
		add_filter('mhmrentiva_render_context', static fn () => array( 'source' => 'bogus' ));
		$this->assertSame('elementor', RenderContext::current()->source(), 'unknown value ignored');

		remove_all_filters('mhmrentiva_render_context');
		add_filter('mhmrentiva_render_context', static fn () => 'not-an-array');
		$this->assertSame('elementor', RenderContext::current()->source(), 'malformed value ignored');
	}

	public function test_elementor_absent_is_front(): void
	{
		$plugin = \Elementor\Plugin::$instance;
		$plugin->editor->set_edit_mode(true);
		try {
			\Elementor\Plugin::$instance = null;
			$this->assertSame('front', RenderContext::current()->source());
		} finally {
			\Elementor\Plugin::$instance = $plugin;
		}
		$this->assertSame('elementor', RenderContext::current()->source());
	}

	public function test_elementor_before_init_is_front(): void
	{
		global $wp_actions;
		$saved = $wp_actions['elementor/init'] ?? null;
		\Elementor\Plugin::$instance->editor->set_edit_mode(true);
		try {
			unset($wp_actions['elementor/init']);
			$this->assertSame(0, did_action('elementor/init'));
			$this->assertSame('front', RenderContext::current()->source());
		} finally {
			if (null !== $saved) {
				$wp_actions['elementor/init'] = $saved;
			}
		}
		$this->assertSame('elementor', RenderContext::current()->source());
	}

	public function test_hooks_pass_response_through_untouched(): void
	{
		$response = new \WP_REST_Response(array( 'a' => 1 ));
		$request  = new WP_REST_Request('GET', self::BLOCK_ROUTE);
		$this->assertSame($response, RenderContext::enter_request($response, array(), $request));
		$this->assertSame($response, RenderContext::leave_request($response, array(), $request));
		$this->assertSame(10, has_filter('rest_request_before_callbacks', array( RenderContext::class, 'enter_request' )));
	}
}
