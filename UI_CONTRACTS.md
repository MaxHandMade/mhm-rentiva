# UI Contracts - MHM Rentiva

Contract Document Version: 1.1.0  
Plugin Snapshot Version: 4.10.0 (header), commit `53d2ad6`  
Date: 2026-02-18  
Breaking Change Definition: Stable olarak işaretli wrapper class, required slot, public attribute veya documented event/data contract kaldırma/yeniden adlandırma/değiştirme.  
Deprecation Policy: Breaking contract changes require minimum 2 minor versions OR 60 days deprecation window.  
Mandatory Deprecation Note Format:  
Deprecated in: x.y.z  
Removal in: x.y.z  
Migration: ...

## 0. Purpose
Bu doküman, MHM Rentiva kısa kodları, Gutenberg blokları ve gelecekteki Elementor widget katmanı için tema-tarafı UI contract sınırlarını koddan türeterek sabitler.

## 1. Global Conventions
- CSS naming base: `mhm-rentiva-*` ve mevcut üretimde kullanılan `rv-*`/`mhm-*` sınıfları.
- Output theme-agnostic hedeflenir, ancak mevcut markup bu sözleşmede kanıtlanan class/slot yüzeyleriyle tanımlıdır.
- Stability levels:
- `Stable`: `mhm-rentiva-*` wrapper/hooklar ve bu dokümanda açıkça Stable işaretlenen slotlar.
- `Semi-stable`: `rv-*` hooklar ve optional markup parçaları.
- `Internal`: debug alanları, nonce/data payload format detayları, geçici class/ID ve implementasyon detayları.
- Required slot tanımı: Slot, wrapper altında tema tarafından bağlanabilir semantik child alandır.
- Slot ifade kuralı: Slotlar class selector ile tanımlanır (örn. `.mhm-rentiva-results`, `.mhm-rentiva-form`).
- Required wrapper/slot stabilite kuralı: `mhm-rentiva-*` = Stable, `rv-*` = Semi-stable (required olsa bile kırılmaz garanti seviyesi Stable değildir).
- ID selector kuralı: `#id` selectorler contract için primary kabul edilmez; class-selector önceliklidir. ID selectorler implementation detail olarak değerlendirilir.

## 1.1 Architectural Layout Invariants

**CSS-Driven Layout Policy:**
Layout switching (e.g., grid vs. list) MUST NOT mutate the inner DOM structure of cards or containers. It is controlled exclusively by context classes on the wrapper (e.g., `rv-layout-grid` vs `rv-layout-list`) driving CSS Grid/Flexbox properties.

**Grid Isolation Contract:**
For Search and Transfer results:
1. The header (route info, summary, sorting) MUST sit strictly outside the main grid container.
2. The grid wrapper MUST contain ONLY card components. No inline notifications, route summaries, or pagination elements are allowed inside the grid container itself.
3. The `columns` attribute is consumed entirely as a CSS variable (e.g., `--mhm-columns: 2`) and does not structurally alter the PHP output loop.

## 2. Shortcodes
### 2.1 Inventory
- Stable/Semi-stable inventory (source of truth):
- `rentiva_booking_form` (Stable)
- `rentiva_availability_calendar` (Stable)
- `rentiva_booking_confirmation` (Stable)
- `rentiva_vehicle_details` (Stable)
- `rentiva_vehicles_list` (Stable)
- `rentiva_featured_vehicles` (Stable)
- `rentiva_vehicles_grid` (Stable)
- `rentiva_search_results` (Stable)
- `rentiva_vehicle_comparison` (Stable)
- `rentiva_unified_search` (Stable)
- `rentiva_my_bookings` (Stable)
- `rentiva_my_favorites` (Stable)
- `rentiva_payment_history` (Stable)
- `rentiva_transfer_search` (Stable)
- `rentiva_transfer_results` (Stable)
- `rentiva_contact` (Stable)
- `rentiva_testimonials` (Stable)
- `rentiva_vehicle_rating_form` (Stable)
- `rentiva_messages` (Stable)

Resolved Discrepancy (Sequence 5):
Item: Shortcodes (`rentiva_hero_section`, `rentiva_trust_stats`, `rentiva_brand_scroller`, `rentiva_comparison_table`, `rentiva_extra_services`)
Legacy (SHORTCODES.md): Elite Edition altında planlanan kısa kodlar olarak listelenmişti.
Code truth: `src/Admin/Core/ShortcodeServiceProvider.php` registry içinde yok.
Status: FIXED. SHORTCODES.md has been structurally segmented, clearly separating "Planned / Not Active" shortcodes from the active inventory.
### 2.2 Contract per shortcode
#### `[rentiva_booking_form]`
Purpose: Rezervasyon formu.
Attributes: `vehicle_id(string, '', any)`, `start_date(string, '', Y-m-d)`, `end_date(string, '', Y-m-d)`, `show_vehicle_selector(string-bool, '1', 0|1)`, `default_days(int/string, settings default, >=1)`, `min_days(int/string, settings default, >=1)`, `max_days(int/string, settings default, >=1)`, `show_payment_options(string-bool, '1', 0|1)`, `show_addons(string-bool, '1', 0|1)`, `redirect_url(string, '', URL)`, `enable_deposit(string-bool, '1', 0|1)`, `default_payment(string, 'deposit', deposit|full)`, `form_title(string, '', any)`, `show_vehicle_info(string-bool, '1', 0|1)`, `show_time_select(string-bool, '1', 0|1)`, `class(string, '', css class)`.
Required wrapper class(es) (Contract; per-selector stability): `rv-booking-form-wrapper`.
Required slots (Contract; per-selector stability): `.rv-booking-form`, `form.rv-booking-form-content`.
Optional elements (Semi-stable): `.rv-form-section`, `.rv-selected-vehicle-preview`, `.rv-vehicle-rating-block`.
Do not rely on (Internal): generated `id` suffixes (`pickup_date-*`), inline styles.
Styling hooks (classes only): `rv-booking-form-wrapper`, `rv-booking-form-content`, `rv-btn-submit`.
Accessibility requirements: form labels/input `for`/`id` eşleşmesi korunmalı, required alanlarda native validation attributes korunmalı.
Examples: `[rentiva_booking_form vehicle_id="123" show_addons="1" enable_deposit="1"]`
Source: `src/Admin/Frontend/Shortcodes/BookingForm.php:62`, `src/Admin/Frontend/Shortcodes/BookingForm.php:72`, `templates/shortcodes/booking-form.php:70`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::get_shortcode_tag`
Verified by: shortcode default attributes + template wrapper/form slot scan.

Resolved Discrepancy (Sequence 5):
Item: Shortcode `[rentiva_booking_form]` attribute `show_insurance`
Legacy (SHORTCODES.md): Parametre mevcut ve destekli gibi listelenmişti.
Code truth: `get_default_attributes()` içinde `show_insurance` yoktu.
Status: FIXED. The canonical mapping and matrices have unified this attribute definitions with the codebase. 

#### `[rentiva_availability_calendar]`
Purpose: Araç müsaitlik takvimi.
Attributes: `vehicle_id(string,'',any)`, `show_pricing(string-bool,'1',0|1)`, `theme(string,'default',default|compact/filtered)`, `start_date(string,'',Y-m)`, `months_ahead(string,'3',1+)`, `show_weekends(string-bool,'1',0|1)`, `show_past_dates(string-bool,'0',0|1)`, `class(string,'',css class)`.
Required wrapper class(es) (Contract; per-selector stability): `rv-availability-calendar`.
Required slots (Contract; per-selector stability): `.rv-availability-grid`, `.rv-calendar-controls`.
Optional elements (Semi-stable): `.rv-availability-legend`, `.rv-vehicle-card-hifi-wrapper`.
Do not rely on (Internal): `data-vehicles` JSON shape, tooltip price payload serialization.
Styling hooks (classes only): `rv-availability-calendar`, `rv-calendar-day`, `rv-control-btn`.
Accessibility requirements: takvim günleri tıklanabilir alanlarda keyboard reachable buton/odak izi korunmalı.
Examples: `[rentiva_availability_calendar vehicle_id="42" show_pricing="1" months_ahead="2"]`
Source: `src/Admin/Frontend/Shortcodes/AvailabilityCalendar.php:62`, `src/Admin/Frontend/Shortcodes/AvailabilityCalendar.php:72`, `templates/shortcodes/availability-calendar.php:87`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::get_default_attributes`
Verified by: default attrs + wrapper + data attribute scan.

