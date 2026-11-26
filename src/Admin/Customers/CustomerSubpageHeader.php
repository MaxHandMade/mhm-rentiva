<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The page header of the customer sub-pages drawn in PHP (details, edit, add).
 *
 * A sub-page starts with a back link to its parent, as the React screens do
 * with the kit's PageHeader; the PHP twin draws the same markup. The page's
 * wrapper carries the shell classes, so the header and the blocks under it
 * take the shell rhythm.
 */
final class CustomerSubpageHeader {
	/**
	 * Echo the header and the admin notice anchor.
	 *
	 * @param string $title      Page title (plain text).
	 * @param string $back_label Back link text (plain text).
	 * @param string $back_url   Absolute admin URL of the parent page.
	 */
	public static function render(string $title, string $back_label, string $back_url): void
	{
		if (function_exists('mhmuicore_page_header_html')) {
			echo wp_kses_post(
				mhmuicore_page_header_html(
					array(
						'back'  => array(
							'label' => $back_label,
							'href'  => $back_url,
						),
						'title' => $title,
						'level' => 1,
					)
				)
			);
		} else {
			echo '<p><a href="' . esc_url($back_url) . '">' . esc_html($back_label) . '</a></p>';
			echo '<h1>' . esc_html($title) . '</h1>';
		}

		// Anchor for admin notices (core common.js and NoticePlacement).
		echo '<hr class="wp-header-end">';
	}

	/**
	 * The customers list, the parent of the details and add pages.
	 */
	public static function list_url(): string
	{
		return admin_url('admin.php?page=mhm-rentiva-customers');
	}
}
