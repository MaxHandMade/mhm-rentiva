<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * WP.org T4 #10 — ContactForm::resolve_attachment_path() must map an uploads
 * URL to a filesystem path using wp_upload_dir(), never a naive
 * str_replace(site_url(), ABSPATH, $url) string-surgery.
 *
 * `$data['attachment']` reaches this method straight from client-controlled
 * POST data (ContactForm::sanitize_contact_form_data() only runs it through
 * sanitize_text_field() — it is NOT re-derived from wp_handle_upload()'s
 * return value on every path, and is stored/replayed as free text). That
 * means resolve_attachment_path() must treat its input as untrusted and
 * refuse to resolve anything that isn't demonstrably inside this site's own
 * uploads directory — otherwise an attacker can submit an absolute local
 * path (e.g. "/etc/passwd") and have it emailed out as an attachment
 * (LFI-to-email-exfiltration).
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\ContactForm
 */
final class ContactFormPathResolutionTest extends WP_UnitTestCase
{
    /** @var string[] Temp files created by a test, cleaned up in tearDown(). */
    private array $tempFiles = array();

    /** @var string[] Temp dirs created by a test, cleaned up in tearDown(). */
    private array $tempDirs = array();

    protected function tearDown(): void
    {
        remove_all_filters('upload_dir');

        foreach ($this->tempFiles as $file) {
            // is_link() is required alongside file_exists(): once a
            // symlink's target has already been unlinked (see the
            // symlink-escape test), file_exists() follows the link and
            // reports false for the now-dangling link itself, which would
            // leave the link's directory entry behind.
            if (file_exists($file) || is_link($file)) {
                unlink($file);
            }
        }

        // Deepest paths first, regardless of insertion order, so a parent
        // directory is never rmdir()'d while a tracked child still exists.
        $dirs = $this->tempDirs;
        usort($dirs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        $this->tempFiles = array();
        $this->tempDirs  = array();

        parent::tearDown();
    }

    /**
     * Creates a real file under a temp "uploads" tree and points the
     * `upload_dir` filter at it, so wp_upload_dir()['basedir'/'baseurl']
     * resolve to a controlled location for the duration of the test.
     *
     * @return array{basedir: string, baseurl: string, file: string, url: string}
     */
    private function fakeUploadsWithFile(string $relative = '2024/01/test.pdf', ?string $baseurl = null): array
    {
        $basedir = rtrim(sys_get_temp_dir(), '/\\') . '/mhm-cf-uploads-' . uniqid();
        $baseurl = $baseurl ?? 'http://example.org/wp-content/uploads/sites/2';

        $this->makeDir($basedir);
        $subdir = $basedir . '/' . dirname($relative);
        $this->makeDir($subdir);

        $file = $basedir . '/' . $relative;
        file_put_contents($file, 'test-content');
        $this->tempFiles[] = $file;

        add_filter(
            'upload_dir',
            static function (array $dir) use ($basedir, $baseurl): array {
                $dir['basedir'] = $basedir;
                $dir['baseurl'] = $baseurl;
                $dir['path']    = $basedir;
                $dir['url']     = $baseurl;
                return $dir;
            }
        );

        return array(
            'basedir' => $basedir,
            'baseurl' => $baseurl,
            'file'    => $file,
            'url'     => $baseurl . '/' . $relative,
        );
    }

    private function makeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Track every level under the system temp dir we might have
        // created (but never the system temp dir itself) so tearDown()
        // can remove them again.
        $temp_root = rtrim(sys_get_temp_dir(), '/\\');
        $walk      = $dir;
        while ($walk !== $temp_root
            && strpos($walk, $temp_root) === 0
            && ! in_array($walk, $this->tempDirs, true)
        ) {
            $this->tempDirs[] = $walk;
            $walk             = dirname($walk);
        }
    }

