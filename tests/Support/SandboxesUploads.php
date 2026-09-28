<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Points wp_upload_dir()'s basedir at a fresh temp directory for one test.
 * baseurl is left as core computes it, so URL <-> path mapping stays real.
 * Call remove_sandbox() in tearDown() AFTER parent::tearDown().
 */
trait SandboxesUploads
{
	private string $sandbox = '';

	private function sandbox_uploads(): string
	{
		$this->sandbox = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/mhm-rentiva-uploads-' . wp_generate_password(10, false, false);
		wp_mkdir_p($this->sandbox);
		$base = $this->sandbox;
		add_filter('upload_dir', static function (array $dir) use ($base): array {
			$dir['basedir'] = $base;
			$dir['path']    = $base . $dir['subdir'];
			$dir['error']   = false;
			return $dir;
		});
		return $this->sandbox;
	}

	private function remove_sandbox(): void
	{
		$tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/';
		foreach (array( $this->sandbox, $tmp . 'mhm-rentiva-tests-contact-root' ) as $dir) {
			if ('' === $dir || ! str_starts_with($dir, $tmp) || ! is_dir($dir)) {
				continue;
			}
			$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($it as $f) {
				$f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
			}
			rmdir($dir);
		}
		$this->sandbox = '';
	}
}
