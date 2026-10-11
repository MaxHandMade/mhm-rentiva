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
| `rentiva_user_dashboard` | account | Yes | Deprecated in 6.2.0 — renders a notice pointing customers to the WooCommerce account page; removed in 7.0. |
| `rentiva_commission_resolver` | account | Yes | Commission resolver |
| `rentiva_transfer_search` | transfer | No | Transfer search form |
| `rentiva_transfer_results` | transfer | No | Transfer results |
| `rentiva_contact` | support | No | Contact form |
| `rentiva_testimonials` | support | No | Testimonials |
| `rentiva_vehicle_rating_form` | support | No | Vehicle rating form |
| `rentiva_messages` | support | Yes | Account messages |
| `rentiva_home_poc` | support | No | Experimental POC shortcode |
| `rentiva_vendor_apply` | vendor | No | Vendor application form |
| `rentiva_vehicle_submit` | vendor | Yes | Vendor vehicle submission |

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
`class`, `default_days`, `enable_deposit`, `end_date`, `form_title`, `redirect_url`, `show_addons`, `show_time_select`, `show_vehicle_info`, `show_vehicle_selector`, `start_date`, `vehicle_id`

#### `[rentiva_availability_calendar]`
`class`, `height`, `months_to_show`, `show_booking_button`, `show_legend`, `show_month_nav`, `show_pricing`, `show_vehicle_selector`, `start_month`, `start_week_on`, `theme`, `vehicle_id`

### 3.2 Vehicle

#### `[rentiva_vehicle_details]`
`class`, `show_booking`, `show_booking_button`, `show_calendar`, `show_favorite_button`, `show_features`, `show_gallery`, `show_price`, `show_pricing`, `show_rating`, `show_reviews`, `show_similar_vehicles`, `similar_vehicles_limit`, `vehicle_id`

#### `[rentiva_vehicles_list]`
`button_text`, `category`, `class`, `columns`, `custom_css_class`, `featured`, `ids`, `image_size`, `layout`, `limit`, `max_features`, `min_rating`, `min_reviews`, `order`, `orderby`, `show_booking_btn`, `show_brand`, `show_category`, `show_compare_btn`, `show_compare_button`, `show_description`, `show_favorite_btn`, `show_favorite_button`, `show_features`, `show_image`, `show_price`, `show_rating`, `show_rating_count`, `show_title`

#### `[rentiva_featured_vehicles]`
`autoplay`, `category`, `class`, `columns`, `filter_categories`, `ids`, `image_size`, `interval`, `layout`, `limit`, `max_features`, `order`, `orderby`, `service_type`, `show_booking_button`, `show_brand`, `show_category`, `show_compare_button`, `show_favorite_button`, `show_features`, `show_price`, `show_rating`

#### `[rentiva_vehicles_grid]`
`category`, `class`, `columns`, `columns_mobile`, `columns_tablet`, `custom_css_class`, `featured`, `filter_categories`, `image_size`, `layout`, `limit`, `min_rating`, `min_reviews`, `order`, `orderby`, `show_booking_btn`, `show_brand`, `show_category`, `show_compare_btn`, `show_compare_button`, `show_favorite_btn`, `show_favorite_button`, `show_features`, `show_image`, `show_price`, `show_rating`, `show_title`

#### `[rentiva_search_results]`
`button_text`, `class`, `default_sort`, `layout`, `limit`, `results_per_page`, `show_badges`, `show_booking_btn`, `show_compare_button`, `show_dropoff`, `show_favorite_button`, `show_features`, `show_filters`, `show_pagination`, `show_pickup`, `show_price`, `show_rating`, `show_sorting`, `show_title`, `show_view_toggle`

#### `[rentiva_vehicle_comparison]`
`class`, `layout`, `manual_add`, `max_vehicles`, `show_add_vehicle`, `show_book_button`, `show_booking_buttons`, `show_images`, `show_prices`, `show_remove_buttons`, `title`, `vehicle_ids`

#### `[rentiva_unified_search]`
`class`, `default_tab`, `redirect_page`, `search_layout`, `service_type`, `show_date_picker`, `show_dropoff_location`, `show_location_select`, `show_luggage`, `show_pax`, `show_rental_tab`, `show_time_select`, `show_transfer_tab`

### 3.3 Account

#### `[rentiva_my_bookings]`
`class`, `hide_nav`, `limit`, `order`, `show_booking_dates`, `show_image`, `show_pagination`, `show_price`, `show_status_toggle`, `status`

#### `[rentiva_my_favorites]`
`class`, `columns`, `layout`, `limit`, `no_results_text`, `order`, `orderby`, `show_availability_status`, `show_booking_btn`, `show_booking_button`, `show_category`, `show_favorite_btn`, `show_features`, `show_image`, `show_price`, `show_rating`, `show_title`

#### `[rentiva_payment_history]`
`class`, `hide_nav`, `limit`, `order`, `orderby`, `show_booking_link`, `show_date`, `show_pagination`, `show_payment_method`, `status`

#### `[rentiva_messages]`
`class`, `hide_nav`, `show_avatar`, `show_thread_preview`

### 3.4 Transfer

#### `[rentiva_transfer_search]`
`button_text`, `class`, `layout`, `show_dropoff`, `show_pickup`

#### `[rentiva_transfer_results]`
`class`, `columns`, `layout`, `limit`, `order`, `show_booking_button`, `show_compare_button`, `show_favorite_button`, `show_luggage_info`, `show_passenger_count`, `show_price`, `show_route_info`, `show_vehicle_details`

### 3.5 Support

#### `[rentiva_contact]`
`auto_reply`, `class`, `description`, `email_to`, `redirect_url`, `show_attachment`, `show_booking_id_field`, `show_company`, `show_company_info`, `show_phone`, `show_priority`, `show_vehicle_selector`, `success_message`, `theme`, `title`, `type`

