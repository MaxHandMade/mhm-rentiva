<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Operator branding for payout statements (header company identity + logo + footer note).
 * Render-time read of the current settings; NOT part of the immutable statement snapshot.
 */
final class PayoutStatementBranding {

	public static function get(): array
	{
		$name = trim( (string) SettingsCore::get('statement_company_name', ''));
		if ($name === '') {
			$name = (string) get_bloginfo('name');
		}

		$logo_id  = (int) SettingsCore::get('statement_logo_id', 0);
		$logo_url = $logo_id > 0 ? (string) ( wp_get_attachment_url($logo_id) ?: '' ) : '';

		return array(
			'company_name' => $name,
			'address'      => (string) SettingsCore::get('statement_company_address', ''),
			'tax_office'   => (string) SettingsCore::get('statement_company_tax_office', ''),
			'tax_number'   => (string) SettingsCore::get('statement_company_tax_number', ''),
			'phone'        => (string) SettingsCore::get('statement_company_phone', ''),
			'email'        => (string) SettingsCore::get('statement_company_email', ''),
			'logo_url'     => $logo_url,
			'footer_note'  => (string) SettingsCore::get('statement_footer_note', ''),
		);
	}
}
