<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * The one normalizer every render comparison of the settings gates uses
 * (spec 2026-10-10-elementor-kopru-dilim-3a §3 G1, and G2 in later tasks).
 *
 * It removes what changes between two renders of the same settings, so a
 * difference that survives it is a difference the setting made:
 *   - data-render-time, the search-results, vehicle-page, calendar and booking-form instance
 *     ids (rvsr-N, rvs-vd-N, rvs-cal-N, rvs-bf-N; wp_unique_id),
 *     10-hex nonces and 10+ digit timestamps;
 *   - id / for / aria-* / data-*id values (they carry per-render ids);
 *   - the uniqid() wrapper ids of the contact, booking and unified search
 *     templates (rv_contact_ / rv_booking_ / rv_unified_ + 13 hex characters).
 *
 * The baseline (Task 0 Step 1) was the measurement tool's normalizer verbatim
 * (docs/superpowers/audits/2026-10-10-elementor-kopru-dilim-3/olcum/block-dead-audit-v4.php:25-32),
 * whose last rule `[a-z0-9]{13,}` also erased every long lower-case word
 * (Fable r2-4). Step 6 narrowed that rule to the uniqid prefixes above; the
 * measured dead set did not change (see the task-0 report).
 */
final class RenderNormalizer
{
	public static function normalize(string $html): string
	{
		$html = (string) preg_replace('/data-render-time="[^"]*"/', 'data-render-time="T"', $html);
		$html = (string) preg_replace('/rvsr-\d+/', 'rvsr-N', $html);
		// The vehicle page, its calendar months and the booking form (wp_unique_id) -- in hrefs too.
		$html = (string) preg_replace('/\b(rvs-vd|rvs-cal|rvs-bf)-\d+/', '$1-N', $html);
		$html = (string) preg_replace('/\b[a-f0-9]{10}\b/', 'NONCE', $html);
		$html = (string) preg_replace('/\b\d{10,}\b/', 'TS', $html);
		$html = (string) preg_replace('/(id|for|aria-[a-z]+|data-[a-z-]*id)="[^"]*"/', '$1="X"', $html);
		$html = (string) preg_replace('/rv_(contact|booking|unified)_[a-z0-9]{13,}/', 'rv_$1_UNIQ', $html);
		return $html;
	}

	/**
	 * normalize(), plus what differs between two runs on different days: the
	 * year/month upload folder of fixture images (`/uploads/2026/10/` becomes
	 * `/uploads/Y/M/`; fixture images take the current month) and the WP_DEBUG-only
	 * `<!-- Debug: ... -->` comments. For the recorded
	 * golden renders (G2) only; G1 compares two renders of one run and keeps
	 * normalize() alone, so its measured dead set is untouched.
	 */
	public static function golden(string $html): string
	{
		$html = self::normalize($html);
		$html = (string) preg_replace('#/uploads/\d{4}/\d{2}/#', '/uploads/Y/M/', $html);
		// Templates print `<!-- Debug: ... -->` comments only when WP_DEBUG is on (CI);
		// the goldens were recorded with it off.
		return (string) preg_replace('/<!--\s*Debug:.*?-->\s*/s', '', $html);
	}
}