Resolved Discrepancy (Sequence 5):
Item: Shortcode `[rentiva_availability_calendar]` attribute envanteri
Legacy (SHORTCODES.md): Attribute listesi verilmemişti.
Code truth: `get_default_attributes()` birden fazla aktif attribute tanımlıyor.
Status: FIXED. A 3-Layer Default Comparison Matrix has comprehensively documented every shortcode's attributes.
#### `[rentiva_booking_confirmation]`
Purpose: Rezervasyon onay ekranı.
Attributes: `booking_id(string,'',booking id)`, `show_details(string-bool,'1',0|1)`, `show_actions(string-bool,'1',0|1)`, `class(string,'',css class)`.
Required wrapper class(es) (Contract; per-selector stability): `rv-booking-confirmation`.
Required slots (Contract; per-selector stability): `.rv-booking-details`, `.rv-payment-info`.
Optional elements (Semi-stable): `.rv-confirmation-actions`, `.rv-next-steps`.
Do not rely on (Internal): status badge text mapping internals.
Styling hooks (classes only): `rv-booking-confirmation`, `rv-detail-row`, `rv-price`.
Accessibility requirements: heading hierarchy ve işlem butonlarının text labels korunmalı.
Examples: `[rentiva_booking_confirmation booking_id="1001"]`
Source: `src/Admin/Frontend/Shortcodes/BookingConfirmation.php:30`, `src/Admin/Frontend/Shortcodes/BookingConfirmation.php:46`, `templates/shortcodes/booking-confirmation.php:83`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation::get_default_attributes`
Verified by: shortcode class + template structure scan.

#### `[rentiva_vehicle_details]`
Purpose: Tek araç detay görünümü.
Attributes: `vehicle_id(string,'',any)`, `show_gallery('1')`, `show_features('1')`, `show_pricing('1')`, `show_booking('1')`, `show_calendar('1')`, `show_price('1')`, `show_booking_button('1')`, `class('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-vehicle-details-wrapper`.
Required slots (Contract; per-selector stability): `.rv-vehicle-details`, `.rv-vehicle-info-section`.
Optional elements (Semi-stable): `.rv-mini-calendar-widget`, `.rv-gallery-thumbnails`.
Do not rely on (Internal): `data-index`, generated calendar month internals.
Styling hooks (classes only): `rv-vehicle-details-wrapper`, `rv-vehicle-gallery-section`, `rv-booking-card-sticky`.
Accessibility requirements: galeri görsellerinde `alt`, CTA butonlarında anlaşılır label.
Examples: `[rentiva_vehicle_details vehicle_id="88" show_calendar="1"]`
Source: `src/Admin/Frontend/Shortcodes/VehicleDetails.php:36`, `src/Admin/Frontend/Shortcodes/VehicleDetails.php:46`, `templates/shortcodes/vehicle-details.php:27`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::get_default_attributes`
Verified by: class defaults + template wrapper/slot scan.

#### `[rentiva_vehicles_list]`
Purpose: Araç liste görünümü.
Attributes: `limit('12')`, `columns('1')`, `orderby('title')`, `order('ASC')`, `category('')`, `featured('0')`, `show_image('1')`, `show_title('1')`, `show_price('1')`, `show_features('1')`, `show_rating('1')`, `show_booking_btn('1')`, `show_favorite_btn('1')`, `show_favorite_button('1')`, `show_category('1')`, `show_brand('0')`, `show_badges('1')`, `show_description('0')`, `show_availability('0')`, `show_compare_btn('1')`, `show_compare_button('1')`, `enable_lazy_load('1')`, `enable_ajax_filtering('0')`, `enable_infinite_scroll('0')`, `image_size('medium')`, `ids('')`, `max_features('5')`, `price_format('daily')`, `class('')`, `custom_css_class('')`, `min_rating('')`, `min_reviews('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-vehicles-list-container`.
Required slots (Contract; per-selector stability): `.rv-vehicles-list__wrapper`.
Optional elements (Semi-stable): `.rv-vehicles-list__empty`.
Do not rely on (Internal): internal query sort fallback ve empty-state metinleri.
Styling hooks (classes only): `rv-vehicles-list-container`, `rv-vehicles-list__wrapper`.
Accessibility requirements: liste kartlarının link/button erişilebilir adları korunmalı.
Examples: `[rentiva_vehicles_list limit="12" orderby="price" order="DESC"]`
Source: `src/Admin/Frontend/Shortcodes/VehiclesList.php:94`, `src/Admin/Frontend/Shortcodes/VehiclesList.php:110`, `templates/shortcodes/vehicles-list.php:28`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_default_attributes`
Verified by: class defaults + list template scan.

#### `[rentiva_featured_vehicles]`
Purpose: Öne çıkan araçlar slider/grid.
Attributes: `title(string, translatable default, any)`, `ids('')`, `category('')`, `limit('6')`, `layout('slider', slider|grid)`, `columns('3')`, `autoplay('1')`, `interval('5000')`, `orderby('date')`, `order('DESC')`, `show_price('1')`, `show_rating('1')`, `show_category('1')`, `show_book_button('1')`, `show_features('0')`, `show_brand('0')`, `show_availability('0')`, `show_compare_btn('1')`, `show_compare_button('1')`, `show_badges('1')`, `show_favorite_btn('1')`, `show_favorite_button('1')`.
Required wrapper class(es) (Contract; per-selector stability): `mhm-rentiva-featured-wrapper`.
Required slots (Contract; per-selector stability): `.mhm-featured-swiper` veya `.mhm-featured-grid`.
Optional elements (Semi-stable): `.swiper-pagination`, `.swiper-button-next`, `.swiper-button-prev`.
Do not rely on (Internal): slider initialization timing/event ordering.
Styling hooks (classes only): `mhm-rentiva-featured-wrapper`, `mhm-featured-grid`, `mhm-rentiva-featured-title`.
Accessibility requirements: slider controls keyboard/touch erişilebilir olmalı.
Examples: `[rentiva_featured_vehicles layout="slider" limit="6" autoplay="1"]`
Source: `src/Admin/Frontend/Shortcodes/FeaturedVehicles.php:25`, `src/Admin/Frontend/Shortcodes/FeaturedVehicles.php:35`, `templates/shortcodes/featured-vehicles.php:18`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles::get_default_attributes`
Verified by: shortcode defaults + wrapper/data attrs scan.

#### `[rentiva_vehicles_grid]`
Purpose: Araç grid görünümü.
Attributes: `limit('12')`, `columns('2')`, `orderby('title')`, `order('ASC')`, `category('')`, `featured('0')`, `show_image('1')`, `show_title('1')`, `show_price('1')`, `show_features('1')`, `show_rating('1')`, `show_booking_btn('1')`, `show_favorite_btn('1')`, `show_favorite_button('1')`, `show_category('1')`, `show_brand('0')`, `show_badges('1')`, `show_description('0')`, `show_availability('0')`, `show_compare_btn('1')`, `show_compare_button('1')`, `enable_lazy_load('1')`, `enable_ajax_filtering('0')`, `enable_infinite_scroll('0')`, `image_size('medium')`, `class('')`, `custom_css_class('')`, `min_rating('')`, `min_reviews('')`, `layout('grid', grid|masonry)`.
Required wrapper class(es) (Contract; per-selector stability): `rv-vehicles-grid-container`.
Required slots (Contract; per-selector stability): `.rv-vehicles-grid`.
Optional elements (Semi-stable): `.rv-vehicles-grid__empty`.
Do not rely on (Internal): masonry helper class calculation internals.
Styling hooks (classes only): `rv-vehicles-grid-container`, `rv-vehicles-grid`.
Accessibility requirements: kart CTA ve favori/compare düğmeleri erişilebilir olmalı.
Examples: `[rentiva_vehicles_grid columns="3" layout="masonry"]`
Source: `src/Admin/Frontend/Shortcodes/VehiclesGrid.php:31`, `src/Admin/Frontend/Shortcodes/VehiclesGrid.php:47`, `templates/shortcodes/vehicles-grid.php:33`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::get_default_attributes`
Verified by: defaults + grid template wrapper scan.

#### `[rentiva_search_results]`
Purpose: Arama sonuçları + filtreleme.
Attributes: `layout('grid', grid|list)`, `show_filters('1')`, `results_per_page('12')`, `show_pagination('1')`, `show_sorting('1')`, `show_view_toggle('1')`, `show_favorite_button('1')`, `show_compare_button('1')`, `default_sort('relevance', relevance|price_asc|price_desc|name_asc|name_desc)`, `class('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-search-results`.
Required slots (Contract; per-selector stability): `.rv-filters-form` (Semi-stable), `.rv-results-content` (Semi-stable), `.rv-vehicle-grid-wrapper` (Semi-stable).
Optional elements (Semi-stable): `.rv-view-toggle`, `.rv-pagination`, `.rv-loading-indicator`.
Do not rely on (Internal): `#rv-filters-form`, `#rv-results-layout-container`, `#rv-results-grid-content` ID selectorleri, ajax response HTML shape and no-result inline emoji block.
Styling hooks (classes only): `rv-search-results`, `rv-filters-sidebar`, `rv-layout-grid`, `rv-layout-list`.
Accessibility requirements: filter form input labels and sort/view controls buton semantics korunmalı.
Examples: `[rentiva_search_results layout="grid" show_filters="1"]`
Source: `src/Admin/Frontend/Shortcodes/SearchResults.php:62`, `src/Admin/Frontend/Shortcodes/SearchResults.php:72`, `templates/shortcodes/search-results.php:27`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::get_default_attributes`
Verified by: shortcode defaults + template IDs used by frontend JS.

Resolved Discrepancy (Sequence 5):
Item: Shortcode `[rentiva_search_results]` filtre parametreleri (`min_price`, `max_price` vb.)
Legacy (SHORTCODES.md): URL filtreleri shortcode attribute gibi sunulmuştu.
Code truth: Default attribute seti farklıdır; filtreler istek akışından okunur.
Status: FIXED. The 3-layer matrix accurately isolates core shortcode configuration attributes from query parameters, establishing canonical truth.

#### `[rentiva_vehicle_comparison]`
Purpose: Araç karşılaştırma görünümü.
Attributes: `vehicle_ids(string,'',comma list)`, `show_features(string,'all', feature key/all)`, `max_vehicles(string,'4',1-10 practical)`, `class(string,'',css class)`.
Required wrapper class(es) (Contract; per-selector stability): `rv-vehicle-comparison`.
Required slots (Contract; per-selector stability): `.rv-comparison-content`, `.rv-comparison-table-wrapper`.
Optional elements (Semi-stable): `.rv-comparison-mobile-list`, `.rv-comparison-cards`, `.rv-add-vehicle-section`.
Do not rely on (Internal): `data-features` / `data-all-vehicles` JSON payload schemas.
Styling hooks (classes only): `rv-vehicle-comparison`, `rv-remove-vehicle`, `rv-feature-item`.
Accessibility requirements: remove/add actions button element olarak kalmalı.
Examples: `[rentiva_vehicle_comparison vehicle_ids="12,34" max_vehicles="4"]`
Source: `src/Admin/Frontend/Shortcodes/VehicleComparison.php:40`, `src/Admin/Frontend/Shortcodes/VehicleComparison.php:50`, `templates/shortcodes/vehicle-comparison.php:35`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison::get_default_attributes`
Verified by: class defaults + template data attributes and slots.

#### `[rentiva_unified_search]`
Purpose: Kiralama + transfer birleşik arama.
Attributes: `default_tab('default', rental|transfer|default)`, `show_rental_tab('default')`, `show_transfer_tab('default')`, `show_location_select('default')`, `show_time_select('default')`, `show_date_picker('default')`, `show_dropoff_location('default')`, `show_pax('default')`, `show_luggage('default')`, `service_type('both', rental|transfer|both)`, `filter_categories('')`, `redirect_page('default')`, `layout('horizontal', horizontal|vertical|compact)`, `search_layout('')`, `style('glass', glass|solid)`, `class('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-unified-search`.
Required slots (Contract; per-selector stability): `.rv-unified-search__tabs`, `.rv-unified-search__panel`, `.rv-unified-search__form`.
Optional elements (Semi-stable): `.rv-unified-search__group--pax`, `.rv-unified-search__group--luggage`.
Do not rely on (Internal): tab switch event payload internals, generated form IDs.
Styling hooks (classes only): `rv-unified-search`, `rv-unified-search__tab`, `rv-unified-search__panel`.
Accessibility requirements: tab buttons `aria-selected`/panel ilişkisi korunmalı.
Examples: `[rentiva_unified_search default_tab="rental" style="glass" show_transfer_tab="1"]`
Source: `src/Admin/Frontend/Shortcodes/UnifiedSearch.php:25`, `src/Admin/Frontend/Shortcodes/UnifiedSearch.php:41`, `templates/shortcodes/unified-search.php:43`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch::get_default_attributes`
Verified by: class defaults + template tabs/panels + JS selectors.

#### `[rentiva_my_bookings]`
Purpose: Kullanıcı rezervasyon listesi.
Attributes: `limit('10')`, `status('')`, `orderby('date')`, `order('DESC')`, `hide_nav(bool,false,true|false)`.
Required wrapper class(es) (Contract; per-selector stability): `mhm-rentiva-account-page`.
Required slots (Contract; per-selector stability): `.rv-bookings-page`, `.rv-filter-form`.
Optional elements (Semi-stable): `.rv-bookings-table`, `.rv-no-bookings`.
Do not rely on (Internal): table column ordering and emoji empty-state icon.
Styling hooks (classes only): `mhm-rentiva-account-page`, `rv-bookings-page`, `rv-bookings-table`.
Accessibility requirements: table headers/cells mapping ve action button titles korunmalı.
Examples: `[rentiva_my_bookings limit="20" status="pending"]`
Source: `src/Admin/Frontend/Shortcodes/Account/MyBookings.php:19`, `src/Admin/Frontend/Shortcodes/Account/MyBookings.php:29`, `templates/account/bookings.php:71`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\Account\MyBookings::get_default_attributes`
Verified by: account shortcode defaults + bookings template wrapper scan.

#### `[rentiva_my_favorites]`
Purpose: Kullanıcı favori araçları.
Attributes: `limit('12')`, `columns('3')`, `orderby('date')`, `order('DESC')`, `show_image('1')`, `show_title('1')`, `show_price('1')`, `show_features('1')`, `show_rating('1')`, `show_booking_btn('1')`, `show_favorite_btn('1')`, `show_badges('1')`, `layout('grid')`, `no_results_text(translatable string)`.
Required wrapper class(es) (Contract; per-selector stability): `mhm-my-favorites-container`, `rv-my-favorites-wrapper`, `rv-vehicles-grid-container`.
Required slots (Contract; per-selector stability): `.rv-vehicles-grid`.
Optional elements (Semi-stable): `.rv-vehicles-grid__empty`.
Do not rely on (Internal): favorites query sort fallback logic.
Styling hooks (classes only): `mhm-my-favorites-container`, `rv-my-favorites-wrapper`, `rv-vehicles-grid`.
Accessibility requirements: kart CTA/favori butonları keyboard erişilebilir kalmalı.
Examples: `[rentiva_my_favorites columns="3" limit="12"]`
Source: `src/Admin/Frontend/Shortcodes/Account/MyFavorites.php:27`, `src/Admin/Frontend/Shortcodes/Account/MyFavorites.php:38`, `src/Admin/Frontend/Shortcodes/Account/MyFavorites.php:111`, `templates/shortcodes/vehicles-grid.php:33`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\Account\MyFavorites::prepare_template_data`
Verified by: shortcode wrapper_class injection + reused grid template scan.

#### `[rentiva_payment_history]`
Purpose: Kullanıcı ödeme geçmişi.
Attributes: `limit('20')`, `hide_nav(bool,false,true|false)`.
Required wrapper class(es) (Contract; per-selector stability): `mhm-rentiva-account-page`.
Required slots (Contract; per-selector stability): `.payment-history-list`, `.payment-item`.
Optional elements (Semi-stable): `.receipt-actions`, `.mhm-upload-receipt`.
Do not rely on (Internal): receipt action button text/icon details.
Styling hooks (classes only): `mhm-rentiva-account-page`, `payment-history-list`, `payment-item`.
Accessibility requirements: upload/remove receipt butonları erişilebilir ad taşımalı.
Examples: `[rentiva_payment_history limit="30"]`
Source: `src/Admin/Frontend/Shortcodes/Account/PaymentHistory.php:19`, `src/Admin/Frontend/Shortcodes/Account/PaymentHistory.php:29`, `templates/account/payment-history.php:24`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\Account\PaymentHistory::get_default_attributes`
Verified by: account shortcode + template wrapper scan.

#### `[rentiva_transfer_search]`
Purpose: Transfer arama formu.
Attributes: explicit default attrs yok (`[]`), request tabanlı alanlar form üzerinden gönderilir.
Required wrapper class(es) (Contract; per-selector stability): `rv-transfer-search-container`.
Required slots (Contract; per-selector stability): `.js-unified-transfer-form`, `.rv-unified-search__action`.
Optional elements (Semi-stable): `.rv-unified-search__group--pax`, `.rv-unified-search__group--luggage`.
Do not rely on (Internal): generated form ID (`mhm-transfer-search-form-*`).
Styling hooks (classes only): `rv-transfer-search-container`, `rv-unified-search__form`.
Accessibility requirements: form alan label ilişkisi korunmalı.
Examples: `[rentiva_transfer_search]`
Source: `src/Admin/Transfer/Frontend/TransferShortcodes.php:32`, `src/Admin/Transfer/Frontend/TransferShortcodes.php:56`, `templates/shortcodes/transfer-search.php:20`
Symbol: `MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes::get_default_attributes`
Verified by: shortcode class + transfer search template scan.

#### `[rentiva_transfer_results]`
Purpose: Transfer sonuç listesi/kartları.
Attributes: `show_favorite_button('1')`, `show_compare_button('1')`.
Required wrapper class(es) (Contract; per-selector stability): `mhm-transfer-results-page`.
Required slots (Contract; per-selector stability): `.mhm-transfer-results`, `.mhm-transfer-card`.
Optional elements (Semi-stable): `.mhm-transfer-results__summary`, `.mhm-transfer-results__empty`.
Do not rely on (Internal): booking CTA data payload (`data-origin-id`, `data-destination-id` vb.) exact shape.
Styling hooks (classes only): `mhm-transfer-results-page`, `mhm-transfer-card`, `rv-unified-search-results`.
Accessibility requirements: action butonlarında metin/icon ikilisi korunmalı.
Examples: `[rentiva_transfer_results show_compare_button="1"]`
Source: `src/Admin/Transfer/Frontend/TransferResults.php:31`, `src/Admin/Transfer/Frontend/TransferResults.php:47`, `templates/shortcodes/transfer-results.php:61`
Symbol: `MHMRentiva\Admin\Transfer\Frontend\TransferResults::get_default_attributes`
Verified by: class defaults + transfer template wrapper/data attrs scan.

#### `[rentiva_contact]`
Purpose: İletişim formu.
Attributes: `type('general', general|booking|support|feedback)`, `title('')`, `description('')`, `show_phone('1')`, `show_company('0')`, `show_vehicle_selector('0')`, `show_priority('0')`, `show_attachment('1')`, `redirect_url('')`, `email_to('')`, `auto_reply('1')`, `theme('default', default|compact|detailed)`, `class('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-contact-form`.
Required slots (Contract; per-selector stability): `.rv-form` (Semi-stable), `.rv-form-actions` (Semi-stable).
Optional elements (Semi-stable): `.rv-contact-success`, `.rv-contact-error`, `.rv-contact-info`.
Do not rely on (Internal): `#rv-contact-form` ID selectorü ve priority `data-color` rendering logic.
Styling hooks (classes only): `rv-contact-form`, `rv-form`, `rv-form-group`.
Accessibility requirements: required fields, email/tel input types, success/error bölgeleri screen-reader dostu tutulmalı.
Examples: `[rentiva_contact type="support" show_phone="1"]`
Source: `src/Admin/Frontend/Shortcodes/ContactForm.php:40`, `src/Admin/Frontend/Shortcodes/ContactForm.php:50`, `templates/shortcodes/contact-form.php:38`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\ContactForm::get_default_attributes`
Verified by: default attrs + contact form template scan.

#### `[rentiva_testimonials]`
Purpose: Müşteri yorumları carousel/list/grid.
Attributes: `limit('5')`, `rating('')`, `vehicle_id('')`, `show_rating('1')`, `show_date('1')`, `show_vehicle('1')`, `show_customer('1')`, `layout('grid', grid|list|carousel)`, `columns('3')`, `auto_rotate('0')`, `class('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-testimonials`.
Required slots (Contract; per-selector stability): `.rv-testimonials-container`, `.rv-testimonial-item`.
Optional elements (Semi-stable): `.rv-testimonials-carousel`, `.rv-testimonials-load-more`.
Do not rely on (Internal): carousel indicator index generation.
Styling hooks (classes only): `rv-testimonials`, `rv-carousel-track`, `rv-load-more-btn`.
Accessibility requirements: carousel controls buton olarak kalmalı, yükle-daha-fazla eylemi erişilebilir olmalı.
Examples: `[rentiva_testimonials layout="carousel" limit="6" auto_rotate="1"]`
Source: `src/Admin/Frontend/Shortcodes/Testimonials.php:56`, `src/Admin/Frontend/Shortcodes/Testimonials.php:66`, `templates/shortcodes/testimonials.php:34`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::get_default_attributes`
Verified by: class defaults + testimonial template slot scan.

#### `[rentiva_vehicle_rating_form]`
Purpose: Araç değerlendirme/yorum formu ve liste.
Attributes: `vehicle_id('')`, `show_rating_display('1')`, `show_form('1')`, `show_ratings_list('1')`, `class('')`.
Required wrapper class(es) (Contract; per-selector stability): `rv-rating-form`.
Required slots (Contract; per-selector stability): `.rv-rating-display`, `.rv-ratings-list`, `form.rv-rating-form-content`.
Optional elements (Semi-stable): `.rv-login-required`, `.rv-delete-rating`.
Do not rely on (Internal): debug data attributes (`data-debug-*`, `data-render-time`).
Styling hooks (classes only): `rv-rating-form`, `rv-review-item`, `rv-rating-stars-input`.
Accessibility requirements: yıldız derecelendirme inputları label ile bağlı kalmalı.
Examples: `[rentiva_vehicle_rating_form vehicle_id="55"]`
Source: `src/Admin/Frontend/Shortcodes/VehicleRatingForm.php:32`, `src/Admin/Frontend/Shortcodes/VehicleRatingForm.php:37`, `templates/shortcodes/vehicle-rating-form.php:66`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::get_default_attributes`
Verified by: defaults + template wrapper and form slot scan.

#### `[rentiva_messages]`
Purpose: Kullanıcı mesajlaşma paneli.
Attributes: `hide_nav(bool,false,true|false)`.
Required wrapper class(es) (Contract; per-selector stability): `mhm-rentiva-account-page`.
Required slots (Contract; per-selector stability): `.messages-list` (Semi-stable), `.message-thread` (Semi-stable), `.new-message-form` (Semi-stable).
Optional elements (Semi-stable): `#reply-form`, `#new-message-form`.
Do not rely on (Internal): `#messages-list`, `#message-thread`, `#send-message-form` ID selectorleri; hidden class toggling order and temporary loading markup.
Styling hooks (classes only): `mhm-rentiva-account-page`, `messages-list`, `message-thread`.
Accessibility requirements: message compose/reply formları label ve required alanlarla kalmalı.
Examples: `[rentiva_messages]`
Source: `src/Admin/Frontend/Shortcodes/Account/AccountMessages.php:19`, `src/Admin/Frontend/Shortcodes/Account/AccountMessages.php:29`, `templates/account/messages.php:36`
Symbol: `MHMRentiva\Admin\Frontend\Shortcodes\Account\AccountMessages::get_default_attributes`
Verified by: account shortcode class + messages template IDs.

## 3. Gutenberg Blocks
### 3.1 Inventory
- `mhm-rentiva/availability-calendar`
- `mhm-rentiva/booking-confirmation`
- `mhm-rentiva/booking-form`
- `mhm-rentiva/contact`
- `mhm-rentiva/featured-vehicles`
- `mhm-rentiva/messages`
- `mhm-rentiva/my-bookings`
- `mhm-rentiva/my-favorites`
- `mhm-rentiva/payment-history`
- `mhm-rentiva/search-results`
- `mhm-rentiva/testimonials`
- `mhm-rentiva/transfer-results`
- `mhm-rentiva/unified-search`
- `mhm-rentiva/vehicle-comparison`
- `mhm-rentiva/vehicle-details`
- `mhm-rentiva/vehicle-rating-form`
- `mhm-rentiva/vehicles-grid`
- `mhm-rentiva/vehicles-list`

### 3.2 Contract per block
Ortak kanıt: tüm bloklar `register_block_type(..., ['render_callback' => [BlockRegistry::class, 'render_callback']])` ile register edilir.

#### `mhm-rentiva/booking-form`
Attributes: `vehicle_id`, `show_insurance` (deprecated), `show_addons`, `show_vehicle_selector`, `show_payment_options`, `enable_deposit`, `show_date_picker`, `show_time_select`, `show_vehicle_info`, `form_title`, `className`.
SSR: Y (Deterministic Parity v4.10.0).
Frontend Output Contract: wrapper WP block wrapper + inner shortcode output. Parity mapping validated.
Required wrapper class(es) (Contract; per-selector stability): outer Gutenberg wrapper + inner `rv-booking-form-wrapper`.
Required slots (Contract; per-selector stability): `form.rv-booking-form-content`.
Optional elements (Semi-stable): selected vehicle preview blocks.
Do not rely on (Internal): outer wrapper `data-debug-atts`.
Editor-specific notes: server-side render preview uses shortcode mapping.
Source: `assets/blocks/booking-form/block.json:4`, `src/Blocks/BlockRegistry.php:116`, `src/Blocks/BlockRegistry.php:316`, `src/Blocks/BlockRegistry.php:338`
Symbol: `MHMRentiva\Blocks\BlockRegistry::render_callback`
Verified by: block.json + registry tag map + render_callback scan.

#### `mhm-rentiva/availability-calendar`
Attributes: `vehicleId`, `showVehicleSelector`, `showLegend`, `showPricing`, `showBookingLinks`, `showMonthNavigation`, `showTodayButton`, `showWeekNumbers`, `monthsToShow`, `startWeekOn`, `calendarHeight`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-availability-calendar`.
Required slots (Contract; per-selector stability): `.rv-availability-grid`, `.rv-calendar-controls`.
Optional elements (Semi-stable): legend/switcher blocks.
Do not rely on (Internal): debug attr serialization.
Editor-specific notes: attrs camelCase map to shortcode attrs.
Source: `assets/blocks/availability-calendar/block.json:4`, `src/Blocks/BlockRegistry.php:57`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks`
Verified by: slug->tag map and SSR registration.

#### `mhm-rentiva/booking-confirmation`
Attributes: `bookingId`, `showBookingDetails`, `showVehicleInfo`, `showPaymentSummary`, `showPickupInstructions`, `showContactInfo`, `showPrintButton`, `showDownloadPDF`, `showCancelButton`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-booking-confirmation`.
Required slots (Contract; per-selector stability): `.rv-booking-details`.
Optional elements (Semi-stable): action/info blocks.
Do not rely on (Internal): debug attr payload.
Editor-specific notes: block attrs mapped to shortcode attrs.
Source: `assets/blocks/booking-confirmation/block.json:4`, `src/Blocks/BlockRegistry.php:62`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::register_blocks`
Verified by: block.json and registry mapping.

#### `mhm-rentiva/contact`
Attributes: `recipientEmail`, `showPhoneField`, `showSubjectField`, `showVehicleSelect`, `showBookingIdField`, `showCompanyInfo`, `showSocialLinks`, `showMap`, `subjectPrefix`, `successMessage`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-contact-form`.
Required slots (Contract; per-selector stability): `.rv-form` (Semi-stable).
Optional elements (Semi-stable): success/error/info panels.
Do not rely on (Internal): debug data attribute serialization.
Editor-specific notes: rendered through shortcode `rentiva_contact`.
Source: `assets/blocks/contact/block.json:4`, `src/Blocks/BlockRegistry.php:90`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['contact']`
Verified by: block config + shared render callback.

#### `mhm-rentiva/featured-vehicles`
Attributes: `layout`, `columns`, `limit`, `sortBy`, `sortOrder`, `serviceType`, `filterCategories`, `filterBrands`, `showPrice`, `showRating`, `showFeatures`, `showBookButton`, `showCategory`, `showBrand`, `showBadges`, `showFavoriteButton`, `showAvailability`, `showCompareButton`, `className` (as defined in block.json).
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `mhm-rentiva-featured-wrapper`.
Required slots (Contract; per-selector stability): `.mhm-featured-swiper` veya `.mhm-featured-grid`.
Optional elements (Semi-stable): swiper navigation.
Do not rely on (Internal): slider reinit event timing.
Editor-specific notes: block maps to shortcode `rentiva_featured_vehicles`.
Source: `assets/blocks/featured-vehicles/block.json:4`, `src/Blocks/BlockRegistry.php:84`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['featured-vehicles']`
Verified by: block.json + registry slug/tag match.

#### `mhm-rentiva/messages`
Attributes: `limitItems`, `sortBy`, `sortOrder`, `filterStatus`, `showThreadPreview`, `showReplyButton`, `showDate`, `showAuthorAvatar`, `showUnreadBadge`, `showBookingLink`, `showPagination`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `mhm-rentiva-account-page`.
Required slots (Contract; per-selector stability): `.messages-list` (Semi-stable), `.message-thread` (Semi-stable).
Optional elements (Semi-stable): new/reply form containers.
Do not rely on (Internal): hidden class toggling implementation.
Editor-specific notes: account-specific rendering still shortcode-based.
Source: `assets/blocks/messages/block.json:4`, `src/Blocks/BlockRegistry.php:122`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['messages']`
Verified by: map to `rentiva_messages` + template IDs.

#### `mhm-rentiva/my-bookings`
Attributes: `limitResults`, `sortBy`, `sortOrder`, `filterStatus`, `showBookingDates`, `showVehicleImage`, `showStatus`, `showPrice`, `showDetailsLink`, `showCancelButton`, `showModifyButton`, `showPagination`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `mhm-rentiva-account-page`.
Required slots (Contract; per-selector stability): `.rv-bookings-page`, `.rv-filter-form`.
Optional elements (Semi-stable): `.rv-bookings-table`.
Do not rely on (Internal): empty-state exact DOM copy.
Editor-specific notes: same renderer as shortcode.
Source: `assets/blocks/my-bookings/block.json:4`, `src/Blocks/BlockRegistry.php:100`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['my-bookings']`
Verified by: registry + template evidence.

#### `mhm-rentiva/my-favorites`
Attributes: `layout`, `columns`, `sortBy`, `sortOrder`, `showAddedDate`, `showPrice`, `showRating`, `showCategory`, `showBookButton`, `showRemoveButton`, `showAvailabilityStatus`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-vehicles-grid-container`.
Required slots (Contract; per-selector stability): `.rv-vehicles-grid`.
Optional elements (Semi-stable): empty-state grid.
Do not rely on (Internal): favorites query internals.
Editor-specific notes: routes through `rentiva_my_favorites`.
Source: `assets/blocks/my-favorites/block.json:4`, `src/Blocks/BlockRegistry.php:105`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['my-favorites']`
Verified by: block mapping + shortcode template reuse.

#### `mhm-rentiva/payment-history`
Attributes: `limitResults`, `sortBy`, `sortOrder`, `filterStatus`, `showDate`, `showPaymentMethod`, `showTransactionId`, `showBookingLink`, `showInvoiceDownload`, `showPagination`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `mhm-rentiva-account-page`.
Required slots (Contract; per-selector stability): `.payment-history-list`.
Optional elements (Semi-stable): receipt upload/actions.
Do not rely on (Internal): receipt tool action markup details.
Editor-specific notes: same as shortcode renderer.
Source: `assets/blocks/payment-history/block.json:4`, `src/Blocks/BlockRegistry.php:111`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['payment-history']`
Verified by: block mapping and account template hooks.

#### `mhm-rentiva/search-results`
Attributes: `layout`, `limit`, `sortBy`, `sortOrder`, `showFilters`, `showSorting`, `showPagination`, `showPrice`, `showRating`, `showFavoriteButton`, `showCompareButton`, `showAvailability`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-search-results`.
Required slots (Contract; per-selector stability): `.rv-filters-form` (Semi-stable), `.rv-results-content` (Semi-stable), `.rv-vehicle-grid-wrapper` (Semi-stable).
Optional elements (Semi-stable): view toggle and pagination blocks.
Do not rely on (Internal): ajax-generated card HTML details.
Editor-specific notes: dynamic preview via shortcode output.
Source: `assets/blocks/search-results/block.json:4`, `src/Blocks/BlockRegistry.php:34`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['search-results']`
Verified by: block config and selector usage in frontend JS.

#### `mhm-rentiva/testimonials`
Attributes: `layout`, `limitItems`, `columns`, `autoplay`, `filterRating`, `sortBy`, `sortOrder`, `showRating`, `showDate`, `showAuthorName`, `showAuthorAvatar`, `showVehicleName`, `showQuotes`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-testimonials`.
Required slots (Contract; per-selector stability): `.rv-testimonials-container`, `.rv-testimonial-item`.
Optional elements (Semi-stable): carousel indicators/load-more.
Do not rely on (Internal): animation class injection.
Editor-specific notes: server-rendered markup preview.
Source: `assets/blocks/testimonials/block.json:4`, `src/Blocks/BlockRegistry.php:52`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['testimonials']`
Verified by: block registry and testimonials template.

#### `mhm-rentiva/transfer-results`
Attributes: `layout`, `limit`, `sortBy`, `sortOrder`, `showPrice`, `showVehicleDetails`, `showBookButton`, `showFavoriteButton`, `showCompareButton`, `showRouteInfo`, `showPassengerCount`, `showLuggageInfo`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `mhm-transfer-results-page`.
Required slots (Contract; per-selector stability): `.mhm-transfer-results`, `.mhm-transfer-card`.
Optional elements (Semi-stable): summary/empty sections.
Do not rely on (Internal): booking payload data attributes exact fields.
Editor-specific notes: mapped to `rentiva_transfer_results` shortcode.
Source: `assets/blocks/transfer-results/block.json:4`, `src/Blocks/BlockRegistry.php:40`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['transfer-results']`
Verified by: registry + template scanning.

#### `mhm-rentiva/unified-search`
Attributes: `default_tab`, `service_type`, `show_rental_tab`, `show_transfer_tab`, `show_location_select`, `show_time_select`, `show_date_picker`, `show_dropoff_location`, `show_pax`, `show_luggage`, `filter_categories`, `redirect_page`, `search_layout`, `style`, `min_width`, `max_width`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-unified-search`.
Required slots (Contract; per-selector stability): `.rv-unified-search__tab`, `.rv-unified-search__panel`, `.rv-unified-search__form`.
Optional elements (Semi-stable): pax/luggage groups.
Do not rely on (Internal): `data-debug-atts` on outer block wrapper.
Editor-specific notes: block attributes camelCase/snake_case alias mapping exists.
Source: `assets/blocks/unified-search/block.json:4`, `src/Blocks/BlockRegistry.php:29`, `src/Blocks/BlockRegistry.php:338`
Symbol: `MHMRentiva\Blocks\BlockRegistry::map_attributes_to_shortcode`
Verified by: block attrs + mapping + renderer path.

#### `mhm-rentiva/vehicle-comparison`
Attributes: `vehicleIds`, `maxVehicles`, `showFeatures`, `showTechnicalSpecs`, `showPrice`, `showRating`, `showBookButton`, `showComparisonImages`, `showCategory`, `showTransmission`, `showFuelType`, `showSeats`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-vehicle-comparison`.
Required slots (Contract; per-selector stability): `.rv-comparison-content`.
Optional elements (Semi-stable): mobile/card layouts.
Do not rely on (Internal): feature JSON payload structure.
Editor-specific notes: alias mapping handles comparison-specific attrs.
Source: `assets/blocks/vehicle-comparison/block.json:4`, `src/Blocks/BlockRegistry.php:46`, `src/Blocks/BlockRegistry.php:518`
Symbol: `MHMRentiva\Blocks\BlockRegistry::map_attributes_to_shortcode`
Verified by: block config + comparison alias map.

#### `mhm-rentiva/vehicle-details`
Attributes: `vehicleId`, `showGallery`, `showFeatures`, `showTechnicalSpecs`, `showPrice`, `showAvailability`, `showBookingForm`, `showRating`, `showReviews`, `showFavoriteButton`, `showShareButtons`, `showBreadcrumb`, `showSimilarVehicles`, `similarVehiclesLimit`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-vehicle-details-wrapper`.
Required slots (Contract; per-selector stability): `.rv-vehicle-details`, `.rv-vehicle-info-section`.
Optional elements (Semi-stable): gallery thumbs/calendar widget.
Do not rely on (Internal): mini-calendar ajax loading markup details.
Editor-specific notes: SSR output mirrors shortcode.
Source: `assets/blocks/vehicle-details/block.json:4`, `src/Blocks/BlockRegistry.php:67`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['vehicle-details']`
Verified by: registry and template wrapper scan.

#### `mhm-rentiva/vehicle-rating-form`
Attributes: `vehicleId`, `requireLogin`, `requireBooking`, `showStarRating`, `showTextReview`, `showPhotoUpload`, `showRecommendToggle`, `showCategoryRatings`, `showVehiclePreview`, `minReviewLength`, `maxPhotos`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-rating-form`.
Required slots (Contract; per-selector stability): `.rv-ratings-list`, `form.rv-rating-form-content`.
Optional elements (Semi-stable): login notice/delete button blocks.
Do not rely on (Internal): debug attributes and transient counters.
Editor-specific notes: rendered by `rentiva_vehicle_rating_form`.
Source: `assets/blocks/vehicle-rating-form/block.json:4`, `src/Blocks/BlockRegistry.php:95`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['vehicle-rating-form']`
Verified by: block map + template evidence.

#### `mhm-rentiva/vehicles-grid`
Attributes: `layout`, `limit`, `columns`, `sortBy`, `sortOrder`, `filterCategories`, `filterBrands`, `showImage`, `showTitle`, `showPrice`, `showFeatures`, `showRating`, `showBookButton`, `showFavoriteButton`, `showCompareButton`, `showCategory`, `showBrand`, `showAvailability`, `minRating`, `minReviews`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-vehicles-grid-container`.
Required slots (Contract; per-selector stability): `.rv-vehicles-grid`.
Optional elements (Semi-stable): empty-state panel.
Do not rely on (Internal): internal class composition (`--columns-*`).
Editor-specific notes: alias mapping to shortcode keys.
Source: `assets/blocks/vehicles-grid/block.json:4`, `src/Blocks/BlockRegistry.php:72`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['vehicles-grid']`
Verified by: block config + grid template.

#### `mhm-rentiva/vehicles-list`
Attributes: `limit`, `orderby`, `order`, `filterCategories`, `filterBrands`, `showImage`, `showTitle`, `showPrice`, `showFeatures`, `showRating`, `showBookButton`, `showFavoriteButton`, `showCompareButton`, `showCategory`, `showBrand`, `showBadges`, `showDescription`, `showAvailability`, `minRating`, `minReviews`, `className`.
SSR: Y.
Required wrapper class(es) (Contract; per-selector stability): outer wrapper + inner `rv-vehicles-list-container`.
Required slots (Contract; per-selector stability): `.rv-vehicles-list__wrapper`.
Optional elements (Semi-stable): `.rv-vehicles-list__empty`.
Do not rely on (Internal): query fallback and inner card composition details.
Editor-specific notes: rendered through shortcode mapping.
Source: `assets/blocks/vehicles-list/block.json:4`, `src/Blocks/BlockRegistry.php:78`, `src/Blocks/BlockRegistry.php:316`
Symbol: `MHMRentiva\Blocks\BlockRegistry::$blocks['vehicles-list']`
Verified by: block map and template wrapper scan.

SSR Consistency Note: Bu snapshotta kayıtlı tüm bloklar `render_callback` kullandığı için SSR=Y. Static save() output kullanan block bulunmadı.

## 4. Shared Components & CSS Tokens
Scope: Bu bölüm yalnızca theme-facing contract hooklarını kapsar.

Common components:
- Vehicle card surfaces: `rv-vehicle-card*`, `mhm-vehicle-card`.
- Grid/list wrappers: `rv-vehicles-grid*`, `rv-vehicles-list*`.
- Account shell: `mhm-rentiva-account-page`.
- Unified search shell: `rv-unified-search*`.

Theme-facing token/hook list:
- Stable class hooks: `mhm-rentiva-featured-wrapper`, `mhm-rentiva-account-page`.
- Semi-stable class hooks: `rv-*` family.
- Exposed CSS vars (Semi-stable): `--mhm-primary`, `--mhm-text-primary`, `--mhm-btn-bg`, `--mhm-price-color` (core css variable layer).

Non-contract (explicit):
- Renk paleti seçimleri.
- Typography scale ve design preference kararları.
- Visual design guideline tercihleri.

## 5. Data & JS Contracts
Contract-level `data-*` attributes (Semi-stable unless noted):
- Booking form: `data-redirect-url`, `data-vehicle-id`.
- Availability: `data-vehicle-id`, `data-vehicle-price`, `data-start-month`, `data-months-to-show`, `data-date`.
- Search results/cards: `data-vehicle-id`.
- Comparison: `data-max-vehicles`, `data-features`, `data-all-vehicles`.
- Transfer results: `data-vehicle-id`, `data-origin-id`, `data-destination-id`, `data-date`, `data-time`.
- Rating form: `data-vehicle-id`.

Emitted DOM events:
- `mhm-rentiva:tab-changed` (Semi-stable): unified-search tab change.
- `mhm-rentiva-reinit-sliders` (Semi-stable): featured slider re-init trigger.

### 5.1 Notification Contract (MHMRentivaToast)
Scope: `assets/js/frontend/toast.js` tarafından kontrol edilen global bildirim katmanı.

- **Singleton API (Stable)**: `MHMRentivaToast.show(message, options)`
- **Options Contract (Semi-stable)**: 
  - `type`: `success | error | info | warning`.
  - `duration`: ms cinsinden (0 = sticky).
  - `idempotencyKey`: Flood koruması için benzersiz anahtar.
  - `action`: `{ label: string, href: string, onClick: function }`.
- **Two-Stage Pattern (Standard)**:
  - **Stage 1 (Optimistic)**: `type: 'info'`, `duration: 0`. "İşlem yapılıyor..." mesajı.
  - **Stage 2 (Final)**: Aynı `idempotencyKey` ile tekrar `show()` çağrılarak mevcut bildirim güncellenir.
- **UI Markup (Stable)**:
  - Wrapper: `.mhm-toast` (class `is-active`, `is-{type}` ile kontrol edilir).
  - Message: `.mhm-toast__message`.
  - Action Element: `.mhm-toast__action`.
  - Close Button: `.mhm-toast__close`.

### 5.2 Data & JS Contracts (Continued)
REST endpoints consumed:
- Namespace: `mhm-rentiva/v1`.
- Public UI Contract endpoints (theme/UI tarafı için contract kabul edilir):
- `GET /locations` (Unified Search location picker)
- `GET|POST /availability` (booking availability check)
- `GET|POST /availability/with-alternatives` (availability + alternatif araçlar)
- `GET /customer/messages`
- `POST /customer/messages`
- `GET /customer/messages/thread/{thread_id}`
- `POST /customer/messages/reply`
- `GET /customer/bookings`
- `POST /customer/messages/close`
- Internal endpoints (UI contract dışı, admin/operasyonel yüzey):
- `/messages`
- `/messages/{id}`
- `/messages/{id}/status`
- `/messages/{id}/reply`
- Versioning guarantee:
- Yukarıdaki Public UI Contract endpointleri `v1` içinde backward-compatible tutulur.
- Path/response breaking değişiklikleri için deprecation policy (2 minor veya 60 gün) uygulanır.

Evidence:
- Source: `assets/js/frontend/unified-search.js:87`, `assets/js/frontend/featured-vehicles.js:68`, `src/Admin/Frontend/Shortcodes/UnifiedSearch.php:165`, `src/Admin/REST/Locations.php:26`, `src/Admin/REST/Availability.php:36`, `templates/account/messages.php:33`, `src/Admin/Messages/REST/Messages.php:35`
- Symbol: `Block/UI frontend JS event + REST route registration`
- Verified by: JS trigger scan + REST route declarations.

## 6. Backward Compatibility Policy
- Stable contractlar (Stable wrapper/slot/attr/event) deprecation olmadan kırılamaz.
- Deprecation minimum penceresi: 2 minor sürüm OR 60 gün.
- Her deprecation kaydı zorunlu formatta yayınlanır:
- Deprecated in: x.y.z
- Removal in: x.y.z
- Migration: ...
- Contract-affecting değişiklikte `Contract Document Version` artırılır.

## 7. Elementor Forward Compatibility
- Elementor widget outputları mevcut shortcode/block renderer yolunu reuse etmelidir.
- Elementor-specific breaking markup divergence yasaktır.
- Documented Stable wrapper ve required slotlar korunmadan Elementor katmanında alternatif DOM sözleşmesi yayınlanamaz.
- Existing evidence: Elementor widget sınıfları `rv-*`/`mhm-rentiva-*` hooklarını hedefleyerek shortcode çıktısıyla hizalanır.

## 8. Evidence / References
Global inventory extraction sources:
- Source: `src/Admin/Core/ShortcodeServiceProvider.php:85`, `src/Admin/Core/ShortcodeServiceProvider.php:258`, `src/Blocks/BlockRegistry.php:28`, `src/Blocks/BlockRegistry.php:316`, `assets/blocks/*/block.json`
- Symbol: `ShortcodeServiceProvider::get_shortcode_registry`, `ShortcodeServiceProvider::register_tag`, `BlockRegistry::$blocks`, `BlockRegistry::register_blocks`
- Verified by: repo-wide keyword scan (`add_shortcode(`, `register_block_type(`, `render_callback`, `block.json`) + template/class cross-check.

Legacy comparison reference:
- Source: `SHORTCODES.md`
- Symbol: `Legacy helper doc`
- Verified by: code-derived inventory ile satır bazlı karşılaştırma; mismatchler dokümanda Discrepancy Note olarak işlendi.
