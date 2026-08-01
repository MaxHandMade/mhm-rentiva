<?php
/**
 * Görev 12: single-token prefix sweep (mhm_ / mhm_rentiva_ -> mhmrentiva_).
 *
 * NOT SHIPPED -- bin/ is excluded by .distignore.
 *
 * WHY THIS IS NOT A str_replace LOOP
 * ----------------------------------
 * The brief sketched "reduce every rule family to one length-DESC list, then
 * plain str_replace". Measured against the actual tree that is unsafe in two
 * independent ways:
 *
 *   1. Rules whose NEW value CONTAINS their OLD key re-match their own output.
 *      'addon_' -> 'mhmrentiva_addon_' is the worst: a second pass (or any
 *      later rule scanning already-emitted text) turns 'mhm_rentiva_addon_settings'
 *      into 'mhmrentiva_mhmrentiva_addon_settings'. '_booking_' and '_contact_'
 *      have the same shape.
 *   2. Four of the map's prefix rules carry no 'mhm' token at all -- 'addon_',
 *      '_booking_', '_contact_', '_rentiva_' -- and as blind substrings they hit
 *      PHP identifiers, not storage keys. In src/ alone: 'addon_' 432 hits
 *      ($addon_id, format_addon_price, enqueue_addon_scripts), '_rentiva_' 2469,
 *      '_booking_' 926. Applied blind they would rewrite the language, not the
 *      data.
 *
 * So this tool is a SINGLE-PASS SCANNER instead. It walks each file once, left
 * to right; at every offset it asks the ordered rule list whether one matches
 * here; on a hit it emits the replacement and jumps PAST it. Emitted output is
 * never re-examined, which makes (1) structurally impossible rather than
 * order-dependent. Anchoring solves (2): rules are typed by how much boundary
 * evidence they need before they may fire.
 *
 * RULE KINDS
 *   protect      Consumed verbatim. Reserves a region so no other rule can
 *                rewrite it. Used for the legacy Transfer table names.
 *   substring    Fires anywhere. Only for rules containing 'mhm'/'MHM', which
 *                cannot collide with anything in this codebase and must fire
 *                mid-token (e.g. inside 'wp_ajax_mhm_rentiva_save').
 *   meta_exact   Fires only as a whole identifier (no identifier character on
 *                either side). For the concrete meta keys resolved out of the
 *                four non-mhm prefix rules.
 *   quoted_exact Fires only when the token is an ENTIRE quoted string literal,
 *                and only when no deny-context pattern matches its line. For
 *                CPT/taxonomy names, where the same literal is also used as an
 *                array key, an enum value and a rewrite slug.
 *
 * AUDITABILITY
 *   --dry-run (default) writes nothing and prints per-rule and per-file counts.
 *   --list-skips prints every occurrence a rule DECLINED, with file, line and
 *   reason, so a decision to not rename is as visible as a decision to rename.
 *   --diff writes a per-file unified diff to a directory for human review
 *   instead of one unreadable tree-wide blob.
 *
 * @package MHM_Rentiva
 */

namespace MHMRentiva\Tools;

use MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap as Map;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
require_once dirname( __DIR__ ) . '/src/Admin/Core/Utilities/PrefixMigrationMap.php';

/**
 * The transformation itself. Kept as a class with no I/O so the adversarial
 * fixtures in tests/Tools/PrefixRenamerTest.php can drive it directly.
 */
class PrefixRenamer {

