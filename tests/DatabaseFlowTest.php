<?php

/**
 * Veritabanı ve WordPress Fonksiyon Testi
 */
class DatabaseFlowTest extends WP_UnitTestCase
{

    /**
     * Test 1: WordPress ve Eklenti Ortamı Ayakta mı?
     */
    public function test_wordpress_is_alive()
    {
        // WordPress fonksiyonu çalışıyor mu?
        $this->assertTrue(function_exists('do_action'));
    }

    /**
     * Test 2: Veritabanına Yazma ve Okuma (CRUD)
     */
    public function test_can_save_and_retrieve_option()
    {
        $test_key   = 'mhm_rentiva_test_data';
        $test_value = 'Test Başarılı - ' . time();

        // Veriyi Kaydet
        update_option($test_key, $test_value);

        // Veriyi Geri Oku
        $retrieved_value = get_option($test_key);

        // Karşılaştır: Gönderdiğim ile Gelen aynı mı?
        $this->assertEquals($test_value, $retrieved_value, 'Veritabanına yazılan veri okunamadı!');

        // Temizlik (Teardown)
        delete_option($test_key);
    }
}
