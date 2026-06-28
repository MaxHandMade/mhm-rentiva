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
		delete_option('mhm_rentiva_statement_counter');
		$a = PayoutStatementNumber::next();
		$b = PayoutStatementNumber::next();
		$this->assertNotSame($a, $b);
		$year = gmdate('Y');
		$this->assertSame("MKB-{$year}-0002", $b);
	}
}
