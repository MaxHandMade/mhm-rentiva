# MHM Rentiva Shortcodes and Gutenberg Block Parameters

This document is the source reference for:
- Active shortcode inventory
- Planned shortcode inventory
- Classic shortcode parameters (canonical)
- Gutenberg block inspector parameters

Data sources:
- `src/Admin/Core/ShortcodeServiceProvider.php` (active shortcode registry)
- `src/Core/Attribute/AllowlistRegistry.php` (canonical shortcode params)
- `assets/blocks/*/block.json` (block inspector params)

---
## Architectural Invariant: The Render Parity Rule
**There is NO dual rendering pipeline.** 
All block attributes MUST fall back to shortcode canonical defaults. 
The system uses a single unified rendering path where Gutenberg blocks act purely as a UI configuration layer that feeds into the canonical Shortcode render methods.

---

## 1. Active Shortcodes

| Shortcode | Group | Auth Required | Notes |
| :--- | :--- | :---: | :--- |
| `rentiva_booking_form` | reservation | No | Booking form |
| `rentiva_availability_calendar` | reservation | No | Availability calendar |
| `rentiva_booking_confirmation` | reservation | No | Booking confirmation |
| `rentiva_vehicle_details` | vehicle | No | Vehicle details |
| `rentiva_vehicles_list` | vehicle | No | Vehicles list |
| `rentiva_featured_vehicles` | vehicle | No | Featured vehicles |
| `rentiva_vehicles_grid` | vehicle | No | Vehicles grid |
| `rentiva_search_results` | vehicle | No | Search results |
| `rentiva_vehicle_comparison` | vehicle | No | Vehicle comparison |
| `rentiva_unified_search` | vehicle | No | Unified search |
| `rentiva_my_bookings` | account | Yes | Account bookings |
| `rentiva_my_favorites` | account | Yes | Account favorites |
| `rentiva_payment_history` | account | Yes | Account payments |
| `rentiva_transfer_search` | transfer | No | Transfer search form |
| `rentiva_transfer_results` | transfer | No | Transfer results |
| `rentiva_contact` | support | No | Contact form |
| `rentiva_testimonials` | support | No | Testimonials |
| `rentiva_vehicle_rating_form` | support | No | Vehicle rating form |
| `rentiva_messages` | support | Yes | Account messages |
| `rentiva_home_poc` | support | No | Experimental POC shortcode |

---

## 2. Planned / Not Active Shortcodes

The following are planned/legacy references and are not active in current registry:
- `rentiva_hero_section`
- `rentiva_trust_stats`
- `rentiva_brand_scroller`
- `rentiva_comparison_table`
- `rentiva_extra_services`

---

## 3. Classic Shortcode Parameters (Canonical)

Note:
- Canonical names below are from `AllowlistRegistry`.
- Some values are also accepted via aliases (camelCase or legacy forms).
- For defaults, see the runtime schema in `AllowlistRegistry` and shortcode implementations.

### 3.1 Reservation

#### `[rentiva_booking_form]`
`class`, `default_days`, `default_payment`, `enable_deposit`, `end_date`, `form_title`, `max_days`, `min_days`, `redirect_url`, `show_addons`, `show_date_picker`, `show_insurance`, `show_payment_options`, `show_time_select`, `show_vehicle_info`, `show_vehicle_selector`, `start_date`, `vehicle_id`

#### `[rentiva_availability_calendar]`
`class`, `height`, `integrate_pricing`, `months_ahead`, `months_to_show`, `show_booking_btn`, `show_booking_links`, `show_discounts`, `show_legend`, `show_month_nav`, `show_past_dates`, `show_pricing`, `show_seasonal_prices`, `show_today_btn`, `show_vehicle_selector`, `show_week_numbers`, `show_weekends`, `start_date`, `start_month`, `start_week_on`, `theme`, `vehicle_id`

#### `[rentiva_booking_confirmation]`
`booking_id`, `class`, `show_actions`, `show_cancel_btn`, `show_contact_info`, `show_details`, `show_download_pdf`, `show_payment_summary`, `show_pickup_instructions`, `show_print_btn`, `show_vehicle_info`

### 3.2 Vehicle

#### `[rentiva_vehicle_details]`
`class`, `show_availability`, `show_booking`, `show_booking_button`, `show_booking_form`, `show_breadcrumb`, `show_calendar`, `show_favorite_button`, `show_features`, `show_gallery`, `show_price`, `show_pricing`, `show_rating`, `show_reviews`, `show_share_btns`, `show_similar_vehicles`, `show_technical_specs`, `similar_vehicles_limit`, `vehicle_id`

#### `[rentiva_vehicles_list]`
`category`, `class`, `columns`, `custom_css_class`, `enable_ajax_filtering`, `enable_infinite_scroll`, `enable_lazy_load`, `featured`, `filter_brands`, `ids`, `image_size`, `limit`, `max_features`, `min_rating`, `min_reviews`, `order`, `orderby`, `price_format`, `show_availability`, `show_badges`, `show_booking_btn`, `show_brand`, `show_category`, `show_compare_btn`, `show_compare_button`, `show_description`, `show_favorite_btn`, `show_favorite_button`, `show_features`, `show_image`, `show_price`, `show_rating`, `show_title`

#### `[rentiva_featured_vehicles]`
`autoplay`, `category`, `class`, `columns`, `filter_brands`, `filter_categories`, `ids`, `image_size`, `interval`, `layout`, `limit`, `max_features`, `order`, `orderby`, `price_format`, `service_type`, `show_availability`, `show_badges`, `show_book_button`, `show_booking_button`, `show_brand`, `show_category`, `show_compare_button`, `show_favorite_button`, `show_features`, `show_price`, `show_rating`, `title`

#### `[rentiva_vehicles_grid]`
`category`, `class`, `columns`, `columns_mobile`, `columns_tablet`, `custom_css_class`, `enable_ajax_filtering`, `enable_infinite_scroll`, `enable_lazy_load`, `featured`, `filter_brands`, `filter_categories`, `image_size`, `layout`, `limit`, `min_rating`, `min_reviews`, `order`, `orderby`, `show_availability`, `show_badges`, `show_booking_btn`, `show_brand`, `show_category`, `show_compare_btn`, `show_compare_button`, `show_description`, `show_favorite_btn`, `show_favorite_button`, `show_features`, `show_image`, `show_price`, `show_rating`, `show_title`

#### `[rentiva_search_results]`
`button_text`, `class`, `default_sort`, `layout`, `limit`, `order`, `orderby`, `results_per_page`, `show_availability`, `show_badges`, `show_booking_btn`, `show_compare_button`, `show_dropoff`, `show_favorite_button`, `show_features`, `show_filters`, `show_pagination`, `show_pickup`, `show_price`, `show_rating`, `show_sorting`, `show_title`, `show_view_toggle`

#### `[rentiva_vehicle_comparison]`
`class`, `layout`, `manual_add`, `max_vehicles`, `show_add_vehicle`, `show_book_button`, `show_booking_buttons`, `show_category`, `show_features`, `show_fuel_type`, `show_images`, `show_prices`, `show_rating`, `show_remove_buttons`, `show_seats`, `show_technical_specs`, `show_transmission`, `title`, `vehicle_ids`

#### `[rentiva_unified_search]`
`class`, `default_tab`, `default_tab_alias`, `filter_categories`, `layout`, `maxwidth`, `minwidth`, `redirect_page`, `search_layout`, `service_type`, `show_date_picker`, `show_dropoff_location`, `show_location_select`, `show_luggage`, `show_pax`, `show_rental_tab`, `show_time_select`, `show_transfer_tab`, `style`

### 3.3 Account

#### `[rentiva_my_bookings]`
`class`, `hide_nav`, `limit`, `limit_results`, `order`, `orderby`, `show_booking_dates`, `show_cancel_btn`, `show_details_link`, `show_image`, `show_modify_btn`, `show_pagination`, `show_price`, `show_status_toggle`, `status`

#### `[rentiva_my_favorites]`
`class`, `columns`, `layout`, `limit`, `no_results_text`, `order`, `orderby`, `show_added_date`, `show_availability_status`, `show_badges`, `show_booking_btn`, `show_booking_button`, `show_category`, `show_favorite_btn`, `show_features`, `show_image`, `show_price`, `show_rating`, `show_remove_button`, `show_title`

