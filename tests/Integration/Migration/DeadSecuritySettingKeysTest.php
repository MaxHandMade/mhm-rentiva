<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Regression test for the removed Settings -> Security tab.
 *
 * That tab rendered seventeen controls under headings such as "Brute Force
 * Protection", "SQL Injection Protection" and "Enable Rate Limiting". Not one
 * of them was wired to anything: the three classes that would have enforced
 * them (LockoutManager, WafManager, SecurityManager) autoloaded and registered
 * zero hooks, so every toggle wrote a row and nothing ever read it. The tab and
 * those classes are gone.
 *
 * Removing the UI is not the whole fix. On any site where an administrator had
 * saved that screen, `mhm_rentiva_settings` still carries
 * `mhm_rentiva_brute_force_protection = '1'` and its twelve siblings -- rows
 * stating that protections are ON while nothing enforces them. That is the same
 * false-security-promise bug as the dead `..._country_restriction_enabled` row
 * that [[DeadCountryRestrictionOptionTest]] covers, and it is settled here the
 * same way: the version-gated migration deletes them.
 *
 * Like that test, this drives the real `run_migrations()` path rather than the
 * private cleanup, because the version gate is precisely what has bitten this
 * project before -- a migration added without bumping CURRENT_VERSION never
 * runs on an existing install.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations
 */
final class DeadSecuritySettingKeysTest extends WP_UnitTestCase
{
	private const SETTINGS_OPTION = 'mhm_rentiva_settings';

	/**
	 * Every key the removed tab owned.
	 */
	private const DEAD_KEYS = array(
		'mhm_rentiva_ip_whitelist_enabled',
		'mhm_rentiva_ip_whitelist',
		'mhm_rentiva_ip_blacklist_enabled',
		'mhm_rentiva_ip_blacklist',
		'mhm_rentiva_brute_force_protection',
		'mhm_rentiva_max_login_attempts',
		'mhm_rentiva_login_lockout_duration',
		'mhm_rentiva_sql_injection_protection',
		'mhm_rentiva_xss_protection',
		'mhm_rentiva_csrf_protection',
		'mhm_rentiva_rate_limit_enabled',
		'mhm_rentiva_rate_limit_block_duration',
		'mhm_rentiva_rate_limit_requests_per_minute',
		'mhm_rentiva_rate_limit_booking_per_minute',
		'mhm_rentiva_rate_limit_payment_per_minute',
	);

	public function tearDown(): void
	{
		delete_option( self::SETTINGS_OPTION );
		delete_option( 'mhm_rentiva_db_version' );
		delete_option( 'mhm_rentiva_api_keys' );
		parent::tearDown();
	}

	/**
	 * The same cleanup, for the credentials the removed API-key manager stored.
	 *
	 * The Integration tab issued keys with READ / WRITE / ADMIN ("Full system
	 * control") levels while `APIKeyManager::verify_api_key()` had no caller,
	 * so no endpoint ever honoured one. The surface is gone; the rows it wrote
	 * describe grants with nothing behind them and go with it.
	 */
	public function test_the_migration_removes_the_dead_api_key_option(): void
	{
		update_option( 'mhm_rentiva_db_version', '1.0.0' );
		update_option(
			'mhm_rentiva_api_keys',
			array(
				'key_abc' => array(
					'name'        => 'Android App',
					'key_hash'    => 'deadbeef',
					'permissions' => array( 'read', 'write', 'admin' ),
					'status'      => 'active',
				),
			)
		);

		DatabaseMigrator::run_migrations();

		$this->assertFalse(
			get_option( 'mhm_rentiva_api_keys', false ),
			'The stored API keys survived; they describe ADMIN grants nothing implements.'
		);
	}

	/**
	 * Puts an install into the state a real upgrade starts from: an old db
	 * version, and a settings array in which the security screen was saved.
	 */
	private function seed_polluted_install(): void
	{
		update_option( 'mhm_rentiva_db_version', '1.0.0' );

		$settings = array(
			// A live setting, to prove the cleanup is surgical.
			'mhm_rentiva_cache_enabled' => '1',
		);
		foreach ( self::DEAD_KEYS as $key ) {
			$settings[ $key ] = '1';
		}

		update_option( self::SETTINGS_OPTION, $settings );
	}

	public function test_the_migration_removes_every_dead_security_key(): void
	{
		$this->seed_polluted_install();

		DatabaseMigrator::run_migrations();

		$settings = (array) get_option( self::SETTINGS_OPTION, array() );

		foreach ( self::DEAD_KEYS as $key ) {
			$this->assertArrayNotHasKey(
				$key,
				$settings,
				sprintf(
					'%s survived the migration. It claims a protection is ON while nothing enforces it.',
					$key
				)
			);
		}
	}

	public function test_the_migration_leaves_live_settings_alone(): void
	{
		$this->seed_polluted_install();

		DatabaseMigrator::run_migrations();

		$settings = (array) get_option( self::SETTINGS_OPTION, array() );

		$this->assertSame(
			'1',
			$settings['mhm_rentiva_cache_enabled'] ?? null,
			'The cleanup removed a setting that is still read.'
		);
	}

	/**
	 * The gate is the whole point: a cleanup that only runs on a fresh install
	 * fixes nobody, since a fresh install never had the rows.
	 */
	public function test_the_cleanup_runs_from_the_version_gate_not_a_fresh_install(): void
	{
		$this->seed_polluted_install();
		update_option( 'mhm_rentiva_db_version', '3.10.0' );

		DatabaseMigrator::run_migrations();

		$settings = (array) get_option( self::SETTINGS_OPTION, array() );

		$this->assertArrayNotHasKey(
			'mhm_rentiva_brute_force_protection',
			$settings,
			'An install one version behind was not migrated; the version constant was not bumped.'
		);
	}

	/**
	 * On an install that never saved the screen there is nothing to clean, and
	 * the cleanup must not invent the keys it is meant to delete. (An exact
	 * array comparison would be the wrong assertion here: `run_migrations()`
	 * legitimately seeds other defaults into the same option.)
	 */
	public function test_a_clean_install_gains_no_dead_keys(): void
	{
		update_option( 'mhm_rentiva_db_version', '1.0.0' );
		update_option( self::SETTINGS_OPTION, array( 'mhm_rentiva_cache_enabled' => '0' ) );

		DatabaseMigrator::run_migrations();

		$settings = (array) get_option( self::SETTINGS_OPTION, array() );

		foreach ( self::DEAD_KEYS as $key ) {
			$this->assertArrayNotHasKey( $key, $settings, $key . ' was created by the migration.' );
		}

		$this->assertSame(
			'0',
			$settings['mhm_rentiva_cache_enabled'] ?? null,
			'A live setting was altered on an install with nothing to clean.'
		);
	}
}
