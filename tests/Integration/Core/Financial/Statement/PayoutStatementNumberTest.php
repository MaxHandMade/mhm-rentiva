<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementNumber;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementNumberTest extends WP_UnitTestCase {

	public function test_first_number_is_one(): void {
		delete_option('mhm_rentiva_statement_counter');
		$year = gmdate('Y');
		$this->assertSame("MKB-{$year}-0001", PayoutStatementNumber::next());
	}

	public function test_numbers_increment_without_duplicates(): void {
		// Two consecutive numbers must differ and increment by exactly 1. Asserted relatively
		// (not absolute 0001/0002) so a counter already advanced by the live statement-generation
		// hook in a full-suite run cannot make this brittle.
		$a = PayoutStatementNumber::next();
		$b = PayoutStatementNumber::next();
		$this->assertNotSame($a, $b);
		$this->assertSame(((int) substr($a, -4)) + 1, (int) substr($b, -4));
	}
}
