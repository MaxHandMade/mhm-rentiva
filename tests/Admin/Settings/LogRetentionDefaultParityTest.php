<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use WP_UnitTestCase;

final class LogRetentionDefaultParityTest extends WP_UnitTestCase
{
	/**
	 * @dataProvider runtimeFilesProvider
	 */
	public function test_runtime_paths_do_not_use_90_day_fallback(string $relativePath): void
	{
		$file = MHMRENTIVA_PLUGIN_PATH . $relativePath;
		$this->assertFileExists($file);

		$contents = (string) file_get_contents($file);
		$this->assertStringNotContainsString(
			"mhmrentiva_log_retention_days', 90",
			$contents,
			$relativePath . ' still uses 90-day fallback and conflicts with settings default.'
		);
	}

	public function test_logs_settings_default_is_30_days(): void
	{
		$file = MHMRENTIVA_PLUGIN_PATH . 'src/Admin/Settings/Groups/LogsSettings.php';
		$this->assertFileExists($file);
		$contents = (string) file_get_contents($file);

		$this->assertStringContainsString("'mhmrentiva_log_retention_days'  => 30", $contents);
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function runtimeFilesProvider(): array
	{
		return array(
			// admin-utilities-actions (src/Admin/Utilities/Actions/Actions.php)
			// was removed whole by Task 9 (slice 5, 2026-08-22): its only
			// registrations were a dead admin_post refund endpoint and the
			// admin_notices callback that reported its result, neither of
			// which ever touched log retention.
			'maintenance-log-retention'=> array('src/Admin/PostTypes/Maintenance/LogRetention.php'),
		);
	}
}
