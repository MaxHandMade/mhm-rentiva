<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

final class ComparisonTableContractTest extends WP_UnitTestCase
{
	public function test_growth_differentiator_rows_exist_with_boolean_contract(): void
	{
		$rows = Mode::get_comparison_table_data();

		$this->assertRowContract($rows, 'advanced_reports');
		$this->assertRowContract($rows, 'messaging_system');
		$this->assertRowContract($rows, 'vendor_payout');
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function assertRowContract(array $rows, string $id): void
	{
		$row = $this->findRowById($rows, $id);

		$this->assertIsArray($row, sprintf('Comparison row [%s] must exist.', $id));
		$this->assertArrayHasKey('lite_available', $row, sprintf('Row [%s] must expose lite_available.', $id));
		$this->assertArrayHasKey('pro_available', $row, sprintf('Row [%s] must expose pro_available.', $id));
		$this->assertFalse((bool) $row['lite_available'], sprintf('Row [%s] lite_available must be false.', $id));
		$this->assertTrue((bool) $row['pro_available'], sprintf('Row [%s] pro_available must be true.', $id));

		$this->assertArrayHasKey('lite', $row);
		$this->assertArrayHasKey('pro', $row);
		$this->assertNotSame('', trim((string) $row['lite']));
		$this->assertNotSame('', trim((string) $row['pro']));
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,mixed>
	 */
	private function findRowById(array $rows, string $id): array
	{
		foreach ($rows as $row) {
			if (($row['id'] ?? '') === $id) {
				return $row;
			}
		}

		return array();
	}
}