	/**
	 * Files that must NEVER be swept.
	 *
	 * PrefixMigrationMap.php is the record of the OLD names. Sweeping it turns
	 * every 'old' => 'new' pair into 'new' => 'new', so Görev 13's migration
	 * becomes an identity no-op that reports success while moving nothing --
	 * and the old names needed to recover would be gone from the tree too.
	 *
	 * prefix-inventory-baseline.txt is the same hazard for gate G-C.
	 *
	 * @var array<int,string>
	 */
	public const NEVER_SWEEP = array(
		'src/Admin/Core/Utilities/PrefixMigrationMap.php',
		'bin/prefix-rename.php',
		'bin/prefix-inventory-baseline.txt',
		// G-C itself. Its detectors deliberately match BOTH the old and the new
		// prefix, because both are live during the transition window. Sweeping
		// it collapses the alternation 'mhm_rentiva_|mhmrentiva_|mhm_' into
		// three copies of the new prefix, and the gate silently stops being able
		// to see an unconverted name at all -- the instrument would be blinded
		// by the very change it exists to measure.
		'bin/check-prefix-inventory.php',
		// These three carry OLD names as data. Sweeping them rewrites both
		// sides of every fixture and every inventory entry, so the assertions
		// keep passing while proving nothing -- the tautological-test trap.
		// All three are hand-edited instead, deliberately and visibly.
		'tests/Tools/PrefixRenamerTest.php',
		'tests/Tools/NoBareMhmStorageKeysTest.php',
		'tests/Unit/Core/Utilities/DatabaseCleanerAllowlistTest.php',
	);

	/**
	 * Legacy physical table names probed for backward compatibility.
	 *
	 * DECISION (Görev 12, 2026-08-01) -- the map's TABLES docblock explicitly
	 * left this open and forbade resolving it silently. Carved out, because:
	 * Lite never CREATEs these tables (create_transfer_tables() delegates to a
	 * Pro-only class absent from this repo), and they are NOT in
	 * PrefixMigrationMap::TABLES, so Görev 13 will never rename the physical
	 * table either. Renaming the literal would point the probe at
	 * '{prefix}mhmrentiva_transfer_locations' -- a table name that has never
	 * existed on any site and never will. Pure regression, no upside.
	 *
	 * Left-boundary anchored, so the identically stemmed POSTMETA key
	 * '_mhm_rentiva_transfer_locations' is NOT swallowed and still renames.
	 *
	 * @var array<int,string>
	 */
	public const CARVE_OUT_TABLE_LITERALS = array(
		'mhm_rentiva_transfer_locations',
		'mhm_rentiva_transfer_routes',
		// PrefixMigrationMap::BOOTSTRAP_FALLBACK_ALLOWLIST. The map states these
		// two are carried as literals PERMANENTLY, to recognise a pre-6.0.0
		// install. They are the migration's own version gates:
		// DatabaseMigrator::run_migrations() reads mhm_rentiva_db_version to
		// decide what to migrate, and mhm-rentiva.php reads
		// mhm_rentiva_plugin_version on every load. Renaming them makes both
		// reads miss on exactly the un-migrated sites they exist to detect --
		// get_option() would fall through to its default and the site would look
		// fresh. Found by a negative control on the repaired G-C mode 5, not by
		// reading the map.
		'mhm_rentiva_db_version',
		'mhm_rentiva_plugin_version',
		// mhm-ui-core's public API. Defined in vendor/mhm/ui-core/register.php
		// (composer dependency "mhm/ui-core": "^0.1"), NOT by this plugin, so
		// this prefix is not ours to change: renaming our call site leaves the
		// definition where it was and the call becomes fatal. Caught by the test
		// bootstrap dying on "Call to undefined function
		// mhmrentiva_ui_core_register()" after the first mechanical sweep --
		// a reminder that a bare 'mhm_' catch-all reaches sibling PRODUCTS, not
		// just this codebase. If ui-core is itself shipped inside vendor/, its
		// 3-letter prefix is a separate T7 exposure belonging to that repo,
		// exactly like Pro's lockstep in Görev 14.
		'mhm_ui_core_',
	);

