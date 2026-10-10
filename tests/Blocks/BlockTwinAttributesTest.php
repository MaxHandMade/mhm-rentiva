<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Tests\Support\BlockAttributeGroups;
use WP_UnitTestCase;

/**
 * No block declares two attributes for one shortcode setting, and every editor
 * control's value reaches the shortcode.
 *
 * Twin attributes (e.g. `showBookButton` + `show_booking_button`) cancelled each
 * other: the editor saves only the changed one, WP appends the other at its
 * default after it, and CAM keeps the later. The uncontrolled twin is deleted from
 * block.json (spec 2026-10-06-gutenberg-onarim-dilim-1 §2.1).
 */
class BlockTwinAttributesTest extends WP_UnitTestCase
{
	/** Lite blocks registered by the plugin's own init hook. */
	private const LITE_BLOCK_COUNT = 16;

	/**
	 * Non-support attributes after the slice (300 before: 2 shadowing + 20 twin deletions).
	 * Slice 3A Task 1: 271, the contact block's seven removed attributes.
	 * Slice 3A Task 3: 261, ten removed from vehicles-list, vehicles-grid, featured-vehicles.
	 * Slice 3A Task 4: 249, five removed from vehicle-details (incl. showBookingForm,
	 * ruling T4-1), seven from vehicle-comparison.
	 * Slice 3A Task 5: 243, three removed from search-results (sortBy, sortOrder,
	 * showAvailability) and three from unified-search (filter_categories, minwidth,
	 * maxwidth); its block.json `layout` was removed too, but the block keeps the
	 * `layout` attribute core adds for `supports.layout`, so the count drops by six.
	 * Slice 3A Task 6: 231, six removed from availability-calendar (showBookingLinks,
	 * showTodayButton, showWeekNumbers, startDate, showSeasonalPrices,
	 * integratePricing) and six from booking-form (show_insurance,
	 * show_payment_options, show_date_picker, minDays, maxDays, defaultPayment).
	 * Ruling T6-1: 230, availability-calendar showDiscounts.
	 * Slice 3A Task 7a: 225, five removed from my-bookings (showCancelButton,
	 * showModifyButton, showDetailsLink, sortBy, limitResults).
	 */
	private const LITE_ATTRIBUTE_COUNT = 225;

