<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Regression test for the dead `mhmrentiva_country_restriction_enabled` cleanup.
 *
 * The geo-blocking feature is gone from both editions (the free core's ip-api.com
 * country check was removed in Faz 2a; Pro's inherited `CountryRestriction` was
 * deleted in Faz 2b Task 9). A surviving `..._country_restriction_enabled = 1`
 * row therefore claims geo-restriction is ON while nothing enforces it — the same
 * false-security-promise bug as the "Brute Force Protection" toggle, which read ON
 * while nothing enforced it and took the whole Security tab down with it (see
 * DeadSecuritySettingKeysTest).
 *
 * These tests drive the REAL version-gated `run_migrations()` path rather than the
 * private cleanup method, because the version gate is precisely what has bitten
 * this project before.
 */
final class DeadCountryRestrictionOptionTest extends WP_UnitTestCase
{
    private const DEAD_OPTION = 'mhmrentiva_country_restriction_enabled';

    /** @var string|false */
    private $previous_version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous_version = get_option('mhmrentiva_db_version');
    }

    protected function tearDown(): void
    {
        if (false === $this->previous_version) {
            delete_option('mhmrentiva_db_version');
        } else {
            update_option('mhmrentiva_db_version', $this->previous_version);
        }
        delete_option(self::DEAD_OPTION);
        delete_option('mhmrentiva_allowed_countries');
        parent::tearDown();
    }

    /**
     * Force the version-gated migrator to actually run.
     */
    private function run_migrations_from_scratch(): void
    {
        update_option('mhmrentiva_db_version', '1.0.0');
        DatabaseMigrator::run_migrations();
    }

    /**
     * The core promise: an install carrying the stale ON flag gets it removed.
     */
    public function test_migration_deletes_the_dead_option(): void
    {
        update_option(self::DEAD_OPTION, '1');
        $this->assertSame('1', get_option(self::DEAD_OPTION), 'Premise: the pollution exists.');

        $this->run_migrations_from_scratch();

        $this->assertFalse(
            get_option(self::DEAD_OPTION),
            'The dead geo-restriction flag must not survive the migration.'
        );
    }

    /**
     * Guards the CURRENT_VERSION bump.
     *
     * A migration added WITHOUT bumping CURRENT_VERSION silently never runs on
     * existing installs — this project has shipped that bug. This test pins the
     * relationship: at a version below CURRENT_VERSION the cleanup fires, so the
     * constant carrying this cleanup must be ahead of what shipped before it.
     */
    public function test_cleanup_is_reached_only_because_the_version_gate_opens(): void
    {
        // At CURRENT_VERSION the gate is shut: run_migrations() is a no-op, so the
        // pollution survives. This is the failure mode of forgetting the bump.
        $current = DatabaseMigrator::get_migration_status()['target_version'];
        update_option('mhmrentiva_db_version', $current);
        update_option(self::DEAD_OPTION, '1');

        DatabaseMigrator::run_migrations();

        $this->assertSame(
            '1',
            get_option(self::DEAD_OPTION),
            'Premise: at CURRENT_VERSION the gate is shut and nothing runs.'
        );

        // Below CURRENT_VERSION the gate opens and the cleanup fires.
        $this->run_migrations_from_scratch();

        $this->assertFalse(
            get_option(self::DEAD_OPTION),
            'Below CURRENT_VERSION the gate must open and the cleanup must fire.'
        );
    }

    /**
     * Fresh installs have no such option. Deleting an absent option is a harmless
     * no-op, which is why this cleanup needs no run-once flag of its own — and a
     * flag would risk the v4.27.2 trap of being stamped before the pollution exists.
     */
    public function test_migration_is_harmless_when_the_option_is_absent(): void
    {
        delete_option(self::DEAD_OPTION);

        $this->run_migrations_from_scratch();

        $this->assertFalse(get_option(self::DEAD_OPTION));
    }

    /**
     * Scope guard: exactly one key dies.
     *
     * `mhmrentiva_allowed_countries` is a value list that claims nothing on its
     * own, and the `mhmrentiva_settings` array is user data read by
     * SettingsCore::get(). Removing either is a separate decision the owner has
     * not made. If a later edit widens the cleanup, this test fails.
     */
    public function test_migration_touches_nothing_but_the_one_dead_key(): void
    {
        update_option(self::DEAD_OPTION, '1');
        update_option('mhmrentiva_allowed_countries', 'TR,US,DE');
        update_option('mhmrentiva_settings', array(
            'mhmrentiva_country_restriction_enabled' => '0',
            'mhmrentiva_allowed_countries'           => '',
            'mhmrentiva_brand_name'                  => 'Otokira Rent a Car',
        ));

        $this->run_migrations_from_scratch();

        $this->assertFalse(get_option(self::DEAD_OPTION), 'The one dead key must go.');
        $this->assertSame(
            'TR,US,DE',
            get_option('mhmrentiva_allowed_countries'),
            'The standalone allowed-countries list is out of scope and must survive.'
        );

        $settings = get_option('mhmrentiva_settings');
        $this->assertIsArray($settings);
        $this->assertSame(
            '0',
            $settings['mhmrentiva_country_restriction_enabled'] ?? null,
            'The settings-array copy is user data and is out of scope.'
        );
        $this->assertSame(
            'Otokira Rent a Car',
            $settings['mhmrentiva_brand_name'] ?? null,
            'Unrelated settings must be untouched.'
        );
    }
}
