<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use WP_UnitTestCase;

/**
 * Plan 0.2 (measured on the dev site): links built from shortcode tags that nothing registers
 * fell through to a wrong page.
 *
 * - "Back to search" asked for `rentiva_search`; the block search for its slug, a prefix match,
 *   also hit the search-results block, so the link opened a copy of the results page.
 * - The login link asked for `rentiva_login`; get_page_url() never returns empty (it falls back
 *   to the home page), so the wp_login_url() fallback after it was unreachable.
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id
 */
final class PageLinkTargetsTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		wp_cache_flush();
	}

	private function page( string $content, string $date ): int
	{
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
				'post_date'    => $date,
			)
		);
	}

	public function test_a_block_matches_by_its_whole_name_not_a_prefix(): void
	{
		$this->page( '<!-- wp:mhm-rentiva/search-results /-->', '2020-01-02 00:00:00' );

		$this->assertNull( ShortcodeUrlManager::get_page_id( 'rentiva_search' ) );
	}

	public function test_the_whole_block_name_is_still_found(): void
	{
		$results = $this->page( '<!-- wp:mhm-rentiva/search-results {"layout":"grid"} /-->', '2020-01-02 00:00:00' );

		$this->assertSame( $results, ShortcodeUrlManager::get_page_id( 'rentiva_search_results' ) );
	}

	public function test_back_to_search_opens_the_search_page(): void
	{
		$search = $this->page( '[rentiva_unified_search]', '2020-01-01 00:00:00' );
		$this->page( '<!-- wp:mhm-rentiva/search-results /-->', '2020-01-02 00:00:00' );

		$html = do_shortcode( '[rentiva_search_results]' );

		$this->assertStringContainsString( 'rv-back-to-search', $html, 'Fixture guard: the empty-results state renders.' );
		$this->assertStringContainsString( 'href="' . esc_url( (string) get_permalink( $search ) ) . '" class="rv-back-to-search"', $html );
	}

	public function test_the_search_results_script_is_told_the_search_page(): void
	{
		$search = $this->page( '[rentiva_unified_search]', '2020-01-01 00:00:00' );
		$this->page( '<!-- wp:mhm-rentiva/search-results /-->', '2020-01-02 00:00:00' );

		// Drop any data an earlier test localized (wp_localize_script appends), then use the
		// public entry that localizes unconditionally (the render path enqueues once per request).
		wp_scripts()->add_data( 'mhm-rentiva-search-results-js', 'data', '' );
		\MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::ensure_runtime_assets();
		$data = (string) wp_scripts()->get_data( 'mhm-rentiva-search-results-js', 'data' );

		$this->assertNotSame( '', $data, 'Fixture guard: the results script was localized.' );
		$this->assertStringContainsString( '"search_page_url":' . wp_json_encode( (string) get_permalink( $search ), JSON_UNESCAPED_SLASHES ), $data );
	}

	private static function login_url_of( string $class ): string
	{
		$login = new \ReflectionMethod( $class, 'get_login_url' );
		$login->setAccessible( true );
		return (string) $login->invoke( null );
	}

	/**
	 * @dataProvider listing_shortcodes
	 */
	public function test_the_login_link_opens_the_account_page( string $class ): void
	{
		$account = $this->page( '[woocommerce_my_account]', '2020-01-01 00:00:00' );
		update_option( 'woocommerce_myaccount_page_id', $account );

		$this->assertSame( (string) get_permalink( $account ), self::login_url_of( $class ) );
	}

	/**
	 * @dataProvider listing_shortcodes
	 */
	public function test_without_an_account_page_the_login_link_is_the_wordpress_login( string $class ): void
	{
		update_option( 'woocommerce_myaccount_page_id', 0 );

		$this->assertSame( wp_login_url(), self::login_url_of( $class ) );
		$this->assertNotSame( home_url( '/' ), self::login_url_of( $class ) );
	}

	/**
	 * @return array<string,array{0:class-string}>
	 */
	public static function listing_shortcodes(): array
	{
		return array(
			'list' => array( \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::class ),
			'grid' => array( \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::class ),
		);
	}
}
