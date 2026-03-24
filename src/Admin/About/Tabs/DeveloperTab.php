<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.





use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\Core\Tabs\AbstractTab;



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

	protected static function get_tab_content(array $data = array()): array
	{
		return array(
			'title'       => self::get_tab_title(),
			'description' => self::get_tab_description(),
			'sections'    => array(
				array(
					'type'          => 'custom',
					'custom_render' => array(self::class, 'render_developer_header'),
				),
				array(
					'type'          => 'custom',
					'title'         => __('Our Expertise', 'mhm-rentiva'),
					'custom_render' => array(self::class, 'render_expertise_grid'),
				),
				array(
					'type'          => 'custom',
					'title'         => __('Contact', 'mhm-rentiva'),
					'custom_render' => array(self::class, 'render_contact_info'),
				),
				array(
					'type'          => 'custom',
					'title'         => __('Our Other Projects', 'mhm-rentiva'),
					'custom_render' => array(self::class, 'render_other_projects'),
				),
			),
		);
	}

	/**
	 * Developer header render
	 */
	public static function render_developer_header(array $section, array $data = array()): void
	{
		echo '<div class="developer-info">';
		echo '<div class="developer-header">';

		echo '<div class="developer-logo">';
		echo '<img src="' . esc_url(MHM_RENTIVA_PLUGIN_URL . 'assets/images/mhm-logo.png') . '" alt="MHM Logo" onerror="this.style.display=\'none\'">';
		echo '</div>';

		echo '<div class="developer-details">';
		echo '<h3>' . esc_html__('MHM (MaxHandMade)', 'mhm-rentiva') . '</h3>';
		echo '<p class="developer-tagline">' . esc_html__('WordPress Expertise and Custom Software Solutions', 'mhm-rentiva') . '</p>';
		echo '<div class="developer-stats">';
		echo '<span class="stat">' . esc_html__('10+ Years Experience', 'mhm-rentiva') . '</span>';
		echo '<span class="stat">' . esc_html__('500+ Projects', 'mhm-rentiva') . '</span>';
		echo '<span class="stat">' . esc_html__('100% Customer Satisfaction', 'mhm-rentiva') . '</span>';
		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '<div class="developer-description">';
		echo '<h4>' . esc_html__('About Us', 'mhm-rentiva') . '</h4>';
		$company_name = __('MHM (MaxHandMade)', 'mhm-rentiva');
		echo '<p>' . sprintf(
			/* translators: %s: company name. */
			esc_html__('%s is an expert team that has been developing WordPress-based solutions and custom software projects since 2014. We specialize in e-commerce, reservation systems, corporate websites, and mobile applications.', 'mhm-rentiva'),
			esc_html($company_name)
		) . '</p>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Expertise grid render
	 */
	public static function render_expertise_grid(array $section, array $data = array()): void
	{
		$expertise_items = array(
			array(
				'title'       => __('WordPress Development', 'mhm-rentiva'),
				'description' => __('Custom plugins, theme development, performance optimization', 'mhm-rentiva'),
			),
			array(
				'title'       => __('E-commerce Solutions', 'mhm-rentiva'),
				'description' => __('WooCommerce customizations, payment integrations', 'mhm-rentiva'),
			),
			array(
				'title'       => __('Reservation Systems', 'mhm-rentiva'),
				'description' => __('Hotel, restaurant, car rental and event reservations', 'mhm-rentiva'),
			),
		);

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
	public static function render_contact_info(array $section, array $data = array()): void
	{
		echo '<div class="contact-grid">';

		$company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
		$support_email   = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();

		echo '<div class="contact-item">';
		echo '<strong>' . esc_html__('Website:', 'mhm-rentiva') . '</strong>';
		echo wp_kses_post(Helpers::render_external_link($company_website, (string) wp_parse_url($company_website, PHP_URL_HOST)));
		echo '</div>';

		echo '<div class="contact-item">';
		echo '<strong>' . esc_html__('Email:', 'mhm-rentiva') . '</strong>';
		echo '<a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>';
		echo '</div>';

		echo '<div class="contact-item">';
		echo '<strong>' . esc_html__('Phone:', 'mhm-rentiva') . '</strong>';
		$phone_number = apply_filters('mhm_rentiva_contact_phone', __('+90 538 556 4158', 'mhm-rentiva'));
		echo '<a href="tel:' . esc_attr(str_replace(' ', '', $phone_number)) . '">' . esc_html($phone_number) . '</a>';
		echo '</div>';

		echo '<div class="contact-item">';
		echo '<strong>' . esc_html__('Address:', 'mhm-rentiva') . '</strong>';
		$address = sprintf(
			/* translators: 1: city, 2: country, 3: zip code */
			esc_html__('%1$s - %2$s %3$s', 'mhm-rentiva'),
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
	public static function render_other_projects(array $section, array $data = array()): void
	{
		// Company website URL
		$company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();

		$projects = array(
			array(
				'title'       => __('MHM E-commerce Package', 'mhm-rentiva'),
				'description' => __('Comprehensive WooCommerce-based e-commerce solution', 'mhm-rentiva'),
			),
			array(
				'title'       => __('MHM Vehicle Reservation', 'mhm-rentiva'),
				'description' => __('Professional vehicle rental and reservation management system.', 'mhm-rentiva'),
			),
		);

		echo '<div class="projects-grid">';
		foreach ($projects as $project) {
			echo '<div class="project-item">';
			echo '<h5>' . esc_html($project['title']) . '</h5>';
			echo '<p>' . esc_html($project['description']) . '</p>';
			echo wp_kses_post(
				Helpers::render_external_link(
					$company_website,
					esc_html__('Learn More', 'mhm-rentiva'),
					array('class' => 'button button-small')
				)
			);
			echo '</div>';
		}
		echo '</div>';
	}
}