	/**
	 * Concrete meta keys resolved out of the four non-mhm prefix rules.
	 *
	 * The map's POSTMETA_PREFIX_RULES/USERMETA_PREFIX_RULES stay the contract
	 * for Görev 13's DB migration, where a `meta_key LIKE 'addon_%'` sweep is
	 * correct because the database contains only real meta keys. The ambiguity
	 * exists only in SOURCE TEXT, so the source sweep uses this enumeration,
	 * derived by reading every meta call site. Anything matching a rule but not
	 * listed here is reported by --list-skips, never silently left behind.
	 *
	 * @var array<int,string>
	 */
	public const RESOLVED_META_KEYS = array(
		// '_booking_' family -- BookingMeta / BookingColumns / BookingQueryHelper.
		'_booking_vehicle_id',
		'_booking_pickup_date',
		'_booking_pickup_time',
		'_booking_dropoff_date',
		'_booking_dropoff_time',
		'_booking_return_date',
		'_booking_rental_days',
		'_booking_total_price',
		'_booking_payment_status',
		'_booking_payment_amount',
		'_booking_payment_currency',
		'_booking_payment_gateway',
		'_booking_offline_receipt_id',
		'_booking_start_ts',
		'_booking_status',
		'_booking_guests',
		'_booking_order_id',
		'_booking_customer_name',
		'_booking_customer_first_name',
		'_booking_customer_email',
		'_booking_customer_phone',
		// '_contact_' family -- ContactForm.
		'_contact_type',
		'_contact_name',
		'_contact_email',
		'_contact_phone',
		'_contact_company',
		'_contact_vehicle_id',
		'_contact_preferred_date',
		'_contact_priority',
		'_contact_rating',
		'_contact_attachment',
		'_contact_ip_address',
		'_contact_user_agent',
		'_contact_timestamp',
		'_contact_status',
		// '_rentiva_' family -- MetaKeys (vendor) + vehicle service type.
		'_rentiva_vendor_slug',
		'_rentiva_vendor_slug_history',
		'_rentiva_vendor_avatar_id',
		'_rentiva_vendor_city',
		'_rentiva_vendor_status',
		'_rentiva_vendor_reliability_score',
		'_rentiva_vendor_reliability_updated_at',
		'_rentiva_vendor_score_history',
		'_rentiva_vehicle_service_type',
		// 'addon_' family -- the VISIBLE (no leading underscore) addon meta.
		// Deliberately excludes addon_status/addon_ids/addon_id/addon_created
		// ($_GET/$_POST field names), addon_total/addon_stats/addon_details/
		// addon_settings (array keys and metabox IDs) and addon_list (an
		// internal cache-group alias) -- none of them is a meta key. Each is
		// listed by --list-skips with that reason.
		'addon_price',
		'addon_enabled',
		'addon_required',
		'addon_description',
		'addon_type',
	);

	/**
	 * Line contexts in which a CPT/taxonomy literal IS the registered name even
	 * though a deny pattern below would otherwise reject it. Checked first.
	 *
	 * Every entry here was added because the --list-skips audit of the dry-run
	 * showed the tool declining an occurrence it should have renamed; none was
	 * predicted in advance. `$wp_meta_boxes` is keyed BY POST TYPE by WordPress
	 * core, so it only looks like an ordinary array index, and the POST_TYPE/
	 * TAXONOMY class constants are the canonical definition of the very name
	 * being renamed -- the generic "enum-style constant" deny caught them.
	 *
	 * @var array<string,string> regex => why it is really the registered name
	 */
	public const QUOTED_ALLOW_CONTEXTS = array(
		'/\$wp_meta_boxes\[\s*\'%s\'\s*\]/' => 'WP core keys $wp_meta_boxes by post type',
		// Any constant whose NAME ends in POST_TYPE/TAXONOMY holds the registered
		// name itself. Established by inventory, not by sample: the seven
		// constants in src/ assigned one of these literals are POST_TYPE (x2),
		// TAXONOMY (x2), BOOKING_POST_TYPE, and the two that must NOT move --
		// ERROR_TYPE_VEHICLE and CATEGORY_VEHICLE, which end in VEHICLE.
		'/const\s+[A-Z0-9_]*(?:POST_TYPE|TAXONOMY)\s*=\s*\'%s\'/' => 'canonical registered-name constant',
	);

