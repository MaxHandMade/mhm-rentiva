<?php
/**
 * Adversarial fixtures for the Görev 12 prefix-rename transformation.
 *
 * The sweep this guards rewrites every WP-registered identifier in the tree.
 * A partial-match bug in it does not fail loudly -- it produces plausible
 * looking output that silently orphans stored data, so the interesting cases
 * are all "a shorter rule fired inside a longer rule's territory" and
 * "a rule matched its own output". Both are asserted here directly rather
 * than inferred from a diff being non-empty.
 *
 * @package MHM_Rentiva
 */

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap as Map;

require_once dirname( __DIR__, 2 ) . '/bin/prefix-rename.php';

use MHMRentiva\Tools\PrefixRenamer;

/**
 * @coversNothing
 */
class PrefixRenamerTest extends TestCase {

	/**
	 * Renamer under test.
	 *
	 * @var PrefixRenamer
	 */
	private $renamer;

	protected function setUp(): void {
		parent::setUp();
		$this->renamer = new PrefixRenamer();
	}

	/**
	 * Convenience: transform a fragment and return only the text.
	 *
	 * @param string $text Input.
	 * @return string Output.
	 */
	private function t( string $text ): string {
		return $this->renamer->transform( $text )[0];
	}

	// -----------------------------------------------------------------
	// Ordering / partial-match damage. These are the cases a naive
	// "str_replace in a loop" gets wrong.
	// -----------------------------------------------------------------

	/**
	 * The exact bug PrefixMigrationMap's own docblock cites: if the bare
	 * '_mhm_' rule fires before '_mhm_rentiva_', the cut point lands in the
	 * wrong place and the word 'rentiva' is duplicated.
	 */
	public function test_underscore_mhm_rule_does_not_pre_empt_the_longer_rentiva_rule(): void {
		$this->assertSame(
			"'_mhmrentiva_welcome_sent'",
			$this->t( "'_mhm_rentiva_welcome_sent'" ),
			'_mhm_ fired before _mhm_rentiva_ and corrupted the key'
		);
	}

	/**
	 * 'addon_' is a POSTMETA_PREFIX_RULES key whose NEW value CONTAINS it.
	 * A sequential whole-file replace re-matches its own output here.
	 */
	public function test_addon_rule_does_not_rematch_inside_an_already_renamed_option(): void {
		$this->assertSame(
			"'mhmrentiva_addon_settings'",
			$this->t( "'mhm_rentiva_addon_settings'" ),
			'addon_ re-matched the output of the mhm_rentiva_ rule'
		);
	}

