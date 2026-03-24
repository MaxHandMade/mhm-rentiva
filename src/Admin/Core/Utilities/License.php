<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Licensing\LicenseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central class for license management
 */
final class License {

	private static ?self $instance = null;
	private LicenseManager $licenseManager;

	private function __construct() {
		$this->licenseManager = LicenseManager::instance();
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Check if license is active
	 */
	public function isActive(): bool {
		return $this->licenseManager->isActive();
	}

	/**
	 * Check if developer mode is active
	 */
	public function isDevMode(): bool {
		// Only automatic developer mode (safe)
		return $this->licenseManager->isDevelopmentEnvironment();
	}

	/**
	 * Get license data
	 */
	public function getLicenseData(): array {
		return $this->licenseManager->get();
	}

	/**
	 * Save license data
	 */
	public function setLicenseData( array $data ): void {
		$this->licenseManager->save( $data );
	}

	/**
	 * Clear license
	 */
	public function clearLicense(): void {
		$this->licenseManager->save( array() );
	}

	/**
	 * Get license key
	 */
	public function getLicenseKey(): string {
		return $this->licenseManager->getKey();
	}

	/**
	 * Get license status
	 */
	public function getStatus(): string {
		$data = $this->getLicenseData();
		return $data['status'] ?? 'inactive';
	}

	/**
	 * Get license plan
	 */
	public function getPlan(): string {
		$data = $this->getLicenseData();
		return $data['plan'] ?? 'lite';
	}

	/**
	 * Get license expiration date
	 */
	public function getExpiresAt(): ?int {
		$data = $this->getLicenseData();
		return isset( $data['expires_at'] ) ? (int) $data['expires_at'] : null;
	}

	/**
	 * Check if license is expired
	 */
	public function isExpired(): bool {
		$expires = $this->getExpiresAt();
		if ( $expires === null ) {
			return false;
		}
		return $expires < time();
	}

	/**
	 * Check if license is valid
	 */
	public function isValid(): bool {
		if ( $this->isDevMode() ) {
			return true;
		}

		if ( ! $this->isActive() ) {
			return false;
		}

		if ( $this->isExpired() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if pro features are active
	 */
	public function hasProFeatures(): bool {
		return $this->isValid() || $this->isDevMode();
	}
}
