<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Allowlist Registry for CAM
 *
 * Authoritative source of attribute definitions for all blocks and shortcodes.
 *
 * @package MHMRentiva\Core\Attribute
 * @since 4.11.0
 */
final class AllowlistRegistry {


    /**
     * Canonical attributes with types and context.
     * This is the SOURCE OF TRUTH.
     */
    public const ALLOWLIST = [
        // Pagination & Sorting
        'limit'                    => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => [ 'limit', 'resultsPerPage' ],
        ],
        'columns'                  => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => [ 'columns' ],
        ],
        'orderby'                  => [
            'type'    => 'enum',
            'group'   => 'sorting',
            'aliases' => [ 'sortBy', 'orderby' ],
            'values'  => [ 'price', 'popularity', 'newest', 'capacity', 'title', 'date', 'modified', 'rand', 'post__in', 'rating', 'rating_average', 'rating_count', 'confidence' ],
        ],
        'order'                    => [
            'type'    => 'enum',
            'group'   => 'sorting',
            'aliases' => [ 'sortOrder', 'order' ],
            'values'  => [ 'asc', 'desc', 'ASC', 'DESC' ],
        ],
        'results_per_page'         => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => [ 'resultsPerPage', 'limit' ],
        ],
        'show_pagination'          => [
            'type'    => 'bool',
            'group'   => 'pagination',
            'aliases' => [ 'showPagination' ],
        ],
        'show_sorting'             => [
            'type'    => 'bool',
            'group'   => 'sorting',
            'aliases' => [ 'showSorting' ],
        ],

        // Dimension Mappings
        'width'                    => [
            'type'  => 'string',
            'group' => 'layout',
        ],
        'height'                   => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'calendarHeight' ],
        ],
        'columns_tablet'           => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => [ 'columnsTablet' ],
        ],
        'columns_mobile'           => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => [ 'columnsMobile' ],
        ],

        // Booking Form – Rental Day Limits
        'default_days'             => [
            'type'    => 'int',
            'group'   => 'workflow',
            'aliases' => [ 'defaultDays' ],
        ],

        // Layout & Styles
        'layout'                   => [
            'type'  => 'string',
            'group' => 'layout',
        ],
        'theme'                    => [
            'type'  => 'string',
            'group' => 'layout',
        ],
        'class'                    => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'className' ],
        ],
        'custom_css_class'         => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'customClassName' ],
        ],
        'image_size'               => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'imageSize' ],
        ],
        'price_format'             => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'priceFormat' ],
        ],
        'default_sort'             => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'defaultSort' ],
        ],
        'per_page'                 => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => [ 'perPage' ],
        ],
        'show_filter_bar'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showFilterBar' ],
        ],
        'empty_message'            => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'emptyMessage' ],
        ],
        'search_layout'            => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => [ 'searchLayout' ],
        ],
        'ids'                      => [
            'type'    => 'idlist',
            'group'   => 'data',
            'aliases' => [ 'ids', 'vehicleIds', 'vehicle_ids' ],
        ],
        // Vehicle ids a list leaves out (featured list: the similar-vehicles
        // section of a vehicle page excludes the page's own vehicle).
        'exclude'                  => [
            'type'    => 'idlist',
            'group'   => 'data',
            'aliases' => [ 'exclude' ],
        ],
        'redirect_page'            => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'redirect_url', 'redirectPage', 'redirectUrl' ],
        ],
        // Visibility Toggles (UI)
        'show_image'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showImage', 'showImages', 'showVehicleImage', 'show_images' ],
        ],
        'show_title'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showTitle' ],
        ],
        'show_price'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPrice' ],
        ],
        'show_rating'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showRating' ],
        ],
        'show_features'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showFeatures' ],
        ],
        'show_booking_button'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookButton', 'show_booking_button', 'show_booking_btn', 'showBookBtn', 'show_book_btn' ],
        ],
        'show_favorite_button'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showFavoriteButton', 'showFavoriteBtn', 'show_favorite_btn', 'show_favorite_button', 'show_favorite' ],
        ],
        'show_compare_button'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showCompareButton', 'showCompareBtn', 'show_compare_btn', 'show_compare_button' ],
        ],
        'show_category'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showCategory' ],
        ],
        'show_brand'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBrand' ],
        ],
        'show_badges'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBadges' ],
        ],
        'show_description'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDescription' ],
        ],
        'show_availability'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showAvailability' ],
        ],
        'show_availability_status' => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showAvailabilityStatus' ],
        ],
        'show_filters'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showFilters' ],
        ],
        'show_view_toggle'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showViewToggle' ],
        ],
        'show_legend'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showLegend' ],
        ],
        'show_month_nav'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showMonthNavigation' ],
        ],
        'months_to_show'           => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => [ 'monthsToShow' ],
        ],
        'start_week_on'            => [
            'type'    => 'int',
            'group'   => 'workflow',
            'aliases' => [ 'startWeekOn' ],
        ],
        'show_payment_summary'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPaymentSummary' ],
        ],
        'show_pickup_instructions' => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPickupInstructions' ],
        ],
        'show_contact_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showContactInfo' ],
        ],
        'show_print_btn'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPrintButton', 'showPrintBtn' ],
        ],
        'show_download_pdf'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDownloadPDF' ],
        ],
        'show_booking_id_field'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookingIdField' ],
        ],
        'show_thread_preview'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showThreadPreview' ],
        ],
        'show_booking_link'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookingLink' ],
        ],
        'show_booking_dates'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookingDates' ],
        ],
        'status'                   => [
            'type'    => 'enum',
            'group'   => 'workflow',
            'aliases' => [ 'filterStatus', 'status' ],
            'values'  => [
                '',
                'active',
                'inactive',
                'draft',
                'pending',
                'pending_payment',
                'confirmed',
                'in_progress',
                'completed',
                'cancelled',
                'refunded',
                'no_show',
                'maintenance',
            ],
        ],
        'show_status_toggle'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showStatus' ],
        ],
        'show_payment_method'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPaymentMethod' ],
        ],
        'show_route_info'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showRouteInfo' ],
        ],
        'show_fuel_type'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showFuelType' ],
        ],
        'show_transmission'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showTransmission' ],
        ],
        'show_seats'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showSeats' ],
        ],
        'show_reviews'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showReviews' ],
        ],
        'show_similar_vehicles'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showSimilarVehicles' ],
        ],
        'show_share_btns'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showShareButtons', 'showShareBtns' ],
        ],
        'show_breadcrumb'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBreadcrumb' ],
        ],
        'similar_vehicles_limit'   => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => [ 'similarVehiclesLimit' ],
        ],
        'success_message'          => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'successMessage' ],
        ],
        'show_vehicle_details'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showVehicleDetails' ],
        ],
        'show_luggage_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showLuggageInfo' ],
        ],
        'show_passenger_count'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPassengerCount' ],
        ],
        'show_company_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showCompanyInfo', 'showCompany' ],
        ],
        'show_author_avatar'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showAuthorAvatar', 'showAvatar' ],
        ],
        'show_quotes'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showQuotes' ],
        ],
        'show_vehicle_selector'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showVehicleSelector' ],
        ],
        'show_vehicle_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showVehicleInfo' ],
        ],
        'show_date_picker'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDatePicker' ],
        ],
        'show_time_select'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showTimeSelect', 'show_time_select' ],
        ],
        'view_all_url'             => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'viewAllUrl' ],
        ],
        'view_all_text'            => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'viewAllText' ],
        ],
        'show_view_all'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showViewAll' ],
        ],
        'heading'                  => [
            'type'  => 'string',
            'group' => 'content',
        ],
        'subheading'               => [
            'type'  => 'string',
            'group' => 'content',
        ],
        'show_duration'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDuration' ],
        ],
        'show_distance'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDistance' ],
        ],
        'show_traffic_note'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showTrafficNote' ],
        ],
        'filter_origin_city'       => [
            'type'    => 'string',
            'group'   => 'data',
            'aliases' => [ 'filterOriginCity' ],
        ],
        'filter_origin_type'       => [
            'type'    => 'string',
            'group'   => 'data',
            'aliases' => [ 'filterOriginType' ],
        ],
        'featured_only'            => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => [ 'featuredOnly' ],
        ],
        'show_addons'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showAddons' ],
        ],
        'min_rating'               => [
            'type'    => 'int',
            'group'   => 'query',
            'aliases' => [ 'minRating' ],
        ],
        'min_reviews'              => [
            'type'    => 'int',
            'group'   => 'query',
            'aliases' => [ 'minReviews' ],
        ],
        'button_text'              => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'buttonText' ],
        ],
        'show_rating_count'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showRatingCount' ],
        ],

        // Vendor profile attributes
        'slug'                     => [
            'type'  => 'string',
            'group' => 'data',
        ],
        'show_badge'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBadge' ],
        ],
        'show_about'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showAbout' ],
        ],
        'show_vehicles'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showVehicles' ],
        ],
        'show_location'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showLocation' ],
        ],
        'vehicle_sort'             => [
            'type'    => 'string',
            'group'   => 'query',
            'aliases' => [ 'vehicleSort' ],
        ],
        'max_reviews'              => [
            'type'    => 'int',
            'group'   => 'query',
            'aliases' => [ 'maxReviews' ],
        ],
        'empty_vehicles_message'   => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'emptyVehiclesMessage' ],
        ],
        'empty_reviews_message'    => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'emptyReviewsMessage' ],
        ],

        // Core Content & Identity
        'title'                    => [
            'type'  => 'string',
            'group' => 'content',
        ],
        'description'              => [
            'type'  => 'string',
            'group' => 'content',
        ],
        'id'                       => [
            'type'  => 'int',
            'group' => 'feature',
        ],
        'vehicle_id'               => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => [ 'vehicleId', 'vehicle_id' ],
        ],
        'vehicle_ids'              => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => [ 'vehicleIds' ],
        ],
        'booking_id'               => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => [ 'bookingId' ],
        ],
        'category'                 => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => [ 'filterCategories', 'category' ],
        ],
        'featured'                 => [
            'type'  => 'bool',
            'group' => 'feature',
        ],

        // Date & Time
        'start_date'               => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'startDate', 'start_date' ],
        ],
        'end_date'                 => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'endDate', 'end_date' ],
        ],
        // Sliders & Interactivity
        'autoplay'                 => [
            'type'    => 'bool',
            'group'   => 'layout',
            'aliases' => [ 'autoplay' ],
        ],
        'interval'                 => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => [ 'interval' ],
        ],
        'auto_rotate'              => [
            'type'    => 'bool',
            'group'   => 'layout',
            'aliases' => [ 'autoRotate' ],
        ],
        'max_features'             => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => [ 'maxFeatures' ],
        ],
        'max_vehicles'             => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => [ 'maxVehicles' ],
        ],

        // Flags & Options
        'enable_deposit'           => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => [ 'enableDeposit' ],
        ],
        'redirect_url'             => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'redirectUrl' ],
        ],
        'form_title'               => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'formTitle' ],
        ],
        'no_results_text'          => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => [ 'noResultsText' ],
        ],

        // Search Specific
        'show_location_select'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showLocationSelect' ],
        ],
        'show_dropoff_location'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDropoffLocation' ],
        ],
        'location_required'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'locationRequired' ],
        ],
        'fields_required'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'fieldsRequired' ],
        ],
        'start_month'              => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'startMonth' ],
        ],
        'show_pax'                 => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPax' ],
        ],
        'show_luggage'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showLuggage' ],
        ],
        'filter_categories'        => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => [ 'filterCategories' ],
        ],

        // Navigation
        'hide_nav'                 => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'hideNavigation' ],
        ],

        // Generic Visibility
        'show_gallery'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showGallery' ],
        ],
        'show_pricing'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPricing' ],
        ],
        'show_booking'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBooking' ],
        ],
		'show_booking_btn'         => [
			'type'    => 'bool',
			'group'   => 'visibility',
			'aliases' => [ 'showBookButton' ],
		],
        'show_prices'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'show_price', 'showPrice' ],
        ],
        'show_images'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showComparisonImages' ],
        ],
        'show_calendar'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showCalendar' ],
        ],
        'show_details'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDetails', 'showBookingDetails' ],
        ],
        'show_actions'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showActions' ],
        ],
        'show_pickup'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPickup' ],
        ],
        'show_dropoff'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDropoff' ],
        ],
        'show_date'                => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showDate' ],
        ],
        'show_vehicle'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showVehicle' ],
        ],
        'show_customer'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showCustomer' ],
        ],
        'show_rating_display'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showRatingDisplay' ],
        ],
        'show_form'                => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showForm' ],
        ],
        'show_ratings_list'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showRatingsList' ],
        ],
        'show_technical_specs'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showTechnicalSpecs' ],
        ],
        'show_booking_form'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookingForm' ],
        ],
        'show_book_button'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookButton' ],
        ],
        'sort_by'                  => [
            'type'    => 'enum',
            'group'   => 'sorting',
            'aliases' => [ 'sort_by' ],
            'values'  => [ 'price', 'popularity', 'newest', 'capacity', 'title', 'date', 'modified', 'rand', 'rating', 'rating_average', 'rating_count', 'confidence' ],
        ],
        'sort_order'               => [
            'type'    => 'enum',
            'group'   => 'sorting',
            'aliases' => [ 'sort_order' ],
            'values'  => [ 'asc', 'desc', 'ASC', 'DESC' ],
        ],

        // Mixed Feature Specific
        'type'                     => [
            'type'    => 'enum',
            'group'   => 'feature',
            'aliases' => [ 'formType' ],
            'values'  => [ 'general', 'booking', 'support', 'feedback' ],
        ],
        'show_phone'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPhone' ],
        ],
        'show_company'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showCompany' ],
        ],
        'show_priority'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showPriority' ],
        ],
        'show_attachment'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showAttachment' ],
        ],
        'email_to'                 => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => [ 'emailTo' ],
        ],
        'auto_reply'               => [
            'type'    => 'bool',
            'group'   => 'workflow',
            'aliases' => [ 'autoReply' ],
        ],
        'show_remove_buttons'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showRemoveButtons' ],
        ],
        'show_booking_buttons'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showBookingButtons' ],
        ],
        'show_comparison_images'   => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => [ 'showComparisonImages' ],
        ],
        'rating'                   => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => [ 'filterRating', 'filter_rating' ],
        ],
    ];

    /**
     * Per-tag attribute mapping.
     * This defines which shortcode/block supports which attributes from the ALLOWLIST.
     */
    private const TAG_MAPPING = [
        'rentiva_booking_form'          => [
            'vehicle_id',
            'start_date',
            'end_date',
            'show_vehicle_selector',
            'default_days',
            'show_addons',
            'class',
            'redirect_url',
            'enable_deposit',
            'form_title',
            'show_vehicle_info',
            'show_time_select',
        ],
        'rentiva_vehicles_list'         => [
            'limit',
            'columns',
            'orderby',
            'order',
            'category',
            'featured',
            'show_image',
            'show_title',
            'show_price',
            'show_features',
            'show_rating',
            'show_booking_button',
            'show_favorite_button',
            'show_category',
            'show_brand',
            'show_description',
            'show_compare_button',
            'image_size',
            'ids',
            'max_features',
            'class',
            'custom_css_class',
            'min_rating',
            'min_reviews',
            // Card inputs of the vehicle card widget (spec 3A Task 2).
            'button_text',
            'show_rating_count',
            // The card's layout (vehicle card widget), spec 3A Task 3.
            'layout' => [
                'default' => 'list',
                'values'  => [ 'list', 'grid' ],
            ],
        ],
        'rentiva_search_results'        => [
            'layout'               => [ 'default' => 'grid' ],
            'show_filters'         => [ 'default' => '1' ],
            'results_per_page'     => [ 'default' => '12' ],
            'show_pagination'      => [ 'default' => '1' ],
            'show_sorting'         => [ 'default' => '1' ],
            'show_view_toggle'     => [ 'default' => '1' ],
            'show_favorite_button' => [ 'default' => '1' ],
            'show_compare_button'  => [ 'default' => '1' ],
            'show_booking_button'  => [ 'default' => '1' ],
            'show_price'           => [ 'default' => '1' ],
            'show_title'           => [ 'default' => '1' ],
            'show_features'        => [ 'default' => '1' ],
            'show_rating'          => [ 'default' => '1' ],
            'show_badges'          => [ 'default' => '1' ],
            // The first request's order. `values` are the sorts SearchResults'
            // switch applies (get_sorting_options()), the list every editor offers
            // (spec 3A §2.5 E, gate G3). The type stays 'string' and block.json
            // carries no enum: a value saved from a removed option (newest/
            // popularity/rating) keeps falling to the switch's relevance branch as
            // today on every surface, instead of becoming price_asc.
            'default_sort'         => [
                'default' => 'price_asc',
                'values'  => [ 'relevance', 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'year_desc' ],
            ],
            'class'                => [ 'default' => '' ],
            'button_text'          => [ 'default' => '' ],
            'show_pickup'          => [ 'default' => '1' ],
            'show_dropoff'         => [ 'default' => '1' ],
        ],
        'rentiva_availability_calendar' => [
            'vehicle_id'            => [ 'default' => '' ],
            'show_pricing'          => [ 'default' => '1' ],
            'theme'                 => [ 'default' => 'default' ],
            'class'                 => [ 'default' => '' ],
            'show_vehicle_selector' => [ 'default' => '1' ],
            'show_legend'           => [ 'default' => '1' ],
            'show_month_nav'        => [ 'default' => '1' ],
            'months_to_show'        => [ 'default' => '1' ],
            'start_week_on'         => [ 'default' => '1' ],
            'start_month'           => [ 'default' => '' ],
            'height'                => [ 'default' => '' ],
            'show_booking_button'   => [ 'default' => '1' ],
        ],
        'rentiva_vehicle_details'       => [
            'vehicle_id',
            'show_pricing',
            'show_features',
            'show_gallery',
            'show_booking',
            'class',
            'show_rating',
            'show_reviews',
            'show_similar_vehicles',
            'show_favorite_button',
            'show_compare_button',
            'similar_vehicles_limit',
            'show_calendar',
            'show_price',
            'show_booking_button',
        ],
        'rentiva_vehicles_grid'         => [
            'limit',
            'columns',
            'orderby' => [
                'values' => [ 'price', 'popularity', 'newest', 'capacity', 'title', 'date', 'modified', 'rand', 'post__in', 'rating', 'rating_average', 'rating_count', 'confidence', 'featured' ],
            ],
            'order',
            'category',
            'featured',
            'show_image',
            'show_title',
            'show_price',
            'show_features',
            'show_rating',
            'show_booking_button',
            'show_favorite_button',
            'show_category',
            'show_brand',
            'show_compare_button',
            'image_size',
            'class',
            'custom_css_class',
            'min_rating',
            'min_reviews',
            'columns_tablet',
            'columns_mobile',
            'layout',
            'filter_categories',
            'view_all_url',
            'view_all_text',
            'sort_by',
            'sort_order',
        ],
        'rentiva_vehicle_rating_form'   => [
            'vehicle_id',
            'class',
            // show_star_rating, show_text_review, show_category_ratings,
            // show_photo_upload, show_vehicle_preview, show_recommend_toggle,
            // require_login, require_booking, max_photos and min_review_length were
            // removed (spec 3A Task 8): never read; login and review length are the
            // global Comments settings, applied server side.
            'show_rating_display',
            'show_form',
            'show_ratings_list',
        ],
        'rentiva_vehicle_comparison'    => [
            'vehicle_ids'          => [ 'default' => '' ],
            // showBookButton (the block's control) belongs to show_booking_buttons, the key the
            // template reads first (vehicle-comparison.php:33); listed first, so it wins the alias.
            'show_booking_buttons' => [
                'default' => '1',
                'aliases' => [ 'showBookingButtons', 'showBookButton' ],
            ],
            'max_vehicles'         => [ 'default' => '4' ],
            'class'                => [ 'default' => '' ],
            'show_images'          => [ 'default' => '1' ],
            'show_prices'          => [ 'default' => '1' ],
            'show_book_button'     => [
                'default' => '1',
                'aliases' => [],
            ],
            'show_remove_buttons'  => [ 'default' => '1' ],
            'layout'               => [ 'default' => 'table' ],
            'title'                => [ 'default' => '' ],
        ],
        'rentiva_contact'               => [
            'type',
            'title',
            'description',
            'show_phone',
            'show_company',
            'show_vehicle_selector',
            'show_priority',
            'show_attachment',
            'redirect_url',
            'email_to',
            'auto_reply',
            'theme',
            'class',
            'show_booking_id_field',
            'show_company_info',
            'success_message',
        ],
        'rentiva_testimonials'          => [
            'limit',
            'rating',
            'vehicle_id',
            // The orders Testimonials::get_testimonials() applies; anything else
            // (the Elementor control's old title/modified) is the date order.
            'orderby' => [
                'values' => [ 'date', 'rating', 'rand' ],
            ],
            'order',
            'show_rating',
            'show_date',
            'show_vehicle',
            'show_customer',
            'layout',
            'columns',
            'auto_rotate',
            'class',
            'show_author_avatar',
            'show_quotes',
            'sort_by',
            'sort_order',
            // category, show_vehicle_name, show_author_name, limit_items and autoplay
            // were removed (spec 3A Task 8); the editor controls of the four twins
            // write show_vehicle, show_customer, limit and auto_rotate.
        ],
        'rentiva_payment_history'       => [
            'limit',
            'class',
            'show_payment_method',
            'show_date',
            'show_booking_link',
            // The normalized payment statuses (AccountRenderer::PAYMENT_STATUSES);
            // '' is no filter (the editors' "All").
            'status'  => [
                'values' => [ '', 'pending', 'completed', 'cancelled' ],
            ],
            'orderby' => [
                'values' => [ 'date', 'amount' ],
            ],
            'order',
            'show_pagination',
            'hide_nav',
            // show_invoice_download, show_transaction_id and limit_results were
            // removed (spec 3A Task 7b).
        ],
        'rentiva_my_favorites'          => [
            'limit',
            'class',
            'show_availability_status',
            'show_category',
            'show_booking_button',
            'show_favorite_button',
            // added: the saved favourites order; 'date' and 'title' are the values
            // the Elementor control saved before (MyFavorites::sort_mode()).
            'orderby' => [
                'values' => [ 'added', 'name', 'price', 'date', 'title' ],
            ],
            'order',
            'columns',
            'show_image',
            'show_title',
            'show_price',
            'show_features',
            'show_rating',
            'show_booking_button',
            'layout',
            'no_results_text',
            // show_badges, show_remove_button and show_added_date were removed
            // (spec 3A Task 7b).
        ],
        'rentiva_my_bookings'           => [
            'limit',
            'class',
            'show_image',
            'show_booking_dates',
            'show_price',
            'show_status_toggle',
            'status',
            'show_pagination',
            // orderby, limit_results, show_cancel_btn, show_modify_btn and
            // show_details_link were removed (spec 3A Task 7a).
            'order',
            'hide_nav',
        ],
        'rentiva_featured_vehicles'     => [
            'ids',
            'exclude',
            'category',
            'limit',
            'layout',
            'columns',
            'autoplay',
            'interval',
            'orderby',
            'order',
            'show_price',
            'show_rating',
            'show_category',
            // The card reads show_booking_button (vehicle-card-base.php:40); its alias
            // showBookButton is the block toggle. show_book_button was removed (spec 3A T3).
            'show_booking_button',
            'show_features',
            'max_features',
            'show_brand',
            'show_compare_button',
            'show_favorite_button',
            'image_size',
            'class',
            'filter_categories',
            'view_all_url',
            'view_all_text',
        ],
        'rentiva_unified_search'        => [
            'show_location_select',
            'show_time_select',
            'show_date_picker',
            'show_dropoff_location',
            'location_required',
            'fields_required',
            'redirect_page',
            'search_layout',
            'class',
        ],
        'rentiva_user_dashboard'        => [
            'class',
        ],
    ];

    /**
     * Returns the complete attribute schema registry.
     *
     * @return array<string, array<string, array>>
     */
    public static function get_registry(): array
    {
        $registry = [];
		foreach (self::TAG_MAPPING as $tag => $allowed_attrs_config) {
			$tag_schema = [];
			foreach ($allowed_attrs_config as $key => $config_or_key) {
				// Handle both simple ['attr1', 'attr2'] and complex ['attr1' => ['default' => 'x']]
				$attr_key       = is_int($key) ? (string) $config_or_key : $key;
				$per_tag_config = is_array($config_or_key) ? $config_or_key : [];

				// Global Allowlist is the foundation, per-tag config is the override (SSOT).
				// SchemaParityTest guarantees every TAG_MAPPING key is declared there.
				$tag_schema[ $attr_key ] = array_merge(self::ALLOWLIST[ $attr_key ], $per_tag_config);
			}
            $registry[ $tag ] = $tag_schema;
        }

        return apply_filters('mhmrentiva_attribute_registry', $registry);
    }

    /**
     * Returns schema for a specific shortcode tag.
     *
     * @param string $tag Shortcode tag.
     * @return array Schema definition.
     */
    public static function get_schema(string $tag): array
    {
        $registry = self::get_registry();
        return $registry[ $tag ] ?? [];
    }
}
