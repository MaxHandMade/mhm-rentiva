<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\SecurityHelper;
use WP_UnitTestCase;

/**
 * SecurityHelper Test Suite
 *
 * Tests security validation, sanitization, and verification methods.
 */
class SecurityHelperTest extends WP_UnitTestCase
{
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing transients
        delete_transient('mhm_rate_limit_test_action_1');
        
        // Reset superglobals
        $_POST = [];
        $_GET = [];
        $_SERVER = [
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
        ];
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void
    {
        // Clean up transients
        delete_transient('mhm_rate_limit_test_action_1');
        
        // Reset superglobals
        $_POST = [];
        $_GET = [];
        $_SERVER = [
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
        ];
        
        parent::tearDown();
    }

    // ============================================
    // sanitize_text_field_safe() Tests
    // ============================================

    /** @test */
    public function it_sanitizes_null_values_to_empty_string()
    {
        $result = SecurityHelper::sanitize_text_field_safe(null);
        $this->assertEquals('', $result);
    }

    /** @test */
    public function it_sanitizes_empty_string_to_empty_string()
    {
        $result = SecurityHelper::sanitize_text_field_safe('');
        $this->assertEquals('', $result);
    }

    /** @test */
    public function it_sanitizes_normal_text_correctly()
    {
        $result = SecurityHelper::sanitize_text_field_safe('Hello World');
        $this->assertEquals('Hello World', $result);
    }

    /** @test */
    public function it_strips_html_tags_from_input()
    {
        $result = SecurityHelper::sanitize_text_field_safe('<script>alert("XSS")</script>Hello');
        $this->assertEquals('Hello', $result);
    }

    // ============================================
    // validate_vehicle_id() Tests
    // ============================================

    /** @test */
    public function it_validates_valid_vehicle_id()
    {
        $result = SecurityHelper::validate_vehicle_id(123);
        $this->assertEquals(123, $result);
    }

    /** @test */
    public function it_validates_string_numeric_vehicle_id()
    {
        $result = SecurityHelper::validate_vehicle_id('456');
        $this->assertEquals(456, $result);
    }

