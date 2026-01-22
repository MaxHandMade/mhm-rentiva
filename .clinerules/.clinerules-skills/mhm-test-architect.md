---
name: mhm-test-architect
description: MHM Rentiva için PHPUnit testleri oluşturma rehberi.
---

# MHM Test Architect Skill

Bu skill, eklentinin kritik bileşenleri için güvenilir ve sürdürülebilir PHPUnit testleri oluşturmayı sağlar.

## Test Ortamı ve Yapısı
Eklenti, WordPress test kütüphanesini kullanır. Testler `tests/` dizini altında bulunmalıdır ve `MHMRentiva` namespace yapısına uygun olmalıdır.

## Test Yazma Kuralları

### 1. Sınıf İsimlendirme
Test sınıfları, test edilen sınıfın adıyla başlamalı ve `Test` son ekiyle bitmelidir.
- Sınıf: `SecurityHelper` -> Test: `SecurityHelperTest`
- Konum: `tests/Admin/Core/SecurityHelperTest.php`

### 2. Namespace Kullanımı
Test dosyaları da eklentinin namespace yapısını takip etmelidir, ancak `Tests` alt namespace'i eklenebilir.

```php
namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\SecurityHelper;
use WP_UnitTestCase;
```

### 3. Örnek Test Senaryosu: SecurityHelper

`SecurityHelper` gibi statik yardımcı sınıfların testi için örnek yapı:

```php
class SecurityHelperTest extends WP_UnitTestCase {
    
    // Her testten önce çalışır
    public function setUp(): void {
        parent::setUp();
        // Gerekli mock user veya data oluşturma
    }

    /** @test */ // Annotation kullanımı
    public function it_validates_vehicle_id_correctly() {
        // Doğru ID
        $valid_id = SecurityHelper::validate_vehicle_id(123);
        $this->assertEquals(123, $valid_id);

        // Hatalı ID (Exception Bekleme)
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_vehicle_id(0);
    }

    /** @test */
    public function it_verifies_nonce_correctly() {
        // Nonce oluştur
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_POST['nonce'] = $nonce;

        // Validasyon
        $result = SecurityHelper::verify_ajax_request($action, 'read');
        $this->assertTrue($result);
    }
}
```

### 4. Dikkat Edilecekler
- **Mocking:** Veritabanı veya dış servis bağımlılığı olan sınıflar için `Mockery` veya PHPUnit mock objeleri kullanın.
- **Database Temizliği:** `WP_UnitTestCase`, her testten sonra veritabanını işlem öncesi duruma (rollback) getirir. Testlerinizde oluşturduğunuz verileri manuel silmenize gerek yoktur.
- **Kapsam:** Özellikle `SecurityHelper`, `BookingForm` (kısmi), ve `VehicleRepository` gibi veri işleyen sınıflar önceliklidir.

## 5. Entegrasyon Testleri (Integration)
Eklentinin WooCommerce ile konuştuğu noktaları test etmek için.

```php
/** @test */
public function it_creates_order_when_booking_confirmed() {
    // 1. Ürün ve Araç oluştur (Factory ile)
    $vehicle_id = $this->factory->post->create(['post_type' => 'mhm_vehicle']);
    
    // 2. Sipariş simülasyonu
    $order = wc_create_order();
    $order->add_product(wc_get_product($vehicle_id), 1);
    $order->calculate_totals();
    
    // 3. Assertion
    $this->assertEquals('pending', $order->get_status());
}
```

## 6. Test Verisi Üretimi (Factories)
WordPress test kütüphanesinin sunduğu factory metodlarını kullanın:
- `$this->factory->post->create(...)`
- `$this->factory->user->create(...)`
- `$this->factory->term->create(...)`

Bu metodlar, test sonunda otomatik temizlenen (cleanup) veriler üretir.

## Hedef
Yazılan her yeni özellik veya yapılan her kritik refactoring sonrası ilgili test dosyası oluşturulmalı veya güncellenmelidir.