	/** The member each editor control writes (spec Ek-1, "kalır"), Lite rows. */
	private const CONTROLLED = array(
		array( 'search-results', 'limit' ),
		array( 'vehicle-comparison', 'showComparisonImages' ),
		array( 'vehicle-comparison', 'showPrice' ),
		array( 'vehicle-comparison', 'showBookButton' ),
		array( 'testimonials', 'filterRating' ),
		array( 'availability-calendar', 'showMonthNavigation' ),
		// Slice 3A Task 6: showTodayButton removed; the "Show Booking Links" toggle
		// now writes showBookingButton (rule 2).
		array( 'availability-calendar', 'showBookingButton' ),
		array( 'availability-calendar', 'calendarHeight' ),
		array( 'vehicles-grid', 'showBookButton' ),
		array( 'vehicles-grid', 'filterCategories' ),
		array( 'vehicles-list', 'showBookButton' ),
		array( 'vehicles-list', 'filterCategories' ),
		array( 'featured-vehicles', 'filterCategories' ),
		array( 'featured-vehicles', 'showBookButton' ),
		array( 'my-bookings', 'showVehicleImage' ),
		array( 'my-bookings', 'showStatus' ),
		// Slice 3A Task 7a: showCancelButton and showModifyButton removed (no such
		// feature on the list).
		array( 'my-bookings', 'filterStatus' ),
		array( 'my-favorites', 'showBookButton' ),
		array( 'payment-history', 'filterStatus' ),
	);

	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
	}

	public function controlled_provider(): array
	{
		$out = array();
		foreach (self::CONTROLLED as $row) {
			$out[ $row[0] . '.' . $row[1] ] = $row;
		}
		return $out;
	}

	public function test_no_block_declares_two_attributes_for_one_setting(): void
	{
		$tags = BlockAttributeGroups::block_tags();
		$this->assertCount(self::LITE_BLOCK_COUNT, $tags, 'Registry inventory changed: ' . implode(', ', array_keys($tags)));

		$attributes = 0;
		foreach (array_keys($tags) as $slug) {
			$attributes += count(BlockAttributeGroups::attributes($slug));
		}
		$this->assertSame(self::LITE_ATTRIBUTE_COUNT, $attributes);

		$this->assertSame(array(), BlockAttributeGroups::collisions());
	}

	public function test_probe_inventory(): void
	{
		$probed = 0;
		foreach (self::CONTROLLED as $row) {
			if (null !== BlockAttributeGroups::probe($row[0], $row[1])) {
				++$probed;
			}
		}
		// 21: vehicle-details showShareButtons was removed (slice 3A Task 4, no share feature).
		// 19: my-bookings showCancelButton and showModifyButton rows removed (slice 3A Task 7a).
		$this->assertSame(19, $probed);
	}

	/**
	 * @dataProvider controlled_provider
	 */
	public function test_controlled_value_reaches_shortcode(string $slug, string $key): void
	{
		$probe = BlockAttributeGroups::probe($slug, $key);
		$this->assertNotNull($probe, "No distinguishing probe for {$slug}.{$key}.");

		$sparse = BlockAttributeGroups::captured_atts($slug, array( $key => $probe['value'] ));
		$this->assertSame($probe['expected'], $sparse[ $probe['canonical'] ] ?? null, 'Sparse saved comment.');

		$full = BlockAttributeGroups::captured_atts($slug, BlockAttributeGroups::full_payload($slug, array( $key => $probe['value'] )));
		$this->assertSame($probe['expected'], $full[ $probe['canonical'] ] ?? null, 'Full preview payload.');
	}

	/**
	 * @dataProvider controlled_provider
	 */
	public function test_reset_returns_to_baseline(string $slug, string $key): void
	{
		$probe = BlockAttributeGroups::probe($slug, $key);
		$this->assertNotNull($probe);

		$changed = BlockAttributeGroups::captured_atts($slug, array( $key => $probe['value'] ));
		$this->assertSame($probe['expected'], $changed[ $probe['canonical'] ] ?? null, 'The change itself must arrive.');

		$default = BlockAttributeGroups::attributes($slug)[ $key ]['default'] ?? '';
		$reset   = BlockAttributeGroups::captured_atts($slug, array( $key => $default ));
		$base    = BlockAttributeGroups::captured_atts($slug, array());
		// CAM keeps processing order, so compare as maps, not as ordered lists.
		ksort($reset);
		ksort($base);
		$this->assertSame($base, $reset);
	}

	/** A hand-written comment carrying only a deleted twin keeps today's behaviour: ignored. */
	public function test_legacy_deleted_key_is_ignored(): void
	{
		$atts = BlockAttributeGroups::captured_atts('vehicles-grid', array( 'show_booking_button' => false ));
		$this->assertSame('1', $atts['show_booking_button']);
	}

	/** Both twins in one comment: the later key wins, today and after the slice. */
	public function test_legacy_both_keys_follow_today(): void
	{
		$a = BlockAttributeGroups::captured_atts('vehicles-grid', array( 'showBookButton' => true, 'show_booking_button' => false ));
		$this->assertSame('0', $a['show_booking_button']);

		$b = BlockAttributeGroups::captured_atts('vehicles-grid', array( 'show_booking_button' => false, 'showBookButton' => true ));
		$this->assertSame('1', $b['show_booking_button']);
	}

	/**
	 * featured-vehicles / vehicle-comparison: before the slice the deleted
	 * `showBookingButton(s)` and the control's `showBookButton` mapped to two
	 * different settings and the template preferred the former; now both map to one
	 * and the later key in the comment wins. A deliberate change (spec R1), reachable
	 * only by hand-written comments -- the editor never wrote the deleted key.
	 */
	public function test_legacy_shadowing_pairs_follow_comment_order(): void
	{
		$pairs = array(
			'featured-vehicles'  => array( 'showBookingButton', 'show_booking_button' ),
			'vehicle-comparison' => array( 'showBookingButtons', 'show_booking_buttons' ),
		);
		foreach ($pairs as $slug => list( $deleted, $canonical )) {
			$later_on = BlockAttributeGroups::captured_atts($slug, array( $deleted => false, 'showBookButton' => true ));
			$this->assertSame('1', $later_on[ $canonical ], "{$slug}: later showBookButton=true wins.");

			$later_off = BlockAttributeGroups::captured_atts($slug, array( 'showBookButton' => true, $deleted => false ));
			$this->assertSame('0', $later_off[ $canonical ], "{$slug}: later {$deleted}=false wins.");
		}
	}

	/** The wrapper reads raw `height` before CAM; a saved legacy value still sizes it. */
	public function test_legacy_raw_height_still_sizes_wrapper(): void
	{
		$html = render_block(
			array(
				'blockName'    => 'mhm-rentiva/availability-calendar',
				'attrs'        => array( 'height' => '500px' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$this->assertMatchesRegularExpression('/<div(?=[^>]*wp-block-mhm-rentiva-availability-calendar)(?=[^>]*height:500px)[^>]*>/', $html);
	}
}
