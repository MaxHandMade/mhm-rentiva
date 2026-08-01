<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Bare `mhm_` names that reach a storage or registry API are enumerated here,
 * and nothing may join them silently.
 *
 * The plugin's prefix is `mhm_rentiva_`. `mhm_` alone is four characters and
 * collides with anything else using the same initials — the rule
 * WordPress.org has already flagged this plugin under.
 *
 * WHY THIS IS A TEST AND NOT A GREP
 * ---------------------------------
 * Three separate hand-rolled sweeps of this class each reported it clean while
 * live writers remained, because each looked for a different shape:
 *
 *   - scanning `set_transient('mhm_…')` call sites missed keys assembled into a
 *     variable first;
 *   - scanning `$var = 'mhm_…'` assignments missed keys returned straight out of
 *     a helper (`return 'mhm_shc_' . md5(…)`) and keys built with sprintf();
 *   - both missed a key held in a class constant.
 *
 * By the third miss, "temporary cache entries now carry the plugin's prefix" had
 * been written into the release notes and into a reply to the reviewer, where a
 * single grep would have contradicted it. So the check no longer starts from the
 * shapes I happen to think of: it collects the string literals themselves,
 * wherever they appear, and only then asks which ones sit next to something that
 * registers or stores.
 *
 * WHAT COUNTS
 * -----------
 * A literal is in scope when it appears within a few lines of a transient,
 * option, user-meta, settings-group, table-prefix, post-type, taxonomy or cron
 * registration. Nonce actions, form field names and admin-notice slugs are not:
 * they are scoped to this plugin's own requests and markup, not to a registry
 * shared with every other plugin.
 */
final class NoBareMhmStorageKeysTest extends TestCase
{
	/**
	 * The reviewed inventory: every bare `mhm_` literal that currently reaches a
	 * storage or registry API, with the reason it is allowed to.
	 *
	 * Adding an entry is a decision, not a formality — if it is storage that
	 * cannot be migrated safely, it also belongs in the prefix section of the
	 * reply to WordPress.org, so the written answer and the code agree.
	 *
	 * @var array<string, string>
	 */
	private const INVENTORY = array(
		// EMPTY, AND THAT IS THE RESULT OF Görev 12, NOT AN OVERSIGHT.
		//
		// Every entry that stood here was a bare `mhm_` name this plugin had
		// decided it could not safely rename -- the vehicle field-configuration
		// options carried since 1.x, the cache and transient families, the log
		// post types, a handful of nonce actions. WordPress.org's T7 review
		// rejected that position by name: "'mhm' only as prefix is not
		// acceptable". Their tokenizer splits at the first underscore, so
		// `mhm_rentiva_*` reads as the three-letter prefix `mhm` exactly as
		// `mhm_*` does, and the reasoning that protected this list protected
		// nothing.
		//
		// The 6.0.0 rename moves all of them to the single token `mhmrentiva_`,
		// with PrefixMigrationMap as the contract and Görev 13's migration
		// carrying the stored values across. So there is no longer a bare `mhm_`
		// name reaching a storage or registry API, and the honest inventory is
		// the empty one.
		//
		// The gate below is NOT weaker for being empty -- it is stricter. It now
		// says "no bare `mhm_` storage name may exist at all", and any new one
		// fails the build instead of being waved through by an entry someone
		// added to make the suite green. test_the_scan_reads_the_plugin_source
		// separately proves the scanner is still reading the tree, so an empty
		// result here means absence, not blindness.
	);

	/**
	 * Registered names that carry no `mhm` prefix at all.
	 *
	 * The scan above hunts for `mhm_`, so by construction it can never see a name
	 * that has no prefix whatsoever — and those are the more dangerous ones.
	 * `vehicle` is a slug any other rental plugin might choose; `addon_context`
	 * is two generic words. A post-type collision is not loud: whichever plugin
	 * registers first wins and every row the loser wrote becomes unreachable.
	 *
	 * They are not renamed for the same reason as the tables and the log post
	 * types: the slug is written into every `wp_posts` row and every term this
	 * plugin has ever created since 1.x, so a rename is a migration over the
	 * site's entire content, and a migration that half-runs loses it. The list is
	 * here so the decision is visible, and so the reply to WordPress.org names
	 * them rather than presenting a storage list that a grep can show is short.
	 *
	 * @var array<string, string>
	 */
	private const UNPREFIXED_REGISTERED = array(
		'vehicle'          => 'post type: every vehicle row on every install',
		'vehicle_booking'  => 'post type: every booking row on every install',
		'vehicle_addon'    => 'post type: every add-on row on every install',
		'vehicle_category' => 'taxonomy: every vehicle category term',
		'addon_category'   => 'taxonomy: every add-on category term',
		'addon_context'    => 'taxonomy: every add-on context term',
	);