#### `[rentiva_payment_history]`
`class`, `hide_nav`, `limit`, `limit_results`, `order`, `orderby`, `show_booking_link`, `show_date`, `show_invoice_download`, `show_pagination`, `show_payment_method`, `show_transaction_id`, `status`

#### `[rentiva_messages]`
`class`, `hide_nav`, `limit`, `limit_items`, `order`, `orderby`, `show_author_avatar`, `show_avatar`, `show_booking_link`, `show_date`, `show_pagination`, `show_reply_btn`, `show_thread_preview`, `show_unread_badge`, `status`

### 3.4 Transfer

#### `[rentiva_transfer_search]`
`button_text`, `class`, `layout`, `show_dropoff`, `show_pickup`

#### `[rentiva_transfer_results]`
`class`, `columns`, `layout`, `limit`, `order`, `orderby`, `show_booking_button`, `show_compare_button`, `show_favorite_button`, `show_luggage_info`, `show_passenger_count`, `show_price`, `show_route_info`, `show_vehicle_details`

### 3.5 Support

#### `[rentiva_contact]`
`auto_reply`, `class`, `description`, `email_to`, `recipient_email`, `redirect_url`, `show_attachment`, `show_booking_id_field`, `show_company`, `show_company_info`, `show_map`, `show_phone`, `show_phone_field`, `show_priority`, `show_social_links`, `show_subject_field`, `show_vehicle_select`, `show_vehicle_selector`, `subject_prefix`, `success_message`, `theme`, `title`, `type`

#### `[rentiva_testimonials]`
`auto_rotate`, `autoplay`, `class`, `columns`, `filter_rating`, `layout`, `limit`, `limit_items`, `rating`, `show_author_avatar`, `show_author_name`, `show_customer`, `show_date`, `show_quotes`, `show_rating`, `show_vehicle`, `show_vehicle_name`, `sort_by`, `sort_order`, `vehicle_id`

#### `[rentiva_vehicle_rating_form]`
`class`, `max_photos`, `min_review_length`, `require_booking`, `require_login`, `show_category_ratings`, `show_form`, `show_photo_upload`, `show_rating_display`, `show_ratings_list`, `show_recommend_toggle`, `show_star_rating`, `show_text_review`, `show_vehicle_preview`, `vehicle_id`

#### `[rentiva_home_poc]`
No canonical schema entry found in current `AllowlistRegistry` (experimental).

---

## 4. Gutenberg Block Inspector Parameters

### 4.1 Active Blocks and Attributes

| Block | Shortcode | Inspector Attributes (`block.json`) |
| :--- | :--- | :--- |
| `mhm-rentiva/availability-calendar` | `rentiva_availability_calendar` | `vehicleId`, `showVehicleSelector`, `showLegend`, `showPricing`, `showBookingLinks`, `showMonthNavigation`, `showTodayButton`, `showWeekNumbers`, `monthsToShow`, `startWeekOn`, `calendarHeight`, `className` |
| `mhm-rentiva/booking-confirmation` | `rentiva_booking_confirmation` | `bookingId`, `showBookingDetails`, `showVehicleInfo`, `showPaymentSummary`, `showPickupInstructions`, `showContactInfo`, `showPrintButton`, `showDownloadPDF`, `showCancelButton`, `className` |
| `mhm-rentiva/booking-form` | `rentiva_booking_form` | `vehicle_id`, `show_insurance`, `show_addons`, `show_vehicle_selector`, `form_title`, `enable_deposit`, `show_vehicle_info`, `show_payment_options`, `show_date_picker`, `show_time_select`, `className`, `startDate`, `endDate`, `defaultDays`, `minDays`, `maxDays`, `redirectUrl`, `defaultPayment` |
| `mhm-rentiva/contact` | `rentiva_contact` | `showPhoneField`, `showSubjectField`, `showBookingIdField`, `showVehicleSelect`, `showCompanyInfo`, `showMap`, `showSocialLinks`, `recipientEmail`, `subjectPrefix`, `successMessage`, `className` |
| `mhm-rentiva/featured-vehicles` | `rentiva_featured_vehicles` | `layout`, `showPrice`, `showRating`, `showCategory`, `showBookButton`, `showFeatures`, `showBrand`, `showAvailability`, `showCompareButton`, `showBadges`, `showFavoriteButton`, `serviceType`, `filterCategories`, `filterBrands`, `sortBy`, `sortOrder`, `limit`, `columns`, `className` |
| `mhm-rentiva/messages` | `rentiva_messages` | `showDate`, `showAuthorAvatar`, `showUnreadBadge`, `showThreadPreview`, `showBookingLink`, `showReplyButton`, `filterStatus`, `sortBy`, `sortOrder`, `limitItems`, `showPagination`, `className` |
| `mhm-rentiva/my-bookings` | `rentiva_my_bookings` | `showVehicleImage`, `showBookingDates`, `showPrice`, `showStatus`, `showCancelButton`, `showModifyButton`, `showDetailsLink`, `filterStatus`, `sortBy`, `sortOrder`, `limitResults`, `showPagination`, `className` |
| `mhm-rentiva/my-favorites` | `rentiva_my_favorites` | `layout`, `showPrice`, `showAvailabilityStatus`, `showCategory`, `showRemoveButton`, `showBookButton`, `showRating`, `showAddedDate`, `sortBy`, `sortOrder`, `columns`, `className` |
| `mhm-rentiva/payment-history` | `rentiva_payment_history` | `showInvoiceDownload`, `showPaymentMethod`, `showTransactionId`, `showDate`, `showBookingLink`, `filterStatus`, `sortBy`, `sortOrder`, `limitResults`, `showPagination`, `className` |
| `mhm-rentiva/search-results` | `rentiva_search_results` | `layout`, `showPrice`, `showRating`, `showFilters`, `showSorting`, `showPagination`, `showAvailability`, `sortBy`, `sortOrder`, `limit`, `showFavoriteButton`, `showCompareButton`, `className` |
| `mhm-rentiva/testimonials` | `rentiva_testimonials` | `layout`, `showDate`, `showAuthorAvatar`, `showAuthorName`, `showRating`, `showVehicleName`, `showQuotes`, `filterRating`, `sortBy`, `sortOrder`, `limitItems`, `columns`, `autoplay`, `className` |
| `mhm-rentiva/transfer-results` | `rentiva_transfer_results` | `layout`, `showPrice`, `showVehicleDetails`, `showLuggageInfo`, `showPassengerCount`, `showBookButton`, `showRouteInfo`, `sortBy`, `sortOrder`, `limit`, `showFavoriteButton`, `showCompareButton`, `className` |
| `mhm-rentiva/transfer-search` | `rentiva_transfer_search` | `layout`, `buttonText`, `showPickup`, `showDropoff`, `className` |
| `mhm-rentiva/unified-search` | `rentiva_unified_search` | `search_layout`, `default_tab`, `show_rental_tab`, `show_transfer_tab`, `show_location_select`, `show_time_select`, `show_date_picker`, `show_dropoff_location`, `show_pax`, `show_luggage`, `service_type`, `filter_categories`, `redirect_page`, `min_width`, `max_width`, `className`, `style` |
| `mhm-rentiva/vehicle-comparison` | `rentiva_vehicle_comparison` | `vehicleIds`, `showTechnicalSpecs`, `showComparisonImages`, `showPrice`, `showRating`, `showFeatures`, `showBookButton`, `showCategory`, `showFuelType`, `showTransmission`, `showSeats`, `maxVehicles`, `className` |
| `mhm-rentiva/vehicle-details` | `rentiva_vehicle_details` | `vehicleId`, `showGallery`, `showPrice`, `showRating`, `showReviews`, `showFeatures`, `showTechnicalSpecs`, `showAvailability`, `showBookingForm`, `showSimilarVehicles`, `showShareButtons`, `showFavoriteButton`, `showBreadcrumb`, `similarVehiclesLimit`, `className` |
| `mhm-rentiva/vehicle-rating-form` | `rentiva_vehicle_rating_form` | `vehicleId`, `showStarRating`, `showTextReview`, `showCategoryRatings`, `showPhotoUpload`, `showVehiclePreview`, `showRecommendToggle`, `requireLogin`, `requireBooking`, `maxPhotos`, `minReviewLength`, `className` |
| `mhm-rentiva/vehicles-grid` | `rentiva_vehicles_grid` | `layout`, `showImage`, `showTitle`, `showPrice`, `showRating`, `showCategory`, `showBrand`, `showAvailability`, `showFavoriteButton`, `showCompareButton`, `showBookButton`, `showFeatures`, `filterCategories`, `filterBrands`, `sortBy`, `sortOrder`, `limit`, `columns`, `className`, `minRating`, `minReviews`, `enableAjaxFiltering` |
| `mhm-rentiva/vehicles-list` | `rentiva_vehicles_list` | `showPrice`, `showRating`, `showCategory`, `showBrand`, `showBookButton`, `showDescription`, `showFeatures`, `showImage`, `showTitle`, `showBadges`, `showFavoriteButton`, `showAvailability`, `showCompareButton`, `filterCategories`, `filterBrands`, `orderby`, `order`, `limit`, `className`, `minRating`, `minReviews`, `enableAjaxFiltering` |


