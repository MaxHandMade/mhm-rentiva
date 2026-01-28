<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View;

use MHMRentiva\Admin\Settings\View\Tabs\BaseSettingsTabRenderer;
use MHMRentiva\Admin\Settings\View\Tabs\GeneralSettingsRenderer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Factory and Registry for Settings Tab Renderers
 */
final class TabRendererRegistry
{

	/**
	 * @var array<string, TabRendererInterface>
	 */
	private array $renderers = array();

	public function __construct()
	{
		$this->register_default_renderers();
	}

	/**
	 * Get a renderer by slug
	 */
	public function get(string $slug): ?TabRendererInterface
	{
		return $this->renderers[$slug] ?? null;
	}

	/**
	 * Get all registered renderers
	 *
	 * @return array<string, TabRendererInterface>
	 */
	public function get_all(): array
	{
		// Sort according to a predefined order
		$order = (array) apply_filters(
			'mhm_rentiva_settings_tabs_order',
			array(
				'general',
				'vehicle',
				'booking',
				'transfer',
				'addons',
				'customer',
				'comments',
				'payment',
				'email',
				'email-templates',
				'messages',
				'system',
				'frontend',
				'integration',
				'database-cleanup',
				'cron-monitor',
				'testing',
			)
		);

		$sorted = array();
		foreach ($order as $slug) {
			if (isset($this->renderers[$slug])) {
				$sorted[$slug] = $this->renderers[$slug];
			}
		}

		// Add any remaining renderers that were not in the order list
		return array_merge($sorted, $this->renderers);
	}

	/**
	 * Register a new renderer
	 */
	public function register(TabRendererInterface $renderer): void
	{
		$this->renderers[$renderer->get_slug()] = $renderer;
	}

	/**
	 * Initialize default renderers
	 */
	private function register_default_renderers(): void
	{
		$this->register(new GeneralSettingsRenderer());

		$this->register(
			new BaseSettingsTabRenderer(
				__('Vehicle Management', 'mhm-rentiva'),
				'vehicle',
				__('Configure vehicle pricing, display options, and availability settings.', 'mhm-rentiva'),
				'\MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings',
				array('mhm_rentiva_vehicle_pricing_section', 'mhm_rentiva_vehicle_display_section', 'mhm_rentiva_vehicle_availability_section', 'mhm_rentiva_vehicle_comparison_section')
			)
		);

		$this->register(
			new BaseSettingsTabRenderer(
				__('Booking Management', 'mhm-rentiva'),
				'booking',
				__('Manage booking workflows, restrictions, and confirmation settings.', 'mhm-rentiva'),
				'\MHMRentiva\Admin\Settings\Groups\BookingSettings'
			)
		);

		$this->register(
			new BaseSettingsTabRenderer(
				__('Extra Service Settings', 'mhm-rentiva'),
				'addons',
				__('Configure addon categories and service types.', 'mhm-rentiva'),
				'\MHMRentiva\Admin\Settings\Groups\AddonSettings'
			)
		);

		$this->register(
			new BaseSettingsTabRenderer(
				__('Customer Management', 'mhm-rentiva'),
				'customer',
				__('Set user permissions, registration options, and profile display.', 'mhm-rentiva'),
				'\MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings'
			)
		);

		$this->register(
			new BaseSettingsTabRenderer(
				__('Comments & Reviews', 'mhm-rentiva'),
				'comments',
				__('Configure comment moderation, rating settings, spam protection, and display options.', 'mhm-rentiva'),
				'\MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup'
			)
		);

		// Payment tab logic moved to a filter-compliant structure
		if (! class_exists('WooCommerce')) {
			$this->register(
				new BaseSettingsTabRenderer(
					__('Payment Settings', 'mhm-rentiva'),
					'payment',
					__('Configure manual payment methods and currency settings.', 'mhm-rentiva'),
					'\MHMRentiva\Admin\Settings\Groups\PaymentSettings'
				)
			);
		}

		$this->register(
			new BaseSettingsTabRenderer(
				__('Email Configuration', 'mhm-rentiva'),
				'email',
				__('Configure outgoing mail server and sender information.', 'mhm-rentiva'),
				null,
				array('mhm_rentiva_email_section')
			)
		);

		// email-templates handled by EmailTemplates class but orchestrated here
		$this->register(
			new class(__('Notification Templates', 'mhm-rentiva'), 'email-templates') extends AbstractTabRenderer {
				public function render(): void
				{
					if (class_exists('\MHMRentiva\Admin\Emails\Core\EmailTemplates')) {
						\MHMRentiva\Admin\Emails\Core\EmailTemplates::render_content_only();
					} else {
						echo '<div class="notice notice-error"><p>' . esc_html__('Email Templates system not found.', 'mhm-rentiva') . '</p></div>';
					}
				}

				public function should_wrap_with_form(): bool
				{
					return false;
				}
			}
		);

		// Messages
		$this->register(new \MHMRentiva\Admin\Settings\View\Tabs\MessagesSettingsRenderer());

		// System
		$this->register(
			new BaseSettingsTabRenderer(
				__('System & Performance', 'mhm-rentiva'),
				'system',
				__('Monitor and configure system health, security, and performance.', 'mhm-rentiva'),
				null,
				array('mhm_rentiva_core_section', 'mhm_rentiva_ip_control_section', 'mhm_rentiva_security_rules_section', 'mhm_rentiva_authentication_section')
			)
		);

		// Frontend
		$this->register(
			new BaseSettingsTabRenderer(
				__('Frontend & Display', 'mhm-rentiva'),
				'frontend',
				__('Control how your rental site looks and feels to customers.', 'mhm-rentiva'),
				'\MHMRentiva\Admin\Settings\Groups\FrontendSettings'
			)
		);

		// Integration
		$this->register(new \MHMRentiva\Admin\Settings\View\Tabs\IntegrationRenderer());

		// Utilities
		$this->register(new \MHMRentiva\Admin\Settings\View\Tabs\DatabaseCleanupRenderer());
		$this->register(new \MHMRentiva\Admin\Settings\View\Tabs\CronMonitorRenderer());
		$this->register(new \MHMRentiva\Admin\Settings\View\Tabs\TransferSettingsRenderer());
		$this->register(new \MHMRentiva\Admin\Settings\View\Tabs\SettingsTestingRenderer());

		/**
		 * Allow modifying renderers after defaults are registered.
		 *
		 * @param TabRendererRegistry $this This registry instance.
		 */
		do_action('mhm_rentiva_settings_register_renderers', $this);
	}
}
