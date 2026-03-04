<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Growth;

if (! defined('ABSPATH')) {
	exit;
}

final class AbVariantResolver
{
	/**
	 * @var array<string, string>
	 */
	private static array $request_cache = array();

	public function resolve(?int $user_id): string
	{
		$cache_key = null === $user_id ? 'anon' : 'user:' . (string) $user_id;

		if (isset(self::$request_cache[$cache_key])) {
			return self::$request_cache[$cache_key];
		}

		if (null === $user_id || $user_id <= 0) {
			self::$request_cache[$cache_key] = 'A';

			return self::$request_cache[$cache_key];
		}

		$hash = crc32((string) $user_id);
		self::$request_cache[$cache_key] = (0 === ($hash % 2)) ? 'A' : 'B';

		return self::$request_cache[$cache_key];
	}

	public static function reset_request_cache_for_tests(): void
	{
		self::$request_cache = array();
	}
}

