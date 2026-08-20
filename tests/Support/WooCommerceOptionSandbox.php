<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Set a site option for the duration of one test and put back exactly what was
 * there -- including the case where there was nothing.
 *
 * Adopting tests call sandbox_option() instead of update_option(), and
 * restore_sandboxed_options() from tearDown().
 */
trait WooCommerceOptionSandbox
{
	/**
	 * name => array{0: bool $existed, 1: mixed $value}
	 *
	 * @var array<string, array{0: bool, 1: mixed}>
	 */
	private array $mhmrentiva_saved_options = array();

	/**
	 * @param mixed $value
	 */
	protected function sandbox_option( string $name, $value ): void {
		if ( ! array_key_exists( $name, $this->mhmrentiva_saved_options ) ) {
			// An object as the default makes "absent" unambiguous. A string or
			// null sentinel cannot be told apart from a real stored value, and
			// '0' and '' are both real WooCommerce settings.
			$sentinel = new \stdClass();
			$current  = get_option( $name, $sentinel );

			$this->mhmrentiva_saved_options[ $name ] = ( $current === $sentinel )
				? array( false, null )
				: array( true, $current );
		}

		update_option( $name, $value );
	}

	protected function restore_sandboxed_options(): void {
		foreach ( $this->mhmrentiva_saved_options as $name => $saved ) {
			list( $existed, $value ) = $saved;

			if ( $existed ) {
				update_option( $name, $value );
			} else {
				delete_option( $name );
			}
		}

		$this->mhmrentiva_saved_options = array();
	}
}
