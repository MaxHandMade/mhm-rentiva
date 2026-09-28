<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

trait ContactAttachmentFixtures
{
	/** Writes one fixture of $kind into $dir and returns its path. */
	private function fixture(string $dir, string $kind): string
	{
		wp_mkdir_p($dir);
		$path = $dir . '/' . $kind . '-' . wp_generate_password(6, false, false) . '.bin';
		switch ($kind) {
			case 'pdf':
				file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
				break;
			case 'png':
				file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
				break;
			case 'gif':
				file_put_contents($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
				break;
			case 'doc_magic':
				file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 1016));
				break;
			case 'docx':
			case 'zip':
				$zip = new \ZipArchive();
				$zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
				if ('docx' === $kind) {
					$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
					$zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
				} else {
					$zip->addFromString('a.txt', 'x');
				}
				$zip->close();
				break;
			case 'html':
				file_put_contents($path, '<!doctype html><script>alert(1)</script>');
				break;
			case 'svg':
				file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
				break;
			case 'php':
				file_put_contents($path, '<?php echo 1;');
				break;
		}
		return $path;
	}

	/**
	 * A ContactAttachmentStore::store_upload()/ContactForm::process_submission()
	 * handler seam backed by a real core function: wp_handle_sideload() takes the
	 * same overrides contract as wp_handle_upload() but checks is_readable()
	 * instead of is_uploaded_file(), so it accepts a fixture that was never an
	 * HTTP upload. It copies (not renames) the source and unlinks the original --
	 * a test that reuses a fixture path after calling this must make a fresh one.
	 */
	private function sideload(): callable
	{
		require_once ABSPATH . 'wp-admin/includes/file.php';
		return static function (array $file, array $overrides): array {
			return wp_handle_sideload($file, $overrides);
		};
	}
}
