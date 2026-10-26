<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\Core\Utilities\ElementorStyleIdMigration;
use WP_UnitTestCase;

/**
 * Aşama 2 PR C: upgrading to 4.9.0 queues the Elementor page CSS rebuild, so
 * CSS generated for the old booking-form selectors does not outlive the form.
 * Only queues: the run (and Elementor's files_manager->clear_cache()) happens
 * on a later admin_init, files regenerate on the next visit.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator
 */
final class ElementorCssClear490Test extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option(ElementorStyleIdMigration::PENDING_OPTION);
		delete_option(ElementorStyleIdMigration::ATTEMPTS_OPTION);
		parent::tearDown();
	}

	public function test_490_marks_elementor_css_for_rebuild(): void
	{
		delete_option(ElementorStyleIdMigration::PENDING_OPTION);
		update_option(ElementorStyleIdMigration::ATTEMPTS_OPTION, '3', false);
		update_option('mhmrentiva_db_version', '4.8.0');

		DatabaseMigrator::run_migrations();

		$this->assertSame('4.9.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::PENDING_OPTION));
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION), 'A fresh queue resets the attempt counter.');
	}
}
