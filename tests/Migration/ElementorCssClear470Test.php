<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\Core\Utilities\ElementorStyleIdMigration;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 2: upgrading to 4.7.0 queues the Elementor page CSS rebuild, so
 * CSS generated for the old card selectors does not outlive the card.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator
 */
final class ElementorCssClear470Test extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option(ElementorStyleIdMigration::PENDING_OPTION);
		delete_option(ElementorStyleIdMigration::ATTEMPTS_OPTION);
		parent::tearDown();
	}

	public function test_upgrade_from_460_queues_the_css_rebuild(): void
	{
		delete_option(ElementorStyleIdMigration::PENDING_OPTION);
		update_option(ElementorStyleIdMigration::ATTEMPTS_OPTION, '3', false);
		update_option('mhmrentiva_db_version', '4.6.0');

		DatabaseMigrator::run_migrations();

		$this->assertSame('4.9.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::PENDING_OPTION));
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION), 'A fresh queue resets the attempt counter.');
	}
}
