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

	/**
	 * No RUNTIME_STRING_RULES entry may shadow a later, more specific one.
	 *
	 * The constant's own docblock says "longest/most-specific first", and it is
	 * NOT sorted by length -- 'mhm_dark_mode_nonce' (19) sits after three 12s.
	 * That is fine, and asserting a total length order would fail today for no
	 * reason. What actually matters is weaker and exact: no earlier rule is a
	 * PREFIX of a later one, so "first match" always means "most specific match".
	 *
	 * Why it is worth locking: transform() sorts by strlen descending, but
	 * PrefixRenamer::substringRulesAgreeWith() walks the rules to answer "would
	 * the generic rule produce the mapped value?" -- and a wrong `true` there is
	 * the unsafe direction, because it skips the explicit rule and silently
	 * re-opens the class that 'mhm_contact_message' (26 chars into a varchar(20))
	 * was fixed to close. That function now sorts defensively, so correctness no
	 * longer depends on declaration order; this assertion keeps the constant
	 * honest for every other reader of it -- notably the bare 'mhm_' catch-all,
	 * which must stay last.
	 */
	public function test_no_runtime_string_rule_shadows_a_later_one(): void {
		$declared = array_keys( Map::RUNTIME_STRING_RULES );
		$shadowed = array();

		foreach ( $declared as $i => $earlier ) {
			foreach ( array_slice( $declared, $i + 1 ) as $later ) {
				if ( 0 === strpos( $later, $earlier ) ) {
					$shadowed[] = sprintf( "'%s' (position %d) shadows the later '%s'", $earlier, $i, $later );
				}
			}
		}

		$this->assertSame(
			array(),
			$shadowed,
			"a RUNTIME_STRING_RULES entry fires before a more specific one, so the specific rule can never match:\n"
			. implode( "\n", $shadowed )
		);
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
			// 'edit-' is an ordinary fragment of hyphenated class names and DOM
			// ids. Without a stricter left boundary the screen-id rule rewrote
			// these into 'mhm-edit-mhmrentiva_vehicle-btn'.
			'hyphenated class'       => array( 'class="mhm-edit-vehicle-btn"', 'a CSS class, not a screen id' ),
			'hyphenated dom id'      => array( 'id="mhm-edit-vehicle-panel"', 'a DOM id, not a screen id' ),
			'hyphenated close btn'   => array( 'id="mhm-close-edit-vehicle"', 'a DOM id, not a screen id' ),
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
			// Body classes and screen ids -- neither is a quoted literal, and in a
			// .css file there is no PHP syntax for a quoted_exact allow pattern to
			// match at all, so these were structurally invisible.
			'css body class'             => array( '.post-type-vehicle_booking #titlediv {', '.post-type-mhmrentiva_booking #titlediv {' ),
			'css body class vehicle'     => array( '.post-type-vehicle #slugdiv {', '.post-type-mhmrentiva_vehicle #slugdiv {' ),
			'js screen guard'            => array( "pagenow !== 'edit-vehicle_booking'", "pagenow !== 'edit-mhmrentiva_booking'" ),
			'js typenow guard'           => array( "typenow === 'vehicle_booking'", "typenow === 'mhmrentiva_booking'" ),
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
	 * A marked region is copied verbatim, so the deliberate pre-rename spellings
	 * the transition window needs survive re-running the tool.
	 */
	public function test_ignore_region_is_copied_verbatim(): void {
		$in = "'mhm_rentiva_a'\n// prefix-rename:ignore-start\n'mhm_rentiva_b'\n// prefix-rename:ignore-end\n'mhm_rentiva_c'";
		$this->assertSame(
			"'mhmrentiva_a'\n// prefix-rename:ignore-start\n'mhm_rentiva_b'\n// prefix-rename:ignore-end\n'mhmrentiva_c'",
			$this->t( $in ),
			'the marked region must be untouched and the surrounding code must still convert'
		);
	}

	/**
	 * An unterminated marker is a RUNTIME fail-safe and a BUILD failure, and this
	 * test only documents the first half.
	 *
	 * The tool protects to end of file rather than resuming, because resuming
	 * would convert exactly the literals the marker was opened to protect -- a
	 * typo must not be able to corrupt data. But the previous version of this
	 * test stopped there, which meant an unbalanced marker could never go red:
	 * one such marker exempted 824 lines of DatabaseCleaner.php from both the
	 * sweep and G-C modes 4a/4b, and "the sweep is a fixed point" was true there
	 * by construction rather than by measurement.
	 *
	 * PrefixRenameRegionsTest::test_every_ignore_region_is_balanced now FAILS the
	 * build on the same input. The two are complementary: the fallback keeps the
	 * typo from destroying anything, the gate keeps it from being permanent.
	 *
	 * @see PrefixRenameRegionsTest::test_every_ignore_region_is_balanced
	 */
	public function test_unterminated_marker_fails_safe_at_runtime_but_is_gated_as_an_error(): void {
		$in = "'mhm_rentiva_a'\n// prefix-rename:ignore-start\n'mhm_rentiva_b'";
		$this->assertSame(
			"'mhmrentiva_a'\n// prefix-rename:ignore-start\n'mhm_rentiva_b'",
			$this->t( $in ),
			'the runtime fallback must not convert what the marker was opened to protect'
		);
	}

	/**
	 * The three Elementor widget names ARE renamed, as of the owner's 2026-08-02
	 * decision -- they were live registered identifiers in the 'mhm_'-tokenized
	 * shape the fifth rejection named. The stored "widgetType" values they leave
	 * behind in _elementor_data are carried by the DB migration, not orphaned.
	 *
	 * The JS pair is asserted alongside the PHP deliberately. The widget's
	 * 'frontend/element_ready/<name>.default' hook must equal get_name() exactly,
	 * and nothing at runtime complains when it stops doing so -- the script just
	 * never runs. Renaming one without the other is the failure this locks.
	 *
	 * @dataProvider elementorWidgetNameProvider
	 *
	 * @param string $before Fragment before the sweep.
	 * @param string $after  What it must become.
	 */
	public function test_elementor_widget_names_are_renamed( string $before, string $after ): void {
		$this->assertSame( $after, $this->t( $before ), 'a registered Elementor widget name kept the old prefix' );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public function elementorWidgetNameProvider(): array {
		return array(
			'featured'         => array( "return 'mhm_rentiva_featured_vehicles';", "return 'mhmrentiva_featured_vehicles';" ),
			'grid'             => array( "return 'mhm_rentiva_vehicles_grid';", "return 'mhmrentiva_vehicles_grid';" ),
			'list'             => array( "return 'mhm_rentiva_vehicles_list';", "return 'mhmrentiva_vehicles_list';" ),
			'js element_ready' => array(
				"'frontend/element_ready/mhm_rentiva_featured_vehicles.default',",
				"'frontend/element_ready/mhmrentiva_featured_vehicles.default',",
			),
		);
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

	// -----------------------------------------------------------------
	// Tree-level invariants. The fixtures above prove the TRANSFORM is
	// right; these prove the TREE is, which is the claim that actually
	// matters and the one a fixture cannot make.
	//
	// Both of these existed once, were lost to a `git checkout` during the
	// deny-by-default rework, and were then reported as present. Nothing
	// noticed, because a test that does not exist cannot fail. That is why
	// they are asserted here rather than described in a report.
	// -----------------------------------------------------------------

	/**
	 * Every protected identifier in this plugin is hyphenated -- the text domain
	 * 'mhm-rentiva', the block namespace 'mhm-rentiva/*', every script and style
	 * handle 'mhm-rentiva-*'. The rename rules all key on the UNDERSCORE form, so
	 * a hyphenated identifier can only change if a rule fired where it must not
	 * have. One 'mhmrentiva-' anywhere in the shipped tree is proof of that, and
	 * it is a single cheap search rather than a per-identifier list that could
	 * silently go stale.
	 */
	public function test_no_hyphenated_identifier_was_renamed(): void {
		$root  = dirname( __DIR__, 2 );
		$leaks = array();

		foreach ( array( 'src', 'templates', 'assets', 'src-react' ) as $dir ) {
			$path = $root . '/' . $dir;
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( ! $file->isFile() || ! preg_match( '/\.(php|js|jsx|css|json)$/', $file->getFilename() ) ) {
					continue;
				}
				$rel = str_replace( '\\', '/', $file->getPathname() );
				if ( str_contains( $rel, '/node_modules/' ) ) {
					continue;
				}
				if ( str_contains( (string) file_get_contents( $file->getPathname() ), 'mhmrentiva-' ) ) {
					$leaks[] = substr( $rel, strlen( $root ) + 1 );
				}
			}
		}

		$this->assertSame(
			array(),
			$leaks,
			"a hyphenated identifier (text domain / block namespace / asset handle) was renamed in:\n" . implode( "\n", $leaks )
		);
	}

	/**
	 * The text domain must equal the plugin slug, which the sweep does not change.
	 */
	public function test_text_domain_is_untouched(): void {
		$main = (string) file_get_contents( dirname( __DIR__, 2 ) . '/mhm-rentiva.php' );
		// Whitespace-tolerant: the header is column-aligned, so a fixed
		// single-space needle fails for a formatting reason and says nothing
		// about the text domain.
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Text Domain:\s*mhm-rentiva\s*$/m', $main );
		$this->assertDoesNotMatchRegularExpression( '/Text Domain:\s*mhmrentiva\b/', $main );
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