    private function resolveAttachmentPath(string $url): ?string
    {
        $method = new ReflectionMethod(ContactForm::class, 'resolve_attachment_path');
        $method->setAccessible(true);

        return $method->invoke(null, $url);
    }

    /**
     * Normal case: attachment URL sits directly under wp_upload_dir()'s
     * baseurl -- must resolve to the matching basedir-rooted path.
     */
    public function test_resolves_uploads_url_to_basedir_path(): void
    {
        $fixture = $this->fakeUploadsWithFile();

        $resolved = $this->resolveAttachmentPath($fixture['url']);

        $this->assertNotNull($resolved);
        $this->assertSame(
            wp_normalize_path(realpath($fixture['file'])),
            wp_normalize_path($resolved)
        );
    }

    /**
     * Subdirectory / multisite-subdirectory-network case: the URL's host is
     * this site's own host, but the URL path segment does NOT contain
     * site_url()'s path (subdirectory network uploads are keyed by
     * `/sites/<id>/`, not by the individual subsite's own home path) --
     * exactly the class of install where site_url()/ABSPATH string surgery
     * silently produces a wrong (non-existent) path while wp_upload_dir()
     * mapping still resolves correctly.
     */
    public function test_subdirectory_install_mismatch_is_resolved_correctly(): void
    {
        add_filter(
            'site_url',
            static function () {
                return 'http://example.org/site2';
            }
        );

        $fixture = $this->fakeUploadsWithFile();

        // Sanity-check the premise: the uploads URL does NOT contain
        // site_url() as a substring on this "subdirectory network" install,
        // so a naive str_replace(site_url(), ABSPATH, $url) cannot find
        // anything to replace and returns the URL untouched -- never a
        // filesystem path, let alone the right one. This is the exact
        // defect this fix removes (WP.org T4 #10).
        $legacy_result = str_replace(site_url(), ABSPATH, $fixture['url']);
        $this->assertSame(
            $fixture['url'],
            $legacy_result,
            'Premise check: legacy str_replace(site_url(), ABSPATH, ...) must NOT match on a subdirectory-network install.'
        );
        $this->assertFalse(
            file_exists($legacy_result),
            'Premise check: the legacy formula must not coincidentally produce a real path.'
        );

        // The new implementation, driven only by wp_upload_dir(), must
        // still resolve correctly.
        $resolved = $this->resolveAttachmentPath($fixture['url']);

        $this->assertNotNull($resolved);
        $this->assertSame(
            wp_normalize_path(realpath($fixture['file'])),
            wp_normalize_path($resolved)
        );
    }

    /**
     * SSRF/LFI guard: a URL on a different host must never resolve, no
     * matter how it is formatted.
     */
    public function test_rejects_url_on_a_different_host(): void
    {
        $this->fakeUploadsWithFile();

        $resolved = $this->resolveAttachmentPath('http://evil.example.com/wp-content/uploads/sites/2/2024/01/test.pdf');

        $this->assertNull($resolved);
    }

    /**
     * SSRF/LFI guard: this field is attacker-reachable free text, not
     * guaranteed to be a URL at all. A bare filesystem path must never be
     * resolved (this is the concrete local-file-disclosure vector: an
     * attacker submitting attachment=/etc/passwd must not get it emailed
     * out as an attachment).
     */
    public function test_rejects_a_bare_filesystem_path(): void
    {
        $this->fakeUploadsWithFile();

        $resolved = $this->resolveAttachmentPath('/etc/passwd');

        $this->assertNull($resolved);
    }

    /**
     * SSRF/LFI guard: an uploads-prefixed URL that tries to traverse back
     * out of basedir via ".." must not escape containment.
     */
    public function test_rejects_path_traversal_within_uploads_prefix(): void
    {
        $fixture = $this->fakeUploadsWithFile();

        $traversal_url = $fixture['baseurl'] . '/2024/01/../../../../../../etc/passwd';

        $resolved = $this->resolveAttachmentPath($traversal_url);

        $this->assertNull($resolved);
    }