	/**
	 * 🔴 The regression that got furthest before being caught.
	 *
	 * 'mhm_rentiva' with NO trailing underscore is real, load-bearing text: it is
	 * the SQL LIKE prefix uninstall.php and DatabaseCleaner use to find this
	 * plugin's options ('mhm_rentiva%'), and it is an object-cache group name.
	 * The map only carries 'mhm_rentiva_' and 'mhm_rentiva/', so the bare
	 * catch-all 'mhm_' fired instead and produced 'mhmrentiva_rentiva%' -- a
	 * prefix that matches nothing, which would have made uninstall and the
	 * option cleanup silently stop deleting anything at all.
	 *
	 * My own adversarial fixtures missed it because every one of them followed
	 * 'mhm_rentiva' with an underscore. This is the case that was not imagined.
	 *
	 * @dataProvider bareRentivaProvider
	 *
	 * @param string $in       Input.
	 * @param string $expected Expected output.
	 */
	public function test_bare_mhm_rentiva_without_trailing_underscore( string $in, string $expected ): void {
		$this->assertSame( $expected, $this->t( $in ), 'the bare mhm_ catch-all fired inside mhm_rentiva' );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function bareRentivaProvider(): array {
		return array(
			'sql like prefix'      => array( "'mhm_rentiva%'", "'mhmrentiva%'" ),
			'sql like underscored' => array( "'_mhm_rentiva%'", "'_mhmrentiva%'" ),
			'transient like'       => array( "'_transient_mhm_rentiva%'", "'_transient_mhmrentiva%'" ),
			'cache group'          => array( "wp_cache_get( \$key, 'mhm_rentiva' )", "wp_cache_get( \$key, 'mhmrentiva' )" ),
			'phpcs prefix config'  => array( '<element value="mhm_rentiva"/>', '<element value="mhmrentiva"/>' ),
			'doubled escape'       => array( "'mhm_rentiva%%'", "'mhmrentiva%%'" ),
		);
	}

	/**
	 * A shorter key that is a strict prefix of a longer one must not truncate it.
	 */
	public function test_shorter_key_does_not_truncate_longer_sibling(): void {
		$this->assertSame(
			"'mhmrentiva_booking_status_vendor_subject'",
			$this->t( "'mhm_rentiva_booking_status_vendor_subject'" )
		);
	}

	/**
	 * The whole transform must be a fixed point: sweeping twice equals
	 * sweeping once. If any rule can match its own output this fails.
	 */
	public function test_transform_is_idempotent_over_every_mapped_identifier(): void {
		$corpus = '';
		foreach ( $this->allMappedOldNames() as $old ) {
			$corpus .= "'" . $old . "'\n";
		}
		$once  = $this->t( $corpus );
		$twice = $this->t( $once );
		$this->assertSame( $once, $twice, 'transform is not idempotent -- a rule matched its own output' );
	}

	/**
	 * Every OLD key in an exact-key family must transform into exactly the
	 * NEW value the map declares. This is the check that keeps the ordered
	 * rule list and the user-approved map from silently diverging.
	 */
	public function test_every_exact_family_key_transforms_to_its_mapped_value(): void {
		$families = array(
			'POST_TYPES'  => Map::POST_TYPES,
			'TAXONOMIES'  => Map::TAXONOMIES,
			'OPTIONS'     => Map::OPTIONS,
			'TABLES'      => Map::TABLES,
			'CRON_HOOKS'  => Map::CRON_HOOKS,
			'COMMENTMETA' => Map::COMMENTMETA,
		);
		$mismatches = array();
		foreach ( $families as $familyName => $family ) {
			foreach ( $family as $old => $new ) {
				// CPT and taxonomy names are only renamed on POSITIVE evidence
				// that the literal is a registration -- a bare quoted 'vehicle'
				// is deliberately left alone, because in this tree it is just as
				// likely to be an array key or the rewrite-slug default. So the
				// fixture has to supply the evidence a real call site supplies.
				$fixture = in_array( $familyName, array( 'POST_TYPES', 'TAXONOMIES' ), true )
					? "register_post_type( '" . $old . "' )"
					: "'" . $old . "'";
				$expected = str_replace( "'" . $old . "'", "'" . $new . "'", $fixture );

				$got = $this->t( $fixture );
				if ( $expected !== $got ) {
					$mismatches[] = sprintf( "%s: '%s' -> %s (map says '%s')", $familyName, $old, $got, $new );
				}
			}
		}
		$this->assertSame( array(), $mismatches, "rule list disagrees with PrefixMigrationMap:\n" . implode( "\n", $mismatches ) );
	}

	// -----------------------------------------------------------------
	// Protected identifiers -- must survive byte-identical.
	// -----------------------------------------------------------------

	/**
	 * @dataProvider protectedIdentifierProvider
	 *
	 * @param string $fragment Source fragment that must not change.
	 * @param string $why      Why it is protected.
	 */
	public function test_protected_identifiers_survive_byte_identical( string $fragment, string $why ): void {
		$this->assertSame( $fragment, $this->t( $fragment ), $why );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function protectedIdentifierProvider(): array {
		return array(
			'text domain'            => array( "__( 'Save', 'mhm-rentiva' )", 'text domain must equal the plugin slug' ),
			'plugin slug in path'    => array( "plugins/mhm-rentiva/assets", 'plugin slug' ),
			'block namespace'        => array( "registerBlockType( 'mhm-rentiva/vehicle-grid' )", 'blocks live in post_content' ),
			'shortcode tag'          => array( "add_shortcode( 'rentiva_vehicles_list', ... )", 'shortcodes live in post_content' ),
			'shortcode tag booking'  => array( "'rentiva_booking_form'", 'shortcodes live in post_content' ),
			'script handle'          => array( "wp_enqueue_script( 'mhm-rentiva-admin' )", 'handles are not storage' ),
			'style handle'           => array( "wp_enqueue_style( 'mhm-rentiva-vehicle-grid' )", 'handles are not storage' ),
			'rewrite slug taxonomy'  => array( "'rewrite' => array( 'slug' => 'vehicle-category' )", 'public URLs must not break' ),
			'php variable'           => array( '$vehicle_id = 5;', 'PHP variables are not registered globals' ),
			'php variable addon'     => array( '$addon_id = (int) $x;', 'PHP variables are not registered globals' ),
			'method name rentiva'    => array( 'public function get_rentiva_endpoints_map() {}', 'method names are not storage' ),
			'method name booking'    => array( 'public function show_booking_button() {}', 'method names are not storage' ),
			'method name addon'      => array( 'private function format_addon_price( $p ) {}', 'method names are not storage' ),
			'css class'              => array( 'class="mhm-rentiva-card"', 'CSS classes are not registered globals' ),
		);
	}

	// -----------------------------------------------------------------
	// Things that MUST change (mutation proof in the positive direction).
	// -----------------------------------------------------------------

	/**
	 * @dataProvider mustChangeProvider
	 *
	 * @param string $in       Input fragment.
	 * @param string $expected Expected output.
	 */
	public function test_identifiers_that_must_change( string $in, string $expected ): void {
		$this->assertSame( $expected, $this->t( $in ) );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function mustChangeProvider(): array {
		return array(
			'ajax action inside hook' => array( "add_action( 'wp_ajax_mhm_rentiva_save', ... )", "add_action( 'wp_ajax_mhmrentiva_save', ... )" ),
			'constant'                => array( 'MHM_RENTIVA_VERSION', 'MHMRENTIVA_VERSION' ),
			'slash filter'            => array( "apply_filters( 'mhm_rentiva/vehicle/args', \$a )", "apply_filters( 'mhmrentiva/vehicle/args', \$a )" ),
			'hidden meta bare mhm'    => array( "'_mhm_booking_id'", "'_mhmrentiva_booking_id'" ),
			'legacy booking meta'     => array( "get_post_meta( \$id, '_booking_pickup_date', true )", "get_post_meta( \$id, '_mhmrentiva_booking_pickup_date', true )" ),
			'legacy contact meta'     => array( "'_contact_email'", "'_mhmrentiva_contact_email'" ),
			'vendor slug meta'        => array( "'_rentiva_vendor_slug'", "'_mhmrentiva_vendor_slug'" ),
			'comment meta'            => array( "'mhm_rating'", "'mhmrentiva_rating'" ),
			'table name'              => array( "\$wpdb->prefix . 'mhm_rentiva_ratings'", "\$wpdb->prefix . 'mhmrentiva_ratings'" ),
			'cron hook'               => array( "'mhm_rentiva_auto_cancel_event'", "'mhmrentiva_auto_cancel_event'" ),
			'bare mhm option'         => array( "get_option( 'mhm_vehicle_settings' )", "get_option( 'mhmrentiva_vehicle_settings' )" ),
			// The four below were FALSE NEGATIVES found by auditing the dry-run's
			// --list-skips output: the tool declined them and would have left a
			// half-renamed CPT behind. Locked here so the deny-context patterns
			// can never re-swallow them.
			'canonical POST_TYPE const'  => array( "public const POST_TYPE = 'vehicle';", "public const POST_TYPE = 'mhmrentiva_vehicle';" ),
			'canonical POST_TYPE addon'  => array( "public const POST_TYPE = 'vehicle_addon';", "public const POST_TYPE = 'mhmrentiva_addon';" ),
			'canonical TAXONOMY const'   => array( "public const TAXONOMY = 'addon_context';", "public const TAXONOMY = 'mhmrentiva_addon_context';" ),
			'post_type query arg'        => array( "'post_type' => 'vehicle',", "'post_type' => 'mhmrentiva_vehicle'," ),
			'post_type comparison'       => array( "if ( \$post->post_type === 'vehicle' ) {}", "if ( \$post->post_type === 'mhmrentiva_vehicle' ) {}" ),
			'is_singular'                => array( "is_singular( 'vehicle' )", "is_singular( 'mhmrentiva_vehicle' )" ),
			// Admin URL query strings. Found by clicking the admin menu, not by
			// any static check -- every Rentiva menu item pointed at the old post
			// type, i.e. at a screen that no longer exists.
			'menu url post_type'         => array( "'edit.php?post_type=vehicle'", "'edit.php?post_type=mhmrentiva_vehicle'" ),
			'menu url booking'           => array( "'edit.php?post_type=vehicle_booking'", "'edit.php?post_type=mhmrentiva_booking'" ),
			'menu url taxonomy'          => array( "'edit-tags.php?taxonomy=vehicle_category&post_type=vehicle'", "'edit-tags.php?taxonomy=mhmrentiva_vehicle_category&post_type=mhmrentiva_vehicle'" ),
			// Right-anchored, so the shorter name cannot eat the longer one's URL.
			'query var not truncated'    => array( "'post_type=vehicle_addon'", "'post_type=mhmrentiva_addon'" ),
			// WP dynamic hooks. A column callback bound to a hook that no longer
			// fires does not error -- the column just silently disappears, which
			// is how this survived a green test suite.
			'manage columns hook'        => array( "'manage_vehicle_posts_columns'", "'manage_mhmrentiva_vehicle_posts_columns'" ),
			'manage columns booking'     => array( "'manage_vehicle_booking_posts_columns'", "'manage_mhmrentiva_booking_posts_columns'" ),
			'sortable columns hook'      => array( "'manage_edit-vehicle_addon_sortable_columns'", "'manage_edit-mhmrentiva_addon_sortable_columns'" ),
			'save_post hook'             => array( "'save_post_vehicle'", "'save_post_mhmrentiva_vehicle'" ),
			'save_post booking hook'     => array( "'save_post_vehicle_booking'", "'save_post_mhmrentiva_booking'" ),
			'add_meta_boxes hook'        => array( "'add_meta_boxes_vehicle'", "'add_meta_boxes_mhmrentiva_vehicle'" ),
			'wp_meta_boxes is post-type keyed' => array( "isset( \$wp_meta_boxes['vehicle']['side'] )", "isset( \$wp_meta_boxes['mhmrentiva_vehicle']['side'] )" ),
		);
	}

	/**
	 * @dataProvider cptLiteralThatIsNotACptProvider
	 *
	 * The same literal 'vehicle' is also an array key, an enum value and a
	 * rewrite-slug default. Those must survive; only the registered name moves.
	 *
	 * @param string $fragment Fragment that must not change.
	 * @param string $why      Why.
	 */
	public function test_cpt_literal_used_as_something_else_is_left_alone( string $fragment, string $why ): void {
		$this->assertSame( $fragment, $this->t( $fragment ), $why );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function cptLiteralThatIsNotACptProvider(): array {
		return array(
			'stats array index'   => array( "\$vehicle_count = \$stats['vehicle'];", 'internal stats array key' ),
			'template data index' => array( "esc_html( \$data['vehicle']['title'] ?? '' )", 'email template payload key' ),
			'settings tab key'    => array( "'vehicle'  => __( 'Vehicle Display', 'mhm-rentiva' ),", 'settings tab key' ),
			'error enum'          => array( "public const ERROR_TYPE_VEHICLE    = 'vehicle';", 'UX error-type enum value' ),
			'log category enum'   => array( "public const CATEGORY_VEHICLE     = 'vehicle';", 'log category enum value' ),
			'rewrite slug default' => array( "\$url_base = sanitize_title( SettingsCore::get( 'mhmrentiva_vehicle_url_base', 'vehicle' ) ) ?: 'vehicle';", 'public URLs must not change' ),
			// The two below are the sites a deny-by-default design missed and a
			// real test caught: SettingsSanitizer's rewrite-slug fallbacks. If
			// these move, every vehicle URL on every site moves with them.
			'sanitizer slug fallback'   => array( "\$url_base = \\sanitize_title( \$input['mhmrentiva_vehicle_url_base'] ?? ( \$defaults['mhmrentiva_vehicle_url_base'] ?? 'vehicle' ) );", 'rewrite-slug default' ),
			'sanitizer slug elvis'      => array( "'mhmrentiva_vehicle_url_base' => \$url_base ?: 'vehicle',", 'rewrite-slug default' ),
			'settings default slug'     => array( "'mhmrentiva_vehicle_url_base'             => 'vehicle',", 'rewrite-slug default' ),
			'metric subject'      => array( "self::flush_subject_metric('vehicle', 'perf', (string) \$vehicle_id);", 'internal metric subject' ),
			'provider key'        => array( "self::register_provider('vehicle', VehicleManagementSettings::class);", 'internal settings-provider key' ),
		);
	}

	// -----------------------------------------------------------------
	// The transfer-table carve-out (Görev 12 decision, 2026-08-01).
	// -----------------------------------------------------------------

	/**
	 * Lite never CREATEs these tables and PrefixMigrationMap::TABLES does not
	 * list them, so Görev 13 will never rename the physical table either.
	 * Renaming the probe literal would make it look for a name that has never
	 * existed on any site.
	 */
	public function test_legacy_transfer_table_literals_are_carved_out(): void {
		$this->assertSame(
			"\$wpdb->prefix . 'mhm_rentiva_transfer_locations'",
			$this->t( "\$wpdb->prefix . 'mhm_rentiva_transfer_locations'" )
		);
		$this->assertSame(
			"'mhm_rentiva_transfer_routes'",
			$this->t( "'mhm_rentiva_transfer_routes'" )
		);
	}

	/**
	 * The two BOOTSTRAP_FALLBACK_ALLOWLIST names are the migration's own version
	 * gates. Renaming them makes the "is this site already migrated?" read miss
	 * on precisely the un-migrated sites it exists to detect.
	 */
	public function test_bootstrap_fallback_version_options_are_carved_out(): void {
		foreach ( Map::BOOTSTRAP_FALLBACK_ALLOWLIST as $name ) {
			$this->assertSame(
				"get_option( '" . $name . "', '1.0.0' )",
				$this->t( "get_option( '" . $name . "', '1.0.0' )" ),
				$name . ' must survive the sweep byte-identical'
			);
		}
	}

	/**
	 * mhm-ui-core is a separate composer package. Its function is defined in
	 * vendor/, so renaming our call site produces a fatal.
	 */
	public function test_sibling_product_api_is_not_renamed(): void {
		$this->assertSame(
			'mhm_ui_core_register( $version, $file );',
			$this->t( 'mhm_ui_core_register( $version, $file );' ),
			"a bare 'mhm_' rule reached another product's API"
		);
	}

	/**
	 * The carve-out is a TABLE-name carve-out. The identically stemmed POSTMETA
	 * key (leading underscore) is a different family and must still be renamed
	 * -- if the carve-out is written as a naive substring skip it swallows this
	 * one too and DatabaseCleaner stops protecting the row.
	 */
	public function test_carve_out_does_not_swallow_the_same_stemmed_meta_key(): void {
		$this->assertSame(
			"'_mhmrentiva_transfer_locations'",
			$this->t( "'_mhm_rentiva_transfer_locations'" )
		);
	}

	// -----------------------------------------------------------------
	// Negative control: the tool must actually be doing something.
	// -----------------------------------------------------------------

	/**
	 * Guards against a transform that returns its input unchanged and makes
	 * every "unchanged" assertion above pass vacuously.
	 */
	public function test_transform_reports_the_rules_it_fired(): void {
		[ $out, $stats ] = $this->renamer->transform( "'mhm_rentiva_settings' '_mhm_booking_id' 'MHM_RENTIVA_VERSION'" );
		$this->assertNotSame( "'mhm_rentiva_settings' '_mhm_booking_id' 'MHM_RENTIVA_VERSION'", $out );
		$this->assertNotEmpty( $stats, 'transform reported no rule hits at all' );
		$this->assertSame( 3, array_sum( $stats ), 'expected exactly three rule firings' );
	}

	/**
	 * Every OLD name across every family, for the idempotence corpus.
	 *
	 * @return array<int,string>
	 */
	private function allMappedOldNames(): array {
		$old = array();
		foreach ( array( Map::POST_TYPES, Map::TAXONOMIES, Map::OPTIONS, Map::TABLES, Map::CRON_HOOKS, Map::COMMENTMETA ) as $family ) {
			$old = array_merge( $old, array_keys( $family ) );
		}
		foreach ( array( Map::POSTMETA_PREFIX_RULES, Map::USERMETA_PREFIX_RULES, Map::RUNTIME_STRING_RULES ) as $rules ) {
			$old = array_merge( $old, array_keys( $rules ) );
		}
		return array_values( array_unique( $old ) );
	}
}
