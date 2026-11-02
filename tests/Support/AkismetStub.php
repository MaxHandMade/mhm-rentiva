<?php
declare(strict_types=1);

use MHMRentiva\Tests\Support\AkismetStubState;

if (! class_exists('Akismet', false)) {
	/**
	 * Stand-in with the signatures of Akismet's own class (class.akismet.php):
	 * get_api_key() and http_post( $request, $path, $ip = null ), which answers
	 * array( headers, body ), both empty on failure.
	 */
	class Akismet
	{
		public static function get_api_key()
		{
			return AkismetStubState::$key;
		}

		public static function http_post($request, $path, $ip = null)
		{
			unset($ip);
			AkismetStubState::$requests[] = array( (string) $request, (string) $path );
			if (AkismetStubState::$answer instanceof \Throwable) {
				throw AkismetStubState::$answer;
			}
			return AkismetStubState::$answer;
		}
	}
}
