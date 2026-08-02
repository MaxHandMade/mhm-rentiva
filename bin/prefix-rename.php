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
		// Seeds rows in their PRE-rename spelling on purpose: it asserts that a
		// foreign 'vehicle' post survives uninstall while one of OURS in the old
		// spelling still goes. Sweeping the fixtures would erase the distinction
		// the test exists to make.
		'tests/Unit/Utilities/UninstallForeignPostSafetyTest.php',
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
		// 🔴 Elementor WIDGET NAMES. Elementor persists get_name() as "widgetType"
		// inside every page's _elementor_data postmeta, so these live in saved
		// content exactly as shortcode tags and block namespaces do -- and those
		// are protected by the constraints for precisely this reason. Renaming
		// them makes Elementor render "missing widget" on every page and template
		// already built with one, and _elementor_data belongs to no migration
		// family, so Görev 13 will never rewrite the stored value either.
		//
		// The generic 'mhm_rentiva_' rule reached them by accident: the 14 sibling
		// widgets in the same directory return 'rv-*' names and were untouched, so
		// these three were never a decision anyone made.
		'mhm_rentiva_featured_vehicles',
		'mhm_rentiva_vehicles_grid',
		'mhm_rentiva_vehicles_list',
	);

	/**
	 * Concrete meta keys resolved out of the four non-mhm prefix rules.
	 *
	 * The map's POSTMETA_PREFIX_RULES/USERMETA_PREFIX_RULES stay the contract
	 * for Görev 13's DB migration, where a `meta_key LIKE 'addon_%'` sweep is
	 * correct because the database contains only real meta keys. The ambiguity
	 * exists only in SOURCE TEXT, so the source sweep uses this enumeration,
	 * derived by reading every meta call site.
	 *
	 * ⚠️ THIS LIST HAS NO SAFETY NET, and an earlier version of this comment
	 * claimed it did ("anything matching a rule but not listed here is reported
	 * by --list-skips"). It is not. buildRules() only promotes map rules that
	 * contain 'mhm', so '_booking_', '_contact_', '_rentiva_' and 'addon_' never
	 * become rules at all: an unlisted '_booking_xyz' matches nothing, produces
	 * no skip entry, and is invisible to modes 3, 4a and 4b alike.
	 *
	 * The enumeration IS complete -- two independent reviewers verified it by
	 * grep -- but that is a fact about today's tree, not a property the tooling
	 * maintains. A meta key added later under one of those four prefixes will be
	 * missed silently, and only a fresh manual sweep would find it.
	 *
	 * @see PrefixMigrationMap::POSTMETA_PREFIX_RULES
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
	/**
	 * Contexts that are decisive enough to beat a deny pattern.
	 *
	 * Both entries LOOK like something they are not: WordPress keys the global
	 * $wp_meta_boxes by post type, so a post-type usage is spelled exactly like
	 * an ordinary array index; and a class constant named POST_TYPE holds the
	 * registered name while being spelled exactly like the ERROR_TYPE_VEHICLE
	 * enum that must not move. Checked before the deny list for that reason.
	 *
	 * @var array<string,string>
	 */
	public const QUOTED_OVERRIDE_CONTEXTS = array(
		'/\$wp_meta_boxes\[\s*\'%s\'\s*\]/'                       => 'WP core keys $wp_meta_boxes by post type',
		'/const\s+[A-Z0-9_]*(?:POST_TYPE|TAXONOMY)\s*=\s*\'%s\'/' => 'canonical registered-name constant',
	);

	public const QUOTED_ALLOW_CONTEXTS = array(
		// --- the literal is being REGISTERED ---
		'/register_post_type\(\s*\'%s\'/'                          => 'register_post_type()',
		'/register_taxonomy\(\s*\'%s\'/'                           => 'register_taxonomy()',

		// --- the literal is being used AS a post type / taxonomy ---
		'/\'post_type\'\s*=>\s*\'%s\'/'                            => "'post_type' query arg",
		'/\'taxonomy\'\s*=>\s*\'%s\'/'                             => "'taxonomy' query arg",
		'/post_type\s*(?:===?|!==?)\s*\'%s\'/'                     => 'post_type comparison',
		'/\'%s\'\s*(?:===?|!==?)\s*\$[A-Za-z_][A-Za-z0-9_]*(?:->)?(?:post_type)?/' => 'reversed post_type comparison',
		// The '$' is OPTIONAL because these same two globals exist in BOTH
		// languages: PHP's $typenow/$pagenow and the JS globals WordPress prints
		// into every admin screen. Every other allow pattern here is PHP-shaped
		// ('self::', '$post->', "'post_type' =>"), which made the whole
		// quoted_exact family structurally incapable of firing in a .js file --
		// under deny-by-default that is a silent refusal, and it shipped three
		// dead admin scripts.
		'/\$?typenow\s*(?:===?|!==?)\s*\'%s\'/'                    => 'typenow comparison (PHP or JS)',
		'/\$?pagenow\s*(?:===?|!==?)\s*\'%s\'/'                    => 'pagenow comparison (PHP or JS)',
		'/searchParams\.(?:delete|get|set|has)\(\s*\'%s\'/'        => 'JS URLSearchParams query-var access',
		'/get_post_type\([^)]*\)\s*(?:===?|!==?)\s*\'%s\'/'        => 'get_post_type() comparison',
		'/(?:is_singular|is_post_type_archive|get_post_type_archive_link|wp_count_posts|post_type_exists|taxonomy_exists|get_post_type_object|get_taxonomy)\(\s*\'%s\'/' => 'post-type/taxonomy API call',
		'/(?:remove_meta_box|add_meta_box)\([^;]*\'%s\'/'          => 'meta-box screen argument',
		'/\$wp_meta_boxes\[\s*\'%s\'\s*\]/'                        => 'WP core keys $wp_meta_boxes by post type',
		'/(?:get_terms|wp_get_post_terms|wp_set_object_terms|has_term|get_the_terms|wp_dropdown_categories)\([^;]*\'%s\'/' => 'taxonomy API call',
		'/\'%s\'\s*,\s*\'objects\'/'                               => 'get_object_taxonomies()-style call',
		'/screen->(?:id|post_type)\s*(?:===?|!==?)\s*\'%s\'/'      => 'admin screen comparison',
		'/(?:in_array|array)\([^;]*post_type[^;]*\'%s\'/'          => 'post-type membership list',

		// --- shapes found by auditing the residue this design deliberately
		// --- produces. Each was added only after reading the call site.
		'/post_type\s*=\s*\'%s\'/'                                 => 'raw SQL post_type predicate',
		'/post_types?\s*=\s*array\([^;]*\'%s\'/'                   => 'post-type list assignment',
		'/get\(\s*\'post_type\'\s*\)[^;]*(?:===?|!==?)\s*\'%s\'/'  => 'WP_Query->get(post_type) comparison',
		'/term_exists\([^;]*\'%s\'/'                               => 'term_exists() taxonomy argument',
		'/get_post_type\(\)[\s\S]{0,160}return\s+\'%s\'\s*;/'      => 'get_post_type() override returning the name',
		'/get_taxonomy\w*\(\)[\s\S]{0,160}return\s+\'%s\'\s*;/'    => 'get_taxonomy() override returning the name',
		'/\'post_type\'\s*=>\s*(?:array\()?[^;]{0,200}\'%s\'/'     => "'post_type' arg spanning lines",
		'/\'taxonomy\'\s*=>\s*(?:array\()?[^;]{0,200}\'%s\'/'      => "'taxonomy' arg spanning lines",
		'/(?:post_types|POST_TYPES)[\s\S]{0,200}\'%s\'/'           => 'post-type collection',
		'/register_post_meta\(\s*\'%s\'/'                          => 'register_post_meta() post-type argument',
		'/taxonomy\s*=\s*\'%s\'/'                                  => 'raw SQL taxonomy predicate',
		'/name="post_type"\s+value="%s"/'                          => 'admin form post_type field',
		// The dominant shape in the reporting/dashboard layer: the post type is a
		// PLACEHOLDER in the SQL and arrives as a positional $wpdb->prepare()
		// argument, so the only evidence is the `post_type = %s` a few lines up.
		// NOTE the doubled %%s: that one is the literal SQL placeholder and must
		// survive sprintf(), which fills the single %s with the rule's name.
		'/post_type\s*(?:=|!=|<>)\s*%%s[\s\S]{0,400}\'%s\'/'       => 'post_type placeholder + prepare() argument',
		'/post_type\s+IN\s*\([^)]*%%s[\s\S]{0,400}\'%s\'/'         => 'post_type IN placeholder + prepare() argument',
	);

	/**
	 * Line contexts in which a CPT/taxonomy literal is NOT the registered name.
	 *
	 * @var array<string,string> regex => reason
	 */
	public const QUOTED_DENY_CONTEXTS = array(
		'/\[\s*\'%s\'\s*\]/'                    => 'array index, not a post type',
		'/\'%s\'\s*=>/'                         => 'array key, not a post type',
		// Excludes constants NAMED *POST_TYPE/*TAXONOMY, which hold the
		// registered name itself and are handled by the allow list.
		'/const\s+(?![A-Z0-9_]*(?:POST_TYPE|TAXONOMY)\s*=)[A-Z_][A-Z0-9_]*\s*=\s*\'%s\'/' => 'enum-style class constant value',
		'/\?\?\s*\'%s\'|\?:\s*\'%s\'/'          => 'null-coalesce/elvis fallback: a DEFAULT VALUE, not a registration',
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
		$substring = array(
			// 🔴 NOT in PrefixMigrationMap, and it has to be here.
			//
			// The map carries 'mhm_rentiva_' and 'mhm_rentiva/' but not the bare
			// stem. Real code uses the bare stem as a SQL LIKE prefix
			// ('mhm_rentiva%' in uninstall.php and DatabaseCleaner, where the next
			// character is '%' and not '_') and as an object-cache group name. On
			// those, the two mapped rules do not match, the bare 'mhm_' catch-all
			// fires instead, and 'mhm_rentiva%' becomes 'mhmrentiva%' --
			// a prefix that matches no option on any site, silently turning
			// uninstall and the option cleanup into no-ops.
			//
			// This is a SWEEP rule, not a map entry: it changes no stored key, it
			// only spells the same prefix correctly. Length-DESC ordering puts it
			// after 'mhm_rentiva_'/'mhm_rentiva/' (12) and before 'mhm_' (4), so
			// it can neither pre-empt the specific rules nor be pre-empted by the
			// catch-all.
			'mhm_rentiva'  => 'mhmrentiva',
			// Same shape with the hidden-meta leading underscore: '_mhm_rentiva%'
			// and '_transient_mhm_rentiva%'. Without this, '_mhm_' wins the offset
			// and yields '_mhmrentiva%'.
			'_mhm_rentiva' => '_mhmrentiva',
		);
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

		// 🔴 Post types and taxonomies embedded in admin URL QUERY STRINGS:
		// 'edit.php?post_type=vehicle', 'edit-tags.php?taxonomy=vehicle_category'.
		//
		// The quoted_exact rules cannot see these -- the name is a fragment of a
		// longer string, never a complete literal -- and --list-skips filtered the
		// refusal out as structural noise, so they were invisible in BOTH
		// directions. They only surfaced by clicking the admin menu in a browser
		// and noticing every Rentiva menu item still pointed at the old post type,
		// i.e. at screens that no longer exist. 25 occurrences across 13 files.
		//
		// Safe as plain substrings: 'post_type=' / 'taxonomy=' followed by the
		// exact old name is unambiguous -- it is a WordPress admin query var and
		// nothing else in this tree spells that.
		foreach ( array_merge( Map::POST_TYPES, Map::TAXONOMIES ) as $old => $new ) {
			if ( false !== stripos( $old, 'mhm' ) ) {
				continue; // already covered by a substring rule.
			}
			$param = isset( Map::POST_TYPES[ $old ] ) ? 'post_type' : 'taxonomy';
			$rules[] = array(
				'id'    => 'queryvar:' . $param . '=' . $old,
				'old'   => $param . '=' . $old,
				'new'   => $param . '=' . $new,
				'kind'  => 'substring',
				'left'  => false,
				'right' => true,
			);
		}

		// 🔴 WordPress DYNAMIC HOOK names that embed the post type or taxonomy:
		// manage_{$post_type}_posts_columns, save_post_{$post_type},
		// add_meta_boxes_{$post_type}, manage_edit-{$post_type}_sortable_columns.
		//
		// Invisible to every other rule for the same reason as the query vars --
		// the name is a fragment, never a whole literal. And invisible to the
		// TESTS too, because a column callback that is never called does not fail,
		// it just renders nothing. Found by opening the bookings list in a browser
		// and seeing it had lost every custom column except Title and Date.
		// 25 occurrences.
		//
		// No right-anchor: the hook continues with '_posts_columns' etc., so the
		// next character is legitimately an identifier character. Correctness
		// rests on length-DESC ordering instead -- 'manage_vehicle_booking' (22)
		// is tried before 'manage_vehicle' (14), so the shorter post type can
		// never claim the longer one's hook.
		// WordPress emits `post-type-{$post_type}` and `edit-{$post_type}` as BODY
		// CLASSES and screen ids. CSS selectors and JS screen guards key on them,
		// and neither is a quoted literal a quoted_exact rule could ever see -- in
		// a .css file there is no PHP syntax for the allow patterns to match at
		// all. 25 occurrences across the stylesheets alone; every one of them a
		// rule that silently stops applying the moment the post type moves.
		$hookPrefixes = array(
			'manage_',
			'manage_edit-',
			'save_post_',
			'add_meta_boxes_',
			'views_edit-',
			'bulk_actions-edit-',
			'post-type-',
			'edit-',
		);
		foreach ( array_merge( Map::POST_TYPES, Map::TAXONOMIES ) as $old => $new ) {
			if ( false !== stripos( $old, 'mhm' ) ) {
				continue;
			}
			foreach ( $hookPrefixes as $prefix ) {
				$rules[] = array(
					'id'      => 'hook:' . $prefix . $old,
					'old'     => $prefix . $old,
					'new'     => $prefix . $new,
					'kind'    => 'substring',
					'left'    => false,
					'right'   => false,
					// Body-class/screen-id prefixes only: see the 'lstrict' note
					// in transform(). The WP hook prefixes above end in '_' or are
					// long enough to be unambiguous; 'edit-' is not.
					'lstrict' => in_array( $prefix, array( 'post-type-', 'edit-' ), true ),
				);
			}
		}

		// Exact post-meta destinations that OVERRIDE the prefix rules.
		//
		// Owner decision 2026-08-02: '_mhm_blocked_dates' (admin JSON with notes)
		// and '_mhm_rentiva_blocked_dates' (flat date array from cancellations)
		// hold different values and must not merge, but the prefix rules send both
		// to '_mhmrentiva_blocked_dates'. Length-DESC ordering puts these exact
		// keys ahead of '_mhm_rentiva_'/'_mhm_', so the override wins the offset.
		foreach ( Map::POSTMETA_EXACT_OVERRIDES as $old => $new ) {
			$rules[] = array(
				'id'    => 'override:' . $old,
				'old'   => $old,
				'new'   => $new,
				'kind'  => 'meta_exact',
				'left'  => true,
				'right' => true,
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
	/**
	 * Byte ranges the sweep must leave alone, from in-source region markers.
	 *
	 * WHY A REGION MARKER AND NOT MORE CARVE-OUTS
	 * -------------------------------------------
	 * The transition window forces a handful of places to spell PRE-rename names
	 * on purpose: uninstall must delete rows on a site that never migrated, the
	 * gallery fallback must still find galleries stored under the old key, cron
	 * cleanup must clear hooks scheduled under every name they ever had. Written
	 * as plain literals, the next run of this tool converts them and silently
	 * removes the very compatibility they exist to provide -- which has now
	 * happened three separate times in this task.
	 *
	 * A token carve-out cannot express it, because the SAME literal is legacy in
	 * one place and live in another: 'mhm_rentiva_queue' is a deliberate old
	 * spelling inside uninstall's legacy table list, and a name that genuinely
	 * must be renamed in DatabaseMigrator's CREATE TABLE. Only the position
	 * distinguishes them, so the annotation belongs at the position.
	 *
	 * @param string $text Source.
	 * @return array<int,array{0:int,1:int}> [start, end) byte ranges.
	 */
	private function ignoredRanges( string $text ): array {
		$ranges = array();
		$offset = 0;
		while ( true ) {
			$start = strpos( $text, self::IGNORE_START, $offset );
			if ( false === $start ) {
				break;
			}
			$end = strpos( $text, self::IGNORE_END, $start );
			if ( false === $end ) {
				// Unterminated marker: protect to end of file rather than guess.
				$ranges[] = array( $start, strlen( $text ) );
				break;
			}
			$ranges[] = array( $start, $end + strlen( self::IGNORE_END ) );
			$offset   = $end + strlen( self::IGNORE_END );
		}
		return $ranges;
	}

	/**
	 * Longest permitted ignore region, in lines INCLUDING both markers.
	 *
	 * A region silences everything between its markers -- not just the literal it
	 * was opened for -- so its length is the size of the blind spot it creates.
	 * The first version of this mechanism had no cap and drifted to a 435-line
	 * region protecting three literals and an 824-line unterminated one, which
	 * together put 53% of DatabaseCleaner.php outside both the tool's and the
	 * gate's field of view. "The sweep is a fixed point" and "mode 4 is green"
	 * were then true there by construction rather than by measurement.
	 *
	 * 30 is chosen against the real shapes a region legitimately wraps: a single
	 * $wpdb statement (11-14 lines) or a literal list with its explanatory
	 * comment (the 14-entry legacy table list is the largest, at 25). It is
	 * comfortably below the smallest METHOD in any of these files, so a region
	 * can never quietly grow to cover logic.
	 */
	public const IGNORE_REGION_MAX_LINES = 30;

	/**
	 * Every ignore region in the shipped tree, with the literals it protects.
	 *
	 * Registration is the accountability half of the mechanism. A region is a
	 * claim that a pre-rename literal must survive HERE; without a registry that
	 * claim is invisible, and an earlier version of this file asserted the
	 * regions were covered by NoBareMhmStorageKeysTest::INVENTORY when they
	 * structurally could not be -- that test's pattern is
	 * `mhm_(?!rentiva)`, so a 'mhm_rentiva_*' literal cannot enter it at all,
	 * and roughly 25 protected literals appeared in no registry anywhere.
	 *
	 * PrefixRenameRegionsTest checks this in BOTH directions: every region in the
	 * source is registered, and every literal a region actually covers is
	 * declared here -- so widening a region to swallow neighbouring code fails
	 * the build instead of silently exempting it.
	 *
	 * @var array<string, array{regions:int, why:string, literals:array<int,string>}>
	 */
	public const IGNORE_REGIONS = array(
		'src/Admin/Booking/Helpers/Locker.php' => array(
			'why'      => 'FOR UPDATE must lock rows in BOTH spellings; a new-prefix-only pattern locks nothing on an un-migrated site and the caller COMMITs believing it holds the lock',
			'regions'  => array(
				0 => array( '_mhm', '_mhm_', '_mhmrentiva_%' ),
				1 => array( '_mhm' ),
			),
		),
		'src/Admin/Core/Utilities/DatabaseCleaner.php' => array(
			'why'      => 'option analysis and the backup-table probe must see pre-6.0.0 rows; backup tables keep their old name forever (no TABLES entry) and are somebody\'s only recovery copy',
			'regions'  => array(
				0 => array( 'mhm_rentiva%', 'mhmrentiva%' ),
				1 => array( 'mhm_rentiva%', 'mhmrentiva%' ),
				2 => array( 'mhm_rentiva%', 'mhmrentiva%' ),
				3 => array( 'mhm_%', 'mhmrentiva_%' ),
			),
		),
		'src/Admin/Core/Utilities/DatabaseMigrator.php' => array(
			'why'      => 'cron hooks and dead tables must be cleared under every name they have ever had; a scheduled event outlives the code that scheduled it',
			'regions'  => array(
				0 => array( '_transient_mhm_rate_limit_%', '_transient_mhm_rentiva_rate_limit_%', '_transient_mhmrentiva_rate_limit_%', '_transient_timeout_mhm_rate_limit_%', '_transient_timeout_mhm_rentiva_rate_limit_%', '_transient_timeout_mhmrentiva_rate_limit_%', 'mhm_rate_limit_', 'mhm_rentiva_rate_limit_', 'mhmrentiva_rate_limit_' ),
				1 => array( 'mhm_rentiva_send_scheduled_notifications', 'mhm_send_scheduled_notifications', 'mhmrentiva_send_scheduled_notifications' ),
				2 => array( 'mhm_notification_queue', 'mhmrentiva_notification_queue' ),
			),
		),
		'src/Admin/Frontend/Shortcodes/VehicleDetails.php' => array(
			'why'      => 'gallery fallback chain: an un-migrated site stores its gallery under a pre-rename key and would otherwise render no gallery at all',
			'regions'  => array(
				0 => array( '_mhm_gallery_images', '_mhm_rentiva_gallery', '_mhm_rentiva_gallery_images', '_mhmrentiva_gallery', '_mhmrentiva_gallery_images' ),
			),
		),
		'src/Admin/Utilities/Dashboard/DashboardPage.php' => array(
			'why'      => 'a cache this code believes it cleared but did not is a stale number on the dashboard with no way for the user to flush it',
			'regions'  => array(
				0 => array( 'mhm_rentiva_booking_report_', 'mhm_rentiva_customer_report_', 'mhm_rentiva_dashboard_recent_bookings_v4', 'mhm_rentiva_dashboard_stats', 'mhm_rentiva_recent_messages_', 'mhm_rentiva_revenue_report_', 'mhm_rentiva_vehicle_report_', 'mhm_rentiva_vlist_', 'mhm_revenue_report_' ),
			),
		),
		'src/Admin/Utilities/Uninstall/Uninstaller.php' => array(
			'why'      => 'uninstall must delete rows on a site that never ran the migration; otherwise the pre-uninstall screen shows a false count and everything is left behind permanently, with the plugin gone and no UI to clean up with',
			'regions'  => array(
				0 => array( 'mhm_rentiva' ),
				1 => array( '_mhm_rentiva%', '_mhmrentiva%', 'mhm_rentiva%', 'mhmrentiva%' ),
				2 => array( '_mhm%', 'mhmrentiva_vehicle' ),
				3 => array( '_mhm%', 'mhmrentiva_booking' ),
				4 => array( '_mhm_rentiva%', '_mhmrentiva%' ),
				5 => array( 'mhm_rentiva_send_scheduled_notifications', 'mhm_send_scheduled_notifications' ),
				6 => array( '_transient_mhm_rentiva%', '_transient_mhmrentiva%', '_transient_timeout_mhm_rentiva%', '_transient_timeout_mhmrentiva%' ),
				7 => array( '_mhm_rentiva%', '_mhmrentiva%', 'mhm_rentiva%', 'mhmrentiva%' ),
				8 => array( '_mhm%', 'mhmrentiva_vehicle' ),
				9 => array( '_mhm%', 'mhmrentiva_booking' ),
				10 => array( '_mhm_rentiva%', '_mhmrentiva%' ),
				11 => array( 'mhm_rentiva_send_scheduled_notifications', 'mhm_send_scheduled_notifications' ),
				12 => array( '_transient_mhm_rentiva%', '_transient_mhmrentiva%', '_transient_timeout_mhm_rentiva%', '_transient_timeout_mhmrentiva%' ),
				13 => array( 'mhm_backup_records', 'mhm_message_logs', 'mhm_notification_queue', 'mhm_payment_log', 'mhm_rentiva_background_jobs', 'mhm_rentiva_commission_policy', 'mhm_rentiva_key_registry', 'mhm_rentiva_ledger', 'mhm_rentiva_payout_audit', 'mhm_rentiva_queue', 'mhm_rentiva_ratings', 'mhm_rentiva_report_queue', 'mhm_rentiva_tenants', 'mhm_rentiva_usage_metrics', 'mhm_sessions', 'mhm_transfers' ),
			),
		),
	);



	public const IGNORE_START = 'prefix-rename:ignore-start';
	public const IGNORE_END   = 'prefix-rename:ignore-end';

	public function transform( string $text, string $file = '' ): array {
		$out     = '';
		$i       = 0;
		$len     = strlen( $text );
		$stats   = array();
		$ignored = $this->ignoredRanges( $text );

		while ( $i < $len ) {
			// Inside an ignore region: copy verbatim and jump to its end.
			$inIgnored = null;
			foreach ( $ignored as $range ) {
				if ( $i >= $range[0] && $i < $range[1] ) {
					$inIgnored = $range;
					break;
				}
			}
			if ( null !== $inIgnored ) {
				$out .= substr( $text, $i, $inIgnored[1] - $i );
				$i    = $inIgnored[1];
				continue;
			}

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
				// 'lstrict' additionally forbids a preceding HYPHEN. Only the
				// body-class/screen-id prefixes use it: 'edit-' is far too common a
				// fragment of ordinary CSS class names and DOM ids, and without
				// this it rewrote 'mhm-edit-vehicle-btn' into
				// 'mhm-edit-mhmrentiva_vehicle-btn'. A screen id or body class is
				// always at the start of a token -- after a quote, a dot or
				// whitespace -- never in the middle of a hyphenated name.
				if ( ! empty( $rule['lstrict'] ) && $i > 0 && 1 === preg_match( '/[A-Za-z0-9_-]/', $text[ $i - 1 ] ) ) {
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
		// 🔴 DEFAULT IS "DO NOT RENAME". This started out the other way round --
		// rename unless a deny pattern objects -- and that put the burden of proof
		// on the wrong side. 'vehicle' appears ~220 times and is simultaneously a
		// post type, an array key, an enum value, a settings-tab id, a metric
		// subject AND the default value of the rewrite slug; with rename as the
		// default, every context nobody had thought of yet was silently renamed.
		// It cost a real public-URL break: SettingsSanitizer's two
		// `?: 'vehicle'` rewrite-slug fallbacks were rewritten to
		// 'mhmrentiva_vehicle', which changes every vehicle URL on every site.
		// A test caught it, which is luck, not method.
		//
		// Inverted, the failure mode is a leftover rather than a silent break,
		// and every leftover is printed by --list-skips for a human to resolve.
		$quoted = preg_quote( $rule['old'], '/' );
		$line   = $this->lineAt( $text, $i );

		foreach ( self::QUOTED_OVERRIDE_CONTEXTS as $pattern => $why ) {
			if ( 1 === preg_match( sprintf( $pattern, $quoted, $quoted ), $line ) ) {
				return null;
			}
		}

		// DENY first, and only ever against the single line the literal is on.
		// These patterns describe the immediate syntax around it -- an array key,
		// an index, an enum constant, a `?:` default -- and that reading must not
		// be overturned by something several lines away.
		foreach ( self::QUOTED_DENY_CONTEXTS as $pattern => $reason ) {
			if ( 1 === preg_match( sprintf( $pattern, $quoted, $quoted ), $line ) ) {
				return $reason;
			}
		}

		// ALLOW against the line, then against a small window. The window exists
		// because real call sites wrap: add_meta_box(), in_array() lists and
		// register_post_type() argument arrays routinely put the literal on a line
		// of its own, where no single-line pattern can ever see the evidence.
		foreach ( self::QUOTED_ALLOW_CONTEXTS as $pattern => $why ) {
			if ( 1 === preg_match( sprintf( $pattern, $quoted, $quoted ), $line ) ) {
				return null;
			}
		}
		$window = $this->windowAt( $text, $i, 5, 3 );
		foreach ( self::QUOTED_ALLOW_CONTEXTS as $pattern => $why ) {
			if ( 1 === preg_match( sprintf( $pattern, $quoted, $quoted ), $window ) ) {
				return null;
			}
		}

		return 'NO POSITIVE EVIDENCE this is a registered post type/taxonomy -- needs a human decision';
	}

	/**
	 * The lines around offset $i, joined, for multi-line context matching.
	 *
	 * @param string $text   Text.
	 * @param int    $i      Offset.
	 * @param int    $before Lines of leading context.
	 * @param int    $after  Lines of trailing context.
	 * @return string
	 */
	private function windowAt( string $text, int $i, int $before, int $after ): string {
		$lines = preg_split( '/\R/', $text ) ?: array();
		$n     = substr_count( $text, "\n", 0, $i );
		return implode( "\n", array_slice( $lines, max( 0, $n - $before ), $before + $after + 1 ) );
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

	$targets = array( 'src', 'templates', 'assets', 'src-react', 'tests', 'bin' );
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
