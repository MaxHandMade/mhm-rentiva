<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ BASE EXCEPTION - MHM Rentiva Exception Hierarchy
 *
 * Base class for all MHM Rentiva exceptions
 */
abstract class MHMException extends \Exception {

	/**
	 * Exception category
	 */
	protected string $category;

	/**
	 * Additional context information
	 */
	protected array $context;

	/**
	 * Constructor
	 *
	 * @param string          $message Exception message
	 * @param int             $code Exception code
	 * @param \Throwable|null $previous Previous exception
	 * @param string          $category Exception category
	 * @param array           $context Additional context information
	 */
	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		string $category = 'general',
		array $context = array()
	) {
		parent::__construct( $message, $code, $previous );
		$this->category = $category;
		$this->context  = $context;
	}

	/**
	 * Get exception category
	 *
	 * @return string
	 */
	public function getCategory(): string {
		return $this->category;
	}

	/**
	 * Get context information
	 *
	 * @return array
	 */
	public function getContext(): array {
		return $this->context;
	}

	/**
	 * Set context information
	 *
	 * @param array $context
	 */
	public function setContext( array $context ): void {
		$this->context = $context;
	}

	/**
	 * Log exception
	 */
	public function log(): void {
		if ( class_exists( \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class ) ) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
				$this->getMessage(),
				array(
					'exception' => static::class,
					'code'      => $this->getCode(),
					'category'  => $this->getCategory(),
					'context'   => $this->getContext(),
					'file'      => $this->getFile(),
					'line'      => $this->getLine(),
					'trace'     => $this->getTraceAsString(),
				),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);
		}
	}
}
