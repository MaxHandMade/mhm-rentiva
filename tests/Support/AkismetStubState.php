<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * What the Akismet stand-in answers (tests/Support/AkismetStub.php), and what it was asked.
 */
final class AkismetStubState
{
	public static string $key = '';

	/** @var mixed An http_post() return value, or a Throwable to throw. */
	public static $answer = null;

	/** @var list<array{0:string,1:string}> */
	public static array $requests = array();

	public static function reset(): void
	{
		self::$key      = '';
		self::$answer   = null;
		self::$requests = array();
	}
}