	/**
	 * The list above must describe the code: a registered name that is not in it
	 * and does not start with the plugin prefix is a new one, and needs a
	 * decision rather than silence.
	 */
	public function test_no_new_unprefixed_post_type_or_taxonomy_is_registered(): void
	{
		$root      = dirname( __DIR__, 2 );
		$offenders = array();

		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) );
		foreach ( $it as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$code = (string) file_get_contents( $file->getPathname() );

			// Both the direct call and the constant these classes register through.
			preg_match_all(
				// The constant names are anchored on a word boundary so that a field-type
				// constant such as TYPE_TAXONOMY is not read as a taxonomy registration.
				'/(?:register_(?:post_type|taxonomy)\(\s*|\b(?:POST_TYPE|TYPE|TAXONOMY)\s+=\s*)[\'"]([a-z][a-z0-9_]*)[\'"]/',
				$code,
				$m
			);

			foreach ( $m[1] as $name ) {
				if ( str_starts_with( $name, 'mhm' ) || isset( self::UNPREFIXED_REGISTERED[ $name ] ) ) {
					continue;
				}

				$offenders[] = $name . '  (' . str_replace( $root . DIRECTORY_SEPARATOR, '', $file->getPathname() ) . ')';
			}
		}

		$offenders = array_values( array_unique( $offenders ) );
		sort( $offenders );

		$this->assertSame(
			array(),
			$offenders,
			"A post type or taxonomy is registered under a name carrying no plugin prefix.
"
				. "Prefix it, or add it to UNPREFIXED_REGISTERED with the reason — and to the
"
				. "reply to WordPress.org, which enumerates these:
  "
				. implode( "
  ", $offenders )
		);
	}

	/**
	 * APIs whose nearby literals are registered or stored names.
	 */
	private const STORAGE_API = '/(?:set|get|delete)_(?:site_)?transient|(?:get|update|add|delete)_option'
		. '|register_setting|\$wpdb->prefix|\$wpdb->options|esc_like|(?:update|get|delete|add)_(?:user|comment|term)_meta'
		. '|register_post_type|register_post_status|register_taxonomy|wp_localize_script'
		. '|wp_schedule_(?:event|single_event)|wp_next_scheduled|wp_cache_(?:set|get|delete)'
		. '|wp_(?:register|enqueue)_(?:script|style)|CACHE_KEYS|_transient_/';

	/**
	 * The bare, four-character prefix this gate was built to hunt.
	 *
	 * '(?!rentiva)' kept it from matching the plugin's own 'mhm_rentiva_' names.
	 * After the 6.0.0 single-token rename there is no 'mhm_' in this codebase at
	 * all, so it now matches nothing -- which is the point, and which is exactly
	 * why the scanner's own sanity guard can no longer be "did it find some".
	 */
	private const BARE_MHM_PATTERN = '/[\'"]_?(?:transient_)?(mhm_(?!rentiva)[a-z0-9_]*)/i';

	/**
	 * The post-rename prefix. Used only to prove the scanner still works.
	 */
	private const PREFIXED_PATTERN = '/[\'"]_?(?:transient_)?(mhmrentiva_[a-z0-9_]*)/i';

	/**
	 * @return array<string, list<string>> literal => file:line list
	 */
	private function storage_literals( string $name_pattern = self::BARE_MHM_PATTERN ): array
	{
		$root  = dirname( __DIR__, 2 );
		$found = array();

		// The two root files register and store as well: `mhm-rentiva.php` writes
		// post meta and `uninstall.php` clears options. Leaving them out of the
		// scan is the difference between "everything shipped" and "everything
		// under src/".
		foreach ( array( '/mhm-rentiva.php', '/uninstall.php' ) as $single ) {
			$path = $root . $single;
			if ( ! is_file( $path ) ) {
				continue;
			}

			foreach ( $this->literals_in( $path, $root, $name_pattern ) as $literal => $sites ) {
				foreach ( $sites as $site ) {
					$found[ $literal ][] = $site;
				}
			}
		}

		foreach ( array( '/src', '/templates' ) as $dir ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir ) );

			foreach ( $it as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}

				// PrefixMigrationMap is the RECORD of the old names -- it has to
				// spell every one of them out, on the left of a `=>`, or Görev 13
				// has nothing to migrate from. Reading it as evidence that a bare
				// name still reaches storage inverts its purpose: the one file
				// guaranteed to contain the old names would permanently fail the
				// gate that exists to prove they are gone from the code. Narrow by
				// design -- every other file under src/ is still scanned.
				if ( str_ends_with(
					str_replace( '\\', '/', $file->getPathname() ),
					'/Admin/Core/Utilities/PrefixMigrationMap.php'
				) ) {
					continue;
				}

				foreach ( $this->literals_in( $file->getPathname(), $root, $name_pattern ) as $literal => $sites ) {
					foreach ( $sites as $site ) {
						$found[ $literal ][] = $site;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Bare `mhm_` literals in one file that sit next to something that stores or
	 * registers.
	 *
	 * @return array<string, list<string>>
	 */
	private function literals_in( string $path, string $root, string $name_pattern = self::BARE_MHM_PATTERN ): array
	{
		$found = array();
		$lines = preg_split( '/\R/', (string) file_get_contents( $path ) ) ?: array();
		{

				foreach ( $lines as $i => $line ) {
					$trimmed = ltrim( $line );
					if ( str_starts_with( $trimmed, '*' ) || str_starts_with( $trimmed, '//' ) ) {
						continue;
					}

					if ( ! preg_match_all( $name_pattern, $line, $m ) ) {
						continue;
					}

					// A key held in a class constant sits arbitrarily far from the call
					// that stores it: MetricCacheManager::PREFIX is declared on line 21
					// and reaches set_transient() on line 64. No window connects those,
					// which is exactly how a live bare-prefixed transient family survived
					// the first version of this gate. A constant whose value starts `mhm_`
					// is therefore in scope wherever it is declared, window or not.
					$is_constant = 1 === preg_match( '/\\bconst\\s+\\w+\\s*=\\s*[\'"]mhm_/i', $line );

					$window = implode( "\n", array_slice( $lines, max( 0, $i - 4 ), 9 ) );
					if ( ! $is_constant && ! preg_match( self::STORAGE_API, $window ) ) {
						continue;
					}

					foreach ( $m[1] as $literal ) {
						$found[ $literal ][] = str_replace( $root . DIRECTORY_SEPARATOR, '', $path )
							. ':' . ( $i + 1 );
					}
				}
		}

		return $found;
	}

	public function test_no_unlisted_bare_mhm_name_reaches_storage(): void
	{
		$offenders = array();

		foreach ( $this->storage_literals() as $literal => $sites ) {
			if ( isset( self::INVENTORY[ $literal ] ) ) {
				continue;
			}

			$offenders[] = $literal . '  (' . implode( ', ', array_slice( $sites, 0, 3 ) ) . ')';
		}

		sort( $offenders );

		$this->assertSame(
			array(),
			$offenders,
			"These names reach a storage or registry API with the bare `mhm_` prefix rather than\n"
				. "`mhm_rentiva_`. Rename them, or add them to INVENTORY here with the reason — and if\n"
				. "the reason is 'storage that cannot be migrated safely', add them to the prefix section\n"
				. "of the reply to WordPress.org as well:\n  "
				. implode( "\n  ", $offenders )
		);
	}

	/**
	 * The specific sentence the release notes and the reviewer reply make:
	 * caches were renamed. Pinned by file so it stays true.
	 */
	public function test_every_cache_prefix_is_fully_prefixed(): void
	{
		$root    = dirname( __DIR__, 2 );
		$sources = array(
			'src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php',
			'src/Admin/Booking/Helpers/Cache.php',
			'src/Admin/Core/PerformanceHelper.php',
			'src/Admin/Core/SecurityHelper.php',
			'src/Admin/Core/ShortcodeUrlManager.php',
			'src/Admin/Utilities/Dashboard/DashboardService.php',
			'src/Admin/Vehicle/ListTable/VehicleColumns.php',
			'src/Admin/Emails/Notifications/BookingNotifications.php',
		);

		foreach ( $sources as $relative ) {
			$code = (string) file_get_contents( $root . '/' . $relative );

			$this->assertSame(
				0,
				preg_match_all(
					"/['\"]mhm_(?!rentiva)(?:shc_|avail_|shortcode_|rv_cache|rate_limit|miss_sc|dashboard_recent|recent_messages|vehicle_stats|welcome_sent)/",
					$code
				),
				$relative . ' still builds a cache key with the bare prefix.'
			);
		}
	}

	/**
	 * Guards the scan itself: a broken iterator or an over-tight window would
	 * make the assertion above pass while reading nothing.
	 */
	public function test_the_scan_reads_the_plugin_source(): void
	{
		// This guard used to count BARE 'mhm_' storage literals and demand more
		// than 15. The 6.0.0 rename drives that count to zero on purpose, so
		// keeping the old assertion would mean either a permanently red gate or
		// -- far worse -- relaxing the threshold to 0 and leaving behind a check
		// that a completely broken iterator would also satisfy.
		//
		// The guard therefore runs the SAME scanner over the prefix the code now
		// uses. It still proves what it was there to prove -- the iterator walks
		// the tree, the window logic works, the pattern matches real lines -- but
		// it no longer needs violations to exist in order to prove it.
		$this->assertGreaterThan(
			15,
			count( $this->storage_literals( self::PREFIXED_PATTERN ) ),
			'The storage-literal scan matched implausibly little; the window or the pattern is broken.'
		);
	}

	/**
	 * An inventory entry that no longer matches anything is stale — it should be
	 * removed, so the list keeps describing the code rather than its history.
	 */
	public function test_the_inventory_has_no_stale_entries(): void
	{
		$live  = array_keys( $this->storage_literals() );
		$stale = array_values( array_diff( array_keys( self::INVENTORY ), $live ) );

		sort( $stale );

		$this->assertSame(
			array(),
			$stale,
			"INVENTORY lists names that no longer appear near a storage API. Remove them:\n  "
				. implode( "\n  ", $stale )
		);
	}
}
