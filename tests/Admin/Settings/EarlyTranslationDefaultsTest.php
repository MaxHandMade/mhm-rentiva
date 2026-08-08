<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings;
use WP_UnitTestCase;

/**
 * WordPress 6.7+ emits
 *
 *     Function _load_textdomain_just_in_time was called incorrectly.
 *     Translation loading for the mhm-rentiva domain was triggered too early.
 *
 * whenever this plugin materialises a translated string before `init`. The
 * shipped 6.0.1 build did exactly that on the real upgrade path, verified by
 * backtrace on the release stack:
 *
 *   mhm-rentiva.php:288 (plugins_loaded, Lane A)
 *     -> DatabaseMigrator::run_migrations():417  "Database migration completed"
 *     -> AdvancedLogger::info() -> log() -> should_skip_log():463
 *     -> SettingsCore::get('mhmrentiva_log_level')
 *     -> SettingsCore::get_defaults()            merges every group's defaults
 *     -> EmailSettings::get_default_settings():51 __('%s - Powered by ...')
 *
 * The cause is that reading ONE plain setting built the WHOLE defaults map,
 * and that map materialised ~54 translated email strings nobody had asked
 * for. This test measures the cause rather than the symptom.
 *
 * WHY THE `gettext` COUNT IS THE LOAD-BEARING ASSERTION (and the
 * `doing_it_wrong_run` spy is not, on its own): `_doing_it_wrong()` is only
 * reached from `_load_textdomain_just_in_time()`, which returns early unless
 * a .mo/.l10n.php actually exists for the CURRENT locale
 * (wp-includes/l10n.php:1437-1442). The PHPUnit environment runs in en_US and
 * this plugin ships tr_TR only, so that guard short-circuits and the notice
 * can never fire here regardless of whether the defect is present -- a spy
 * alone would be a vacuous green. `translate()` fires the `gettext` filter on
 * every single translation lookup in every locale, so counting those calls
 * for this text domain measures the exact thing the notice is a downstream
 * symptom of. The spy is kept anyway (it is free, and it is the literal
 * symptom the reviewer sees), and the notice's real before/after is evidenced
 * physically on the WP_DEBUG release stack, not here.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsCore::get
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsCore::get_defaults
 * @covers \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_default_settings
 * @covers \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_settings
 */
final class EarlyTranslationDefaultsTest extends WP_UnitTestCase
{
    private const DOMAIN = 'mhm-rentiva';

    /** @var list<string> */
    private array $translated = array();

    /** @var list<string> */
    private array $incorrect_usage = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->reset_defaults_cache();

        $this->translated      = array();
        $this->incorrect_usage = array();

