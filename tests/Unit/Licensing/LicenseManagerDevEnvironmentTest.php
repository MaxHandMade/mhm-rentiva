<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

/**
 * v4.38.2 — isDevelopmentEnvironment() must recognise *.localhost domains
 * (Traefik reverse-proxy convention: rentiva.localhost, bozcon.localhost, …).
 *
 * Before this fix only .local / .test / .dev / .staging were handled; the
 * Traefik migration from localhost:port to *.localhost caused isActive() to
 * return false on all local Docker stacks.
 */
final class LicenseManagerDevEnvironmentTest extends WP_UnitTestCase
{
    private string $original_home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original_home = (string) get_option('home');
        update_option('mhm_rentiva_disable_dev_mode', false, false);
        delete_option(LicenseManager::OPTION);
    }

    protected function tearDown(): void
    {
        update_option('home', $this->original_home);
        delete_option('mhm_rentiva_disable_dev_mode');
        parent::tearDown();
    }

    private function setHome(string $url): void
    {
        update_option('home', $url);
    }

    private function isDev(): bool
    {
        return LicenseManager::instance()->isDevelopmentEnvironment();
    }

    // --- Traefik *.localhost (the fix) ---

    public function test_traefik_localhost_tld_is_development(): void
    {
        $this->setHome('http://rentiva.localhost');
        $this->assertTrue($this->isDev(), 'rentiva.localhost must be a dev environment');
    }

    public function test_traefik_localhost_tld_other_subdomain(): void
    {
        $this->setHome('http://bozcon.localhost');
        $this->assertTrue($this->isDev(), 'bozcon.localhost must be a dev environment');
    }

    public function test_traefik_localhost_tld_with_path(): void
    {
        $this->setHome('http://maxhandmade.localhost/wp');
        $this->assertTrue($this->isDev(), '*.localhost with sub-path must be a dev environment');
    }

    // --- Existing dev domains still work ---

    public function test_dot_local_tld_is_development(): void
    {
        $this->setHome('http://mysite.local');
        $this->assertTrue($this->isDev());
    }

    public function test_dot_test_tld_is_development(): void
    {
        $this->setHome('http://mysite.test');
        $this->assertTrue($this->isDev());
    }

    public function test_dot_dev_tld_is_development(): void
    {
        $this->setHome('http://mysite.dev');
        $this->assertTrue($this->isDev());
    }

    public function test_localhost_bare_is_development(): void
    {
        $this->setHome('http://localhost');
        $this->assertTrue($this->isDev());
    }

    // --- Production domains are NOT dev ---

    public function test_production_domain_is_not_development(): void
    {
        $this->setHome('https://mhmrentiva.com');
        $this->assertFalse($this->isDev(), 'mhmrentiva.com must not be a dev environment');
    }

    public function test_production_domain_not_localhost_suffix(): void
    {
        $this->setHome('https://notlocalhost.com');
        $this->assertFalse($this->isDev(), '.com domain that contains "localhost" in value must not match');
    }

    // --- isActive() auto-grants Pro on *.localhost without a license key ---

    public function test_is_active_auto_dev_on_localhost_tld(): void
    {
        $this->setHome('http://rentiva.localhost');
        delete_option(LicenseManager::OPTION);

        $this->assertTrue(
            LicenseManager::instance()->isActive(),
            'isActive() must return true for *.localhost when no license key is set'
        );
    }

    public function test_is_active_not_auto_dev_on_production(): void
    {
        $this->setHome('https://mhmrentiva.com');
        delete_option(LicenseManager::OPTION);

        $this->assertFalse(
            LicenseManager::instance()->isActive(),
            'isActive() must return false on production domain with no license'
        );
    }

    public function test_disable_dev_mode_option_prevents_auto_active(): void
    {
        $this->setHome('http://rentiva.localhost');
        update_option('mhm_rentiva_disable_dev_mode', true, false);
        delete_option(LicenseManager::OPTION);

        $this->assertFalse(
            LicenseManager::instance()->isActive(),
            'mhm_rentiva_disable_dev_mode=true must suppress auto-dev even on *.localhost'
        );
    }
}
