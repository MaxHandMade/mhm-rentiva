<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * CityHelper
 *
 * Returns a city/province list based on the WooCommerce store's base country.
 * Falls back to Turkey's 81 provinces when WooCommerce is unavailable.
 */
final class CityHelper
{
	/**
	 * Returns an array of city/province names for the store's base country.
	 *
	 * @return string[]
	 */
	public static function get_city_list(): array
	{
		if (function_exists('WC') && WC()->countries) {
			$country = WC()->countries->get_base_country();
			$states  = WC()->countries->get_states($country);
			if (! empty($states) && is_array($states)) {
				return array_values($states);
			}
		}

		// Fallback: Turkey 81 provinces (alphabetical)
		return array(
			'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara',
			'Antalya', 'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman',
			'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa',
			'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne',
			'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun',
			'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir',
			'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri',
			'Kilis', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya',
			'Kütahya', 'Malatya', 'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş',
			'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun',
			'Siirt', 'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak', 'Tekirdağ', 'Tokat',
			'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak',
		);
	}

	/**
	 * Returns the HTML for a <datalist> element containing all city options.
	 *
	 * @param  string $id The datalist element ID.
	 * @return string     Safe HTML string.
	 */
	public static function render_datalist(string $id = 'mhm-cities-list'): string
	{
		$cities = self::get_city_list();
		$html   = '<datalist id="' . esc_attr($id) . '">';
		foreach ($cities as $city) {
			$html .= '<option value="' . esc_attr($city) . '">';
		}
		$html .= '</datalist>';
		return $html;
	}

	/**
	 * Returns the HTML for a <select> element with searchable city options.
	 *
	 * Designed to be enhanced with selectWoo/Select2 for in-field search UX.
	 *
	 * @param  string $name     The select name attribute.
	 * @param  string $id       The select id attribute.
	 * @param  string $selected Currently selected city value.
	 * @param  array  $attrs    Additional HTML attributes (e.g. 'required', 'class').
	 * @return string           Safe HTML string.
	 */
	public static function render_select(string $name, string $id, string $selected = '', array $attrs = array()): string
	{
		$cities = self::get_city_list();

		$extra = '';
		foreach ($attrs as $key => $value) {
			if ($value === true) {
				$extra .= ' ' . esc_attr($key);
			} else {
				$extra .= ' ' . esc_attr($key) . '="' . esc_attr((string) $value) . '"';
			}
		}

		$html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="mhm-city-select"' . $extra . '>';
		$html .= '<option value="">' . esc_html__('Select a city...', 'mhm-rentiva') . '</option>';
		foreach ($cities as $city) {
			$html .= '<option value="' . esc_attr($city) . '"'
				. selected($selected, $city, false)
				. '>' . esc_html($city) . '</option>';
		}
		$html .= '</select>';
		return $html;
	}
}
