<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\Core\Tabs\AbstractTab;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Support tab
 */
final class SupportTab extends AbstractTab
{
    protected static function get_tab_id(): string
    {
        return 'support';
    }

    protected static function get_tab_title(): string
    {
        return __('Support', 'mhm-rentiva');
    }

    protected static function get_tab_description(): string
    {
        return __('Support channels, documentation and version history', 'mhm-rentiva');
    }

    protected static function get_tab_content(array $data = []): array
    {
        // If no data is passed, get the changelog
        if (empty($data)) {
            $data = static::get_changelog();
        }

        return [
            'title' => static::get_tab_title(),
            'description' => static::get_tab_description(),
            'sections' => [
                [
                    'type' => 'custom',
                    'custom_render' => [self::class, 'render_support_cards'],
                ],
                [
                    'type' => 'custom',
                    'title' => __('Version History', 'mhm-rentiva'),
                    'custom_render' => [self::class, 'render_changelog'],
                ],
            ],
        ];
    }

    /**
     * Support cards render
     */
    public static function render_support_cards(array $section, array $data = []): void
    {
        echo '<div class="support-grid">';

        // Documentation card
        $company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();

        echo '<div class="support-card">';
        echo '<h3>' . __('Documentation', 'mhm-rentiva') . '</h3>';
        echo '<p>' . __('Detailed user guides, video tutorials and API documentation.', 'mhm-rentiva') . '</p>';
        echo '<div class="support-links">';
        echo Helpers::render_external_link(
            'https://maxhandmade.github.io/mhm-rentiva-docs/',
            __('User Guide', 'mhm-rentiva'),
            ['class' => 'button button-secondary']
        );
        echo Helpers::render_external_link(
            'https://maxhandmade.github.io/mhm-rentiva-docs/docs/developer/rest-api/',
            __('API Documentation', 'mhm-rentiva'),
            ['class' => 'button button-secondary']
        );
        echo Helpers::render_external_link(
            'https://www.youtube.com/channel/UC3qBE6ZCCEc8ugFUYXwtcpA',
            __('Video Tutorials', 'mhm-rentiva'),
            ['class' => 'button button-secondary']
        );
        echo '</div>';
        echo '</div>';

        $support_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();

        // Support channels card
        echo '<div class="support-card">';
        echo '<h3>' . __('Support Channels', 'mhm-rentiva') . '</h3>';
        echo '<p>' . __('Contact us for your questions.', 'mhm-rentiva') . '</p>';
        echo '<div class="support-links">';
        echo Helpers::render_external_link(
            'https://maxhandmade.com/iletisim/',
            __('Contact Form', 'mhm-rentiva'),
            ['class' => 'button button-primary']
        );

        if (Mode::isPro()) {
            echo Helpers::render_external_link(
                'mailto:' . $support_email,
                __('Priority Support', 'mhm-rentiva'),
                ['class' => 'button button-secondary']
            );
        }

        echo '<div class="contact-info">';
        echo '<p><strong>' . __('Email:', 'mhm-rentiva') . '</strong> ' . esc_html($support_email) . '</p>';
        $phone_number = apply_filters('mhm_rentiva_contact_phone', __('+90 538 556 4158', 'mhm-rentiva'));
        echo '<p><strong>' . __('Phone:', 'mhm-rentiva') . '</strong> ' . esc_html($phone_number) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Community card
        echo '<div class="support-card">';
        echo '<h3>' . __('Community', 'mhm-rentiva') . '</h3>';
        echo '<p>' . __('Share your experiences with other users.', 'mhm-rentiva') . '</p>';
        echo '<div class="support-links">';
        echo Helpers::render_external_link(
            'https://wordpress.org/support/plugin/mhm-rentiva',
            __('WordPress Support Forum', 'mhm-rentiva'),
            ['class' => 'button button-secondary dashicons-before dashicons-wordpress']
        );
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Changelog render
     */
    public static function render_changelog(array $section, array $data = []): void
    {
        $changelog = self::get_changelog();

        echo '<div class="changelog-list">';

        if (!empty($changelog)) {
            foreach ($changelog as $release) {
                echo '<div class="changelog-item ' . esc_attr($release['type'] ?? '') . '">';
                echo '<div class="changelog-header">';
                echo '<div class="version-info">';
                echo '<strong>v' . esc_html($release['version']) . '</strong>';
                echo '<span class="release-date">' . esc_html($release['date']) . '</span>';

                if (($release['type'] ?? '') === 'current') {
                    echo '<span class="current-badge">' . __('Current Version', 'mhm-rentiva') . '</span>';
                }

                echo '</div>';
                echo '</div>';
                echo '<div class="changelog-content">';
                echo '<ul>';

                foreach ($release['changes'] as $change) {
                    echo '<li>' . esc_html($change) . '</li>';
                }

                echo '</ul>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>' . __('Version history information not found.', 'mhm-rentiva') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Get the changelog
     */
    public static function get_changelog(): array
    {
        // Detect current WordPress locale
        $locale = get_locale();

        // Use Turkish changelog if locale is Turkish
        $changelog_filename = 'changelog.json';
        if (strpos($locale, 'tr_') === 0) {
            $changelog_filename = 'changelog-tr.json';
        }

        $changelog_file = MHM_RENTIVA_PLUGIN_DIR . $changelog_filename;

        if (!file_exists($changelog_file)) {
            // Fallback to default changelog.json if localized version doesn't exist
            $changelog_file = MHM_RENTIVA_PLUGIN_DIR . 'changelog.json';

            if (!file_exists($changelog_file)) {
                return self::get_default_changelog();
            }
        }

        $changelog = json_decode(file_get_contents($changelog_file), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MHM Rentiva Changelog JSON Error: ' . json_last_error_msg());
            return self::get_default_changelog();
        }

        return $changelog;
    }

    /**
     * Default changelog
     */
    private static function get_default_changelog(): array
    {
        return [
            [
                'version' => MHM_RENTIVA_VERSION,
                'date' => date('Y-m-d'),
                'type' => 'current',
                'changes' => [
                    __('Current version', 'mhm-rentiva'),
                    __('About page added', 'mhm-rentiva'),
                    __('Messaging system added', 'mhm-rentiva'),
                    __('Advanced reports system', 'mhm-rentiva'),
                ]
            ]
        ];
    }
}