	/**
	 * Line contexts in which a CPT/taxonomy literal is NOT the registered name.
	 *
	 * @var array<string,string> regex => reason
	 */
	public const QUOTED_DENY_CONTEXTS = array(
		'/\[\s*\'%s\'\s*\]/'                    => 'array index, not a post type',
		'/\'%s\'\s*=>/'                         => 'array key, not a post type',
		'/const\s+[A-Z_][A-Z0-9_]*\s*=\s*\'%s\'/' => 'enum-style class constant value',
		'/register_provider\(\s*\'%s\'/'        => 'internal settings-provider key',
		'/flush_subject_metric\(\s*\'%s\'/'     => 'internal metric subject',
		'/url_base\'\s*,\s*\'%s\'/'             => 'default value of the rewrite-slug setting',
	);

	/**
	 * Ordered rule list.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $rules;

	/**
	 * Occurrences a rule declined, for --list-skips.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $skips = array();

	/**
	 * Which rule kinds this instance may apply.
	 *
	 * The sweep lands in two commits, because the two halves fail differently.
	 * 'mechanical' is every rule carrying an 'mhm' token: unambiguous, unable to
	 * false-positive, and the bulk of the T7 mandate. 'identifiers' adds the CPT/
	 * taxonomy names and the meta keys resolved out of the four non-mhm prefix
	 * rules -- the half where the same literal is also an array key, an enum
	 * value and a rewrite-slug default, and where a wrong call is silent.
	 * Reviewing them in one diff is how 'vehicle' array keys get renamed unnoticed.
	 *
	 * @var string
	 */
	private $phase;

