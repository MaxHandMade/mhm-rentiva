<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer list helper class
 *
 * This class handles customer list data.
 * Moved from Menu.php - safe refactoring process
 */
final class CustomersListPage {

	/**
	 * Get customer list (optimized)
	 *
	 * @param int    $page Page number
	 * @param int    $per_page Records per page
	 * @param string $search Search term
	 * @return array
	 */
	public static function get_customers_list( int $page = 1, int $per_page = 20, string $search = '' ): array {
		$data = CustomersOptimizer::get_customers_optimized( $page, $per_page, $search );
		return $data['customers'];
	}

	/**
	 * Customer list pagination information
	 *
	 * @param int    $page Page number
	 * @param int    $per_page Records per page
	 * @param string $search Search term
	 * @return array
	 */
	public static function get_customers_pagination( int $page = 1, int $per_page = 20, string $search = '' ): array {
		$data = CustomersOptimizer::get_customers_optimized( $page, $per_page, $search );
		return array(
			'total'       => $data['total'],
			'page'        => $data['page'],
			'per_page'    => $data['per_page'],
			'total_pages' => $data['total_pages'],
		);
	}
}
