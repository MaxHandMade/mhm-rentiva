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
final class AllowlistRegistry
{

    /**
     * Canonical attributes with types and context.
     * This is the SOURCE OF TRUTH.
     */
    public const ALLOWLIST = [
        // Pagination & Sorting
        'limit'                    => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => ['limit', 'resultsPerPage', 'results_per_page'],
        ],
        'columns'                  => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => ['columns'],
        ],
        'orderby'                  => [
            'type'    => 'enum',
            'group'   => 'sorting',
            'aliases' => ['sortBy', 'orderby'],
            'values'  => ['price', 'popularity', 'newest', 'capacity', 'title', 'date', 'rand', 'rating'],
        ],
        'order'                    => [
            'type'    => 'enum',
            'group'   => 'sorting',
            'aliases' => ['sortOrder', 'order'],
            'values'  => ['asc', 'desc', 'ASC', 'DESC'],
        ],
        'results_per_page'         => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => ['resultsPerPage', 'limit'],
        ],
        'show_pagination'          => [
            'type'    => 'bool',
            'group'   => 'pagination',
            'aliases' => ['showPagination'],
        ],
        'show_sorting'             => [
            'type'    => 'bool',
            'group'   => 'sorting',
            'aliases' => ['showSorting'],
        ],
        'limit_results'            => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => ['limitResults'],
        ],
        'limit_items'              => [
            'type'    => 'int',
            'group'   => 'pagination',
            'aliases' => ['limitItems'],
        ],

        // Dimension Mappings
        'minwidth'                 => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['minWidth', 'min_width'],
        ],
        'maxwidth'                 => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['maxWidth', 'max_width'],
        ],
        'width'                    => [
            'type'  => 'string',
            'group' => 'layout',
        ],
        'height'                   => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['calendarHeight'],
        ],
        'columns_tablet'           => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => ['columnsTablet'],
        ],
        'columns_mobile'           => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => ['columnsMobile'],
        ],

        // Layout & Styles
        'layout'                   => [
            'type'  => 'string',
            'group' => 'layout',
        ],
        'style'                    => [
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
            'aliases' => ['className'],
        ],
        'custom_css_class'         => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['customClassName'],
        ],
        'image_size'               => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['imageSize'],
        ],
        'price_format'             => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['priceFormat'],
        ],
        'default_sort'             => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['defaultSort'],
        ],
        'search_layout'            => [
            'type'    => 'string',
            'group'   => 'layout',
            'aliases' => ['searchLayout'],
        ],
        'ids'                      => [
            'type'    => 'idlist',
            'group'   => 'data',
            'aliases' => ['ids', 'vehicleIds', 'vehicle_ids'],
        ],
        'redirect_page'            => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['redirect_url', 'redirectPage', 'redirectUrl'],
        ],
        'default_tab'              => [
            'type'    => 'enum',
            'group'   => 'workflow',
            'aliases' => ['defaultTab'],
        ],

        // Visibility Toggles (UI)
        'show_image'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showImage', 'showImages', 'showVehicleImage'],
        ],
        'show_title'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTitle'],
        ],
        'show_price'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPrice'],
        ],
        'show_rating'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRating', 'filterRating', 'filter_rating'],
        ],
        'show_features'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showFeatures', 'showTechnicalSpecs'],
        ],
        'show_booking_button'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookButton', 'show_booking_button', 'showBookBtn', 'show_book_btn'],
        ],
        'show_favorite_btn'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showFavoriteBtn'],
        ],
        'show_favorite_button'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showFavoriteButton'],
        ],
        'show_compare_btn'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCompareBtn'],
        ],
        'show_compare_button'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCompareButton'],
        ],
        'show_category'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCategory'],
        ],
        'show_brand'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBrand'],
        ],
        'show_badges'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBadges'],
        ],
        'show_description'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDescription'],
        ],
        'show_availability'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAvailability'],
        ],
        'show_availability_status' => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAvailabilityStatus'],
        ],
        'show_filters'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showFilters'],
        ],
        'show_view_toggle'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showViewToggle'],
        ],
        'show_legend'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showLegend'],
        ],
        'show_booking_links'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookingLinks'],
        ],
        'show_month_nav'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showMonthNavigation'],
        ],
        'show_today_btn'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTodayButton'],
        ],
        'show_week_numbers'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showWeekNumbers'],
        ],
        'months_to_show'           => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => ['monthsToShow'],
        ],
        'start_week_on'            => [
            'type'    => 'int',
            'group'   => 'workflow',
            'aliases' => ['startWeekOn'],
        ],
        'show_payment_summary'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPaymentSummary'],
        ],
        'show_pickup_instructions' => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPickupInstructions'],
        ],
        'show_contact_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showContactInfo'],
        ],
        'show_print_btn'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPrintButton', 'showPrintBtn'],
        ],
        'show_download_pdf'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDownloadPDF'],
        ],
        'show_cancel_btn'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCancelButton', 'showCancelBtn'],
        ],
        'show_insurance'           => [
            'type'  => 'bool',
            'group' => 'visibility',
        ],
        'show_subject_field'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showSubjectField'],
        ],
        'show_booking_id_field'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookingIdField'],
        ],
        'show_map'                 => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showMap'],
        ],
        'show_social_links'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showSocialLinks'],
        ],
        'show_unread_badge'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showUnreadBadge'],
        ],
        'show_thread_preview'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showThreadPreview'],
        ],
        'show_booking_link'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookingLink'],
        ],
        'show_reply_btn'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showReplyButton', 'showReplyBtn'],
        ],
        'show_booking_dates'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookingDates'],
        ],
        'status'                   => [
            'type'    => 'enum',
            'group'   => 'workflow',
            'aliases' => ['filterStatus', 'status'],
        ],
        'show_status_toggle'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showStatus'],
        ],
        'show_modify_btn'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showModifyButton', 'showModifyBtn'],
        ],
        'show_added_date'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAddedDate'],
        ],
        'show_invoice_download'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showInvoiceDownload'],
        ],
        'show_payment_method'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPaymentMethod'],
        ],
        'show_transaction_id'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTransactionId'],
        ],
        'show_route_info'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRouteInfo'],
        ],
        'show_fuel_type'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showFuelType'],
        ],
        'show_transmission'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTransmission'],
        ],
        'show_seats'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showSeats'],
        ],
        'show_reviews'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showReviews'],
        ],
        'show_similar_vehicles'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showSimilarVehicles'],
        ],
        'show_share_btns'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showShareButtons', 'showShareBtns'],
        ],
        'show_breadcrumb'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBreadcrumb'],
        ],
        'show_text_review'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTextReview'],
        ],
        'show_category_ratings'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCategoryRatings'],
        ],
        'show_photo_upload'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPhotoUpload'],
        ],
        'show_vehicle_preview'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehiclePreview'],
        ],
        'show_recommend_toggle'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRecommendToggle'],
        ],
        'require_login'            => [
            'type'    => 'bool',
            'group'   => 'workflow',
            'aliases' => ['requireLogin'],
        ],
        'require_booking'          => [
            'type'    => 'bool',
            'group'   => 'workflow',
            'aliases' => ['requireBooking'],
        ],
        'max_photos'               => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['maxPhotos'],
        ],
        'min_review_length'        => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['minReviewLength'],
        ],
        'similar_vehicles_limit'   => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['similarVehiclesLimit'],
        ],
        'recipient_email'          => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['recipientEmail'],
        ],
        'subject_prefix'           => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['subjectPrefix'],
        ],
        'success_message'          => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['successMessage'],
        ],
        'show_details_link'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDetailsLink'],
        ],
        'show_star_rating'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showStarRating'],
        ],
        'show_vehicle_details'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehicleDetails'],
        ],
        'show_luggage_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showLuggageInfo'],
        ],
        'show_passenger_count'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPassengerCount'],
        ],
        'show_phone_field'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPhoneField', 'showPhone'],
        ],
        'show_company_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCompanyInfo', 'showCompany'],
        ],
        'show_vehicle_select'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehicleSelect', 'showVehicleSelector'],
        ],
        'show_author_avatar'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAuthorAvatar', 'showAvatar'],
        ],
        'show_author_name'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAuthorName'],
        ],
        'show_vehicle_name'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehicleName'],
        ],
        'show_quotes'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showQuotes'],
        ],
        'show_vehicle_selector'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehicleSelector'],
        ],
        'show_vehicle_info'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehicleInfo'],
        ],
        'show_date_picker'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDatePicker'],
        ],
        'show_time_select'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTimeSelect', 'show_time_select'],
        ],
        'filter_brands'            => [
            'type'    => 'string',
            'group'   => 'data',
            'aliases' => ['filterBrands'],
        ],
        'show_remove_button'       => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRemoveButton'],
        ],
        'show_addons'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAddons'],
        ],
        'min_rating'               => [
            'type'    => 'int',
            'group'   => 'query',
            'aliases' => ['minRating'],
        ],
        'min_reviews'              => [
            'type'    => 'int',
            'group'   => 'query',
            'aliases' => ['minReviews'],
        ],
        'button_text'              => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => ['buttonText'],
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
        'ids'                      => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => ['ids'],
        ],
        'id'                       => [
            'type'  => 'int',
            'group' => 'feature',
        ],
        'vehicle_id'               => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['vehicleId', 'vehicle_id'],
        ],
        'vehicle_ids'              => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => ['vehicleIds'],
        ],
        'booking_id'               => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['bookingId'],
        ],
        'category'                 => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => ['filterCategories', 'category'],
        ],
        'featured'                 => [
            'type'  => 'bool',
            'group' => 'feature',
        ],

        // Date & Time
        'start_date'               => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['startDate', 'start_date'],
        ],
        'end_date'                 => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['endDate', 'end_date'],
        ],
        'default_days'             => [
            'type'    => 'int',
            'group'   => 'workflow',
            'aliases' => ['defaultDays'],
        ],
        'min_days'                 => [
            'type'    => 'int',
            'group'   => 'workflow',
            'aliases' => ['minDays'],
        ],
        'max_days'                 => [
            'type'    => 'int',
            'group'   => 'workflow',
            'aliases' => ['maxDays'],
        ],
        'months_ahead'             => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => ['monthsAhead'],
        ],

        // Sliders & Interactivity
        'autoplay'                 => [
            'type'    => 'bool',
            'group'   => 'layout',
            'aliases' => ['autoplay'],
        ],
        'interval'                 => [
            'type'    => 'int',
            'group'   => 'layout',
            'aliases' => ['interval'],
        ],
        'auto_rotate'              => [
            'type'    => 'bool',
            'group'   => 'layout',
            'aliases' => ['autoRotate'],
        ],
        'max_features'             => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['maxFeatures'],
        ],
        'max_vehicles'             => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['maxVehicles'],
        ],

        // Flags & Options
        'enable_deposit'           => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => ['enableDeposit'],
        ],
        'enable_lazy_load'         => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => ['enableLazyLoad'],
        ],
        'enable_ajax_filtering'    => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => ['enableAjaxFiltering'],
        ],
        'enable_infinite_scroll'   => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => ['enableInfiniteScroll'],
        ],
        'show_payment_options'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPaymentOptions'],
        ],
        'default_payment'          => [
            'type'    => 'enum',
            'group'   => 'workflow',
            'aliases' => ['defaultPayment'],
        ],
        'redirect_url'             => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['redirectUrl'],
        ],
        'redirect_page'            => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['redirectPage'],
        ],
        'form_title'               => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => ['formTitle'],
        ],
        'no_results_text'          => [
            'type'    => 'string',
            'group'   => 'content',
            'aliases' => ['noResultsText'],
        ],

        // Search Specific
        'default_tab'              => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => ['defaultTab'],
        ],
        'default_tab_alias'        => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => ['defaultTabAlias'],
        ],
        'show_rental_tab'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRentalTab'],
        ],
        'show_transfer_tab'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showTransferTab'],
        ],
        'show_location_select'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showLocationSelect'],
        ],
        'show_dropoff_location'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDropoffLocation'],
        ],
        'start_month'              => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['startMonth'],
        ],
        'show_pax'                 => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPax'],
        ],
        'show_luggage'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showLuggage'],
        ],
        'service_type'             => [
            'type'    => 'enum',
            'group'   => 'feature',
            'aliases' => ['serviceType'],
        ],
        'filter_categories'        => [
            'type'    => 'string',
            'group'   => 'feature',
            'aliases' => ['filterCategories'],
        ],

        // Navigation
        'hide_nav'                 => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['hideNavigation'],
        ],

        // Generic Visibility
        'show_gallery'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showGallery'],
        ],
        'show_pricing'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPricing'],
        ],
        'show_booking'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBooking'],
        ],
        'show_booking_button'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookButton'],
        ],
        'show_booking_btn'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookButton'],
        ],
        'show_favorite_btn'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['show_favorite_button'],
        ],
        'show_compare_btn'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['show_compare_button'],
        ],
        'show_prices'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['show_price', 'showPrice'],
        ],
        'show_images'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showComparisonImages'],
        ],
        'show_calendar'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCalendar'],
        ],
        'show_weekends'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showWeekends'],
        ],
        'show_past_dates'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPastDates'],
        ],
        'show_details'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDetails', 'showBookingDetails'],
        ],
        'show_actions'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showActions'],
        ],
        'show_pickup'              => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPickup'],
        ],
        'show_dropoff'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDropoff'],
        ],
        'show_date'                => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDate'],
        ],
        'show_vehicle'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showVehicle'],
        ],
        'show_customer'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCustomer'],
        ],
        'show_rating_display'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRatingDisplay'],
        ],
        'show_form'                => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showForm'],
        ],
        'show_ratings_list'        => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRatingsList'],
        ],

        // Mixed Feature Specific
        'type'                     => [
            'type'  => 'enum',
            'group' => 'feature',
        ],
        'show_phone'               => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPhone'],
        ],
        'show_company'             => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showCompany'],
        ],
        'show_priority'            => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showPriority'],
        ],
        'show_attachment'          => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAttachment'],
        ],
        'email_to'                 => [
            'type'    => 'string',
            'group'   => 'workflow',
            'aliases' => ['emailTo'],
        ],
        'auto_reply'               => [
            'type'    => 'bool',
            'group'   => 'workflow',
            'aliases' => ['autoReply'],
        ],
        'manual_add'               => [
            'type'    => 'bool',
            'group'   => 'workflow',
            'aliases' => ['manualAdd'],
        ],
        'show_add_vehicle'         => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showAddVehicle'],
        ],
        'show_remove_buttons'      => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showRemoveButtons'],
        ],
        'show_booking_buttons'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showBookingButtons'],
        ],
        'show_comparison_images'    => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showComparisonImages'],
        ],
        'integrate_pricing'        => [
            'type'    => 'bool',
            'group'   => 'feature',
            'aliases' => ['integratePricing'],
        ],
        'show_seasonal_prices'     => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showSeasonalPrices'],
        ],
        'show_discounts'           => [
            'type'    => 'bool',
            'group'   => 'visibility',
            'aliases' => ['showDiscounts'],
        ],
        'months_to_show'           => [
            'type'    => 'int',
            'group'   => 'feature',
            'aliases' => ['monthsToShow'],
        ],
        'rating'                   => [
            'type'  => 'int',
            'group' => 'feature',
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
            'min_days',
            'max_days',
            'show_payment_options',
            'show_addons',
            'class',
            'redirect_url',
            'enable_deposit',
            'default_payment',
            'form_title',
            'show_vehicle_info',
            'show_time_select',
            'show_insurance',
            'show_date_picker',
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
            'show_booking_btn',
            'show_favorite_btn',
            'show_favorite_button',
            'show_category',
            'show_brand',
            'show_badges',
            'show_description',
            'show_availability',
            'show_compare_btn',
            'show_compare_button',
            'enable_lazy_load',
            'enable_ajax_filtering',
            'enable_infinite_scroll',
            'image_size',
            'ids',
            'max_features',
            'price_format',
            'class',
            'custom_css_class',
            'min_rating',
            'min_reviews',
            'filter_brands',
        ],
        'rentiva_search_results'        => [
            'layout'           => ['default' => 'grid'],
            'show_filters'     => ['default' => '1'],
            'results_per_page' => ['default' => '12'],
            'limit'            => ['default' => '12'],
            'show_pagination'  => ['default' => '1'],
            'show_sorting'     => ['default' => '1'],
            'show_view_toggle' => ['default' => '1'],
            'show_favorite_button' => ['default' => '1'],
            'show_compare_button'  => ['default' => '1'],
            'show_booking_button'  => ['default' => '1'],
            'show_booking_btn'     => ['default' => '1'],
            'show_price'       => ['default' => '1'],
            'show_title'       => ['default' => '1'],
            'show_features'    => ['default' => '1'],
            'show_rating'      => ['default' => '1'],
            'show_badges'      => ['default' => '1'],
            'default_sort'     => ['default' => 'newest'],
            'class'            => ['default' => ''],
            'show_availability' => ['default' => '1'],
            'orderby'          => ['default' => 'date'],
            'order'            => ['default' => 'DESC'],
            'button_text'      => ['default' => ''],
            'show_pickup'      => ['default' => '1'],
            'show_dropoff'     => ['default' => '1'],
        ],
        'rentiva_transfer_results'      => [
            'layout'               => ['default' => 'list'],
            'columns'              => ['default' => 2],
            'orderby'              => ['default' => 'price'],
            'order'                => ['default' => 'asc'],
            'limit'                => ['default' => '10'],
            'show_passenger_count' => ['default' => true],
            'show_luggage_info'    => ['default' => true],
            'show_price'           => ['default' => true],
            'show_booking_button'  => ['default' => true],
            'show_vehicle_details' => ['default' => true],
            'show_route_info'      => ['default' => true],
            'class'                => ['default' => ''],
            'show_favorite_button' => ['default' => '1'],
            'show_compare_button'  => ['default' => '1'],
        ],
        'rentiva_availability_calendar' => [
            'vehicle_id'           => ['default' => ''],
            'show_pricing'         => ['default' => '1'],
            'theme'                => ['default' => 'default'],
            'start_date'           => ['default' => ''],
            'months_ahead'         => ['default' => '3'],
            'show_weekends'        => ['default' => '1'],
            'show_past_dates'      => ['default' => '0'],
            'class'                => ['default' => ''],
            'show_vehicle_selector' => ['default' => '0'],
            'show_legend'          => ['default' => '1'],
            'show_booking_links'   => ['default' => '1'],
            'show_month_nav'       => ['default' => '1'],
            'show_today_btn'       => ['default' => '1'],
            'show_week_numbers'    => ['default' => '0'],
            'months_to_show'       => ['default' => '1'],
            'start_week_on'        => ['default' => '1'],
            'start_month'          => ['default' => ''],
            'height'               => ['default' => 'auto'],
            'show_seasonal_prices' => ['default' => '1'],
            'show_discounts'       => ['default' => '1'],
            'show_booking_button'  => ['default' => '1'],
            'show_booking_btn'     => ['default' => '1'],
            'integrate_pricing'    => ['default' => '1'],
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
            'show_technical_specs',
            'show_availability',
            'show_booking_form',
            'show_similar_vehicles',
            'show_share_btns',
            'show_favorite_button',
            'show_breadcrumb',
            'similar_vehicles_limit',
            'show_calendar',
            'show_price',
            'show_booking_button',
        ],
        'rentiva_vehicles_grid'         => [
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
            'show_booking_btn',
            'show_favorite_btn',
            'show_favorite_button',
            'show_category',
            'show_brand',
            'show_badges',
            'show_description',
            'show_availability',
            'show_compare_btn',
            'show_compare_button',
            'enable_lazy_load',
            'enable_ajax_filtering',
            'enable_infinite_scroll',
            'image_size',
            'class',
            'custom_css_class',
            'min_rating',
            'min_reviews',
            'columns_tablet',
            'columns_mobile',
            'layout',
            'filter_categories',
            'filter_brands',
        ],
        'rentiva_vehicle_rating_form'   => [
            'vehicle_id',
            'class',
            'show_star_rating',
            'show_text_review',
            'show_category_ratings',
            'show_photo_upload',
            'show_vehicle_preview',
            'show_recommend_toggle',
            'require_login',
            'require_booking',
            'max_photos',
            'min_review_length',
            'show_rating_display',
            'show_form',
            'show_ratings_list',
        ],
        'rentiva_vehicle_comparison'    => [
            'vehicle_ids'          => ['default' => ''],
            'show_booking_buttons' => ['default' => '1'],
            'max_vehicles'         => ['default' => '4'],
            'class'                => ['default' => ''],
            'show_technical_specs' => ['default' => 'all'],
            'show_images'          => ['default' => '1'],
            'show_prices'          => ['default' => '1'],
            'show_rating'          => ['default' => '1'],
            'show_book_button'     => ['default' => '1'],
            'show_category'        => ['default' => '1'],
            'show_fuel_type'       => ['default' => '1'],
            'show_transmission'    => ['default' => '1'],
            'show_seats'           => ['default' => '1'],
            'show_features'        => ['default' => 'all'],
            'show_add_vehicle'     => ['default' => '1'],
            'show_remove_buttons'  => ['default' => '1'],
            'layout'               => ['default' => 'table'],
            'title'                => ['default' => ''],
            'manual_add'           => ['default' => '0'],
        ],
        'rentiva_booking_confirmation'  => [
            'booking_id',
            'show_details',
            'show_actions',
            'class',
            'show_vehicle_info',
            'show_payment_summary',
            'show_pickup_instructions',
            'show_contact_info',
            'show_print_btn',
            'show_download_pdf',
            'show_cancel_btn',
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
            'show_phone_field',
            'show_subject_field',
            'show_booking_id_field',
            'show_vehicle_select',
            'show_company_info',
            'show_map',
            'show_social_links',
            'recipient_email',
            'subject_prefix',
            'success_message',
        ],
        'rentiva_testimonials'          => [
            'limit',
            'category',
            'rating',
            'vehicle_id',
            'orderby',
            'order',
            'show_rating',
            'show_date',
            'show_vehicle',
            'show_vehicle_name',
            'show_customer',
            'layout',
            'columns',
            'auto_rotate',
            'class',
            'show_author_avatar',
            'show_author_name',
            'show_quotes',
            'filter_rating',
            'sort_by',
            'sort_order',
            'limit_items',
            'autoplay',
        ],
        'rentiva_payment_history'       => [
            'limit',
            'class',
            'show_invoice_download',
            'show_payment_method',
            'show_transaction_id',
            'show_date',
            'show_booking_link',
            'status',
            'orderby',
            'order',
            'limit_results',
            'show_pagination',
            'hide_nav',
        ],
        'rentiva_my_favorites'          => [
            'limit',
            'class',
            'show_availability_status',
            'show_category',
            'show_remove_button',
            'show_booking_button',
            'show_added_date',
            'orderby',
            'order',
            'columns',
            'show_image',
            'show_title',
            'show_price',
            'show_features',
            'show_rating',
            'show_booking_button',
            'show_booking_btn',
            'show_favorite_btn',
            'show_badges',
            'layout',
            'no_results_text',
        ],
        'rentiva_my_bookings'           => [
            'limit',
            'class',
            'show_image',
            'show_booking_dates',
            'show_price',
            'show_status_toggle',
            'show_cancel_btn',
            'show_modify_btn',
            'show_details_link',
            'status',
            'limit_results',
            'show_pagination',
            'orderby',
            'order',
            'hide_nav',
        ],
        'rentiva_messages'              => [
            'limit',
            'class',
            'show_date',
            'show_avatar',
            'show_unread_badge',
            'show_thread_preview',
            'show_booking_link',
            'show_reply_btn',
            'status',
            'orderby',
            'order',
            'limit_items',
            'show_pagination',
            'hide_nav',
            'show_author_avatar',
        ],
        'rentiva_featured_vehicles'     => [
            'title',
            'ids',
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
            'show_book_button',
            'show_booking_button',
            'show_features',
            'max_features',
            'show_brand',
            'show_availability',
            'show_compare_button',
            'show_favorite_button',
            'show_badges',
            'image_size',
            'price_format',
            'class',
            'service_type',
            'filter_brands',
            'filter_categories',
        ],
        'rentiva_unified_search'        => [
            'default_tab',
            'default_tab_alias',
            'show_rental_tab',
            'show_transfer_tab',
            'show_location_select',
            'show_time_select',
            'show_date_picker',
            'show_dropoff_location',
            'show_pax',
            'show_luggage',
            'service_type',
            'filter_categories',
            'redirect_page',
            'layout',
            'search_layout',
            'style',
            'class',
            'minwidth',
            'maxwidth',
        ],
        'rentiva_transfer_search'       => [
            'layout',
            'class',
            'button_text',
            'show_pickup',
            'show_dropoff',
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
                $attr_key = is_int($key) ? $config_or_key : $key;
                $per_tag_config = is_array($config_or_key) ? $config_or_key : [];

                if (isset(self::ALLOWLIST[$attr_key])) {
                    // Global Allowlist is the foundation, per-tag config is the override (SSOT)
                    $tag_schema[$attr_key] = array_merge(self::ALLOWLIST[$attr_key], $per_tag_config);
                } else {
                    // Allow attributes defined ONLY in TAG_MAPPING
                    $tag_schema[$attr_key] = $per_tag_config;
                }
            }
            $registry[$tag] = $tag_schema;
        }

        return apply_filters('mhm_rentiva_attribute_registry', $registry);
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
        return $registry[$tag] ?? [];
    }
}