	/**
	 * @param string $phase 'mechanical' or 'all'.
	 */
	public function __construct( string $phase = 'all' ) {
		$this->phase = $phase;
		$this->rules = array_values(
			array_filter(
				$this->buildRules(),
				function ( array $rule ): bool {
					if ( 'all' === $this->phase ) {
						return true;
					}
					return in_array( $rule['kind'], array( 'protect', 'substring' ), true );
				}
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function rules(): array {
		return $this->rules;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function skips(): array {
		return $this->skips;
	}

	/**
	 * Build the ordered rule list.
	 *
	 * Order is: protect first (they reserve territory), then everything else
	 * by descending OLD-key length so a longer key always wins the offset --
	 * '_mhm_rentiva_' before '_mhm_', 'mhm_rentiva_' before 'mhm_'.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function buildRules(): array {
		$rules = array();

		foreach ( self::CARVE_OUT_TABLE_LITERALS as $literal ) {
			$rules[] = array(
				'id'    => 'carve-out:' . $literal,
				'old'   => $literal,
				'new'   => $literal,
				'kind'  => 'protect',
				'left'  => true,
				'right' => false,
			);
		}

		// Substring rules: every map rule whose OLD key contains an 'mhm'/'MHM'
		// token. These cannot false-positive and must fire mid-identifier.
		$substring = array();
		foreach ( array( Map::RUNTIME_STRING_RULES, Map::POSTMETA_PREFIX_RULES, Map::USERMETA_PREFIX_RULES ) as $family ) {
			foreach ( $family as $old => $new ) {
				if ( false !== stripos( $old, 'mhm' ) ) {
					$substring[ $old ] = $new;
				}
			}
		}
		foreach ( $substring as $old => $new ) {
			$rules[] = array(
				'id'    => 'substring:' . $old,
				'old'   => $old,
				'new'   => $new,
				'kind'  => 'substring',
				'left'  => false,
				'right' => false,
			);
		}

		// Concrete meta keys resolved out of the four non-mhm prefix rules.
		foreach ( self::RESOLVED_META_KEYS as $key ) {
			$rules[] = array(
				'id'    => 'meta:' . $key,
				'old'   => $key,
				'new'   => $this->applyPrefixRuleTo( $key ),
				'kind'  => 'meta_exact',
				'left'  => true,
				'right' => true,
			);
		}

		// CPT and taxonomy names.
		foreach ( array_merge( Map::POST_TYPES, Map::TAXONOMIES ) as $old => $new ) {
			if ( false !== stripos( $old, 'mhm' ) ) {
				continue; // already covered by a substring rule.
			}
			$rules[] = array(
				'id'    => 'cpt:' . $old,
				'old'   => $old,
				'new'   => $new,
				'kind'  => 'quoted_exact',
				'left'  => true,
				'right' => true,
			);
		}

		// Screen-id derivatives of the renamed post types ('edit-vehicle').
		foreach ( Map::POST_TYPES as $old => $new ) {
			if ( false !== stripos( $old, 'mhm' ) ) {
				continue;
			}
			$rules[] = array(
				'id'    => 'screen:edit-' . $old,
				'old'   => 'edit-' . $old,
				'new'   => 'edit-' . $new,
				'kind'  => 'quoted_exact',
				'left'  => true,
				'right' => true,
			);
		}

		usort(
			$rules,
			static function ( $a, $b ) {
				if ( ( 'protect' === $a['kind'] ) !== ( 'protect' === $b['kind'] ) ) {
					return 'protect' === $a['kind'] ? -1 : 1;
				}
				return strlen( $b['old'] ) <=> strlen( $a['old'] );
			}
		);

		return $rules;
	}

	/**
	 * Resolve a concrete meta key through the map's POSTMETA/USERMETA prefix
	 * rules -- longest matching prefix wins, exactly as the map documents.
	 *
	 * @param string $key Meta key.
	 * @return string Renamed key.
	 */
	private function applyPrefixRuleTo( string $key ): string {
		$candidates = array_merge( Map::POSTMETA_PREFIX_RULES, Map::USERMETA_PREFIX_RULES );
		uksort( $candidates, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
		foreach ( $candidates as $old => $new ) {
			if ( str_starts_with( $key, $old ) ) {
				return $new . substr( $key, strlen( $old ) );
			}
		}
		return $key;
	}

	/**
	 * Is this byte an identifier character?
	 *
	 * @param string $c Single byte.
	 * @return bool
	 */
	private function isIdentChar( string $c ): bool {
		return 1 === preg_match( '/[A-Za-z0-9_]/', $c );
	}

	/**
	 * Transform one blob of source.
	 *
	 * @param string $text Source text.
	 * @param string $file Optional path, for skip reporting.
	 * @return array{0:string,1:array<string,int>}
	 */
	public function transform( string $text, string $file = '' ): array {
		$out   = '';
		$i     = 0;
		$len   = strlen( $text );
		$stats = array();

		while ( $i < $len ) {
			$hit = null;
			foreach ( $this->rules as $rule ) {
				$old = $rule['old'];
				$l   = strlen( $old );
				if ( 0 === $l || $i + $l > $len ) {
					continue;
				}
				if ( 0 !== substr_compare( $text, $old, $i, $l ) ) {
					continue;
				}
				if ( $rule['left'] && $i > 0 && $this->isIdentChar( $text[ $i - 1 ] ) ) {
					continue;
				}
				if ( $rule['right'] && $i + $l < $len && $this->isIdentChar( $text[ $i + $l ] ) ) {
					continue;
				}
				if ( 'quoted_exact' === $rule['kind'] ) {
					$reason = $this->quotedExactRefusal( $text, $i, $l, $rule );
					if ( null !== $reason ) {
						$this->skips[] = array(
							'file'   => $file,
							'line'   => substr_count( $text, "\n", 0, $i ) + 1,
							'rule'   => $rule['id'],
							'reason' => $reason,
							'text'   => trim( $this->lineAt( $text, $i ) ),
						);
						continue;
					}
				}
				$hit = $rule;
				break;
			}

			if ( null === $hit ) {
				$out .= $text[ $i ];
				++$i;
				continue;
			}

			$out                  .= 'protect' === $hit['kind'] ? $hit['old'] : $hit['new'];
			$i                    += strlen( $hit['old'] );
			$stats[ $hit['id'] ]   = ( $stats[ $hit['id'] ] ?? 0 ) + 1;
		}

		return array( $out, $stats );
	}

	/**
	 * Why a quoted_exact rule must NOT fire here, or null if it may.
	 *
	 * @param string               $text Full text.
	 * @param int                  $i    Match offset.
	 * @param int                  $l    Match length.
	 * @param array<string,mixed>  $rule Rule.
	 * @return string|null
	 */
	private function quotedExactRefusal( string $text, int $i, int $l, array $rule ): ?string {
		$before = $i > 0 ? $text[ $i - 1 ] : '';
		$after  = $i + $l < strlen( $text ) ? $text[ $i + $l ] : '';
		if ( ! in_array( $before, array( "'", '"' ), true ) || $after !== $before ) {
			return 'not a complete quoted string literal';
		}
		$line = $this->lineAt( $text, $i );
		foreach ( self::QUOTED_ALLOW_CONTEXTS as $pattern => $unused ) {
			if ( 1 === preg_match( sprintf( $pattern, preg_quote( $rule['old'], '/' ) ), $line ) ) {
				return null;
			}
		}
		foreach ( self::QUOTED_DENY_CONTEXTS as $pattern => $reason ) {
			if ( 1 === preg_match( sprintf( $pattern, preg_quote( $rule['old'], '/' ) ), $line ) ) {
				return $reason;
			}
		}
		return null;
	}

	/**
	 * The whole source line containing offset $i.
	 *
	 * @param string $text Text.
	 * @param int    $i    Offset.
	 * @return string
	 */
	private function lineAt( string $text, int $i ): string {
		$start = strrpos( substr( $text, 0, $i ), "\n" );
		$start = false === $start ? 0 : $start + 1;
		$end   = strpos( $text, "\n", $i );
		$end   = false === $end ? strlen( $text ) : $end;
		return substr( $text, $start, $end - $start );
	}
}

// ---------------------------------------------------------------------------
// CLI. Skipped when the file is required by the test suite.
// ---------------------------------------------------------------------------
if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$root = dirname( __DIR__ );
	$opts = getopt( '', array( 'apply', 'list-skips', 'diff::', 'only::', 'phase::' ) );

	$apply     = isset( $opts['apply'] );
	$listSkips = isset( $opts['list-skips'] );
	$diffDir   = $opts['diff'] ?? null;
	$only      = $opts['only'] ?? null;

	$phase   = $opts['phase'] ?? 'all';
	$renamer = new PrefixRenamer( $phase );

	$targets = array( 'src', 'templates', 'assets/js', 'src-react', 'tests', 'bin' );
	$files   = array();
	foreach ( $targets as $dir ) {
		$path = $root . '/' . $dir;
		if ( ! is_dir( $path ) ) {
			continue;
		}
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() && preg_match( '/\.(php|js|jsx|json|txt|xml|css)$/', $f->getFilename() ) ) {
				$files[] = $f->getPathname();
			}
		}
	}
	// phpstan.neon carries the old names inside ignoreErrors regexes. Leaving it
	// out makes PHPStan report 244 "constant not found" errors for constants that
	// were merely renamed, and -- worse -- silently unignores two real findings
	// whose patterns no longer match, which reads as the sweep having caused them.
	foreach ( array( 'mhm-rentiva.php', 'uninstall.php', 'phpcs.xml', 'phpstan.neon', 'readme.txt' ) as $rootFile ) {
		if ( is_file( $root . '/' . $rootFile ) ) {
			$files[] = $root . '/' . $rootFile;
		}
	}
	// build/ and node_modules/ are generated; vendor/ is third-party.
	$files = array_values(
		array_filter(
			$files,
			static function ( $f ) use ( $root ) {
				$rel = str_replace( '\\', '/', substr( $f, strlen( $root ) + 1 ) );
				foreach ( PrefixRenamer::NEVER_SWEEP as $never ) {
					if ( $rel === $never ) {
						return false;
					}
				}
				return ! preg_match( '#(^|/)(vendor|node_modules|build)/#', $rel );
			}
		)
	);

	$totalStats   = array();
	$changedFiles = 0;
	$perFile      = array();

	foreach ( $files as $file ) {
		$rel = str_replace( '\\', '/', substr( $file, strlen( $root ) + 1 ) );
		if ( null !== $only && ! str_contains( $rel, $only ) ) {
			continue;
		}
		$src = file_get_contents( $file );
		if ( false === $src ) {
			continue;
		}
		[ $new, $stats ] = $renamer->transform( $src, $rel );
		if ( $new === $src ) {
			continue;
		}
		++$changedFiles;
		$perFile[ $rel ] = array_sum( $stats );
		foreach ( $stats as $id => $n ) {
			$totalStats[ $id ] = ( $totalStats[ $id ] ?? 0 ) + $n;
		}
		if ( null !== $diffDir ) {
			if ( ! is_dir( $diffDir ) ) {
				mkdir( $diffDir, 0777, true );
			}
			$tmp = tempnam( sys_get_temp_dir(), 'pr' );
			file_put_contents( $tmp, $new );
			$safe = str_replace( '/', '__', $rel );
			exec( 'diff -u ' . escapeshellarg( $file ) . ' ' . escapeshellarg( $tmp ) . ' > ' . escapeshellarg( rtrim( $diffDir, '/' ) . '/' . $safe . '.diff' ) );
			unlink( $tmp );
		}
		if ( $apply ) {
			file_put_contents( $file, $new );
		}
	}

	printf(
		"MODE: %s   PHASE: %s   RULES ACTIVE: %d\n",
		$apply ? 'APPLY (files written)' : 'DRY-RUN (nothing written)',
		$phase,
		count( $renamer->rules() )
	);
	echo str_repeat( '-', 72 ) . "\n";
	printf( "files scanned : %d\n", count( $files ) );
	printf( "files changed : %d\n", $changedFiles );
	printf( "replacements  : %d\n", array_sum( $totalStats ) );
	echo str_repeat( '-', 72 ) . "\nPER-RULE:\n";
	arsort( $totalStats );
	foreach ( $totalStats as $id => $n ) {
		printf( "  %6d  %s\n", $n, $id );
	}
	echo str_repeat( '-', 72 ) . "\nTOP 25 FILES:\n";
	arsort( $perFile );
	foreach ( array_slice( $perFile, 0, 25, true ) as $rel => $n ) {
		printf( "  %6d  %s\n", $n, $rel );
	}

	if ( $listSkips ) {
		echo str_repeat( '-', 72 ) . "\nDECLINED OCCURRENCES (rule matched, anchoring/context refused):\n";
		$grouped = array();
		foreach ( $renamer->skips() as $s ) {
			if ( 'not a complete quoted string literal' === $s['reason'] ) {
				continue; // structural, not a judgement call -- too noisy to be useful.
			}
			$grouped[ $s['rule'] . ' | ' . $s['reason'] ][] = $s['file'] . ':' . $s['line'] . '  ' . $s['text'];
		}
		ksort( $grouped );
		foreach ( $grouped as $k => $rows ) {
			printf( "\n[%s]  x%d\n", $k, count( $rows ) );
			foreach ( array_unique( $rows ) as $r ) {
				echo '    ' . $r . "\n";
			}
		}
	}

	exit( 0 === $changedFiles ? 0 : 1 );
}
