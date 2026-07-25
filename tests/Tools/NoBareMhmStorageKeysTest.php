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
		// Options holding each site's vehicle field configuration since 1.x.
		// Renaming means a migration on every install; a migration that
		// half-runs loses the configuration, which is worse for the user than a
		// short name.
		'mhm_selected_details'         => 'option: site vehicle-field configuration',
		'mhm_selected_features'        => 'option: site vehicle-field configuration',
		'mhm_selected_equipment'       => 'option: site vehicle-field configuration',
		'mhm_custom_details'           => 'option: site vehicle-field configuration',
		'mhm_custom_features'          => 'option: site vehicle-field configuration',
		'mhm_custom_equipment'         => 'option: site vehicle-field configuration',
		'mhm_custom_field_meta'        => 'option: site vehicle-field configuration',
		'mhm_vehicle_details'          => 'option: site vehicle-field configuration',
		'mhm_vehicle_features'         => 'option: site vehicle-field configuration',
		'mhm_vehicle_equipment'        => 'option: site vehicle-field configuration',
		'mhm_vehicle_settings'         => 'settings group for the options above',
		'mhm_vehicle_'                 => 'prefix fragment of the options above',

		// Tables. Same argument, with more at stake.
		'mhm_message_logs'             => 'table',
		'mhm_backup_records'           => 'table',
		'mhm_payment_log'              => 'table',
		'mhm_sessions'                 => 'table',
		'mhm_transfers'                => 'table',
		'mhm_notification_queue'       => 'table: dropped by migration, name kept for the drop and for uninstall',
		'mhm_postmeta_backup_'         => 'table prefix',
		'mhm_postmeta_backup_invalid_' => 'table prefix',

		// Per-user e-mail preferences and dashboard layout, written since 1.x.
		'mhm_welcome_email'            => 'user meta: per-user e-mail preference',
		'mhm_booking_notifications'    => 'user meta: per-user e-mail preference',
		'mhm_marketing_emails'         => 'user meta: per-user e-mail preference',
		'mhm_dashboard_widget_order'   => 'user meta: dashboard layout',

		// Post types and post statuses stored on every existing post.

		// Legacy names retained only so cleanup can still find old rows.
		'mhm_send_scheduled_notifications' => 'legacy cron name, cleared by migration and uninstall',
		'mhm_transfer_deposit_type'    => 'legacy option name, migrated away by DatabaseMigrator',
		'mhm_message'                  => 'table-name fragment in the System Info report',
		'mhm_data_consent_given'       => 'booking post meta, written since 1.x',
		'mhm_log_retention'            => 'legacy cron name, cleared by uninstall',

		// Nonce actions that happen to sit beside an option read in the same
		// localize call. A nonce action is scoped to one request, not to a
		// registry shared with other plugins; listed so the scan stays strict
		// rather than being loosened to let a real name through with them.
		'mhm_admin_nonce'              => 'nonce action, not storage',
		'mhm_ajax_nonce'               => 'nonce action, not storage',
		'mhm_email_preview_action'     => 'nonce action, not storage',
	);

	/**
	 * APIs whose nearby literals are registered or stored names.
	 */
	private const STORAGE_API = '/(?:set|get|delete)_transient|(?:get|update|add|delete)_option'
		. '|register_setting|\$wpdb->prefix|(?:update|get|delete|add)_user_meta'
		. '|register_post_type|register_taxonomy|wp_schedule_event|wp_next_scheduled/';

	/**
	 * @return array<string, list<string>> literal => file:line list
	 */
	private function storage_literals(): array
	{
		$root  = dirname( __DIR__, 2 );
		$found = array();

		foreach ( array( '/src', '/templates' ) as $dir ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir ) );

			foreach ( $it as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}

				$lines = preg_split( '/\R/', (string) file_get_contents( $file->getPathname() ) ) ?: array();

				foreach ( $lines as $i => $line ) {
					$trimmed = ltrim( $line );
					if ( str_starts_with( $trimmed, '*' ) || str_starts_with( $trimmed, '//' ) ) {
						continue;
					}

					if ( ! preg_match_all( '/[\'"](mhm_(?!rentiva)[a-z0-9_]*)/i', $line, $m ) ) {
						continue;
					}

					$window = implode( "\n", array_slice( $lines, max( 0, $i - 4 ), 9 ) );
					if ( ! preg_match( self::STORAGE_API, $window ) ) {
						continue;
					}

					foreach ( $m[1] as $literal ) {
						$found[ $literal ][] = str_replace( $root . DIRECTORY_SEPARATOR, '', $file->getPathname() )
							. ':' . ( $i + 1 );
					}
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
		$this->assertGreaterThan(
			15,
			count( $this->storage_literals() ),
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