#### `[rentiva_testimonials]`
`auto_rotate`, `class`, `columns`, `filter_rating`, `layout`, `limit`, `rating`, `show_author_avatar`, `show_customer`, `show_date`, `show_quotes`, `show_rating`, `show_vehicle`, `sort_by`, `sort_order`, `vehicle_id`

#### `[rentiva_vehicle_rating_form]`
`class`, `show_form`, `show_rating_display`, `show_ratings_list`, `vehicle_id`

#### `[rentiva_home_poc]`
No canonical schema entry found in current `AllowlistRegistry` (experimental).

### 3.6 Settings applied or re-bound in Elementor bridge slice 3A

Slice 3A (2026-10) made saved-but-ignored settings take effect and removed the ones that had no
effect. The defaults below reproduce the render from before the slice; a page only changes when a
value was set. Removed attributes are no longer read anywhere (no alias is kept): a removed key in
hand-written shortcode text is ignored, and a saved value of a removed block or Elementor control
renders like the default.

| Shortcode | Attribute | Default | Behaviour |
| :--- | :--- | :--- | :--- |
| `rentiva_booking_form` | `default_days` | `""` | When greater than 0 (clamped to 365), an empty return date is prefilled with pickup + N days. A return date already chosen is never overwritten. Empty = no prefill. |
| `rentiva_booking_form` | `form_title` | `""` | `<h2 class="rv-booking-form-title">` above the form, only when set. |
| `rentiva_availability_calendar` | `height` | `""` | Block `calendarHeight` sets the wrapper height (e.g. `600px`); an older block saved with `height` keeps using it while `calendarHeight` is empty. `""` and `auto` write no style. |
| `rentiva_availability_calendar` | `show_legend` | `1` | Shows the legend. |
| `rentiva_availability_calendar` | `show_month_nav` | `1` | Shows the previous/next month controls. |
| `rentiva_availability_calendar` | `show_vehicle_selector` | `1` | Shows the "Change Vehicle" / "Choose Another Vehicle" switch (only when there is more than one vehicle). |
| `rentiva_availability_calendar` | `start_week_on` | `1` | `1` = Monday, `0` = Sunday (header and grid). |
| `rentiva_availability_calendar` | `show_booking_button` | `1` | Re-bound: the block's "Show Booking Button" toggle (formerly writing `show_booking_links`) now writes this key. |
| `rentiva_vehicle_details` | `show_rating` | `1` | The star line in the sidebar. |
| `rentiva_vehicle_details` | `show_reviews` | `1` | The "Ratings & Reviews" section (it followed `show_booking_form` before). |
| `rentiva_vehicle_details` | `show_similar_vehicles` | `1` | The similar vehicles section. |
| `rentiva_vehicle_details` | `similar_vehicles_limit` | `""` | Empty/0 keeps 6 (mobile slider) / 3 (desktop grid); 1–12 sets both (larger values are capped at 12). |
| `rentiva_vehicle_details` | `show_booking` | `1` | Re-bound: the block's "Show Booking Section" toggle (formerly `show_booking_form`) writes this key; it hides the booking call to action. |
| `rentiva_vehicles_list` | `columns` | `1` | `rv-vehicles-list--columns-N` (1–4): 2–4 are a grid (3/4 drop to 2 at ≤1024px, all to 1 at ≤782px). Elementor default is now `1`. |
| `rentiva_vehicles_list` | `layout` | `list` | Card layout, `list` or `grid` (Elementor vehicle card). |
| `rentiva_vehicles_list` | `custom_css_class` | `""` | Extra wrapper classes (each passed through `sanitize_html_class`). |
| `rentiva_vehicles_list` | `button_text` | `""` | Booking button label. Order: "Unavailable" when the vehicle is down, else `button_text`, else the global "Book Now Button" text setting, else "Book Now". Elementor "Button Text" now writes this key. |
| `rentiva_vehicles_list` | `show_rating_count` | `0` | Adds `(N)` after the average rating. |
| `rentiva_vehicles_grid` | `columns_tablet` / `columns_mobile` | `2` / `1` | Columns at ≤1024px / ≤782px (1–4). |
| `rentiva_featured_vehicles` | `show_booking_button` | `1` | The Elementor switcher now writes this key. **`show_book_button` is no longer read** — use `show_booking_button="0"`. |
| `rentiva_search_results` | `default_sort` | `price_asc` | One sort control on block and Elementor (replaces Sort By / Sort Order). Values: `relevance`, `price_asc`, `price_desc`, `name_asc`, `name_desc`, `year_desc`. |
| `rentiva_vehicle_comparison` | `show_images` / `show_remove_buttons` | `1` / `1` | Vehicle images / remove buttons in all layouts. |
| `rentiva_unified_search` | `redirect_page` | `default` | A published page ID or a same-site URL sets the form action; anything else (including `0`) falls back to the global search results page. |
| `rentiva_unified_search` | `search_layout` | `horizontal` | `horizontal` / `vertical`. The Elementor "Layout Style" control writes this key. **`layout` is no longer read** — use `search_layout`. |
| `rentiva_my_bookings` | `show_booking_dates`, `show_status_toggle`, `show_price` | `1` | Columns of both tables. |
| `rentiva_my_bookings` | `show_image` | `1` | The image column. **Takes effect only outside the WooCommerce account page** (the integrated page never shows it). |
| `rentiva_my_bookings` | `show_pagination` | `1` | Pager over `limit`; the page comes from `?mhmrentiva_page=N` (out of range shows the last page; invalid shows page 1). The status/search filters run before paging. The block's "Limit Results" control now writes `limit`. |
| `rentiva_my_favorites` | `orderby` | `added` | `added` (saved order; `order` does not apply), `name`, `price` (favourites without a price go last). Legacy `date` = `added`, `title` = `name`. |
| `rentiva_my_favorites` | `layout` | `grid` | `grid` or `list` (one card per row). |
| `rentiva_my_favorites` | `no_results_text` | `""` | Replaces the empty-state text when set. |
| `rentiva_my_favorites` | `show_availability_status` | `0` | Shows Available / Out of Order on the card. The block's "Show Remove Button" control now writes `show_favorite_button`. |
| `rentiva_payment_history` | `limit` | `10` | Rows per page (0 or negative = 10). The block's "Limit Results" control now writes this key. |
| `rentiva_payment_history` | `orderby` / `order` | `date` / `desc` | `date` or `amount`; a payment without a date or amount goes last. |
| `rentiva_payment_history` | `status` | `""` | `""` (all), `pending`, `completed`, `cancelled`; filtered before paging. |
| `rentiva_payment_history` | `show_date`, `show_payment_method`, `show_pagination` | `1` | Pager uses `?mhmrentiva_page=N`. |
| `rentiva_payment_history` | `show_booking_link` | `0` | Links the payment title to its booking. |
| `rentiva_contact` | `type` | `general` | `general`, `booking`, `support`, `feedback` — now applied (the template read a variable that never existed). |
| `rentiva_contact` | `title` / `description` | `""` | Heading and intro, only when set. |
| `rentiva_contact` | `show_phone`, `show_company`, `show_vehicle_selector`, `show_priority`, `show_attachment` | `1`, `0`, `0`, `0`, `1` | Form fields. Block "Show Phone Field" / "Show Vehicle Select" write `show_phone` / `show_vehicle_selector`. A field the form `type` requires is always shown: `show_phone` does not hide phone on `booking`, `show_priority` does not hide priority on `support`. |
| `rentiva_contact` | `show_company_info` | `1` | The "Contact Information" box. |
| `rentiva_contact` | `show_booking_id_field` | `0` | Booking ID field; linked to the message only for the logged-in owner of the booking. |
| `rentiva_contact` | `success_message` | `""` | Replaces the success text. |
| `rentiva_contact` | `redirect_url` | `""` | Same-site URL only; the redirect happens 1.5 s after the success message is shown. |
| `rentiva_contact` | `email_to` | `""` | Recipient override, sent as an encrypted token (the address is not in the page source). Used only when the page's author can edit others' posts; otherwise the global recipient is used and `mhmrentiva_contact_recipient_fallback` fires. Block "Recipient Email" writes this key. |
| `rentiva_contact` | `theme` / `auto_reply` | `default` / `1` | Now applied with the `type` fix. |
| `rentiva_testimonials` | `orderby` | `date` | `date`, `rating` (unrated last), `rand`. Load More continues the same order, filters and display flags. |
| `rentiva_testimonials` | `show_author_avatar` / `show_quotes` | `1` / `1` | Avatar and quote icon. Block "Autoplay", "Limit Items", "Show Author Name", "Show Vehicle Name" write `auto_rotate`, `limit`, `show_customer`, `show_vehicle`. |
| `rentiva_messages` (Pro) | `show_thread_preview` | `0` | Message preview under the subject. |
| `rentiva_transfer_search` (Pro) | `layout` | `horizontal` | `horizontal` / `vertical` (stacked fields). `compact` is gone (renders horizontal). |
| `rentiva_transfer_results` (Pro) | `order` | `asc` | Results are always ordered by price; `order` sets the direction. `layout` is `list` / `grid` (`compact` renders `list`). |
| `rentiva_popular_routes` (Pro) | `filter_origin_type` | `""` | Options are the stored location types (station, port, …); station 🚆 and port ⛵ icons show. |
| `rentiva_vendor_profile` (Pro) | `vehicle_sort` | `rating-newest` | `rating-newest`, `newest`, `price-asc`, `price-desc` (vehicles without a price go last). |

