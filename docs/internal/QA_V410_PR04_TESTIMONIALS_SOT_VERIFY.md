# QA_V410_PR04_TESTIMONIALS_SOT_VERIFY

## A) Shortcode Defaults (SOT)
- **File:** `src/Admin/Frontend/Shortcodes/Testimonials.php`
- **Line Reference:** [L70-L85](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Frontend/Shortcodes/Testimonials.php#L70-L85)

### Full Defaults List:
```php
return array(
    'limit'         => apply_filters('mhm_rentiva/testimonials/limit', '5'),
    'rating'        => apply_filters('mhm_rentiva/testimonials/rating', ''),
    'vehicle_id'    => apply_filters('mhm_rentiva/testimonials/vehicle_id', ''),
    'show_rating'   => apply_filters('mhm_rentiva/testimonials/show_rating', '1'),
    'show_date'     => apply_filters('mhm_rentiva/testimonials/show_date', '1'),
    'show_vehicle'  => apply_filters('mhm_rentiva/testimonials/show_vehicle', '1'),
    'show_customer' => apply_filters('mhm_rentiva/testimonials/show_customer', '1'),
    'layout'        => apply_filters('mhm_rentiva/testimonials/layout', 'grid'),
    'columns'       => apply_filters('mhm_rentiva/testimonials/columns', '3'),
    'auto_rotate'   => apply_filters('mhm_rentiva/testimonials/auto_rotate', '0'),
    'class'         => apply_filters('mhm_rentiva/testimonials/class', ''),
);
```

## B) Accepted Attribute Surface
- **Mechanism:** `shortcode_atts()` is used in `render()` method ([L152](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Frontend/Shortcodes/Testimonials.php#L152)).
- **`author_name` supported?** **NO**.
- **Evidence:** `author_name` is missing from the defaults array above. `shortcode_atts` will strip it if passed. Furthermore, `get_testimonials()` ([L176-234](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Frontend/Shortcodes/Testimonials.php#L176-234)) does not implement any filtering logic for a customer/author name.

## C) Template Consumption
- **File:** `templates/shortcodes/testimonials.php`
- **Template uses `author_name`?** **NO**.
- **Evidence:** 
    - At [L91-L95](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/templates/shortcodes/testimonials.php#L91-L95), it uses `$testimonial['customer_name']` to display the name, but this value comes from the query result, not the shortcode attributes.
    - There is no reference to `$atts['author_name']` or any filtering variable for the name in the template.

## D) Block Current Attributes
- **File:** `assets/blocks/testimonials/block.json`
- **`authorName` attribute present?** **NO**.
- **Naming/Defaults:**
    - `limitItems`: `"6"` (Shortcode use `limit: "5"`)
    - `layout`: `"carousel"` (Shortcode use `layout: "grid"`)
    - `autoplay`: `true` (Shortcode use `auto_rotate: "0"`)
- **Inspector UI:** `index.js` contains no controls for Author/Customer name filtering.

## E) Decision
**Decision: `author_name` is NOT SUPPORTED.**

### PR-04 Action:
- **NOT SUPPORTED** -> Parity Matrix "Missing-in-Block: author_name" notu düzeltilecek (bu özellik shortcode tarafında da yok).
- Blok tarafına `authorName` eklenmeyecek.
- **Odak Noktası:** `limit`, `layout` ve `autoplay/auto_rotate` varsayılanlarının eşitlenmesi.
- **Risk:** Shortcode runtime davranışında değişiklik yapılmayacak (NO behavior change).
