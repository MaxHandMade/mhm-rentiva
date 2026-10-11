<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * golden() masks the WP_DEBUG-only `<!-- Debug: ... -->` comments (CI runs with
 * WP_DEBUG on, the goldens were recorded with it off); normalize() must not.
 */
final class RenderNormalizerTest extends TestCase
{
	public function test_golden_masks_debug_comments_and_the_whitespace_after_them(): void
	{
		$noisy = "<div><!-- Debug: User rating data: null -->
<p>a</p>
<!-- Debug: Found 1 approved comments for vehicle 3299 --><span>b</span></div>";
		$this->assertSame("<div><p>a</p>
<span>b</span></div>", RenderNormalizer::golden($noisy));
	}

	public function test_golden_keeps_other_comments_and_normalize_keeps_debug(): void
	{
		$html = "<!-- keep -->\n<!-- Debug: x -->\n";
		$this->assertSame("<!-- keep -->\n", RenderNormalizer::golden($html));
		$this->assertSame($html, RenderNormalizer::normalize($html));
	}
}