    /**
     * A file that legitimately lives under baseurl but does not exist on
     * disk must resolve to null, not a guessed/nonexistent path.
     */
    public function test_returns_null_for_missing_file_under_uploads(): void
    {
        $fixture = $this->fakeUploadsWithFile();

        $resolved = $this->resolveAttachmentPath($fixture['baseurl'] . '/2024/01/does-not-exist.pdf');

        $this->assertNull($resolved);
    }

    public function test_empty_url_returns_null(): void
    {
        $this->assertNull($this->resolveAttachmentPath(''));
    }

    /**
     * Containment guard, symlink-escape variant: a symlink that lives
     * INSIDE the uploads basedir but points to a file OUTSIDE it must not
     * be resolvable. realpath() follows the symlink to its real target,
     * and the post-realpath() containment recheck must catch that the
     * resolved real path no longer sits under basedir -- this is the
     * classic containment-check bypass this design has to defend against.
     */
    public function test_rejects_symlink_that_escapes_basedir(): void
    {
        if (! function_exists('symlink') || stripos(PHP_OS, 'WIN') === 0) {
            $this->markTestSkipped('symlink() is not reliably available on this platform.');
        }

        $fixture = $this->fakeUploadsWithFile();

        // A real file OUTSIDE basedir -- the escape target.
        $outside_dir = rtrim(sys_get_temp_dir(), '/\\') . '/mhm-cf-outside-' . uniqid();
        $this->makeDir($outside_dir);
        $secret_file = $outside_dir . '/secret.txt';
        file_put_contents($secret_file, 'outside-basedir');
        $this->tempFiles[] = $secret_file;

        // A symlink INSIDE basedir pointing at the outside file.
        $symlink_path = $fixture['basedir'] . '/2024/01/escape-link.txt';
        $created      = @symlink($secret_file, $symlink_path);
        if (! $created) {
            $this->markTestSkipped('symlink() call failed on this platform/filesystem.');
        }
        $this->tempFiles[] = $symlink_path;

        $symlink_url = $fixture['baseurl'] . '/2024/01/escape-link.txt';

        $resolved = $this->resolveAttachmentPath($symlink_url);

        $this->assertNull($resolved, 'A symlink inside basedir pointing outside it must be rejected.');
    }

    /**
     * Functional-regression guard: some CDN / media-offload configurations
     * (e.g. S3/CDN-backed offload plugins) produce a wp_upload_dir()
     * baseurl with no path component at all (e.g.
     * "https://cdn.example.org"), so there is no "/wp-content/uploads"-style
     * string prefix to match against. A legitimate attachment must still
     * resolve in that configuration -- the resolver must not silently
     * fail-closed on every attachment just because baseurl is pathless.
     */
    public function test_resolves_legit_attachment_when_baseurl_is_pathless(): void
    {
        $fixture = $this->fakeUploadsWithFile('2024/01/test.pdf', 'https://example.org');

        $resolved = $this->resolveAttachmentPath($fixture['url']);

        $this->assertNotNull($resolved, 'A legit attachment must still resolve when baseurl has no path component.');
        $this->assertSame(
            wp_normalize_path(realpath($fixture['file'])),
            wp_normalize_path($resolved)
        );
    }

    /**
     * Same pathless-baseurl configuration, but the containment boundary
     * must still hold: a traversal attempt riding on a pathless baseurl
     * must still be rejected by the realpath() containment recheck, proving
     * the fix for the functional regression above did not also reopen the
     * SSRF/LFI guard.
     */
    public function test_rejects_traversal_when_baseurl_is_pathless(): void
    {
        $fixture = $this->fakeUploadsWithFile('2024/01/test.pdf', 'https://example.org');

        $traversal_url = $fixture['baseurl'] . '/2024/01/../../../../../../etc/passwd';

        $resolved = $this->resolveAttachmentPath($traversal_url);

        $this->assertNull($resolved);
    }
}
