<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\ListTable;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Faz 2 Task 6 — the Vehicles Cards face (`VehicleColumns::render_cards_view()`).
 * Renders a card grid from the MAIN query's current page — no query of its
 * own beyond the one `update_post_thumbnail_cache()` priming call.
 *
 * @covers \MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns
 */
final class VehicleCardsFaceTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        VehicleColumns::register();
        OccupancyMapService::reset_memo();
        // get_edit_post_link() is capability-gated; the anonymous default
        // WP_UnitTestCase user (ID 0) always fails it, which would make
        // the edit-link assertions fail for reasons unrelated to the card
        // markup itself.
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type, $wp_query;
        $pagenow   = 'index.php';
        $post_type = null;
        $wp_query  = null;
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        parent::tearDown();
    }

    /**
     * go_to() resets `$pagenow`/`current_screen`/`$wp_query` (see
     * WP_UnitTestCase_Base::go_to()), so both the screen globals
     * render_cards_view() gates on AND the fake main query set up via
     * setMainQuery() must be applied AFTER it, never before.
     */
    private function goToCardsFace( string $extraQuery = '' ): void
    {
        $query = 'mhmrentiva_view=cards' . ( '' !== $extraQuery ? '&' . $extraQuery : '' );
        $this->go_to( admin_url( 'edit.php?' . $query ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_vehicle';
    }

    private function goToListFace(): void
    {
        $this->go_to( admin_url( 'edit.php' ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_vehicle';
    }

    private function goToCalendarFace(): void
    {
        $this->go_to( admin_url( 'edit.php?mhmrentiva_view=calendar' ) );
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_vehicle';
    }

    /**
     * @return int Vehicle post ID.
     */
    private function makeVehicle( string $title, array $meta = array() ): int
    {
        $id = self::factory()->post->create(
            array(
                'post_type'  => 'mhmrentiva_vehicle',
                'post_title' => $title,
            )
        );

        foreach ( $meta as $key => $value ) {
            update_post_meta( $id, $key, $value );
        }

        return $id;
    }

    /**
     * A bare `factory()->attachment->create()` has no real file/metadata,
     * so `wp_get_attachment_image()` returns empty and `set_post_thumbnail()`
     * silently no-ops (WP core deletes `_thumbnail_id` instead of setting
     * it when the image can't be rendered) — caught by an early debug run,
     * not assumed. A real fixture image gives the card a genuine thumbnail.
     */
    private function attachThumbnail( int $vehicle_id ): int
    {
        $attachment_id = self::factory()->attachment->create_upload_object(
            '/opt/wordpress-tests-lib/data/images/canola.jpg',
            $vehicle_id
        );
        set_post_thumbnail( $vehicle_id, $attachment_id );
        return $attachment_id;
    }

    /**
     * Fake the main query's current page to the given vehicles, in the
     * given order — mirroring "fake the main query page to 2 of them"
     * from the brief. MUST be called AFTER go_to()/goTo*Face(): go_to()
     * is what makes `$GLOBALS['wp_query']` carry the REAL parsed request
     * (including `mhmrentiva_view`, which `get_current_view()` reads off
     * that exact object via `get_query_var()` -> `$wp_query->get()`).
     * Replacing the object outright (rather than mutating its `->posts`)
     * would silently reset that parsed state and break the view guard —
     * caught by an early debug run, not assumed.
     *
     * @param int[] $post_ids
     */
    private function setMainQuery( array $post_ids ): \WP_Query
    {
        global $wp_query;

        $source = new \WP_Query(
            array(
                'post_type'      => 'mhmrentiva_vehicle',
                'post__in'       => ! empty( $post_ids ) ? $post_ids : array( 0 ),
                'orderby'        => 'post__in',
                'posts_per_page' => -1,
            )
        );

        $wp_query->posts       = $source->posts;
        $wp_query->post_count  = $source->post_count;
        $wp_query->found_posts = $source->found_posts;

        return $wp_query;
    }

    private function render(): string
    {
        ob_start();
        VehicleColumns::render_cards_view();
        return (string) ob_get_clean();
    }

    // --- Test 1: cards render from the main query's current page -----------

    public function test_cards_render_from_main_query_current_page_in_order(): void
    {
        $v1 = $this->makeVehicle( 'Alpha' );
        $v2 = $this->makeVehicle( 'Beta' );
        $this->makeVehicle( 'Gamma' );

        $this->goToCardsFace();
        // "Fake the main query page to 2 of them" — only v2, v1 (this
        // order) are on the current page; Gamma exists but is not.
        $this->setMainQuery( array( $v2, $v1 ) );

        $html = $this->render();

        $this->assertSame( 2, substr_count( $html, 'rv-vhl-card"' ), 'Expected exactly 2 card blocks.' );

        $posBeta  = strpos( $html, 'Beta' );
        $posAlpha = strpos( $html, 'Alpha' );
        $this->assertNotFalse( $posBeta );
        $this->assertNotFalse( $posAlpha );
        $this->assertLessThan( $posAlpha, $posBeta, 'Cards must follow the main query order (Beta before Alpha).' );
        $this->assertStringNotContainsString( 'Gamma', $html, 'A vehicle outside the current page must not appear.' );
    }

    // --- Test 2: featured star only for the featured vehicle ---------------

    public function test_featured_star_renders_only_for_the_featured_vehicle(): void
    {
        $featured    = $this->makeVehicle( 'Featured Car', array( MetaKeys::VEHICLE_FEATURED => '1' ) );
        $notFeatured = $this->makeVehicle( 'Plain Car' );

        $this->goToCardsFace();
        $this->setMainQuery( array( $featured, $notFeatured ) );

        $html = $this->render();

        // Split the HTML at the card boundary and check each card individually.
        $cards        = preg_split( '/(?=<div class="rv-vhl-card")/', $html );
        $featuredCard = null;
        $plainCard    = null;
        foreach ( $cards as $card ) {
            if ( strpos( $card, 'Featured Car' ) !== false ) {
                $featuredCard = $card;
            }
            if ( strpos( $card, 'Plain Car' ) !== false ) {
                $plainCard = $card;
            }
        }

        $this->assertNotNull( $featuredCard );
        $this->assertNotNull( $plainCard );
        $this->assertStringContainsString( 'rv-vhl-card__star', $featuredCard );
        $this->assertStringNotContainsString( 'rv-vhl-card__star', $plainCard );
    }

    // --- Test 3: badge derivation matches the list face ---------------------

    public function test_badge_matches_the_list_face_for_the_same_seeded_state(): void
    {
        $vehicle = $this->makeVehicle( 'Occupied Car', array( MetaKeys::VEHICLE_STATUS => 'maintenance' ) );

        // Realistic scenario: an in-progress booking today (occupancy is a
        // SEPARATE concept from the Available/Lifecycle status pill — this
        // just proves both faces derive the pill from the identical source
        // regardless of what else is true about the vehicle today).
        $today   = current_time( 'Y-m-d' );
        $booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking, '_mhmrentiva_status', 'in_progress' );
        update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle );
        update_post_meta( $booking, '_mhmrentiva_pickup_date', $today );
        update_post_meta( $booking, '_mhmrentiva_dropoff_date', $today );

        $this->goToCardsFace();
        $this->setMainQuery( array( $vehicle ) );
        $cardsHtml = $this->render();

        ob_start();
        VehicleColumns::render( 'mhmrentiva_available', $vehicle );
        $listHtml = (string) ob_get_clean();

        // Same label text in both faces.
        $this->assertStringContainsString( 'Maintenance', $listHtml );
        $this->assertStringContainsString( 'Maintenance', $cardsHtml );

        // Same underlying status class in both faces.
        $this->assertStringContainsString( 'status-maintenance', $listHtml );
        $this->assertStringContainsString( 'status-maintenance', $cardsHtml );
    }

    // --- Test 4: query budget ------------------------------------------------

    /**
     * Renders every list-face column a card also draws from (thumbnail,
     * week strip, price, features, available) for the given vehicles —
     * the same per-row cost the admin screen's list face pays today.
     */
    private function renderListFaceColumns( array $vehicles ): void
    {
        foreach ( $vehicles as $vehicle ) {
            $id = $vehicle instanceof \WP_Post ? $vehicle->ID : (int) $vehicle;
            VehicleColumns::render( 'mhmrentiva_vehicle', $id );
            VehicleColumns::render( 'mhmrentiva_week', $id );
            VehicleColumns::render( 'mhmrentiva_price_per_day', $id );
            VehicleColumns::render( 'mhmrentiva_features', $id );
            VehicleColumns::render( 'mhmrentiva_available', $id );
        }
    }

    public function test_query_budget_cards_face_adds_at_most_one_query_over_the_list_face(): void
    {
        global $wpdb;

        $vehicles = array();
        for ( $i = 0; $i < 3; $i++ ) {
            $id = $this->makeVehicle( 'Fleet ' . $i, array( MetaKeys::VEHICLE_PRICE_PER_DAY => '100' ) );
            $this->attachThumbnail( $id );
            $vehicles[] = $id;
        }

        // List face, from a cold cache.
        $this->goToListFace();
        wp_cache_flush();
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        foreach ( $vehicles as $id ) {
            clean_post_cache( $id );
        }
        $wp_query_list_cold = $this->setMainQuery( $vehicles );
        $beforeListCold      = $wpdb->num_queries;
        ob_start();
        $this->renderListFaceColumns( $wp_query_list_cold->posts );
        ob_end_clean();
        $listQueries = $wpdb->num_queries - $beforeListCold;

        // Cards face, from an equally cold cache.
        $this->goToCardsFace();
        wp_cache_flush();
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        foreach ( $vehicles as $id ) {
            clean_post_cache( $id );
        }
        $this->setMainQuery( $vehicles );
        $beforeCards = $wpdb->num_queries;
        $this->render();
        $cardsQueries = $wpdb->num_queries - $beforeCards;

        $this->assertLessThanOrEqual(
            $listQueries + 1,
            $cardsQueries,
            "Cards face must not exceed the list face's query budget by more than 1 (list={$listQueries}, cards={$cardsQueries})."
        );
    }

    /**
     * The thumbnail/week-strip/price/features costs are all batched or
     * memoized (see the other query-budget test), but the chips row's
     * category lookup is NOT: `wp_get_post_terms()` — the SAME call the
     * list face's Vehicle column already makes for the identical purpose
     * — has no bulk-object cache path in WordPress core the way
     * `get_the_terms()` does (confirmed by dumping the actual SQL log via
     * the `query` filter, since SAVEQUERIES is off in this test env), so
     * it costs exactly one query per row on BOTH faces alike. That is
     * pre-existing, shared behavior this task neither introduces nor
     * owns fixing — the assertion below only guards that nothing else
     * (thumbnail, strip, price, features) scales beyond that one known
     * per-row query.
     */
    public function test_query_budget_scales_by_at_most_one_query_per_additional_vehicle(): void
    {
        global $wpdb;

        $small = array();
        for ( $i = 0; $i < 2; $i++ ) {
            $id = $this->makeVehicle( 'Small ' . $i, array( MetaKeys::VEHICLE_PRICE_PER_DAY => '100' ) );
            $this->attachThumbnail( $id );
            $small[] = $id;
        }

        $this->goToCardsFace();
        wp_cache_flush();
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        foreach ( $small as $id ) {
            clean_post_cache( $id );
        }
        $this->setMainQuery( $small );
        $beforeSmall  = $wpdb->num_queries;
        $this->render();
        $queriesSmall = $wpdb->num_queries - $beforeSmall;

        $large     = $small;
        $extraCount = 5;
        for ( $i = 0; $i < $extraCount; $i++ ) {
            $id = $this->makeVehicle( 'Large ' . $i, array( MetaKeys::VEHICLE_PRICE_PER_DAY => '100' ) );
            $this->attachThumbnail( $id );
            $large[] = $id;
        }

        $this->goToCardsFace();
        wp_cache_flush();
        OccupancyMapService::reset_memo();
        OccupancyMapService::invalidate();
        foreach ( $large as $id ) {
            clean_post_cache( $id );
        }
        $this->setMainQuery( $large );
        $beforeLarge  = $wpdb->num_queries;
        $this->render();
        $queriesLarge = $wpdb->num_queries - $beforeLarge;

        // At most 1 extra query per additional vehicle (the known
        // per-row category lookup), plus 1 query of slack for anything
        // environmental (e.g. an object-cache group flush boundary).
        $this->assertLessThanOrEqual(
            $queriesSmall + $extraCount + 1,
            $queriesLarge,
            "Query count must not scale by more than 1 query per additional vehicle (small={$queriesSmall}, large={$queriesLarge}, extra vehicles={$extraCount})."
        );
    }

    // --- Test 5: cards output only appears on the cards face ---------------

    public function test_cards_output_absent_on_the_list_face(): void
    {
        $vehicle = $this->makeVehicle( 'Solo' );
        $this->goToListFace();
        $this->setMainQuery( array( $vehicle ) );

        $html = $this->render();

        $this->assertSame( '', $html );
    }

    public function test_cards_output_absent_on_the_calendar_face(): void
    {
        $vehicle = $this->makeVehicle( 'Solo' );
        $this->goToCalendarFace();
        $this->setMainQuery( array( $vehicle ) );

        $html = $this->render();

        $this->assertSame( '', $html );
    }

    public function test_cards_output_present_only_on_the_cards_face(): void
    {
        $vehicle = $this->makeVehicle( 'Solo' );
        $this->goToCardsFace();
        $this->setMainQuery( array( $vehicle ) );

        $html = $this->render();

        $this->assertStringContainsString( 'rv-vhl-cards', $html );
    }

    // --- Markup coverage ------------------------------------------------------

    public function test_card_markup_contains_all_documented_classes(): void
    {
        $vehicle = $this->makeVehicle(
            'Full Card',
            array(
                MetaKeys::VEHICLE_LICENSE_PLATE => '34 ABC 123',
                MetaKeys::VEHICLE_PRICE_PER_DAY => '250',
                MetaKeys::VEHICLE_SEATS         => '5',
                MetaKeys::VEHICLE_TRANSMISSION  => 'automatic',
                MetaKeys::VEHICLE_FEATURED      => '1',
            )
        );
        $this->attachThumbnail( $vehicle );

        $this->goToCardsFace();
        $this->setMainQuery( array( $vehicle ) );

        $html = $this->render();

        foreach (
            array(
                'rv-vhl-cards',
                'rv-vhl-card',
                'rv-vhl-card__media',
                'rv-vhl-card__badge',
                'rv-vhl-card__star',
                'rv-vhl-card__body',
                'rv-vhl-card__title',
                'rv-vhl-card__subline',
                'rv-vhl-card__chips',
                'rv-vhl-card__strip',
                'rv-vhl-card__footer',
                'rv-vhl-card__price',
                'rv-vhl-card__edit',
                'rv-vhl-week',
                'rv-vhl-day',
            ) as $class
        ) {
            $this->assertStringContainsString( $class, $html, "Missing expected class: {$class}" );
        }

        $this->assertStringContainsString( 'Full Card', $html );
        $this->assertStringContainsString( '34 ABC 123', $html );
        $this->assertStringContainsString( '/ day', $html );
    }

    public function test_card_placeholder_shown_when_vehicle_has_no_thumbnail(): void
    {
        $vehicle = $this->makeVehicle( 'No Photo Car' );
        $this->goToCardsFace();
        $this->setMainQuery( array( $vehicle ) );

        $html = $this->render();

        $this->assertStringContainsString( 'No Photo Car', $html );
        $this->assertStringNotContainsString( '<img', $html );
    }

    public function test_no_row_actions_or_checkboxes_on_cards(): void
    {
        $vehicle = $this->makeVehicle( 'Clean Card' );
        $this->goToCardsFace();
        $this->setMainQuery( array( $vehicle ) );

        $html = $this->render();

        $this->assertStringNotContainsString( 'type="checkbox"', $html );
        $this->assertStringNotContainsString( 'row-actions', $html );
    }
}