Notes:
- `show_book_button` on `[rentiva_featured_vehicles]` → use `show_booking_button`.
- `layout` on `[rentiva_unified_search]` → use `search_layout`.
- `[rentiva_my_bookings]` `show_image` only takes effect outside the WooCommerce account page.
- Account lists page with the public query var `mhmrentiva_page` (core `paged` is redirected away on the WooCommerce endpoint).

---

## 4. Gutenberg Block Inspector Parameters

### 4.1 Active Blocks and Attributes

| Block | Shortcode | Inspector Attributes (`block.json`) |
| :--- | :--- | :--- |
| `mhm-rentiva/availability-calendar` | `rentiva_availability_calendar` | `vehicleId`, `showVehicleSelector`, `showLegend`, `showPricing`, `showMonthNavigation`, `monthsToShow`, `startWeekOn`, `calendarHeight`, `className`, `theme`, `startMonth`, `showBookingButton` |
| `mhm-rentiva/booking-form` | `rentiva_booking_form` | `vehicle_id`, `show_addons`, `show_vehicle_selector`, `form_title`, `enable_deposit`, `show_vehicle_info`, `show_time_select`, `className`, `startDate`, `endDate`, `defaultDays`, `redirectUrl` |
| `mhm-rentiva/contact` | `rentiva_contact` | `showBookingIdField`, `showCompanyInfo`, `successMessage`, `className`, `type`, `title`, `description`, `showPhone`, `showCompany`, `showVehicleSelector`, `showPriority`, `showAttachment`, `redirectUrl`, `emailTo`, `autoReply`, `theme` |
| `mhm-rentiva/featured-vehicles` | `rentiva_featured_vehicles` | `layout`, `showPrice`, `showRating`, `showCategory`, `showBookButton`, `showFeatures`, `showBrand`, `showCompareButton`, `showFavoriteButton`, `serviceType`, `filterCategories`, `sortBy`, `sortOrder`, `limit`, `columns`, `className`, `ids`, `autoplay`, `interval`, `maxFeatures`, `imageSize`, `viewAllUrl`, `viewAllText` |
| `mhm-rentiva/messages` | `rentiva_messages` | `showThreadPreview`, `className`, `hideNav` |
| `mhm-rentiva/my-bookings` | `rentiva_my_bookings` | `showVehicleImage`, `showBookingDates`, `showPrice`, `showStatus`, `filterStatus`, `sortOrder`, `showPagination`, `className`, `limit`, `hideNav` |
| `mhm-rentiva/my-favorites` | `rentiva_my_favorites` | `layout`, `showPrice`, `showAvailabilityStatus`, `showCategory`, `showBookButton`, `showRating`, `sortBy`, `sortOrder`, `columns`, `className`, `limit`, `showFavoriteButton`, `showImage`, `showTitle`, `showFeatures`, `noResultsText` |
| `mhm-rentiva/payment-history` | `rentiva_payment_history` | `showPaymentMethod`, `showDate`, `showBookingLink`, `filterStatus`, `sortBy`, `sortOrder`, `showPagination`, `className`, `limit`, `hideNav` |
| `mhm-rentiva/search-results` | `rentiva_search_results` | `layout`, `showPrice`, `showRating`, `showFilters`, `showSorting`, `showPagination`, `limit`, `showFavoriteButton`, `showCompareButton`, `className`, `showBookingButton`, `showTitle`, `showFeatures`, `showBadges`, `showViewToggle`, `defaultSort` |
| `mhm-rentiva/testimonials` | `rentiva_testimonials` | `layout`, `showDate`, `showAuthorAvatar`, `showRating`, `showQuotes`, `filterRating`, `sortBy`, `sortOrder`, `columns`, `className`, `limit`, `vehicleId`, `showVehicle`, `showCustomer`, `autoRotate` |
| `mhm-rentiva/transfer-results` | `rentiva_transfer_results` | `layout`, `showPrice`, `showVehicleDetails`, `showLuggageInfo`, `showPassengerCount`, `showBookButton`, `showRouteInfo`, `sortOrder`, `limit`, `showFavoriteButton`, `showCompareButton`, `className`, `columns` |
| `mhm-rentiva/transfer-search` | `rentiva_transfer_search` | `layout`, `buttonText`, `showPickup`, `showDropoff`, `className` |
| `mhm-rentiva/unified-search` | `rentiva_unified_search` | `search_layout`, `default_tab`, `show_rental_tab`, `show_transfer_tab`, `show_location_select`, `show_time_select`, `show_date_picker`, `show_dropoff_location`, `show_pax`, `show_luggage`, `service_type`, `redirect_page`, `className`, `style`, `locationRequired`, `fieldsRequired` |
| `mhm-rentiva/vehicle-comparison` | `rentiva_vehicle_comparison` | `vehicleIds`, `showComparisonImages`, `showPrice`, `showBookButton`, `maxVehicles`, `className`, `layout`, `title`, `showRemoveButtons` |
| `mhm-rentiva/vehicle-details` | `rentiva_vehicle_details` | `vehicleId`, `showGallery`, `showPrice`, `showRating`, `showReviews`, `showFeatures`, `showSimilarVehicles`, `showFavoriteButton`, `similarVehiclesLimit`, `className`, `showPricing`, `showBooking`, `showCalendar`, `showBookingButton`, `showCompareButton` |
| `mhm-rentiva/vehicle-rating-form` | `rentiva_vehicle_rating_form` | `vehicleId`, `className`, `showRatingDisplay`, `showForm`, `showRatingsList` |
| `mhm-rentiva/vehicles-grid` | `rentiva_vehicles_grid` | `layout`, `showImage`, `showTitle`, `showPrice`, `showRating`, `showCategory`, `showBrand`, `showFavoriteButton`, `showCompareButton`, `showBookButton`, `showFeatures`, `filterCategories`, `sortBy`, `sortOrder`, `limit`, `columns`, `className`, `minRating`, `minReviews`, `featured`, `imageSize`, `customCssClass`, `columns_tablet`, `columns_mobile`, `viewAllUrl`, `viewAllText` |
| `mhm-rentiva/vehicles-list` | `rentiva_vehicles_list` | `showPrice`, `showRating`, `showCategory`, `showBrand`, `showBookButton`, `showDescription`, `showFeatures`, `showImage`, `showTitle`, `showFavoriteButton`, `showCompareButton`, `filterCategories`, `orderby`, `order`, `limit`, `className`, `minRating`, `minReviews`, `columns`, `featured`, `imageSize`, `ids`, `maxFeatures`, `customCssClass` |