    /** @test */
    public function it_throws_exception_for_zero_vehicle_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_vehicle_id(0);
    }

    /** @test */
    public function it_throws_exception_for_negative_vehicle_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_vehicle_id(-1);
    }

    /** @test */
    public function it_throws_exception_for_invalid_string_vehicle_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_vehicle_id('invalid');
    }

    // ============================================
    // validate_date() Tests
    // ============================================

    /** @test */
    public function it_validates_valid_date_format()
    {
        $result = SecurityHelper::validate_date('2024-01-15');
        $this->assertEquals('2024-01-15', $result);
    }

    /** @test */
    public function it_validates_valid_datetime_format()
    {
        $result = SecurityHelper::validate_date('2024-01-15 14:30:00');
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function it_throws_exception_for_empty_date()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_date('');
    }

    /** @test */
    public function it_throws_exception_for_invalid_date_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_date('not-a-date');
    }

    /** @test */
    public function it_throws_exception_for_null_date()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_date(null);
    }

    // ============================================
    // validate_email() Tests
    // ============================================

    /** @test */
    public function it_validates_valid_email_address()
    {
        $result = SecurityHelper::validate_email('test@example.com');
        $this->assertEquals('test@example.com', $result);
    }

    /** @test */
    public function it_sanitizes_email_address()
    {
        // sanitize_email() removes whitespace but doesn't lowercase
        // WordPress's sanitize_email() preserves case
        $result = SecurityHelper::validate_email('  TEST@EXAMPLE.COM  ');
        $this->assertEquals('TEST@EXAMPLE.COM', $result);
    }

    /** @test */
    public function it_throws_exception_for_null_email()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_email(null);
    }

    /** @test */
    public function it_throws_exception_for_empty_email()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_email('');
    }

    /** @test */
    public function it_throws_exception_for_invalid_email_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_email('not-an-email');
    }

    /** @test */
    public function it_throws_exception_for_email_without_at_symbol()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_email('invalidemail.com');
    }

    // ============================================
    // validate_phone() Tests
    // ============================================

    /** @test */
    public function it_validates_valid_phone_number()
    {
        $result = SecurityHelper::validate_phone('+90 555 123 4567');
        $this->assertEquals('+90 555 123 4567', $result);
    }

    /** @test */
    public function it_validates_phone_with_dashes()
    {
        $result = SecurityHelper::validate_phone('555-123-4567');
        $this->assertEquals('555-123-4567', $result);
    }

    /** @test */
    public function it_validates_phone_with_parentheses()
    {
        $result = SecurityHelper::validate_phone('(555) 123-4567');
        $this->assertEquals('(555) 123-4567', $result);
    }

    /** @test */
    public function it_accepts_empty_phone_as_optional_field()
    {
        $result = SecurityHelper::validate_phone('');
        $this->assertEquals('', $result);
    }

    /** @test */
    public function it_accepts_null_phone_as_optional_field()
    {
        $result = SecurityHelper::validate_phone(null);
        $this->assertEquals('', $result);
    }

    /** @test */
    public function it_throws_exception_for_invalid_phone_with_letters()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_phone('555-ABC-1234');
    }

    /** @test */
    public function it_throws_exception_for_invalid_phone_with_special_chars()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_phone('555@123#4567');
    }

    // ============================================
    // validate_numeric_array() Tests
    // ============================================

    /** @test */
    public function it_validates_valid_numeric_array()
    {
        $result = SecurityHelper::validate_numeric_array([1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    /** @test */
    public function it_converts_string_to_array()
    {
        $result = SecurityHelper::validate_numeric_array('123');
        $this->assertEquals([123], $result);
    }

    /** @test */
    public function it_converts_numeric_to_array()
    {
        $result = SecurityHelper::validate_numeric_array(456);
        $this->assertEquals([456], $result);
    }

    /** @test */
    public function it_filters_out_zero_and_negative_values()
    {
        $result = SecurityHelper::validate_numeric_array([1, 0, -5, 2, 3]);
        $this->assertEquals([1, 2, 3], $result);
    }

    /** @test */
    public function it_converts_string_array_to_numeric()
    {
        $result = SecurityHelper::validate_numeric_array(['1', '2', '3']);
        $this->assertEquals([1, 2, 3], $result);
    }

    /** @test */
    public function it_throws_exception_for_non_array_non_string_input()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_numeric_array(new \stdClass());
    }

    /** @test */
    public function it_returns_empty_array_when_all_values_filtered()
    {
        $result = SecurityHelper::validate_numeric_array([0, -1, -5]);
        $this->assertEquals([], $result);
    }

    // ============================================
    // get_client_ip() Tests
    // ============================================

    /** @test */
    public function it_gets_ip_from_remote_addr()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $result = SecurityHelper::get_client_ip();
        $this->assertEquals('192.168.1.100', $result);
    }

    /** @test */
    public function it_prioritizes_http_client_ip_over_remote_addr()
    {
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $result = SecurityHelper::get_client_ip();
        $this->assertEquals('203.0.113.1', $result);
    }

    /** @test */
    public function it_handles_comma_separated_ip_list()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1';
        $result = SecurityHelper::get_client_ip();
        $this->assertEquals('203.0.113.1', $result);
    }

    /** @test */
    public function it_returns_default_when_no_ip_available()
    {
        unset($_SERVER['REMOTE_ADDR']);
        $result = SecurityHelper::get_client_ip();
        $this->assertEquals('0.0.0.0', $result);
    }

    /** @test */
    public function it_filters_private_ip_ranges()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $result = SecurityHelper::get_client_ip();
        // Should return REMOTE_ADDR as fallback even if private
        $this->assertEquals('10.0.0.1', $result);
    }

    // ============================================
    // verify_ajax_request() Tests
    // ============================================

    /** @test */
    public function it_verifies_valid_ajax_request_with_nonce()
    {
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_POST['nonce'] = $nonce;
        
        $result = SecurityHelper::verify_ajax_request($action, 'read');
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_ajax_request_with_invalid_nonce()
    {
        $_POST['nonce'] = 'invalid_nonce';
        
        $result = SecurityHelper::verify_ajax_request('test_action', 'read');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_ajax_request_without_nonce()
    {
        unset($_POST['nonce']);
        
        $result = SecurityHelper::verify_ajax_request('test_action', 'read');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_accepts_nonce_from_security_key()
    {
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_POST['security'] = $nonce;
        
        $result = SecurityHelper::verify_ajax_request($action, 'read');
        $this->assertTrue($result);
    }

    /** @test */
    public function it_accepts_nonce_from_get_request()
    {
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_GET['nonce'] = $nonce;
        
        $result = SecurityHelper::verify_ajax_request($action, 'read');
        $this->assertTrue($result);
    }

    /** @test */
    public function it_checks_user_capability_for_logged_in_users()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);
        
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_POST['nonce'] = $nonce;
        
        // Subscriber doesn't have 'manage_options' capability
        $result = SecurityHelper::verify_ajax_request($action, 'manage_options');
        $this->assertFalse($result);
    }

    /** @test */
    public function it_allows_ajax_request_for_users_with_correct_capability()
    {
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        $action = 'test_action';
        $nonce = wp_create_nonce($action);
        $_POST['nonce'] = $nonce;
        
        $result = SecurityHelper::verify_ajax_request($action, 'manage_options');
        $this->assertTrue($result);
    }

    // ============================================
    // check_rate_limit() Tests
    // ============================================

    /** @test */
    public function it_allows_request_within_rate_limit()
    {
        $result = SecurityHelper::check_rate_limit('test_action', 5, 300, 1);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_blocks_request_exceeding_rate_limit()
    {
        $action = 'test_action';
        $user_id = 1;
        
        // Exceed limit
        for ($i = 0; $i < 5; $i++) {
            SecurityHelper::check_rate_limit($action, 5, 300, $user_id);
        }
        
        // 6th request should be blocked
        $result = SecurityHelper::check_rate_limit($action, 5, 300, $user_id);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_uses_current_user_id_when_not_provided()
    {
        $user_id = $this->factory->user->create();
        wp_set_current_user($user_id);
        
        $result = SecurityHelper::check_rate_limit('test_action', 5, 300);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_uses_ip_for_anonymous_users()
    {
        wp_set_current_user(0);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        $result = SecurityHelper::check_rate_limit('test_action', 5, 300);
        $this->assertTrue($result);
    }

    // ============================================
    // get_safe_error_message() Tests
    // ============================================

    /** @test */
    public function it_returns_generic_message_in_production_mode()
    {
        $result = SecurityHelper::get_safe_error_message('Sensitive error details', false);
        $this->assertNotEquals('Sensitive error details', $result);
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function it_returns_detailed_message_in_debug_mode_for_admin()
    {
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        
        $result = SecurityHelper::get_safe_error_message('Sensitive error details', true);
        $this->assertEquals('Sensitive error details', $result);
    }

    /** @test */
    public function it_returns_generic_message_in_debug_mode_for_non_admin()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);
        
        $result = SecurityHelper::get_safe_error_message('Sensitive error details', true);
        $this->assertNotEquals('Sensitive error details', $result);
    }

    // ============================================
    // safe_meta_query() Tests
    // ============================================

    /** @test */
    public function it_creates_safe_meta_query_with_default_compare()
    {
        $result = SecurityHelper::safe_meta_query('test_key', 'test_value');
        
        $this->assertIsArray($result);
        $this->assertEquals('test_key', $result['key']);
        $this->assertEquals('test_value', $result['value']);
        $this->assertEquals('=', $result['compare']);
    }

    /** @test */
    public function it_sanitizes_meta_key()
    {
        $result = SecurityHelper::safe_meta_query('Test Key With Spaces!', 'value');
        
        $this->assertNotEquals('Test Key With Spaces!', $result['key']);
        $this->assertNotEmpty($result['key']);
    }

    /** @test */
    public function it_validates_compare_operator()
    {
        $result = SecurityHelper::safe_meta_query('key', 'value', 'LIKE');
        $this->assertEquals('LIKE', $result['compare']);
    }

    /** @test */
    public function it_defaults_to_equals_for_invalid_compare_operator()
    {
        $result = SecurityHelper::safe_meta_query('key', 'value', 'INVALID_OPERATOR');
        $this->assertEquals('=', $result['compare']);
    }

    // ============================================
    // safe_output() Tests
    // ============================================

    /** @test */
    public function it_escapes_html_output()
    {
        $result = SecurityHelper::safe_output('<script>alert("XSS")</script>', 'html');
        $this->assertStringNotContainsString('<script>', $result);
    }

    /** @test */
    public function it_escapes_attr_output()
    {
        $result = SecurityHelper::safe_output('test"value', 'attr');
        $this->assertStringNotContainsString('"', $result);
    }

    /** @test */
    public function it_escapes_url_output()
    {
        $result = SecurityHelper::safe_output('javascript:alert("XSS")', 'url');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    /** @test */
    public function it_escapes_js_output()
    {
        $result = SecurityHelper::safe_output("test'value", 'js');
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function it_converts_array_to_json()
    {
        $data = ['key' => 'value', 'number' => 123];
        $result = SecurityHelper::safe_output($data, 'html');
        $this->assertIsString($result);
    }

    /** @test */
    public function it_defaults_to_html_escaping()
    {
        $result = SecurityHelper::safe_output('<script>alert("XSS")</script>', 'unknown');
        $this->assertStringNotContainsString('<script>', $result);
    }
}
