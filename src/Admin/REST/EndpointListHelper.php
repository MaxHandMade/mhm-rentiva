<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ ENDPOINT LIST HELPER - REST API Endpoint List
 *
 * Lists all registered REST endpoints using
 * FunctionalTest::test_rest_api_endpoints() logic
 */
final class EndpointListHelper {

	const NAMESPACE = 'mhm-rentiva/v1';

	/**
	 * List all registered endpoints
	 *
	 * @return array Endpoint list
	 */
	public static function get_all_endpoints(): array {
		$rest_server = rest_get_server();

		if ( ! $rest_server ) {
			return array();
		}

		$namespaces    = $rest_server->get_namespaces();
		$has_namespace = in_array( self::NAMESPACE, $namespaces );

		if ( ! $has_namespace ) {
			return array();
		}

		$routes    = $rest_server->get_routes( self::NAMESPACE );
		$endpoints = array();

		foreach ( $routes as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				if ( ! isset( $handler['methods'] ) || ! isset( $handler['callback'] ) ) {
					continue;
				}

				$methods = is_array( $handler['methods'] ) ? array_keys( $handler['methods'] ) : array( $handler['methods'] );

				foreach ( $methods as $method ) {
					$endpoints[] = array(
						'route'               => '/' . self::NAMESPACE . $route,
						'method'              => strtoupper( $method ),
						'path'                => $route,
						'callback'            => self::format_callback( $handler['callback'] ?? '' ),
						'permission_callback' => self::format_callback( $handler['permission_callback'] ?? null ),
					);
				}
			}
		}

		// Sort by method and path
		usort(
			$endpoints,
			function ( $a, $b ) {
				// First by method
				$method_compare = strcmp( $a['method'], $b['method'] );
				if ( $method_compare !== 0 ) {
					return $method_compare;
				}
				// Then by path
				return strcmp( $a['path'], $b['path'] );
			}
		);

		return $endpoints;
	}

	/**
	 * Get endpoint count
	 *
	 * @return int Endpoint count
	 */
	public static function get_endpoint_count(): int {
		return count( self::get_all_endpoints() );
	}

	/**
	 * Filter endpoints by method
	 *
	 * @param string $method HTTP method (GET, POST, etc.)
	 * @return array Endpoint list
	 */
	public static function get_endpoints_by_method( string $method ): array {
		$all_endpoints = self::get_all_endpoints();

		return array_filter(
			$all_endpoints,
			function ( $endpoint ) use ( $method ) {
				return strtoupper( $endpoint['method'] ) === strtoupper( $method );
			}
		);
	}

	/**
	 * Format callback
	 *
	 * @param mixed $callback Callback
	 * @return string Formatted callback
	 */
	private static function format_callback( $callback ): string {
		if ( is_null( $callback ) ) {
			return '';
		}

		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && count( $callback ) === 2 ) {
			$class  = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
			$method = $callback[1];
			return $class . '::' . $method;
		}

		if ( is_callable( $callback ) ) {
			return 'callable';
		}

		return 'unknown';
	}
}
