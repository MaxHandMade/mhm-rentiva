<?php declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\Core\Tabs\AbstractTab;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Developer tab
 */
final class DeveloperTab extends AbstractTab
{
    protected static function get_tab_id(): string
    {
        return 'developer';
    }

    protected static function get_tab_title(): string
    {
        return __('Developer', 'mhm-rentiva');
    }

    protected static function get_tab_description(): string
    {
        return __('MHM (MaxHandMade) developer information and projects', 'mhm-rentiva');
    }

    protected static function get_tab_content(array $data = []): array
    {
        return [
            'title' => static::get_tab_title(),
            'description' => static::get_tab_description(),
            'sections' => [
                [
                    'type' => 'custom',
                    'custom_render' => [self::class, 'render_developer_header'],
                ],
                [
                    'type' => 'custom',
                    'title' => __('Our Expertise', 'mhm-rentiva'),
                    'custom_render' => [self::class, 'render_expertise_grid'],
                ],
                [
                    'type' => 'custom',
                    'title' => __('Contact', 'mhm-rentiva'),
                    'custom_render' => [self::class, 'render_contact_info'],
                ],
                [
                    'type' => 'custom',
                    'title' => __('Our Other Projects', 'mhm-rentiva'),
                    'custom_render' => [self::class, 'render_other_projects'],
                ],
            ],
        ];
    }

    /**
     * Developer header render
     */
    public static function render_developer_header(array $section, array $data = []): void
    {
        echo '<div class="developer-info">';
        echo '<div class="developer-header">';
        
        echo '<div class="developer-logo">';
        echo '<img src="' . esc_url(MHM_RENTIVA_PLUGIN_URL . 'assets/images/mhm-logo.png') . '" alt="MHM Logo" onerror="this.style.display=\'none\'">';
        echo '</div>';
        
        echo '<div class="developer-details">';
        echo '<h3>' . esc_html__('MHM (MaxHandMade)', 'mhm-rentiva') . '</h3>';
        echo '<p class="developer-tagline">' . __('WordPress Expertise and Custom Software Solutions', 'mhm-rentiva') . '</p>';
        echo '<div class="developer-stats">';
        echo '<span class="stat">' . __('10+ Years Experience', 'mhm-rentiva') . '</span>';
        echo '<span class="stat">' . __('500+ Projects', 'mhm-rentiva') . '</span>';
        echo '<span class="stat">' . __('100% Customer Satisfaction', 'mhm-rentiva') . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="developer-description">';
        echo '<h4>' . __('About Us', 'mhm-rentiva') . '</h4>';
        $company_name = __('MHM (MaxHandMade)', 'mhm-rentiva');
        echo '<p>' . sprintf(
            __('%s is an expert team that has been developing WordPress-based solutions and custom software projects since 2014. We specialize in e-commerce, reservation systems, corporate websites, and mobile applications.', 'mhm-rentiva'),
            $company_name
        ) . '</p>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Expertise grid render
     */
    public static function render_expertise_grid(array $section, array $data = []): void
    {
        $expertise_items = [
            [
                'title' => __('WordPress Development', 'mhm-rentiva'),
                'description' => __('Custom plugins, theme development, performance optimization', 'mhm-rentiva'),
            ],
            [
                'title' => __('E-commerce Solutions', 'mhm-rentiva'),
                'description' => __('WooCommerce customizations, payment integrations', 'mhm-rentiva'),
            ],
            [
                'title' => __('Reservation Systems', 'mhm-rentiva'),
                'description' => __('Hotel, restaurant, car rental and event reservations', 'mhm-rentiva'),
            ],
            [
                'title' => __('Mobile Applications', 'mhm-rentiva'),
                'description' => __('React Native and Flutter based native applications', 'mhm-rentiva'),
            ],
            [
                'title' => __('API Integrations', 'mhm-rentiva'),
                'description' => __('Payment systems, cargo companies, SMS services', 'mhm-rentiva'),
            ],
            [
                'title' => __('Database Optimization', 'mhm-rentiva'),
                'description' => __('Performance improvement and scaling', 'mhm-rentiva'),
            ],
        ];

        echo '<div class="expertise-grid">';
        foreach ($expertise_items as $item) {
            echo '<div class="expertise-item">';
            echo '<h5>' . esc_html($item['title']) . '</h5>';
            echo '<p>' . esc_html($item['description']) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Contact info render
     */
    public static function render_contact_info(array $section, array $data = []): void
    {
        echo '<div class="contact-grid">';
        
        $company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
        $support_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();
        
        echo '<div class="contact-item">';
        echo '<strong>' . __('Website:', 'mhm-rentiva') . '</strong>';
        echo Helpers::render_external_link($company_website, parse_url($company_website, PHP_URL_HOST));
        echo '</div>';
        
        echo '<div class="contact-item">';
        echo '<strong>' . __('Email:', 'mhm-rentiva') . '</strong>';
        echo '<a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>';
        echo '</div>';
        
        echo '<div class="contact-item">';
        echo '<strong>' . __('Phone:', 'mhm-rentiva') . '</strong>';
        $phone_number = apply_filters('mhm_rentiva_contact_phone', __('+90 538 556 4158', 'mhm-rentiva'));
        echo '<a href="tel:' . esc_attr(str_replace(' ', '', $phone_number)) . '">' . esc_html($phone_number) . '</a>';
        echo '</div>';
        
        echo '<div class="contact-item">';
        echo '<strong>' . __('Address:', 'mhm-rentiva') . '</strong>';
        $address = sprintf(
            '%s - %s %s',
            __('Kocaeli', 'mhm-rentiva'),
            __('Turkey', 'mhm-rentiva'),
            '41400'
        );
        echo '<span>' . esc_html($address) . '</span>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Other projects render
     */
    public static function render_other_projects(array $section, array $data = []): void
    {
        // Company website URL
        $company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
        
        $projects = [
            [
                'title' => __('MHM E-commerce Package', 'mhm-rentiva'),
                'description' => __('Comprehensive WooCommerce-based e-commerce solution', 'mhm-rentiva'),
            ],
            [
                'title' => __('MHM Hotel Reservation', 'mhm-rentiva'),
                'description' => __('Reservation system for hotels and accommodation facilities', 'mhm-rentiva'),
            ],
            [
                'title' => __('MHM Restaurant Management', 'mhm-rentiva'),
                'description' => __('Table reservation and ordering system for restaurants', 'mhm-rentiva'),
            ],
            [
                'title' => __('MHM Event Management', 'mhm-rentiva'),
                'description' => __('Event and conference management system', 'mhm-rentiva'),
            ],
        ];

        echo '<div class="projects-grid">';
        foreach ($projects as $project) {
            echo '<div class="project-item">';
            echo '<h5>' . esc_html($project['title']) . '</h5>';
            echo '<p>' . esc_html($project['description']) . '</p>';
            echo Helpers::render_external_link(
                $company_website,
                __('Learn More', 'mhm-rentiva'),
                ['class' => 'button button-small']
            );
            echo '</div>';
        }
        echo '</div>';
    }
}