### 4.2 3-Layer Default Comparison Matrix

This matrix enforces the Render Parity Rule. It compares the explicit defaults across the three architectural layers for all blocks/shortcodes.

#### `mhm-rentiva/booking-form` -> `[rentiva_booking_form]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `defaultPayment` | `deposit` | *N/A* | Schema: enum (maps to `default_payment`) | ⚠️ Block Only |
| `default_days` | *N/A* | `1` | Schema: int | ⚠️ SC Only |
| `default_payment` | *N/A* | `deposit` | Schema: enum | ⚠️ SC Only |
| `enable_deposit` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `endDate` | `""` | *N/A* | Schema: string (maps to `end_date`) | ⚠️ Block Only |
| `end_date` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `form_title` | `""` | `""` | Schema: string | ✅ |
| `max_days` | *N/A* | `30` | Schema: int | ⚠️ SC Only |
| `min_days` | *N/A* | `1` | Schema: int | ⚠️ SC Only |
| `redirectUrl` | `""` | *N/A* | Schema: string (maps to `redirect_url`) | ⚠️ Block Only |
| `redirect_url` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `show_addons` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_date_picker` | `true` | *N/A* | Schema: bool | ⚠️ Block Only |
| `show_insurance` | `true` | *N/A* | Schema: bool | ⚠️ Block Only |
| `show_payment_options` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_time_select` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_vehicle_info` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_vehicle_selector` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `startDate` | `""` | *N/A* | Schema: string (maps to `start_date`) | ⚠️ Block Only |
| `start_date` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `vehicle_id` | `""` | `""` | Schema: int | ✅ |

