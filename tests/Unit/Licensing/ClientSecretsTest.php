<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\ClientSecrets;
use WP_UnitTestCase;

final class ClientSecretsTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=');
        putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=');
        putenv('MHM_RENTIVA_LICENSE_PING_SECRET=');
    }

    protected function tearDown(): void
    {
        putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=');
        putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=');
        putenv('MHM_RENTIVA_LICENSE_PING_SECRET=');
        parent::tearDown();
    }

    public function test_returns_empty_strings_when_no_constants_or_env_set(): void
    {
        if (defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            $this->markTestSkipped('Environment defines RESPONSE_HMAC_SECRET; empty path cannot be asserted.');
        }

        $this->assertSame('', ClientSecrets::getResponseHmacSecret());
        $this->assertSame('', ClientSecrets::getFeatureTokenKey());
        $this->assertSame('', ClientSecrets::getPingSecret());
    }

    public function test_reads_from_env_when_constants_not_defined(): void
    {
        if (defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            $this->markTestSkipped('Environment defines constants; env-only path cannot be asserted.');
        }

        putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=env-resp-secret');
        putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=env-token-key');
        putenv('MHM_RENTIVA_LICENSE_PING_SECRET=env-ping-secret');

        $this->assertSame('env-resp-secret', ClientSecrets::getResponseHmacSecret());
        $this->assertSame('env-token-key', ClientSecrets::getFeatureTokenKey());
        $this->assertSame('env-ping-secret', ClientSecrets::getPingSecret());
    }

    public function test_trims_whitespace_from_env_values(): void
    {
        if (defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            $this->markTestSkipped('Environment defines constants.');
        }

        putenv("MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=  spaced-secret  \n");
        $this->assertSame('spaced-secret', ClientSecrets::getResponseHmacSecret());
    }

    public function test_three_secrets_resolve_to_distinct_constants(): void
    {
        // Sanity: ensure the helper is reading three different sources, not a single shared one.
        if (defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            $this->markTestSkipped('Constants pre-defined.');
        }

        putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=A');
        putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=B');
        putenv('MHM_RENTIVA_LICENSE_PING_SECRET=C');

        $r = ClientSecrets::getResponseHmacSecret();
        $f = ClientSecrets::getFeatureTokenKey();
        $p = ClientSecrets::getPingSecret();

        $this->assertSame('A', $r);
        $this->assertSame('B', $f);
        $this->assertSame('C', $p);
    }
}
