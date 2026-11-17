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
		$dir = wp_upload_dir( null, false );

		$this->assertStringEndsWith( '/wp-content/uploads', untrailingslashit( $dir['baseurl'] ) );
		$this->assertSame( $dir['baseurl'] . $dir['subdir'], $dir['url'] );
	}

	public function test_a_file_written_by_a_test_never_lands_in_the_real_uploads_directory(): void {
		$upload = wp_upload_bits( 'mhm-rentiva-sandbox-probe.txt', null, 'probe' );

		$this->assertEmpty( $upload['error'] );
		$this->assertStringStartsWith( $this->temp_root(), wp_normalize_path( $upload['file'] ) );
		$this->assertStringNotContainsString( wp_normalize_path( WP_CONTENT_DIR ) . '/uploads/', wp_normalize_path( $upload['file'] ) );

		unlink( $upload['file'] );
	}
}
