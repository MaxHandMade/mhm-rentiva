<?php

/**
 * Tests for MessagesSettings sanitization logic.
 *
 * @package MHMRentiva\Tests\Admin\Messages\Settings
 */

namespace MHMRentiva\Tests\Admin\Messages\Settings;

use MHMRentiva\Admin\Messages\Settings\MessagesSettings;
use WP_UnitTestCase;

/**
 * MessagesSettingsTest Class
 *
 * Tests category and status sanitization, including empty and populated save scenarios.
 */
class MessagesSettingsTest extends WP_UnitTestCase
{

    /**
     * Store original POST data for cleanup.
     *
     * @var array
     */
    private $original_post = [];

    /**
     * Set up for each test.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Store original POST data if available
        $this->original_post = isset($_POST) ? $_POST : [];
        // Clear POST for clean tests
        $_POST = [];
    }

    /**
     * Clean up after each test.
     */
    public function tearDown(): void
    {
        // Restore original POST data
        $_POST = $this->original_post;
        parent::tearDown();
    }

    // =========================================================================
    // EMPTY SAVE SCENARIOS (Boş Kaydetme Senaryoları)
    // =========================================================================

    /**
     * @test
     * Scenario: New category field is empty, no validation error should occur.
     */
    public function it_handles_empty_new_category_field_gracefully()
    {
        // Arrange: Simulate empty new category input
        $_POST['mhm_new_category_entry'] = '';

        $input = [
            'categories' => [
                'general' => 'General',
                'support' => 'Support'
            ],
            'statuses' => [
                'new'    => 'New',
                'closed' => 'Closed'
            ]
        ];

        // Act: Run sanitization
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: Existing categories preserved, no empty category added
        $this->assertCount(2, $result['categories'], 'Should have exactly 2 existing categories');
        $this->assertArrayHasKey('general', $result['categories']);
        $this->assertArrayHasKey('support', $result['categories']);
        $this->assertArrayNotHasKey('', $result['categories'], 'Empty key should not exist');
    }

    /**
     * @test
     * Scenario: New status field is empty, no validation error should occur.
     */
    public function it_handles_empty_new_status_field_gracefully()
    {
        // Arrange: Simulate empty new status input
        $_POST['mhm_new_status_entry'] = '';

        $input = [
            'categories' => ['general' => 'General'],
            'statuses' => [
                'new'    => 'New',
                'closed' => 'Closed'
            ]
        ];

        // Act: Run sanitization
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: Existing statuses preserved, no empty status added
        $this->assertCount(2, $result['statuses'], 'Should have exactly 2 existing statuses');
        $this->assertArrayHasKey('new', $result['statuses']);
        $this->assertArrayHasKey('closed', $result['statuses']);
        $this->assertArrayNotHasKey('', $result['statuses'], 'Empty key should not exist');
    }

