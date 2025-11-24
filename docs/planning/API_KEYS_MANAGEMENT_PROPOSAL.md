# API Key Yönetim Sistemi - Öneri

## 📊 Mevcut Durum

### ✅ Şu An Ne Var?

1. **REST API Endpoint'leri: MANUEL** (kod ile oluşturuluyor)
   - `src/Admin/REST/Availability.php`
   - `src/Admin/Messages/REST/Messages.php`
   - `src/Admin/Payment/REST/Payments.php`
   - vb.

2. **API Key Sistemi: Kısmi**
   - `AuthHelper::verifyApiKey()` fonksiyonu var
   - API key'ler `mhm_rentiva_api_keys` option'ında saklanıyor
   - **AMA:** UI'da oluşturma/yönetim yok

3. **Token Sistemi: Var**
   - `SecureToken` class'ı mevcut
   - `RESTSettings` içinde token ayarları var
   - Customer token sistemi çalışıyor

---

## 🎯 Önerilen Çözüm

### 1. REST API Endpoint'leri: **MANUEL KALACAK**
- ✅ Kod ile oluşturulmaya devam edecek
- ✅ Her yeni endpoint için dosya oluşturulacak
- ✅ Bu standart WordPress REST API yaklaşımı

### 2. Integration Settings'e Eklenecekler:

#### A. **API Keys Yönetimi** (YENİ)
```
Integration Settings > REST API Settings
└── API Keys Section
    ├── Create New API Key
    │   ├── Name/Description
    │   ├── Permissions (read, write, admin)
    │   ├── Expiry Date (optional)
    │   └── Generate Key Button
    │
    ├── Active API Keys List
    │   ├── Key Name
    │   ├── Key Preview (first 8 chars...last 4 chars)
    │   ├── Created Date
    │   ├── Last Used
    │   ├── Permissions
    │   ├── Status (Active/Expired/Revoked)
    │   ├── Copy Key Button
    │   └── Revoke/Delete Button
    │
    └── Usage Statistics
        └── Total requests per key
```

#### B. **Endpoint Listesi** (Bilgilendirme)
```
Integration Settings > REST API Settings
└── Available Endpoints
    ├── GET /wp-json/mhm-rentiva/v1/availability
    ├── GET /wp-json/mhm-rentiva/v1/messages
    ├── POST /wp-json/mhm-rentiva/v1/payments/create-intent
    └── ... (tüm endpoint'ler listelenir)
```

---

## 💡 Implementasyon Planı

### Adım 1: API Key Manager Class
```php
src/Admin/REST/APIKeyManager.php
- create_api_key()
- revoke_api_key()
- list_api_keys()
- verify_api_key()
- get_api_key_stats()
```

### Adım 2: UI Ekleme
- `Settings.php::render_rest_settings()` içine API Keys bölümü
- JavaScript ile AJAX işlemleri
- Copy-to-clipboard özelliği

### Adım 3: Veritabanı Yapısı
- `mhm_rentiva_api_keys` option'ı güncellenecek
- Her key için:
  - `id` (unique)
  - `name` (description)
  - `key_hash` (hashed key)
  - `permissions` (array)
  - `created_at`
  - `expires_at` (optional)
  - `last_used_at`
  - `status` (active/revoked)

---

## 🔐 Güvenlik

1. **Key Storage:**
   - Key'ler hash'lenerek saklanacak
   - Sadece oluşturulduğu anda gösterilecek
   - Daha sonra sadece preview (ilk/son karakterler)

2. **Key Format:**
   ```
   mhm_rentiva_live_xxxxxxxxxxxx...xxxxxxxx
   mhm_rentiva_test_xxxxxxxxxxxx...xxxxxxxx
   ```

3. **Permissions:**
   - `read` - Sadece GET istekleri
   - `write` - POST, PUT, PATCH istekleri
   - `admin` - Tüm istekler + admin endpoint'leri

---

## 📝 Örnek Kullanım

### Frontend'den API Key ile İstek:
```javascript
fetch('/wp-json/mhm-rentiva/v1/vehicles', {
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY_HERE',
        'Content-Type': 'application/json'
    }
})
```

### Backend'de Kontrol:
```php
$api_key = $request->get_header('Authorization');
if (strpos($api_key, 'Bearer ') === 0) {
    $key = substr($api_key, 7);
    if (APIKeyManager::verify_api_key($key)) {
        // Allow request
    }
}
```

