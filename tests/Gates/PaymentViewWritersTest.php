<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use PHPUnit\Framework\TestCase;

/**
 * The payment-view writer inventory (bin/check-payment-view-writers.php,
 * spec PR 3a, plan r4 Task 6) matches its baseline, and its patterns find
 * what they must and nothing else.
 */
final class PaymentViewWritersTest extends TestCase
{
	private static string $plugin;

	public static function setUpBeforeClass(): void
	{
		self::$plugin = dirname(__DIR__, 2);
		require_once self::$plugin . '/bin/check-payment-view-writers.php';
	}

	public function test_the_tree_matches_the_baseline(): void
	{
		$now  = mhmrentiva_pvw_render(mhmrentiva_find_payment_view_writers(self::$plugin . '/src'));
		$want = str_replace("\r\n", "\n", (string) file_get_contents(self::$plugin . '/bin/payment-view-writers-baseline.txt'));
		$this->assertSame(
			$want,
			$now,
			'A payment-view writer appeared or went. A new one: put its entry point in a PaymentView scope, prove it in a PaymentHistory*ScopesTest, then add it to the baseline by name (php bin/check-payment-view-writers.php --write).'
		);
	}

	/**
	 * @return array{writer: list<string>, create: list<string>, generic: list<string>}
	 */
	private function scan(string $php): array
	{
		$dir = sys_get_temp_dir() . '/mhm-pvw-' . bin2hex(random_bytes(4));
		mkdir($dir);
		file_put_contents($dir . '/Fixture.php', $php);
		try {
			return mhmrentiva_find_payment_view_writers($dir);
		} finally {
			unlink($dir . '/Fixture.php');
			rmdir($dir);
		}
	}

	public function test_a_multi_line_call_is_a_writer(): void
	{
		$found = $this->scan("<?php\nfunction pay(\$id) {\n\tupdate_post_meta(\n\t\t\$id,\n\t\t'_mhmrentiva_remaining_amount',\n\t\t0\n\t);\n}\n");
		$this->assertSame(array( 'Fixture::pay' ), $found['writer']);
	}

	public function test_a_helper_call_and_a_constant_key_are_writers(): void
	{
		$found = $this->scan("<?php\nclass F {\n\tfunction a(\$id) { self::write_booking_meta_alone(\$id, '_mhmrentiva_payment_status', 'paid'); }\n\tfunction b(\$id) { update_post_meta(\$id, MetaKeys::BOOKING_PAYMENT_TYPE, 'full'); }\n}\n");
		$this->assertSame(array( 'Fixture::a', 'Fixture::b' ), $found['writer']);
	}

	public function test_a_closure_counts_to_the_function_that_defines_it(): void
	{
		$found = $this->scan("<?php\nfunction outer(\$id) {\n\t\$f = static function () use (\$id) { delete_post_meta(\$id, '_mhmrentiva_payment_status'); };\n}\n");
		$this->assertSame(array( 'Fixture::outer' ), $found['writer']);
	}

	public function test_a_comment_or_a_read_is_not_a_writer(): void
	{
		$found = $this->scan("<?php\nfunction r(\$id) {\n\t// update_post_meta(\$id, '_mhmrentiva_payment_status', 'paid');\n\t/* update_post_meta(\$id, '_mhmrentiva_payment_type', 'x'); */\n\treturn get_post_meta(\$id, '_mhmrentiva_payment_status', true);\n}\n");
		$this->assertSame(array(), $found['writer']);
	}

	public function test_meta_input_is_a_create_and_a_variable_key_is_generic(): void
	{
		$found = $this->scan("<?php\nfunction c() { wp_insert_post(array('meta_input' => array('_mhmrentiva_payment_status' => 'pending'))); }\nfunction g(\$id, \$key, \$v) { update_post_meta(\$id, \$key, \$v); }\nfunction q(\$booking_id, \$meta_key, \$meta_value) { update_post_meta(\$booking_id, \$meta_key, \$meta_value); }\n");
		$this->assertSame(array( 'Fixture::c' ), $found['create']);
		$this->assertSame(array( 'Fixture::g', 'Fixture::q' ), $found['generic']);
		$this->assertSame(array(), $found['writer']);
	}

	public function test_the_generic_writer_is_found_by_its_shape(): void
	{
		// WooCommerceBridge::write_booking_meta writes through `$key`; with its
		// two meta calls gone it must drop out of the generic list.
		$with    = $this->scan("<?php\nclass W {\n\tfunction write_booking_meta(\$booking_id, \$key, \$value) {\n\t\tadd_post_meta(\$booking_id, \$key, \$value, true);\n\t\tupdate_post_meta(\$booking_id, \$key, \$value);\n\t}\n}\n");
		$without = $this->scan("<?php\nclass W {\n\tfunction write_booking_meta(\$booking_id, \$key, \$value) {\n\t\treturn null;\n\t}\n}\n");
		$this->assertSame(array( 'Fixture::write_booking_meta' ), $with['generic']);
		$this->assertSame(array(), $without['generic']);
	}
}