#### `mhm-rentiva/availability-calendar` -> `[rentiva_availability_calendar]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `calendarHeight` | `auto` | *N/A* | Schema: string (maps to `height`) | ⚠️ Block Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `integrate_pricing` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `monthsToShow` | `2` | *N/A* | Schema: int (maps to `months_to_show`) | ⚠️ Block Only |
| `months_ahead` | *N/A* | `3` | Schema: int | ⚠️ SC Only |
| `months_to_show` | *N/A* | `1` | Schema: int | ⚠️ SC Only |
| `showBookingLinks` | `true` | *N/A* | Schema: bool (maps to `show_booking_links`) | ⚠️ Block Only |
| `showLegend` | `true` | *N/A* | Schema: bool (maps to `show_legend`) | ⚠️ Block Only |
| `showMonthNavigation` | `true` | *N/A* | Schema: bool (maps to `show_month_nav`) | ⚠️ Block Only |
| `showPricing` | `true` | *N/A* | Schema: bool (maps to `show_pricing`) | ⚠️ Block Only |
| `showTodayButton` | `true` | *N/A* | Schema: bool (maps to `show_today_btn`) | ⚠️ Block Only |
| `showVehicleSelector` | `true` | *N/A* | Schema: bool (maps to `show_vehicle_select`) | ⚠️ Block Only |
| `showWeekNumbers` | `false` | *N/A* | Schema: bool (maps to `show_week_numbers`) | ⚠️ Block Only |
| `show_booking_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_discounts` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_past_dates` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_pricing` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_seasonal_prices` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_weekends` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `startWeekOn` | `1` | *N/A* | Schema: int (maps to `start_week_on`) | ⚠️ Block Only |
| `start_date` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `start_month` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `theme` | *N/A* | `default` | Schema: string | ⚠️ SC Only |
| `vehicleId` | `""` | *N/A* | Schema: int (maps to `vehicle_id`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/booking-confirmation` -> `[rentiva_booking_confirmation]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `bookingId` | `""` | *N/A* | Schema: int (maps to `booking_id`) | ⚠️ Block Only |
| `booking_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `showBookingDetails` | `true` | *N/A* | Schema: bool (maps to `show_details`) | ⚠️ Block Only |
| `showCancelButton` | `false` | *N/A* | Schema: bool (maps to `show_cancel_btn`) | ⚠️ Block Only |
| `showContactInfo` | `true` | *N/A* | Schema: bool (maps to `show_contact_info`) | ⚠️ Block Only |
| `showDownloadPDF` | `true` | *N/A* | Schema: bool (maps to `show_download_pdf`) | ⚠️ Block Only |
| `showPaymentSummary` | `true` | *N/A* | Schema: bool (maps to `show_payment_summary`) | ⚠️ Block Only |
| `showPickupInstructions` | `true` | *N/A* | Schema: bool (maps to `show_pickup_instructions`) | ⚠️ Block Only |
| `showPrintButton` | `true` | *N/A* | Schema: bool (maps to `show_print_btn`) | ⚠️ Block Only |
| `showVehicleInfo` | `true` | *N/A* | Schema: bool (maps to `show_vehicle_info`) | ⚠️ Block Only |
| `show_actions` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_details` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |

#### `mhm-rentiva/vehicle-details` -> `[rentiva_vehicle_details]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `showAvailability` | `true` | *N/A* | Schema: bool (maps to `show_availability`) | ⚠️ Block Only |
| `showBookingForm` | `true` | *N/A* | *Unregistered* | ⚠️ Block Only 🚫 Not in Allowlist! |
| `showBreadcrumb` | `true` | *N/A* | Schema: bool (maps to `show_breadcrumb`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showGallery` | `true` | *N/A* | Schema: bool (maps to `show_gallery`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showReviews` | `true` | *N/A* | Schema: bool (maps to `show_reviews`) | ⚠️ Block Only |
| `showShareButtons` | `true` | *N/A* | Schema: bool (maps to `show_share_btns`) | ⚠️ Block Only |
| `showSimilarVehicles` | `true` | *N/A* | Schema: bool (maps to `show_similar_vehicles`) | ⚠️ Block Only |
| `showTechnicalSpecs` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `show_booking` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_calendar` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_gallery` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pricing` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `similarVehiclesLimit` | `4` | *N/A* | Schema: int (maps to `similar_vehicles_limit`) | ⚠️ Block Only |
| `vehicleId` | `""` | *N/A* | Schema: int (maps to `vehicle_id`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/vehicles-list` -> `[rentiva_vehicles_list]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `category` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | *N/A* | `1` | Schema: int | ⚠️ SC Only |
| `custom_css_class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `enableAjaxFiltering` | `false` | *N/A* | Schema: bool (maps to `enable_ajax_filtering`) | ⚠️ Block Only |
| `enable_ajax_filtering` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `enable_infinite_scroll` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `enable_lazy_load` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `featured` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `filterBrands` | `""` | *N/A* | Schema: string (maps to `filter_brands`) | ⚠️ Block Only |
| `filterCategories` | `""` | *N/A* | Schema: string (maps to `category`) | ⚠️ Block Only |
| `ids` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `image_size` | *N/A* | `medium` | Schema: string | ⚠️ SC Only |
| `limit` | `12` | `12` | Schema: int | ✅ |
| `max_features` | *N/A* | `5` | Schema: int | ⚠️ SC Only |
| `minRating` | `""` | *N/A* | Schema: int (maps to `min_rating`) | ⚠️ Block Only |
| `minReviews` | `""` | *N/A* | Schema: int (maps to `min_reviews`) | ⚠️ Block Only |
| `min_rating` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `min_reviews` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `order` | `asc` | `ASC` | Schema: enum | ❌ |
| `orderby` | `title` | `title` | Schema: enum | ✅ |
| `price_format` | *N/A* | `daily` | Schema: string | ⚠️ SC Only |
| `showAvailability` | `false` | *N/A* | Schema: bool (maps to `show_availability`) | ⚠️ Block Only |
| `showBadges` | `true` | *N/A* | Schema: bool (maps to `show_badges`) | ⚠️ Block Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showBrand` | `false` | *N/A* | Schema: bool (maps to `show_brand`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showCompareButton` | `true` | *N/A* | Schema: bool (maps to `show_compare_button`) | ⚠️ Block Only |
| `showDescription` | `true` | *N/A* | Schema: bool (maps to `show_description`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showImage` | `true` | *N/A* | Schema: bool (maps to `show_image`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showTitle` | `true` | *N/A* | Schema: bool (maps to `show_title`) | ⚠️ Block Only |
| `show_availability` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_badges` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_brand` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_category` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_description` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_image` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_title` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |

#### `mhm-rentiva/featured-vehicles` -> `[rentiva_featured_vehicles]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `autoplay` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `category` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `3` | `3` | Schema: int | ✅ |
| `filterBrands` | `""` | *N/A* | Schema: string (maps to `filter_brands`) | ⚠️ Block Only |
| `filterCategories` | `""` | *N/A* | Schema: string (maps to `category`) | ⚠️ Block Only |
| `filter_brands` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `filter_categories` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `ids` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `image_size` | *N/A* | `medium_large` | Schema: string | ⚠️ SC Only |
| `interval` | *N/A* | `5000` | Schema: int | ⚠️ SC Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `6` | `6` | Schema: int | ✅ |
| `max_features` | *N/A* | `5` | Schema: int | ⚠️ SC Only |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum | ⚠️ SC Only |
| `price_format` | *N/A* | `daily` | Schema: string | ⚠️ SC Only |
| `serviceType` | `rental` | *N/A* | Schema: enum (maps to `service_type`) | ⚠️ Block Only |
| `showAvailability` | `false` | *N/A* | Schema: bool (maps to `show_availability`) | ⚠️ Block Only |
| `showBadges` | `true` | *N/A* | Schema: bool (maps to `show_badges`) | ⚠️ Block Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showBrand` | `false` | *N/A* | Schema: bool (maps to `show_brand`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showCompareButton` | `true` | *N/A* | Schema: bool (maps to `show_compare_button`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `show_availability` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_badges` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_book_button` | *N/A* | `1` | *Unregistered* | ⚠️ SC Only 🚫 Not in Allowlist! |
| `show_brand` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_category` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |
| `title` | *N/A* | `Featured Vehicles` | Schema: string | ⚠️ SC Only |

#### `mhm-rentiva/vehicles-grid` -> `[rentiva_vehicles_grid]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `category` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `2` | `2` | Schema: int | ✅ |
| `custom_css_class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `enableAjaxFiltering` | `false` | *N/A* | Schema: bool (maps to `enable_ajax_filtering`) | ⚠️ Block Only |
| `enable_ajax_filtering` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `enable_infinite_scroll` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `enable_lazy_load` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `featured` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `filterBrands` | `""` | *N/A* | Schema: string (maps to `filter_brands`) | ⚠️ Block Only |
| `filterCategories` | `""` | *N/A* | Schema: string (maps to `category`) | ⚠️ Block Only |
| `image_size` | *N/A* | `medium` | Schema: string | ⚠️ SC Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `12` | `12` | Schema: int | ✅ |
| `minRating` | `""` | *N/A* | Schema: int (maps to `min_rating`) | ⚠️ Block Only |
| `minReviews` | `""` | *N/A* | Schema: int (maps to `min_reviews`) | ⚠️ Block Only |
| `min_rating` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `min_reviews` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `order` | *N/A* | `ASC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `title` | Schema: enum | ⚠️ SC Only |
| `showAvailability` | `false` | *N/A* | Schema: bool (maps to `show_availability`) | ⚠️ Block Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showBrand` | `false` | *N/A* | Schema: bool (maps to `show_brand`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showCompareButton` | `true` | *N/A* | Schema: bool (maps to `show_compare_button`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showImage` | `true` | *N/A* | Schema: bool (maps to `show_image`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showTitle` | `true` | *N/A* | Schema: bool (maps to `show_title`) | ⚠️ Block Only |
| `show_availability` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_badges` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_brand` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_category` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_description` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_favorite_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_image` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_title` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `title` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `asc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/search-results` -> `[rentiva_search_results]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `default_sort` | *N/A* | `price_asc` | Schema: string | ⚠️ SC Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `12` | *N/A* | Schema: int | ⚠️ Block Only |
| `results_per_page` | *N/A* | `12` | Schema: int | ⚠️ SC Only |
| `showAvailability` | `true` | *N/A* | Schema: bool (maps to `show_availability`) | ⚠️ Block Only |
| `showCompareButton` | `true` | *N/A* | Schema: bool (maps to `show_compare_button`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFilters` | `true` | *N/A* | Schema: bool (maps to `show_filters`) | ⚠️ Block Only |
| `showPagination` | `true` | *N/A* | Schema: bool (maps to `show_pagination`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showSorting` | `true` | *N/A* | Schema: bool (maps to `show_sorting`) | ⚠️ Block Only |
| `show_badges` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_filters` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pagination` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_sorting` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_title` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_view_toggle` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `price` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `asc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/vehicle-comparison` -> `[rentiva_vehicle_comparison]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `layout` | *N/A* | `table` | Schema: string | ⚠️ SC Only |
| `manual_add` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `maxVehicles` | `4` | *N/A* | Schema: int (maps to `max_vehicles`) | ⚠️ Block Only |
| `max_vehicles` | *N/A* | `4` | Schema: int | ⚠️ SC Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showComparisonImages` | `true` | *N/A* | Schema: bool (maps to `show_images`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showFuelType` | `true` | *N/A* | Schema: bool (maps to `show_fuel_type`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showSeats` | `true` | *N/A* | Schema: bool (maps to `show_seats`) | ⚠️ Block Only |
| `showTechnicalSpecs` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showTransmission` | `true` | *N/A* | Schema: bool (maps to `show_transmission`) | ⚠️ Block Only |
| `show_add_vehicle` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_buttons` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `all` | Schema: bool | ⚠️ SC Only |
| `show_images` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_prices` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_remove_buttons` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `title` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `vehicleIds` | `""` | *N/A* | Schema: string (maps to `vehicle_ids`) | ⚠️ Block Only |
| `vehicle_ids` | *N/A* | `""` | Schema: string | ⚠️ SC Only |

#### `mhm-rentiva/unified-search` -> `[rentiva_unified_search]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `default_tab` | `default` | `default` | Schema: string | ✅ |
| `default_tab_alias` | *N/A* | `defaultTab` | Schema: string | ⚠️ SC Only |
| `filter_categories` | `""` | `""` | Schema: string | ✅ |
| `layout` | *N/A* | `horizontal` | Schema: string | ⚠️ SC Only |
| `max_width` | `""` | *N/A* | Schema: string (maps to `maxwidth`) | ⚠️ Block Only |
| `min_width` | `""` | *N/A* | Schema: string (maps to `minwidth`) | ⚠️ Block Only |
| `redirect_page` | `default` | `default` | Schema: string | ✅ |
| `search_layout` | `horizontal` | `""` | Schema: string | ❌ |
| `service_type` | `both` | `both` | Schema: enum | ✅ |
| `show_date_picker` | `default` | `default` | Schema: bool | ✅ |
| `show_dropoff_location` | `default` | `default` | Schema: bool | ✅ |
| `show_location_select` | `default` | `default` | Schema: bool | ✅ |
| `show_luggage` | `default` | `default` | Schema: bool | ✅ |
| `show_pax` | `default` | `default` | Schema: bool | ✅ |
| `show_rental_tab` | `default` | `default` | Schema: bool | ✅ |
| `show_time_select` | `default` | `default` | Schema: bool | ✅ |
| `show_transfer_tab` | `default` | `default` | Schema: bool | ✅ |
| `style` | `glass` | `glass` | Schema: string | ✅ |

#### `mhm-rentiva/my-bookings` -> `[rentiva_my_bookings]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `filterStatus` | `all` | *N/A* | Schema: enum (maps to `status`) | ⚠️ Block Only |
| `hide_nav` | *N/A* | `false` | Schema: bool | ⚠️ SC Only |
| `limit` | *N/A* | `10` | Schema: int | ⚠️ SC Only |
| `limitResults` | `10` | *N/A* | Schema: int (maps to `limit_results`) | ⚠️ Block Only |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum | ⚠️ SC Only |
| `showBookingDates` | `true` | *N/A* | Schema: bool (maps to `show_booking_dates`) | ⚠️ Block Only |
| `showCancelButton` | `true` | *N/A* | Schema: bool (maps to `show_cancel_btn`) | ⚠️ Block Only |
| `showDetailsLink` | `true` | *N/A* | Schema: bool (maps to `show_details_link`) | ⚠️ Block Only |
| `showModifyButton` | `true` | *N/A* | Schema: bool (maps to `show_modify_btn`) | ⚠️ Block Only |
| `showPagination` | `true` | *N/A* | Schema: bool (maps to `show_pagination`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showStatus` | `true` | *N/A* | Schema: bool (maps to `show_status_toggle`) | ⚠️ Block Only |
| `showVehicleImage` | `true` | *N/A* | Schema: bool (maps to `show_image`) | ⚠️ Block Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |
| `status` | *N/A* | `""` | Schema: enum | ⚠️ SC Only |

#### `mhm-rentiva/my-favorites` -> `[rentiva_my_favorites]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `3` | `3` | Schema: int | ✅ |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | *N/A* | `12` | Schema: int | ⚠️ SC Only |
| `no_results_text` | *N/A* | `You have no favorite vehicles yet.` | Schema: string | ⚠️ SC Only |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum | ⚠️ SC Only |
| `showAddedDate` | `false` | *N/A* | Schema: bool (maps to `show_added_date`) | ⚠️ Block Only |
| `showAvailabilityStatus` | `true` | *N/A* | Schema: bool (maps to `show_availability_status`) | ⚠️ Block Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showRemoveButton` | `true` | *N/A* | Schema: bool (maps to `show_remove_button`) | ⚠️ Block Only |
| `show_added_date` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_badges` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_image` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_remove_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_title` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `added` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/payment-history` -> `[rentiva_payment_history]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `filterStatus` | `all` | *N/A* | Schema: enum (maps to `status`) | ⚠️ Block Only |
| `hide_nav` | *N/A* | `false` | Schema: bool | ⚠️ SC Only |
| `limit` | *N/A* | `20` | Schema: int | ⚠️ SC Only |
| `limitResults` | `20` | *N/A* | Schema: int (maps to `limit_results`) | ⚠️ Block Only |
| `showBookingLink` | `true` | *N/A* | Schema: bool (maps to `show_booking_link`) | ⚠️ Block Only |
| `showDate` | `true` | *N/A* | Schema: bool (maps to `show_date`) | ⚠️ Block Only |
| `showInvoiceDownload` | `true` | *N/A* | Schema: bool (maps to `show_invoice_download`) | ⚠️ Block Only |
| `showPagination` | `true` | *N/A* | Schema: bool (maps to `show_pagination`) | ⚠️ Block Only |
| `showPaymentMethod` | `true` | *N/A* | Schema: bool (maps to `show_payment_method`) | ⚠️ Block Only |
| `showTransactionId` | `true` | *N/A* | Schema: bool (maps to `show_transaction_id`) | ⚠️ Block Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/transfer-search` -> `[rentiva_transfer_search]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `buttonText` | `""` | *N/A* | Schema: string (maps to `button_text`) | ⚠️ Block Only |
| `button_text` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `layout` | `horizontal` | `horizontal` | Schema: string | ✅ |
| `showDropoff` | `true` | *N/A* | Schema: bool (maps to `show_dropoff`) | ⚠️ Block Only |
| `showPickup` | `true` | *N/A* | Schema: bool (maps to `show_pickup`) | ⚠️ Block Only |
| `show_dropoff` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pickup` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |

#### `mhm-rentiva/transfer-results` -> `[rentiva_transfer_results]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | *N/A* | `2` | Schema: int | ⚠️ SC Only |
| `layout` | `list` | `list` | Schema: string | ✅ |
| `limit` | `10` | `10` | Schema: int | ✅ |
| `order` | *N/A* | `asc` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `price` | Schema: enum | ⚠️ SC Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showCompareButton` | `true` | *N/A* | Schema: bool (maps to `show_compare_button`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showLuggageInfo` | `true` | *N/A* | Schema: bool (maps to `show_luggage_info`) | ⚠️ Block Only |
| `showPassengerCount` | `true` | *N/A* | Schema: bool (maps to `show_passenger_count`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRouteInfo` | `true` | *N/A* | Schema: bool (maps to `show_route_info`) | ⚠️ Block Only |
| `showVehicleDetails` | `true` | *N/A* | Schema: bool (maps to `show_vehicle_details`) | ⚠️ Block Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_luggage_info` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_passenger_count` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_route_info` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_vehicle_details` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `price` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `asc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/contact` -> `[rentiva_contact]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `auto_reply` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `description` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `email_to` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `recipientEmail` | `""` | *N/A* | Schema: string (maps to `recipient_email`) | ⚠️ Block Only |
| `redirect_url` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `showBookingIdField` | `false` | *N/A* | Schema: bool (maps to `show_booking_id_field`) | ⚠️ Block Only |
| `showCompanyInfo` | `true` | *N/A* | Schema: bool (maps to `show_company_info`) | ⚠️ Block Only |
| `showMap` | `true` | *N/A* | Schema: bool (maps to `show_map`) | ⚠️ Block Only |
| `showPhoneField` | `true` | *N/A* | Schema: bool (maps to `show_phone_field`) | ⚠️ Block Only |
| `showSocialLinks` | `true` | *N/A* | Schema: bool (maps to `show_social_links`) | ⚠️ Block Only |
| `showSubjectField` | `true` | *N/A* | Schema: bool (maps to `show_subject_field`) | ⚠️ Block Only |
| `showVehicleSelect` | `false` | *N/A* | Schema: bool (maps to `show_vehicle_select`) | ⚠️ Block Only |
| `show_attachment` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_company` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_phone` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_priority` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_vehicle_selector` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `subjectPrefix` | `""` | *N/A* | Schema: string (maps to `subject_prefix`) | ⚠️ Block Only |
| `successMessage` | `""` | *N/A* | Schema: string (maps to `success_message`) | ⚠️ Block Only |
| `theme` | *N/A* | `default` | Schema: string | ⚠️ SC Only |
| `title` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `type` | *N/A* | `general` | Schema: enum | ⚠️ SC Only |

#### `mhm-rentiva/testimonials` -> `[rentiva_testimonials]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `auto_rotate` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `autoplay` | `false` | *N/A* | Schema: bool | ⚠️ Block Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `3` | `3` | Schema: int | ✅ |
| `filterRating` | `""` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | *N/A* | `5` | Schema: int | ⚠️ SC Only |
| `limitItems` | `5` | *N/A* | Schema: int (maps to `limit_items`) | ⚠️ Block Only |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum | ⚠️ SC Only |
| `rating` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `showAuthorAvatar` | `true` | *N/A* | Schema: bool (maps to `show_author_avatar`) | ⚠️ Block Only |
| `showAuthorName` | `true` | *N/A* | Schema: bool (maps to `show_author_name`) | ⚠️ Block Only |
| `showDate` | `true` | *N/A* | Schema: bool (maps to `show_date`) | ⚠️ Block Only |
| `showQuotes` | `true` | *N/A* | Schema: bool (maps to `show_quotes`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showVehicleName` | `true` | *N/A* | Schema: bool (maps to `show_vehicle_name`) | ⚠️ Block Only |
| `show_customer` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_date` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_vehicle` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/vehicle-rating-form` -> `[rentiva_vehicle_rating_form]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `maxPhotos` | `3` | *N/A* | Schema: int (maps to `max_photos`) | ⚠️ Block Only |
| `minReviewLength` | `50` | *N/A* | Schema: int (maps to `min_review_length`) | ⚠️ Block Only |
| `requireBooking` | `true` | *N/A* | Schema: bool (maps to `require_booking`) | ⚠️ Block Only |
| `requireLogin` | `true` | *N/A* | Schema: bool (maps to `require_login`) | ⚠️ Block Only |
| `showCategoryRatings` | `true` | *N/A* | Schema: bool (maps to `show_category_ratings`) | ⚠️ Block Only |
| `showPhotoUpload` | `true` | *N/A* | Schema: bool (maps to `show_photo_upload`) | ⚠️ Block Only |
| `showRecommendToggle` | `true` | *N/A* | Schema: bool (maps to `show_recommend_toggle`) | ⚠️ Block Only |
| `showStarRating` | `true` | *N/A* | Schema: bool (maps to `show_star_rating`) | ⚠️ Block Only |
| `showTextReview` | `true` | *N/A* | Schema: bool (maps to `show_text_review`) | ⚠️ Block Only |
| `showVehiclePreview` | `true` | *N/A* | Schema: bool (maps to `show_vehicle_preview`) | ⚠️ Block Only |
| `show_form` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating_display` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_ratings_list` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `vehicleId` | `""` | *N/A* | Schema: int (maps to `vehicle_id`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/messages` -> `[rentiva_messages]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `filterStatus` | `all` | *N/A* | Schema: enum (maps to `status`) | ⚠️ Block Only |
| `hide_nav` | *N/A* | `false` | Schema: bool | ⚠️ SC Only |
| `limitItems` | `20` | *N/A* | Schema: int (maps to `limit_items`) | ⚠️ Block Only |
| `showAuthorAvatar` | `true` | *N/A* | Schema: bool (maps to `show_author_avatar`) | ⚠️ Block Only |
| `showBookingLink` | `true` | *N/A* | Schema: bool (maps to `show_booking_link`) | ⚠️ Block Only |
| `showDate` | `true` | *N/A* | Schema: bool (maps to `show_date`) | ⚠️ Block Only |
| `showPagination` | `true` | *N/A* | Schema: bool (maps to `show_pagination`) | ⚠️ Block Only |
| `showReplyButton` | `true` | *N/A* | Schema: bool (maps to `show_reply_btn`) | ⚠️ Block Only |
| `showThreadPreview` | `true` | *N/A* | Schema: bool (maps to `show_thread_preview`) | ⚠️ Block Only |
| `showUnreadBadge` | `true` | *N/A* | Schema: bool (maps to `show_unread_badge`) | ⚠️ Block Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |



---

## 5. Parameter Mapping Examples

These are common block-to-shortcode mappings used by CAM:

| Block Inspector Attribute | Canonical Shortcode Attribute | Example |
| :--- | :--- | :--- |
| `showBookButton` | `show_booking_button` | `showBookButton: true` -> `show_booking_button=\"1\"` |
| `showPrice` | `show_price` | `showPrice: false` -> `show_price=\"0\"` |
| `showFavoriteButton` | `show_favorite_button` | `showFavoriteButton: true` -> `show_favorite_button=\"1\"` |
| `showCompareButton` | `show_compare_button` | `showCompareButton: false` -> `show_compare_button=\"0\"` |
| `className` | `class` | `className: \"my-class\"` -> `class=\"my-class\"` |
| `sortBy` | `orderby` | `sortBy: \"price\"` -> `orderby=\"price\"` |
| `sortOrder` | `order` | `sortOrder: \"desc\"` -> `order=\"desc\"` |
| `resultsPerPage` | `results_per_page` | `resultsPerPage: 12` -> `results_per_page=\"12\"` |
| `vehicleId` | `vehicle_id` | `vehicleId: 88` -> `vehicle_id=\"88\"` |
| `defaultTab` | `default_tab` | `defaultTab: \"rental\"` -> `default_tab=\"rental\"` |
| `searchLayout` | `search_layout` | `searchLayout: \"compact\"` -> `search_layout=\"compact\"` |
| `minWidth` | `minwidth` | `minWidth: 360` -> `minwidth=\"360\"` |
| `maxWidth` | `maxwidth` | `maxWidth: 1200` -> `maxwidth=\"1200\"` |

Important:
- Mapping is alias-driven first, then camelCase to snake_case normalization.
- Values are transformed by type (`bool`, `int`, `enum`, `url`, `idlist`, etc.).
- Unknown attributes can be dropped in strict mapping mode.

## 6. Expanded Dependency Map

The end-to-end dependency chain is broader than only 3 files.

### 6.1 Inventory and Registration
- `src/Admin/Core/ShortcodeServiceProvider.php`
- `src/Blocks/BlockRegistry.php`
- `assets/blocks/*/block.json`

### 6.2 Canonical Mapping Pipeline (CAM)
- `src/Core/Attribute/AllowlistRegistry.php`
- `src/Core/Attribute/CanonicalAttributeMapper.php`
- `src/Core/Attribute/KeyNormalizer.php`
- `src/Core/Attribute/Transformers.php`

### 6.3 Runtime Shortcode Execution
- `src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php`
- `src/Admin/Frontend/Shortcodes/*.php`
- `src/Admin/Transfer/Frontend/TransferResults.php`
- `templates/shortcodes/*.php`

### 6.4 Block Editor Runtime
- `assets/blocks/*/index.js`
- `src/Blocks/BlockRegistry.php` (dynamic `render_callback` path)

### 6.5 URL and Flow Integration
- `src/Admin/Core/ShortcodeUrlManager.php`
- `templates/shortcodes/unified-search.php`
- `templates/shortcodes/transfer-search.php`

### 6.6 Styling and Visual Parity
- `assets/css/frontend/*.css`
- `assets/css/core/css-variables.css`
- `templates/shortcodes/*.php` (class hooks and wrappers)

### 6.7 Notes
- Canonical defaults can come from both `AllowlistRegistry` and shortcode classes.
- Block inspector attributes are not always 1:1 with canonical names, aliases resolve this.
- `SHORTCODES.md` should be updated whenever any file above changes behavior.

---

## 7. Compatibility Matrix

Use this matrix before changing shortcode or block parameters.

| Area | Safe Change | Breaking Risk | Required Action |
| :--- | :--- | :--- | :--- |
| Classic shortcode attribute name | Add new optional attribute | Rename/remove existing attribute | Keep old key as alias and document deprecation |
| Classic shortcode attribute type | Broaden accepted values | Narrow accepted values (e.g. string -> int only) | Add compatibility transform and validation note |
| Block inspector attribute | Add optional inspector field | Rename/remove existing inspector field | Keep old attribute as alias in mapping layer |
| Block -> shortcode mapping | Add new alias mapping | Remove existing alias mapping | Add migration rule and changelog entry |
| Default values | Non-functional default tuning | Behavior-changing default switch | Mark as behavior change and add release note |
| Template CSS class hooks | Add new class hooks | Remove/rename existing class hooks | Keep backward-compatible hooks for one cycle |
| URL/query contracts | Add optional query params | Rename/remove consumed query params | Keep legacy param parsing and flag deprecation |
| Auth requirements | Relax access where safe | Tighten access unexpectedly | Announce explicitly and provide fallback UX |

---

## 8. Deprecation and Alias Table

Source of truth:
- `src/Core/Attribute/AllowlistRegistry.php`
- `src/Core/Attribute/KeyNormalizer.php`

Alias behavior:
- Aliases are **not deprecated by default**. They are active if present in schema `aliases`.
- Resolution order is explicit alias match first, then camelCase to snake_case fallback.

| Canonical Attribute | Accepted Alias(es) from Schema | Current Status | Removal Target | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `class` | `className` | Active | N/A | `customClassName` maps to `custom_css_class`, not `class` |
| `custom_css_class` | `customClassName` | Active | N/A | Separate canonical key |
| `show_booking_button` | `showBookButton`, `show_booking_button`, `showBookBtn`, `show_book_btn` | Active | N/A | Used across vehicle/transfer blocks |
| `show_favorite_btn` | `showFavoriteBtn` | Active | N/A | Coexists with `show_favorite_button` |
| `show_favorite_button` | `showFavoriteButton`, `show_favorite_button` | Active | N/A | Both canonical keys may exist by tag |
| `show_compare_btn` | `showCompareBtn` | Active | N/A | Coexists with `show_compare_button` |
| `show_compare_button` | `showCompareButton`, `show_compare_button` | Active | N/A | Both canonical keys may exist by tag |
| `vehicle_id` | `vehicleId`, `vehicle_id` | Active | N/A | Declared per tag in registry mapping |
| `orderby` | `sortBy`, `orderby` | Active | N/A | Enum validated (`price`, `date`, etc.) |
| `order` | `sortOrder`, `order` | Active | N/A | Enum validated (`asc`, `desc`, `ASC`, `DESC`) |
| `results_per_page` | `resultsPerPage`, `limit` | Active | N/A | Separate from canonical `limit` |
| `limit` | `limit`, `resultsPerPage`, `results_per_page` | Active | N/A | Ambiguous aliasing by design; tag schema decides key |
| `default_tab` | `defaultTab` | Active | N/A | Also used in unified search |
| `search_layout` | `searchLayout` | Active | N/A | Unified search block bridge |
| `redirect_page` | `redirect_url`, `redirectPage`, `redirectUrl` | Active | N/A | Contract bridge for URL/page routing |

Deprecation workflow rule:
- Mark status as `Deprecated` first.
- Keep alias for at least one minor release cycle with changelog note.
- Remove alias only after migration guidance is published.

---

## 9. Validation Rules by Parameter

Source of truth:
- `src/Core/Attribute/CanonicalAttributeMapper.php`
- `src/Core/Attribute/Transformers.php`

Runtime processing order:
1. Normalize key (`aliases` first, else camelCase->snake_case).
2. In strict mode, drop unknown keys not present in tag schema.
3. Transform value by declared type.
4. Apply schema default only for missing keys with `default`.

| Declared Type | Actual Transformer Behavior | Example Attributes | Invalid / Edge Handling |
| :--- | :--- | :--- | :--- |
| `bool` | Returns string: `\"1\"`, `\"0\"`, or `\"default\"` | `show_price`, `show_booking_button` | Unrecognized values become `\"0\"` |
| `int` | Casts with `(int)` and clamps only if `min/max` exists | `limit`, `columns`, `min_days` | Non-numeric becomes `0` before clamp |
| `float` | Casts with `(float)` and clamps only if `min/max` exists | (future numeric ratings/deposits) | Non-numeric becomes `0.0` before clamp |
| `date` | Accepts exact `Y-m-d` or parses via `DateTime`, outputs `Y-m-d` | `start_date`, `end_date` | Parse fail -> schema default or empty string |
| `idlist` | Accepts array/comma list, runs `absint`, unique, joins with comma | `ids`, `vehicle_ids` | Invalid/non-positive IDs removed |
| `url` | Uses `esc_url_raw` | URL-like workflow params | Invalid URL sanitizes to empty/cleaned output |
| `enum` | Must exactly match `values` list (strict `in_array`) | `orderby`, `order`, `status`, `service_type` | Fallback to schema default or first allowed value |
| `string` | `sanitize_text_field` on scalar; else default/empty | `class`, `button_text`, `search_layout` | Non-scalar -> default or empty string |

Strict mapping contract:
- `CanonicalAttributeMapper::map(..., true)` drops unknown keys.
- If strict is `false`, unknown keys pass through after normalization.
- Defaults are not universal; they apply only where schema includes `default`.

### 9.1 Tag-Level Alias Coverage (Mini Matrix)

This matrix shows high-impact aliases that are actively used for block/classic parity.

| Tag | Canonical Attribute | Common Accepted Inputs | Notes |
| :--- | :--- | :--- | :--- |
| `rentiva_transfer_results` | `layout` | `layout` | Direct key, no special alias |
| `rentiva_transfer_results` | `show_booking_button` | `showBookButton`, `show_booking_button`, `showBookBtn`, `show_book_btn` | Main CTA visibility toggle |
| `rentiva_transfer_results` | `show_favorite_button` | `showFavoriteButton`, `show_favorite_button` | Favorite control bridge |
| `rentiva_transfer_results` | `show_compare_button` | `showCompareButton`, `show_compare_button` | Compare control bridge |
| `rentiva_transfer_results` | `show_route_info` | `showRouteInfo`, `show_route_info` | Route summary visibility |
| `rentiva_search_results` | `orderby` | `sortBy`, `orderby` | Enum validated |
| `rentiva_search_results` | `order` | `sortOrder`, `order` | Enum validated |
| `rentiva_search_results` | `results_per_page` | `resultsPerPage`, `results_per_page`, `limit` | Coexists with canonical `limit` |
| `rentiva_vehicles_grid` | `show_booking_btn` | `showBookButton`, `show_book_btn` (via normalization) | Legacy/canonical key in grid context |
| `rentiva_vehicles_grid` | `show_favorite_button` | `showFavoriteButton`, `show_favorite_button` | Used by block inspector |
| `rentiva_unified_search` | `default_tab` | `defaultTab`, `default_tab` | Workflow routing key |
| `rentiva_unified_search` | `search_layout` | `searchLayout`, `search_layout` | Layout mode bridge |
| `rentiva_unified_search` | `redirect_page` | `redirect_url`, `redirectPage`, `redirectUrl` | URL/page compatibility bridge |
| `rentiva_booking_form` | `vehicle_id` | `vehicleId`, `vehicle_id` | Primary entity binding |
| `rentiva_booking_form` | `show_date_picker` | `showDatePicker`, `show_date_picker` | UI control toggle |

Maintenance note:
- When a new block attribute is introduced, add its canonical target and accepted aliases here.

### 9.2 Documentation Audit Checklist

Run this checklist whenever shortcode/block contract changes.

| Check | Yes/No | Notes |
| :--- | :---: | :--- |
| Added/updated attribute exists in `AllowlistRegistry::ALLOWLIST` |  |  |
| Tag mapping updated in `AllowlistRegistry::TAG_MAPPING` |  |  |
| Alias mapping validated in `KeyNormalizer` flow |  |  |
| Type transform verified in `Transformers` (`bool/int/enum/...`) |  |  |
| `SHORTCODES.md` Section 3 (canonical params) updated |  |  |
| `SHORTCODES.md` Section 4 (block inspector params) updated |  |  |
| `SHORTCODES.md` Section 8 (alias table) updated |  |  |
| `SHORTCODES.md` Section 9.1 (tag-level mini matrix) updated |  |  |
| `SHORTCODES.md` Section 10 (contract change log) updated |  |  |
| Frontend parity validated (block editor vs shortcode frontend output) |  |  |
| Regression check done for active shortcodes in same group |  |  |

Completion rule:
- A contract change is not complete unless all relevant rows are marked `Yes`.

---

## 10. Contract Change Log

Use this section to record contract-level changes affecting shortcodes and blocks.

| Date | Scope | Change Type | Summary | Backward Compatibility | Action Required |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 2026-02-20 | `rentiva_transfer_results` | Fix | Preserved canonical block attrs in shortcode runtime to keep block inspector settings effective on frontend | Compatible | None |
| 2026-02-20 | `rentiva_transfer_results` | Fix | Grid/list and column behavior moved to inner results container so route summary is not treated as a grid item | Compatible | Clear cache/regenerate assets if needed |
| 2026-02-25 | `AllowlistRegistry` | Audit Finding | v4.20.1 X-Ray: Detected 10 duplicate PHP array keys in `ALLOWLIST` constant. Aliases for `show_booking_button`, `show_favorite_btn`, `show_compare_btn`, `redirect_page`, `ids` affected. Section 8 alias table reflects first definitions; runtime uses second definitions. Code fix required in separate authorized task. | Breaking Risk | See Section 12 — C1 finding |
| 2026-02-25 | `rentiva_home_poc` | Audit Finding | v4.20.1 X-Ray: Confirmed SC-Only with no AllowlistRegistry schema and no Block equivalent. Experimental status. | Compatible | No action required (by design) |
| YYYY-MM-DD | shortcode/block name | Added/Changed/Deprecated/Removed/Fix | Describe what changed | Compatible/Conditional/Breaking | Migration/test notes |

Maintenance rules:
- Record every parameter contract change in this table.
- If change is `Breaking`, include migration notes in release docs.
- Keep this log synchronized with release notes and changelog.

---

Last updated: 2026-02-25

---

## 11. Elementor Widget Registry

> Source: `src/Admin/Frontend/Widgets/Elementor/ElementorIntegration.php`
> Registered via: `elementor/widgets/register` hook (requires Elementor active)

All Elementor widgets reuse the shortcode renderer via their `render()` method — Render Parity Rule is maintained. No widget has its own divergent DOM pipeline.

| Widget Class | Maps To Shortcode | Category |
| :--- | :--- | :--- |
| `VehicleCardWidget` | *Component (no dedicated SC)* | vehicle |
| `UnifiedSearchWidget` | `rentiva_unified_search` | vehicle |
| `SearchResultsWidget` | `rentiva_search_results` | vehicle |
| `VehiclesListWidget` | `rentiva_vehicles_list` | vehicle |
| `VehiclesGridWidget` | `rentiva_vehicles_grid` | vehicle |
| `FeaturedVehiclesWidget` | `rentiva_featured_vehicles` | vehicle |
| `VehicleDetailsWidget` | `rentiva_vehicle_details` | vehicle |
| `BookingFormWidget` | `rentiva_booking_form` | reservation |
| `AvailabilityCalendarWidget` | `rentiva_availability_calendar` | reservation |
| `BookingConfirmationWidget` | `rentiva_booking_confirmation` | reservation |
| `MyBookingsWidget` | `rentiva_my_bookings` | account |
| `MyFavoritesWidget` | `rentiva_my_favorites` | account |
| `PaymentHistoryWidget` | `rentiva_payment_history` | account |
| `MyMessagesWidget` | `rentiva_messages` | account |
| `VehicleComparisonWidget` | `rentiva_vehicle_comparison` | vehicle |
| `ContactFormWidget` | `rentiva_contact` | support |
| `TestimonialsWidget` | `rentiva_testimonials` | support |
| `VehicleRatingWidget` | `rentiva_vehicle_rating_form` | support |
| `TransferSearchWidget` | `rentiva_transfer_search` | transfer |
| `TransferResultsWidget` | `rentiva_transfer_results` | transfer |

Notes:
- `VehicleCardWidget` renders the vehicle card template component directly, not via a named shortcode.
- `rentiva_home_poc` has NO Elementor widget counterpart.
- All 19 shortcodes (excluding `rentiva_home_poc`) have a corresponding Elementor widget.

---

## 12. Ecosystem Audit Findings — v4.20.1 X-Ray (2026-02-25)

> Instruction: V4.20.1-ECOSYSTEM-AUDIT
> Evidence Plan: `docs/plans/2026-02-25-v4201-ecosystem-audit.md`

### 12.1 Inventory Snapshot Summary

| Layer | Count | Coverage |
| :--- | :---: | :--- |
| Active Shortcodes | 20 | 19 fully covered, 1 experimental (home_poc) |
| Gutenberg Blocks | 19 | All 19 map to shortcodes via render_callback |
| AllowlistRegistry TAG_MAPPING | 19 | 19 shortcodes covered (home_poc missing) |
| Elementor Widgets | 20 | 19 SC-mapped + 1 component widget |
| block.json files | 19 | Verified in `assets/blocks/*/block.json` |

### 12.2 Critical Findings (C)

#### C1: AllowlistRegistry::ALLOWLIST — 10 Duplicate PHP Array Keys
**File:** `src/Core/Attribute/AllowlistRegistry.php`
**Impact:** PHP silently overwrites first array key definition with second. Aliases for affected attributes at runtime differ from documented state in this file (Section 8).

| Key | Runtime Aliases (2nd def, actual) | Documented Aliases (1st def, stale) |
| :--- | :--- | :--- |
| `ids` | `['ids']` | `['ids','vehicleIds','vehicle_ids']` |
| `redirect_page` | `['redirectPage']` | `['redirect_url','redirectPage','redirectUrl']` |
| `default_tab` | `['defaultTab']` (type: string) | `['defaultTab']` (type: enum) |
| `show_booking_button` | `['showBookButton']` | `['showBookButton','show_booking_button','showBookBtn','show_book_btn']` |
| `show_favorite_btn` | `['show_favorite_button']` | `['showFavoriteBtn']` |
| `show_compare_btn` | `['show_compare_button']` | `['showCompareBtn']` |
| `default_days` | Same (identical) | Same |
| `min_days` | Same (identical) | Same |
| `max_days` | Same (identical) | Same |
| `months_to_show` | group: `feature` | group: `layout` |

**Action Required:** PHP code fix in `AllowlistRegistry.php` — remove duplicate definitions, merge intended aliases into single canonical entry. This is a **code defect** — requires separate authorized task. The Section 8 alias table in this document currently reflects pre-duplicate (first) definitions which do NOT match runtime.

#### C2: `rentiva_home_poc` — Orphaned Shortcode (SC-Only)
- Registered in `ShortcodeServiceProvider` (group: support, no auth)
- NO entry in `AllowlistRegistry::TAG_MAPPING`
- NO Block equivalent
- NO Elementor widget
- Experimental: guarded by `mhm_rentiva_enable_home_poc` filter (default: `true`)
- No attributes accepted (template-only render)
- Status: Intentionally experimental per v4.12.0 hardening sprint

### 12.3 Major Findings (M)

#### M1: Enum Attributes Without `values` Constraint
These attributes are typed `enum` but have no `values` array defined in ALLOWLIST. CAM `Transformers` cannot validate values against an allowed set.

| Attribute | Canonical Group | Used By |
| :--- | :--- | :--- |
| `status` | workflow | `rentiva_my_bookings`, `rentiva_payment_history`, `rentiva_messages` |
| `default_payment` | workflow | `rentiva_booking_form` |
| `service_type` | feature | `rentiva_featured_vehicles`, `rentiva_unified_search` |
| `type` | feature | `rentiva_contact` |

Note: `default_tab` originally had `type: enum`, but due to C1 duplicate key, runtime type is now `string`.

#### M2: TAG_MAPPING References Attributes NOT in Canonical ALLOWLIST
`get_registry()` falls back to empty config `[]` for these. They are effectively untyped.

| Shortcode | Attribute in TAG_MAPPING | Nearest Canonical | Risk |
| :--- | :--- | :--- | :--- |
| `rentiva_vehicle_details` | `show_technical_specs` | None | Untyped pass-through |
| `rentiva_vehicle_details` | `show_booking_form` | None | Untyped pass-through |
| `rentiva_vehicle_comparison` | `show_technical_specs` | None | Untyped pass-through |
| `rentiva_vehicle_comparison` | `show_book_button` | `show_booking_button` | Alias gap |
| `rentiva_testimonials` | `sort_by` | `orderby` (canonical) | Non-canonical alias |
| `rentiva_testimonials` | `sort_order` | `order` (canonical) | Non-canonical alias |
| `rentiva_messages` | `show_avatar` | `show_author_avatar` (canonical) | Alias mismatch |

Also: `filter_rating` appears in `rentiva_testimonials` TAG_MAPPING but is only an alias of `show_rating` in ALLOWLIST — not a standalone canonical key.

### 12.4 Minor Findings (Mi)

#### Mi1: Intentional Canonical Attribute Pairs (Acknowledged Redundancy)
The following twin-attribute pairs coexist by design from the v4.11.x migration:
- `show_booking_button` / `show_booking_btn`
- `show_favorite_button` / `show_favorite_btn`
- `show_compare_button` / `show_compare_btn`

These are not bugs, but they do contribute to the 10 duplicate key issue (C1).

#### Mi2: Block-Only / SC-Only Attributes
All `⚠️ Block Only` and `⚠️ SC Only` entries in Section 4.2 are by architectural design. Blocks use camelCase aliases (CAM resolves them); shortcodes use canonical snake_case. Not a defect.

### 12.5 QA Decision

| Item | Status |
| :--- | :--- |
| Shortcode inventory verified | ✅ 20/20 shortcodes confirmed |
| Block registry verified | ✅ 19/19 blocks confirmed |
| AllowlistRegistry TAG_MAPPING verified | ✅ 19 entries (home_poc intentionally excluded) |
| Elementor widget inventory | ✅ 20 widgets confirmed |
| block.json files verified | ✅ 19 files confirmed |
| C1 (duplicate ALLOWLIST keys) | ⚠️ CRITICAL — Code fix required (separate task) |
| C2 (home_poc orphan) | ℹ️ By design — experimental status documented |
| M1 (enum without values) | ⚠️ MAJOR — No values constraint for 4 enum attributes |
| M2 (unregistered TAG_MAPPING attrs) | ⚠️ MAJOR — 7 attributes are untyped pass-through |
| PHPUnit Baseline | ✅ v4.20.0 foundation: 268 tests, 1379 assertions |
| Render Parity Rule | ✅ No dual render paths detected |
| Documentation Updated | ✅ SHORTCODES.md sections added |
| PROJECT_MEMORIES.md Updated | ✅ Audit entry added |

**Overall QA Decision: CONDITIONAL PASS**
Documentation updated. Runtime stable. Critical code defect (C1) and major schema gaps (M1, M2) flagged for resolution in a separate authorized task.
