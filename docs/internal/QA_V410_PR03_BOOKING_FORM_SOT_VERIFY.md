# QA_V410_PR03_BOOKING_FORM_SOT_VERIFY

## A) Shortcode Defaults (SOT)
- **File:** `src/Admin/Frontend/Shortcodes/BookingForm.php`
- **Line Reference:** [L105-L125](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Frontend/Shortcodes/BookingForm.php#L105-L125)

**Full Defaults List:**
```php
return array(
    'vehicle_id'            => '',
    'start_date'            => '',
    'end_date'              => '',
    'show_vehicle_selector' => '1',
    'default_days'          => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_default_rental_days', 1),
    'min_days'              => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1),
    'max_days'              => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30),
    'show_payment_options'  => '1',
    'show_addons'           => '1',
    'class'                 => '',
    'redirect_url'          => '',
    'enable_deposit'        => '1',
    'default_payment'       => 'deposit',
    'form_title'            => '',
    'show_vehicle_info'     => '1',
    'show_time_select'      => '1',
);
```

## B) Accepted Attribute Surface
- **Shortcode attributes merge mekanizması:** `AbstractShortcode` üzerinden `shortcode_atts` ile merge ediliyor.
- **step_mode supported?** NO.
- **Kanıt:** `get_default_attributes` listesinde yok. `grep` ile projenin tamamında arama yapıldı, PHP tarafında eşleşen bir iş mantığına rastlanmadı.

## C) Template Consumption
- **Template `step_mode` kullanıyor mu?** NO.
- **Kanıt:** [templates/shortcodes/booking-form.php](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/templates/shortcodes/booking-form.php) dosyası 1-442 arası satırlarda "step" kelimesi geçmemektedir.

## D) Block Current Attributes
- **File:** `assets/blocks/booking-form/block.json`
- **Attribute List:**
    - `title`
    - `description`
    - `show_login_prompt`
    - `show_title`
    - `show_description`
    - `show_date_picker`
    - `show_insurance`
    - `show_extras`
    - `show_price_summary`

**Uyumsuzluk Tespiti:**
Blok tarafındaki bazı boolean özniteliklerin (`show_insurance`, `show_extras`, `show_price_summary`) shortcode tarafında karşılığı yoktur veya farklı isimlendirilmiştir.

## E) Decision
**Decision:** `step_mode` is **NOT SUPPORTED**.

**PR-03 Action:**
- **Dropped:** `step_mode` için blok tarafına öznitelik eklenmeyecek.
- **Corrected Matrix:** `QA_V410_BLOCK_GATES.md` dosyasındaki "Missing-in-Block: step_mode" notu bir planlama kalıntısıdır, silinecektir.
- **Parity Alignment:** Shortcode'un mevcut öznitelikleri (`show_vehicle_selector`, `show_addons`, `show_payment_options`, `show_vehicle_info`, `show_time_select`) bloğa eklenecek ve varsayılanlar eşitlenecektir.

**Risk:** `step_mode` eklenmediği için runtime behavior değişmeyecektir.