        add_filter('gettext', array( $this, 'record_translation' ), 10, 3);
        add_filter('gettext_with_context', array( $this, 'record_translation_with_context' ), 10, 4);
        add_action('doing_it_wrong_run', array( $this, 'record_incorrect_usage' ), 1, 1);
    }

    protected function tearDown(): void
    {
        remove_filter('gettext', array( $this, 'record_translation' ), 10);
        remove_filter('gettext_with_context', array( $this, 'record_translation_with_context' ), 10);
        remove_action('doing_it_wrong_run', array( $this, 'record_incorrect_usage' ), 1);

        $this->reset_defaults_cache();
        delete_option('mhmrentiva_settings');

        parent::tearDown();
    }

    private function reset_defaults_cache(): void
    {
        $property = ( new \ReflectionClass(SettingsCore::class) )->getProperty('defaults_cache');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    public function record_translation(string $translation, string $text, string $domain): string
    {
        if (self::DOMAIN === $domain) {
            $this->translated[] = $text;
        }

        return $translation;
    }

    public function record_translation_with_context(string $translation, string $text, string $context, string $domain): string
    {
        if (self::DOMAIN === $domain) {
            $this->translated[] = $text;
        }

        return $translation;
    }

    public function record_incorrect_usage(string $function_name): void
    {
        $this->incorrect_usage[] = $function_name;
    }

    /**
     * The exact chain the release-stack backtrace showed, run end to end.
     *
     * `info()` is skipped by should_skip_log() under the default 'error' log
     * level, so nothing is written; the settings read still happens, which is
     * the whole point.
     */
    public function test_migration_completion_log_materialises_no_translation(): void
    {
        AdvancedLogger::info(
            'Database migration completed',
            array(
                'from_version' => '4.2.0',
                'to_version'   => '4.3.0',
            ),
            AdvancedLogger::CATEGORY_SYSTEM
        );

        $this->assertSame(
            array(),
            $this->translated,
            'Logging a migration must not translate anything: ' . implode(' | ', array_slice($this->translated, 0, 5))
        );
        $this->assertNotContains('_load_textdomain_just_in_time', $this->incorrect_usage);
    }

    /**
     * The same defect one level down, so a future refactor of AdvancedLogger
     * cannot silently move the problem out from under the test above.
     */
    public function test_reading_one_plain_setting_materialises_no_translation(): void
    {
        $this->assertSame('error', SettingsCore::get('mhmrentiva_log_level', 'error'));

        $this->assertSame(
            array(),
            $this->translated,
            'SettingsCore::get() of a plain key must not translate anything: '
                . implode(' | ', array_slice($this->translated, 0, 5))
        );
        $this->assertNotContains('_load_textdomain_just_in_time', $this->incorrect_usage);
    }

    /**
     * Same class of defect, second site found by the sweep: the seasonal
     * pricing read path is the public booking form's, called once per rental
     * day, and it built the pricing defaults (9 translated strings) as an
     * eagerly-evaluated argument even when a stored value existed.
     */
    public function test_seasonal_pricing_read_materialises_no_translation_when_stored(): void
    {
        update_option(
            'mhmrentiva_settings',
            array(
                'vehicle_pricing' => array(
                    'seasonal_multipliers' => array(
                        'summer' => array(
                            'name'       => 'Yaz',
                            'months'     => array( 6, 7, 8 ),
                            'multiplier' => 1.3,
                        ),
                    ),
                ),
            )
        );

        $this->assertSame(1.3, VehiclePricingSettings::get_seasonal_multiplier_for_month(7));

        $this->assertSame(
            array(),
            $this->translated,
            'The seasonal read path must not translate the defaults it does not use: '
                . implode(' | ', array_slice($this->translated, 0, 5))
        );
    }

    /**
     * Stored-option semantics are unchanged: a site that already saved these
     * values reads back exactly what it saved, and a site that saved nothing
     * still gets the translated default.
     */
    public function test_stored_email_settings_read_back_unchanged(): void
    {
        update_option(
            'mhmrentiva_settings',
            array(
                'mhmrentiva_booking_created_subject' => 'Rezervasyonunuz onaylandi #{booking_id}',
                'mhmrentiva_email_footer_text'       => 'Custom footer',
                'mhmrentiva_booking_created_body'    => '<p>Merhaba</p>',
            )
        );

        $this->assertSame('Rezervasyonunuz onaylandi #{booking_id}', SettingsCore::get('mhmrentiva_booking_created_subject'));
        $this->assertSame('Custom footer', SettingsCore::get('mhmrentiva_email_footer_text'));
        $this->assertSame('<p>Merhaba</p>', SettingsCore::get('mhmrentiva_booking_created_body'));
    }

    /**
     * Unstored keys still resolve to the very same strings the defaults
     * declared before this change -- the deferral must change WHEN they are
     * built, never WHAT they are.
     */
    public function test_unstored_email_settings_still_resolve_to_the_declared_defaults(): void
    {
        $declared = EmailSettings::get_default_settings();

        foreach (array(
            'mhmrentiva_booking_created_subject',
            'mhmrentiva_email_footer_text',
            'mhmrentiva_booking_created_body',
            'mhmrentiva_auto_cancel_email_subject',
            'mhmrentiva_refund_admin_body',
        ) as $key) {
            $this->assertSame($declared[ $key ], SettingsCore::get($key), $key);
        }

        $this->assertSame(
            sprintf(
                /* translators: %s: site name */
                __('%s - Powered by MHM Rentiva', 'mhm-rentiva'),
                get_bloginfo('name')
            ),
            $declared['mhmrentiva_email_footer_text']
        );
    }

    /**
     * A deferred default must never escape as a Closure: SettingsService's
     * reset path and RESTSettings both write these arrays straight into
     * `update_option()`, and PHP cannot serialize a Closure -- it is a fatal,
     * not a warning.
     */
    public function test_no_default_escapes_unresolved(): void
    {
        foreach (array( SettingsCore::get_defaults(), EmailSettings::get_default_settings(), VehiclePricingSettings::get_default_settings() ) as $index => $defaults) {
            array_walk_recursive(
                $defaults,
                function ($value, $key) use ($index): void {
                    $this->assertNotInstanceOf(\Closure::class, $value, "map #{$index} key {$key}");
                }
            );
            $this->assertIsString(maybe_serialize($defaults));
        }
    }
}
