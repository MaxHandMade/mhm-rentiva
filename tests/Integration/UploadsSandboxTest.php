<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use WP_UnitTestCase;

/**
 * The suite shares the dev site's ABSPATH. Core's remove_added_uploads()
 * deletes every file under the uploads basedir that was not there when the
 * run started, so a photo uploaded to the dev site during a run was deleted
 * (a vendor's vehicle photos, 2026-11-15). The bootstrap points the basedir
 * at a temp directory for the whole run; URLs stay as core computes them.
 */
final class UploadsSandboxTest extends WP_UnitTestCase {

	private function temp_root(): string {
		return rtrim( wp_normalize_path( sys_get_temp_dir() ), '/' ) . '/';
	}

	public function test_uploads_basedir_and_path_are_in_the_temp_directory(): void {
		$dir = wp_upload_dir( null, false );

		$this->assertStringStartsWith( $this->temp_root(), wp_normalize_path( $dir['basedir'] ) );
		$this->assertStringStartsWith( $this->temp_root(), wp_normalize_path( $dir['path'] ) );
	}

	public function test_uploads_url_is_left_as_core_computes_it(): void {
		$core = _wp_upload_dir();
		$dir  = wp_upload_dir( null, false );

		$this->assertSame( $core['baseurl'], $dir['baseurl'] );
		$this->assertSame( $core['url'], $dir['url'] );
	}

	/**
	 * A custom uploads root (UPLOADS, ms-files' blogs.dir) is not under
	 * WP_CONTENT_DIR/uploads; two such roots must not share one sandbox
	 * directory, or two sites' same-named files collide.
	 */
	public function test_two_custom_roots_keep_separate_sandbox_directories(): void {
		$basedir_for = function ( string $root ): string {
			$pin = static function ( array $dir ) use ( $root ): array {
				$dir['basedir'] = $root;
				$dir['path']    = $root . $dir['subdir'];
				return $dir;
			};
			add_filter( 'upload_dir', $pin, 0 );
			$basedir = wp_normalize_path( wp_upload_dir( null, false )['basedir'] );
			remove_filter( 'upload_dir', $pin, 0 );
			return $basedir;
		};

		$site_2 = $basedir_for( '/srv/media/sites/2' );
		$site_3 = $basedir_for( '/srv/media/sites/3' );

		$this->assertStringStartsWith( $this->temp_root(), $site_2 );
		$this->assertStringStartsWith( $this->temp_root(), $site_3 );
		$this->assertNotSame( $site_2, $site_3 );
	}

	/**
	 * @group multisite
	 */
	public function test_a_switched_site_writes_to_its_own_sandbox_directory(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'multisite only (composer test:multisite)' );
		}

		// Known debt, declared as in CustomersCacheTypeTest: creating a site fires
		// the plugin's own wpmu_new_blog listener (mhm-rentiva.php), deprecated
		// since WordPress 5.1.0 in favour of wp_initialize_site.
		$this->setExpectedDeprecated( 'wpmu_new_blog' );

		$main = wp_upload_bits( 'mhm-rentiva-sandbox-same-name.txt', null, 'main' );
		$blog = self::factory()->blog->create();
		switch_to_blog( $blog );
		try {
			$other = wp_upload_bits( 'mhm-rentiva-sandbox-same-name.txt', null, 'other' );
		} finally {
			restore_current_blog();
		}

		$this->assertStringStartsWith( $this->temp_root(), wp_normalize_path( $other['file'] ) );
		$this->assertNotSame( dirname( $main['file'] ), dirname( $other['file'] ) );
		$this->assertSame( 'main', file_get_contents( $main['file'] ) );

		unlink( $main['file'] );
		unlink( $other['file'] );
	}

	public function test_a_file_written_by_a_test_never_lands_in_the_real_uploads_directory(): void {
		$upload = wp_upload_bits( 'mhm-rentiva-sandbox-probe.txt', null, 'probe' );

		$this->assertEmpty( $upload['error'] );
		$this->assertStringStartsWith( $this->temp_root(), wp_normalize_path( $upload['file'] ) );
		$this->assertStringNotContainsString( wp_normalize_path( WP_CONTENT_DIR ) . '/uploads/', wp_normalize_path( $upload['file'] ) );

		unlink( $upload['file'] );
	}
}