    /**
     * @test
     * Scenario: Whitespace-only new category should be treated as empty.
     */
    public function it_ignores_whitespace_only_new_category()
    {
        // Arrange: Whitespace-only input
        $_POST['mhm_new_category_entry'] = '   ';

        $input = [
            'categories' => ['general' => 'General'],
            'statuses' => []
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: Only original category exists
        $this->assertCount(1, $result['categories']);
        $this->assertEquals('General', $result['categories']['general']);
    }

    /**
     * @test
     * Scenario: No POST variable for new category at all.
     */
    public function it_works_when_new_category_post_variable_not_set()
    {
        // Arrange: No POST variable (simulating page load without form submission)
        // $_POST['mhm_new_category_entry'] is NOT set

        $input = [
            'categories' => ['support' => 'Support'],
            'statuses' => ['open' => 'Open']
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert
        $this->assertCount(1, $result['categories']);
        $this->assertArrayHasKey('support', $result['categories']);
    }

    // =========================================================================
    // POPULATED SAVE SCENARIOS (Dolu Kaydetme Senaryoları)
    // =========================================================================

    /**
     * @test
     * Scenario: New category is provided and should be added.
     */
    public function it_adds_new_category_when_provided()
    {
        // Arrange: Valid new category
        $_POST['mhm_new_category_entry'] = 'Billing';

        $input = [
            'categories' => ['general' => 'General'],
            'statuses' => []
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: New category added
        $this->assertCount(2, $result['categories']);
        $this->assertArrayHasKey('general', $result['categories']);
        $this->assertArrayHasKey('billing', $result['categories']);
        $this->assertEquals('Billing', $result['categories']['billing']);
    }

    /**
     * @test
     * Scenario: New status is provided and should be added.
     */
    public function it_adds_new_status_when_provided()
    {
        // Arrange: Valid new status
        $_POST['mhm_new_status_entry'] = 'In Progress';

        $input = [
            'categories' => [],
            'statuses' => ['new' => 'New', 'closed' => 'Closed']
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: New status added
        $this->assertCount(3, $result['statuses']);
        $this->assertArrayHasKey('inprogress', $result['statuses']);
        $this->assertEquals('In Progress', $result['statuses']['inprogress']);
    }

    /**
     * @test
     * Scenario: New category with special characters should be sanitized.
     */
    public function it_sanitizes_new_category_with_special_characters()
    {
        // Arrange: Category with special chars
        $_POST['mhm_new_category_entry'] = 'Müşteri Destek!';

        $input = [
            'categories' => [],
            'statuses' => []
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: Key is sanitized, value is preserved after sanitize_text_field
        $this->assertCount(1, $result['categories']);
        // sanitize_key removes non-alphanumeric except dashes
        $this->assertNotEmpty($result['categories']);
    }

    /**
     * @test
     * Scenario: Duplicate category should not be added.
     */
    public function it_prevents_duplicate_category_addition()
    {
        // Arrange: Try to add existing category
        $_POST['mhm_new_category_entry'] = 'General';

        $input = [
            'categories' => ['general' => 'General'],
            'statuses' => []
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: Still only 1 category
        $this->assertCount(1, $result['categories']);
        $this->assertEquals('General', $result['categories']['general']);
    }

    /**
     * @test
     * Scenario: Both new category and new status provided simultaneously.
     */
    public function it_handles_both_new_category_and_status_together()
    {
        // Arrange
        $_POST['mhm_new_category_entry'] = 'Sales';
        $_POST['mhm_new_status_entry'] = 'Pending';

        $input = [
            'categories' => ['general' => 'General'],
            'statuses' => ['new' => 'New']
        ];

        // Act
        $result = MessagesSettings::sanitize_settings($input);

        // Assert: Both added
        $this->assertCount(2, $result['categories']);
        $this->assertCount(2, $result['statuses']);
        $this->assertArrayHasKey('sales', $result['categories']);
        $this->assertArrayHasKey('pending', $result['statuses']);
    }

    // =========================================================================
    // EXISTING CATEGORIES SANITIZATION (Mevcut Kategorilerin Sanitize Edilmesi)
    // =========================================================================

    /**
     * @test
     * Scenario: Existing categories in key => value format are preserved.
     */
    public function it_preserves_existing_categories_in_key_value_format()
    {
        $input = [
            'categories' => [
                'general' => 'General Inquiry',
                'sales'   => 'Sales Question',
                'support' => 'Technical Support'
            ],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertCount(3, $result['categories']);
        $this->assertEquals('General Inquiry', $result['categories']['general']);
        $this->assertEquals('Sales Question', $result['categories']['sales']);
        $this->assertEquals('Technical Support', $result['categories']['support']);
    }

    /**
     * @test
     * Scenario: Empty category names should be filtered out.
     */
    public function it_filters_out_empty_category_names()
    {
        $input = [
            'categories' => [
                'general' => 'General',
                'empty1'  => '',
                'empty2'  => '   ',
                'support' => 'Support'
            ],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertCount(2, $result['categories']);
        $this->assertArrayHasKey('general', $result['categories']);
        $this->assertArrayHasKey('support', $result['categories']);
        $this->assertArrayNotHasKey('empty1', $result['categories']);
        $this->assertArrayNotHasKey('empty2', $result['categories']);
    }

    /**
     * @test
     * Scenario: Categories in nested array format are handled.
     */
    public function it_handles_nested_array_format_categories()
    {
        $input = [
            'categories' => [
                0 => ['name' => 'FAQ'],
                1 => ['name' => 'Complaint']
            ],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertCount(2, $result['categories']);
        $this->assertArrayHasKey('faq', $result['categories']);
        $this->assertArrayHasKey('complaint', $result['categories']);
        $this->assertEquals('FAQ', $result['categories']['faq']);
        $this->assertEquals('Complaint', $result['categories']['complaint']);
    }

    // =========================================================================
    // BOOLEAN FIELDS SANITIZATION
    // =========================================================================

    /**
     * @test
     * Scenario: Boolean fields are correctly cast.
     */
    public function it_sanitizes_boolean_fields_correctly()
    {
        $input = [
            'email_admin_notifications' => '1',
            'email_customer_notifications' => 0,
            'auto_reply_enabled' => true,
            'categories' => [],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertTrue($result['email_admin_notifications']);
        $this->assertFalse($result['email_customer_notifications']);
        $this->assertTrue($result['auto_reply_enabled']);
        // Unset fields should default to false
        $this->assertFalse($result['dashboard_widget_enabled']);
    }

    // =========================================================================
    // NUMERIC FIELDS SANITIZATION
    // =========================================================================

    /**
     * @test
     * Scenario: Numeric fields use defaults when not provided.
     */
    public function it_uses_defaults_for_missing_numeric_fields()
    {
        $input = [
            'categories' => [],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertEquals(10, $result['lite_messages_per_month']);
        $this->assertEquals(3, $result['lite_messages_per_day']);
        $this->assertEquals(5, $result['dashboard_widget_max_messages']);
    }

    /**
     * @test
     * Scenario: Numeric fields are sanitized with absint.
     */
    public function it_sanitizes_numeric_fields_with_absint()
    {
        $input = [
            'lite_messages_per_month' => -5,
            'lite_messages_per_day' => '10abc',
            'dashboard_widget_max_messages' => 15.7,
            'categories' => [],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertEquals(5, $result['lite_messages_per_month']); // absint(-5) = 5
        $this->assertEquals(10, $result['lite_messages_per_day']); // absint('10abc') = 10
        $this->assertEquals(15, $result['dashboard_widget_max_messages']); // absint(15.7) = 15
    }

    // =========================================================================
    // STRING FIELDS SANITIZATION
    // =========================================================================

    /**
     * @test
     * Scenario: Email fields are properly sanitized.
     */
    public function it_sanitizes_email_fields()
    {
        $input = [
            'admin_email' => 'admin@example.com',
            'from_name' => '<script>alert("xss")</script>Company Name',
            'from_email' => 'sender@example.com',
            'categories' => [],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertEquals('admin@example.com', $result['admin_email']);
        $this->assertStringNotContainsString('<script>', $result['from_name']);
        $this->assertEquals('sender@example.com', $result['from_email']);
    }

    /**
     * @test
     * Scenario: Invalid email addresses are cleared.
     */
    public function it_clears_invalid_email_addresses()
    {
        $input = [
            'admin_email' => 'not-an-email',
            'from_email' => 'also-invalid',
            'categories' => [],
            'statuses' => []
        ];

        $result = MessagesSettings::sanitize_settings($input);

        $this->assertEmpty($result['admin_email']);
        $this->assertEmpty($result['from_email']);
    }
}
