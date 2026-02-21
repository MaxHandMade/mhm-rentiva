# Datepicker Parity Specification

Bu belge, Transfer Search bileşeninin Unified Search ile tarih seçici (datepicker) uyumluluğunu sağlamak için gereken teknik standartları tanımlar.

## Source of Truth (Referans)
*   **Bileşen:** `UnifiedSearch`
*   **JS Dosyası:** `assets/js/frontend/unified-search.js`
*   **CSS Dosyası:** `assets/css/frontend/datepicker-custom.css`

## Teknik Gereksinimler

### 1. HTML Yapısı (Selector Contract)
*   **Input Type:** `type="text"` (NOT `type="date"`, jQuery UI ile çakışmaları önlemek için).
*   **Sınıf (Class):** `.js-datepicker` zorunludur.
*   **Wrapper:** `.rv-input-wrapper` içinde olmalıdır.

### 2. JavaScript Başlatma (Initialization)
*   **Global Listener:** `$(document).on('focus', '.js-datepicker:not(.hasDatepicker)', ...)` yapısı kullanılmalıdır.
*   **Konum:** `assets/js/core/datepicker-init.js` (Yeni oluşturulacak).
*   **Ayarlar (Config):**
    ```javascript
    {
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        showButtonPanel: true,
        beforeShow: function (input, inst) {
            $('#ui-datepicker-div').addClass('rv-datepicker-skin');
        }
    }
    ```

### 3. Varlık Yönetimi (Asset Management)
*   **Helper:** `DatepickerAssets::enqueue()` metodu tüm render path'lerde çağrılmalıdır.
*   **Bağımlılıklar:**
    *   `jquery-ui-datepicker` (Script)
    *   `mhm-datepicker-init` (Yeni Script, Bağımlılıklar: `jquery`, `jquery-ui-datepicker`)
    *   `mhm-rentiva-datepicker-custom` (CSS)

### 4. Block SSR Parity
*   **Flag:** `self::$rendered_transfer_search` değişkeni blok render callback'i tarafından da `true` set edilmelidir. Aksi takdirde `wp_enqueue_style` / `wp_enqueue_script` çağrıları tetiklenmez.

### 4. İkon Entegrasyonu (Inline Icons)
*   **Markup:** İkon, input ile aynı wrapper (`.rv-input-wrapper`) içinde, input'tan önce gelmelidir.
*   **CSS:** İkon absolute olarak konumlandırılmalı, input'a `padding-left: 40px` verilmelidir.