### 4.2 3-Layer Default Comparison Matrix

This matrix enforces the Render Parity Rule. It compares the explicit defaults across the three architectural layers for all blocks/shortcodes.

#### `mhm-rentiva/booking-form` -> `[rentiva_booking_form]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `defaultDays` | `""` | *N/A* | Schema: int (maps to `default_days`) | ⚠️ Block Only |
| `default_days` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `enable_deposit` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `endDate` | `""` | *N/A* | Schema: string (maps to `end_date`) | ⚠️ Block Only |
| `end_date` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `form_title` | `""` | `""` | Schema: string | ✅ |
| `redirectUrl` | `""` | *N/A* | Schema: string (maps to `redirect_url`) | ⚠️ Block Only |
| `redirect_url` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `show_addons` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_time_select` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_vehicle_info` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `show_vehicle_selector` | `true` | `1` | Schema: bool | ✅ (Bool->Str) |
| `startDate` | `""` | *N/A* | Schema: string (maps to `start_date`) | ⚠️ Block Only |
| `start_date` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `vehicle_id` | `""` | `""` | Schema: int | ✅ |

#### `mhm-rentiva/availability-calendar` -> `[rentiva_availability_calendar]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `calendarHeight` | `""` | *N/A* | Schema: string (maps to `height`; legacy `height` used when empty) | ⚠️ Block Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `monthsToShow` | `2` | *N/A* | Schema: int (maps to `months_to_show`) | ⚠️ Block Only |
| `months_to_show` | *N/A* | `1` | Schema: int | ⚠️ SC Only |
| `showBookingButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showLegend` | `true` | *N/A* | Schema: bool (maps to `show_legend`) | ⚠️ Block Only |
| `showMonthNavigation` | `true` | *N/A* | Schema: bool (maps to `show_month_nav`) | ⚠️ Block Only |
| `showPricing` | `true` | *N/A* | Schema: bool (maps to `show_pricing`) | ⚠️ Block Only |
| `showVehicleSelector` | `true` | *N/A* | Schema: bool (maps to `show_vehicle_selector`) | ⚠️ Block Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_legend` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_month_nav` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pricing` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_vehicle_selector` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `startWeekOn` | `1` | *N/A* | Schema: int (maps to `start_week_on`) | ⚠️ Block Only |
| `start_month` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `start_week_on` | *N/A* | `1` | Schema: int | ⚠️ SC Only |
| `theme` | *N/A* | `default` | Schema: string | ⚠️ SC Only |
| `vehicleId` | `""` | *N/A* | Schema: int (maps to `vehicle_id`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/vehicle-details` -> `[rentiva_vehicle_details]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `showBooking` | `true` | *N/A* | Schema: bool (maps to `show_booking`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showGallery` | `true` | *N/A* | Schema: bool (maps to `show_gallery`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showReviews` | `true` | *N/A* | Schema: bool (maps to `show_reviews`) | ⚠️ Block Only |
| `showSimilarVehicles` | `true` | *N/A* | Schema: bool (maps to `show_similar_vehicles`) | ⚠️ Block Only |
| `show_booking` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_calendar` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_gallery` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pricing` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_reviews` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_similar_vehicles` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `similarVehiclesLimit` | `""` | *N/A* | Schema: int (maps to `similar_vehicles_limit`) | ⚠️ Block Only |
| `similar_vehicles_limit` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `vehicleId` | `""` | *N/A* | Schema: int (maps to `vehicle_id`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/vehicles-list` -> `[rentiva_vehicles_list]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `button_text` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `category` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `1` | `1` | Schema: int | ✅ |
| `customCssClass` | `""` | *N/A* | Schema: string (maps to `custom_css_class`) | ⚠️ Block Only |
| `custom_css_class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `featured` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `filterCategories` | `""` | *N/A* | Schema: string (maps to `category`) | ⚠️ Block Only |
| `ids` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `image_size` | *N/A* | `medium` | Schema: string | ⚠️ SC Only |
| `layout` | *N/A* | `list` | Schema: enum (`list`, `grid`) | ⚠️ SC Only |
| `limit` | `12` | `12` | Schema: int | ✅ |
| `max_features` | *N/A* | `5` | Schema: int | ⚠️ SC Only |
| `minRating` | `""` | *N/A* | Schema: int (maps to `min_rating`) | ⚠️ Block Only |
| `minReviews` | `""` | *N/A* | Schema: int (maps to `min_reviews`) | ⚠️ Block Only |
| `min_rating` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `min_reviews` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `order` | `asc` | `ASC` | Schema: enum | ❌ |
| `orderby` | `title` | `title` | Schema: enum | ✅ |
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
| `show_rating_count` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_title` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |

#### `mhm-rentiva/featured-vehicles` -> `[rentiva_featured_vehicles]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `autoplay` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `category` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `3` | `3` | Schema: int | ✅ |
| `filterCategories` | `""` | *N/A* | Schema: string (maps to `category`) | ⚠️ Block Only |
| `filter_categories` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `ids` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `image_size` | *N/A* | `medium_large` | Schema: string | ⚠️ SC Only |
| `interval` | *N/A* | `5000` | Schema: int | ⚠️ SC Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `6` | `6` | Schema: int | ✅ |
| `max_features` | *N/A* | `5` | Schema: int | ⚠️ SC Only |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum | ⚠️ SC Only |
| `serviceType` | `rental` | *N/A* | Schema: enum (maps to `service_type`) | ⚠️ Block Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showBrand` | `false` | *N/A* | Schema: bool (maps to `show_brand`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showCompareButton` | `true` | *N/A* | Schema: bool (maps to `show_compare_button`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showFeatures` | `true` | *N/A* | Schema: bool (maps to `show_features`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_brand` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_category` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/vehicles-grid` -> `[rentiva_vehicles_grid]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `category` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `2` | `2` | Schema: int | ✅ |
| `columns_mobile` | `1` | `1` | Schema: int | ✅ |
| `columns_tablet` | `2` | `2` | Schema: int | ✅ |
| `custom_css_class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `featured` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
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
| `show_booking_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_brand` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_category` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_compare_button` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
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
| `defaultSort` | `price_asc` | *N/A* | Schema: string (maps to `default_sort`) | ⚠️ Block Only |
| `default_sort` | *N/A* | `price_asc` | Schema: string (values `relevance`, `price_asc`, `price_desc`, `name_asc`, `name_desc`, `year_desc`) | ⚠️ SC Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `12` | *N/A* | Schema: int | ⚠️ Block Only |
| `results_per_page` | *N/A* | `12` | Schema: int | ⚠️ SC Only |
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
| `showComparisonImages` | `true` | *N/A* | Schema: bool (maps to `show_images`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRemoveButtons` | `true` | *N/A* | Schema: bool (maps to `show_remove_buttons`) | ⚠️ Block Only |
| `show_add_vehicle` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_buttons` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
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
| `redirect_page` | `default` | `default` | Schema: string | ✅ |
| `search_layout` | `horizontal` | `horizontal` | Schema: string | ✅ |
| `service_type` | `both` | `both` | Schema: enum | ✅ |
| `show_date_picker` | `default` | `default` | Schema: bool | ✅ |
| `show_dropoff_location` | `default` | `default` | Schema: bool | ✅ |
| `show_location_select` | `default` | `default` | Schema: bool | ✅ |
| `show_luggage` | `default` | `default` | Schema: bool | ✅ |
| `show_pax` | `default` | `default` | Schema: bool | ✅ |
| `show_rental_tab` | `default` | `default` | Schema: bool | ✅ |
| `show_time_select` | `default` | `default` | Schema: bool | ✅ |
| `show_transfer_tab` | `default` | `default` | Schema: bool | ✅ |

#### `mhm-rentiva/my-bookings` -> `[rentiva_my_bookings]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `filterStatus` | `all` | *N/A* | Schema: enum (maps to `status`) | ⚠️ Block Only |
| `hide_nav` | *N/A* | `false` | Schema: bool | ⚠️ SC Only |
| `limit` | `10` | `10` | Schema: int | ✅ |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `showBookingDates` | `true` | *N/A* | Schema: bool (maps to `show_booking_dates`) | ⚠️ Block Only |
| `showPagination` | `true` | *N/A* | Schema: bool (maps to `show_pagination`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showStatus` | `true` | *N/A* | Schema: bool (maps to `show_status_toggle`) | ⚠️ Block Only |
| `showVehicleImage` | `true` | *N/A* | Schema: bool (maps to `show_image`) | ⚠️ Block Only |
| `show_booking_dates` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_image` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pagination` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_status_toggle` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |
| `status` | *N/A* | `""` | Schema: enum | ⚠️ SC Only |

#### `mhm-rentiva/my-favorites` -> `[rentiva_my_favorites]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `3` | `3` | Schema: int | ✅ |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `12` | `12` | Schema: int | ✅ |
| `noResultsText` | `""` | *N/A* | Schema: string (maps to `no_results_text`) | ⚠️ Block Only |
| `no_results_text` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `added` | Schema: enum (`added`, `name`, `price`; legacy `date`, `title`) | ⚠️ SC Only |
| `showAvailabilityStatus` | `false` | *N/A* | Schema: bool (maps to `show_availability_status`) | ⚠️ Block Only |
| `showBookButton` | `true` | *N/A* | Schema: bool (maps to `show_booking_button`) | ⚠️ Block Only |
| `showCategory` | `true` | *N/A* | Schema: bool (maps to `show_category`) | ⚠️ Block Only |
| `showFavoriteButton` | `true` | *N/A* | Schema: bool (maps to `show_favorite_button`) | ⚠️ Block Only |
| `showPrice` | `true` | *N/A* | Schema: bool (maps to `show_price`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `show_availability_status` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_booking_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_favorite_btn` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_features` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_image` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_price` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_title` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `added` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/payment-history` -> `[rentiva_payment_history]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `filterStatus` | `all` | *N/A* | Schema: enum (maps to `status`) | ⚠️ Block Only |
| `hide_nav` | *N/A* | `false` | Schema: bool | ⚠️ SC Only |
| `limit` | `10` | `10` | Schema: int | ✅ |
| `order` | *N/A* | `desc` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum (`date`, `amount`) | ⚠️ SC Only |
| `showBookingLink` | `false` | *N/A* | Schema: bool (maps to `show_booking_link`) | ⚠️ Block Only |
| `showDate` | `true` | *N/A* | Schema: bool (maps to `show_date`) | ⚠️ Block Only |
| `showPagination` | `true` | *N/A* | Schema: bool (maps to `show_pagination`) | ⚠️ Block Only |
| `showPaymentMethod` | `true` | *N/A* | Schema: bool (maps to `show_payment_method`) | ⚠️ Block Only |
| `show_booking_link` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_date` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_pagination` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_payment_method` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `sortBy` | `date` | *N/A* | Schema: enum (maps to `orderby`) | ⚠️ Block Only |
| `sortOrder` | `desc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |
| `status` | *N/A* | `""` | Schema: enum (`""`, `pending`, `completed`, `cancelled`) | ⚠️ SC Only |

#### `mhm-rentiva/transfer-search` -> `[rentiva_transfer_search]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `buttonText` | `""` | *N/A* | Schema: string (maps to `button_text`) | ⚠️ Block Only |
| `button_text` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `layout` | `horizontal` | `horizontal` | Schema: enum (`horizontal`, `vertical`) | ✅ |
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
| `layout` | `list` | `list` | Schema: enum (`list`, `grid`) | ✅ |
| `limit` | `10` | `10` | Schema: int | ✅ |
| `order` | *N/A* | `asc` | Schema: enum | ⚠️ SC Only |
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
| `sortOrder` | `asc` | *N/A* | Schema: enum (maps to `order`) | ⚠️ Block Only |

#### `mhm-rentiva/contact` -> `[rentiva_contact]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `auto_reply` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `description` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `emailTo` | `""` | *N/A* | Schema: string (maps to `email_to`) | ⚠️ Block Only |
| `email_to` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `redirect_url` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `showBookingIdField` | `false` | *N/A* | Schema: bool (maps to `show_booking_id_field`) | ⚠️ Block Only |
| `showCompanyInfo` | `true` | *N/A* | Schema: bool (maps to `show_company_info`) | ⚠️ Block Only |
| `showPhone` | `true` | *N/A* | Schema: bool (maps to `show_phone`) | ⚠️ Block Only |
| `showVehicleSelector` | `false` | *N/A* | Schema: bool (maps to `show_vehicle_selector`) | ⚠️ Block Only |
| `show_attachment` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_booking_id_field` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_company` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_company_info` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_phone` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_priority` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `show_vehicle_selector` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `successMessage` | `""` | *N/A* | Schema: string (maps to `success_message`) | ⚠️ Block Only |
| `success_message` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `theme` | *N/A* | `default` | Schema: string | ⚠️ SC Only |
| `title` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `type` | *N/A* | `general` | Schema: enum | ⚠️ SC Only |

#### `mhm-rentiva/testimonials` -> `[rentiva_testimonials]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `autoRotate` | `false` | *N/A* | Schema: bool (maps to `auto_rotate`) | ⚠️ Block Only |
| `auto_rotate` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |
| `class` | *N/A* | `""` | Schema: string | ⚠️ SC Only |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `columns` | `3` | `3` | Schema: int | ✅ |
| `filterRating` | `""` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `layout` | `grid` | `grid` | Schema: string | ✅ |
| `limit` | `5` | `5` | Schema: int | ✅ |
| `order` | *N/A* | `DESC` | Schema: enum | ⚠️ SC Only |
| `orderby` | *N/A* | `date` | Schema: enum (`date`, `rating`, `rand`) | ⚠️ SC Only |
| `rating` | *N/A* | `""` | Schema: int | ⚠️ SC Only |
| `showAuthorAvatar` | `true` | *N/A* | Schema: bool (maps to `show_author_avatar`) | ⚠️ Block Only |
| `showCustomer` | `true` | *N/A* | Schema: bool (maps to `show_customer`) | ⚠️ Block Only |
| `showDate` | `true` | *N/A* | Schema: bool (maps to `show_date`) | ⚠️ Block Only |
| `showQuotes` | `true` | *N/A* | Schema: bool (maps to `show_quotes`) | ⚠️ Block Only |
| `showRating` | `true` | *N/A* | Schema: bool (maps to `show_rating`) | ⚠️ Block Only |
| `showVehicle` | `true` | *N/A* | Schema: bool (maps to `show_vehicle`) | ⚠️ Block Only |
| `show_author_avatar` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_customer` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_date` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_quotes` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
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
| `show_form` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_rating_display` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `show_ratings_list` | *N/A* | `1` | Schema: bool | ⚠️ SC Only |
| `vehicleId` | `""` | *N/A* | Schema: int (maps to `vehicle_id`) | ⚠️ Block Only |
| `vehicle_id` | *N/A* | `""` | Schema: int | ⚠️ SC Only |

#### `mhm-rentiva/messages` -> `[rentiva_messages]`
| Attribute | Block (`block.json`) | Shortcode Class | Canonical `Allowlist` | Match |
| :--- | :--- | :--- | :--- | :--- |
| `className` | `""` | *N/A* | Schema: string (maps to `class`) | ⚠️ Block Only |
| `hide_nav` | *N/A* | `false` | Schema: bool | ⚠️ SC Only |
| `showThreadPreview` | `false` | *N/A* | Schema: bool (maps to `show_thread_preview`) | ⚠️ Block Only |
| `show_thread_preview` | *N/A* | `0` | Schema: bool | ⚠️ SC Only |



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

Removed without an alias in Elementor bridge slice 3A (2026-10): these keys had a working twin, so a
saved or hand-written value is now ignored instead of being mapped.

| Removed Attribute | Tag | Use Instead |
| :--- | :--- | :--- |
| `show_book_button` | `rentiva_featured_vehicles` | `show_booking_button` |
| `layout` | `rentiva_unified_search` | `search_layout` |
| `minwidth`, `maxwidth` (aliases `minWidth`, `maxWidth`, `min_width`, `max_width`) | `rentiva_unified_search` | Theme / block layout styles |
| `limit_results` (block `limitResults`) | `rentiva_my_bookings`, `rentiva_payment_history` | `limit` |
| `show_remove_button` (block `showRemoveButton`) | `rentiva_my_favorites` | `show_favorite_button` |
| `recipient_email`, `show_phone_field`, `show_vehicle_select` | `rentiva_contact` | `email_to`, `show_phone`, `show_vehicle_selector` |
| `autoplay`, `limit_items`, `show_author_name`, `show_vehicle_name` | `rentiva_testimonials` | `auto_rotate`, `limit`, `show_customer`, `show_vehicle` |
| `show_booking_form` | `rentiva_vehicle_details` | `show_booking` (reviews follow `show_reviews`) |
| `show_booking_links` | `rentiva_availability_calendar` | `show_booking_button` |
| `orderby`, `order` | `rentiva_search_results` | `default_sort` |

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

Enum value sets (v4.20.3):
- `status`: `""`, `draft`, `pending_payment`, `pending`, `confirmed`, `in_progress`, `completed`, `cancelled`, `refunded`, `no_show`
- `orderby`: `price`, `popularity`, `newest`, `capacity`, `title`, `date`, `modified`, `rand`, `post__in`, `rating`, `rating_average`, `rating_count`, `confidence`
- `sort_by`: `price`, `popularity`, `newest`, `capacity`, `title`, `date`, `modified`, `rand`, `rating`, `rating_average`, `rating_count`, `confidence`

Per-tag value sets narrowed in slice 3A (the tag schema overrides the global set):
- `rentiva_testimonials` `orderby`: `date`, `rating`, `rand`
- `rentiva_my_favorites` `orderby`: `added`, `name`, `price` (legacy `date`, `title` accepted)
- `rentiva_payment_history` `orderby`: `date`, `amount`; `status`: `""`, `pending`, `completed`, `cancelled`
- `rentiva_search_results` `default_sort`: `relevance`, `price_asc`, `price_desc`, `name_asc`, `name_desc`, `year_desc`
- `rentiva_vehicles_list` `layout`: `list`, `grid`; `rentiva_transfer_results` `layout`: `list`, `grid`; `rentiva_transfer_search` `layout`: `horizontal`, `vertical`
- `rentiva_vendor_profile` `vehicle_sort`: `rating-newest`, `newest`, `price-asc`, `price-desc`

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
| `rentiva_search_results` | `default_sort` | `defaultSort`, `default_sort` | Values: `relevance`, `price_asc`, `price_desc`, `name_asc`, `name_desc`, `year_desc` |
| `rentiva_search_results` | `results_per_page` | `resultsPerPage`, `results_per_page`, `limit` | Coexists with canonical `limit` |
| `rentiva_vehicles_grid` | `show_booking_btn` | `showBookButton`, `show_book_btn` (via normalization) | Legacy/canonical key in grid context |
| `rentiva_vehicles_grid` | `show_favorite_button` | `showFavoriteButton`, `show_favorite_button` | Used by block inspector |
| `rentiva_unified_search` | `default_tab` | `defaultTab`, `default_tab` | Workflow routing key |
| `rentiva_unified_search` | `search_layout` | `searchLayout`, `search_layout` | Layout mode bridge; the old `layout` key is no longer read |
| `rentiva_unified_search` | `redirect_page` | `redirect_url`, `redirectPage`, `redirectUrl` | URL/page compatibility bridge |
| `rentiva_booking_form` | `vehicle_id` | `vehicleId`, `vehicle_id` | Primary entity binding |

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
| 2026-10-11 | Lite + Pro shortcodes, blocks, Elementor widgets | Fix | Elementor bridge slice 3A: 61 saved-but-ignored block/Elementor settings now apply (defaults keep the old render); see Section 3.6 | Conditional (appearance changes only where a value was set) | Review pages that set these values |
| 2026-10-11 | Lite + Pro shortcodes, blocks, Elementor widgets | Removed | Settings with no effect removed from block.json, Elementor, the tag schema and the aliases; twin controls re-bound to the live key (Sections 3, 4, 8) | Compatible (saved values are ignored as before) except `show_book_button` and unified-search `layout` in shortcode text | Use `show_booking_button` / `search_layout` |
| 2026-10-11 | Account lists | Added | Public query var `mhmrentiva_page` pages my-bookings and payment-history (also on the WooCommerce endpoint) | Compatible | None |
| YYYY-MM-DD | shortcode/block name | Added/Changed/Deprecated/Removed/Fix | Describe what changed | Compatible/Conditional/Breaking | Migration/test notes |

Maintenance rules:
- Record every parameter contract change in this table.
- If change is `Breaking`, include migration notes in release docs.
- Keep this log synchronized with release notes and changelog.

---

Last updated: 2026-10-11

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
- 22 of the 27 production shortcodes (excluding `rentiva_home_poc`) have a corresponding Elementor widget. The remaining five are intentionally shortcode-only: `rentiva_vendor_apply`, `rentiva_vehicle_submit`, `rentiva_vendor_bookings`, `rentiva_vendor_ledger`, `rentiva_commission_resolver`.

---

## 12. Ecosystem Audit Findings — v4.20.1 X-Ray (2026-02-25)

> **Historical record, not the current contract.** This section is the audit as it stood on
> 2026-02-25 (v4.20.1). Its findings were resolved in v4.20.2 (see *v4.20.2 Resolution Status* at
> the end of this section), and Elementor bridge slice 3A (2026-10-11) later reworked the settings
> it names: `min_days` / `max_days` are no longer registry keys, `show_booking_form` was removed
> in favour of `show_booking`, and featured vehicles' `show_book_button` in favour of
> `show_booking_button`. None of the rows below is a live gap; the current keys, defaults and
> removals are in Sections 3 (3.6), 4 and 8.

> Instruction: V4.20.1-ECOSYSTEM-AUDIT
> Evidence Plan: `docs/plans/2026-02-25-v4201-ecosystem-audit.md`

### 12.1 Inventory Snapshot Summary

| Layer | Count | Coverage |
| :--- | :---: | :--- |
| Active Shortcodes | 27 | 26 covered (1 experimental: home_poc) |
| Gutenberg Blocks | 22 | All 22 map to shortcodes via render_callback |
| AllowlistRegistry TAG_MAPPING | 23 | 23 shortcodes covered (home_poc missing) |
| Elementor Widgets | 23 | 22 SC-mapped + 1 component widget |
| block.json files | 22 | Verified in `assets/blocks/*/block.json` |

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
| Shortcode inventory verified | ✅ 27/27 shortcodes confirmed |
| Block registry verified | ✅ 22/22 blocks confirmed |
| AllowlistRegistry TAG_MAPPING verified | ✅ 23 entries (home_poc intentionally excluded) |
| Elementor widget inventory | ✅ 23 widgets confirmed |
| block.json files verified | ✅ 22 files confirmed |
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

### v4.20.2 Resolution Status

| Finding | Status |
|---|---|
| C1: 10 duplicate ALLOWLIST keys | ✅ FIXED in v4.20.2 |
| M1: 4 enum attributes missing values | ✅ FIXED in v4.20.2 |
| M2: 6 TAG_MAPPING canonical gaps | ✅ FIXED in v4.20.2 |
| Governance: SHORTCODES.md not tracked | ✅ FIXED in v4.20.2 |
