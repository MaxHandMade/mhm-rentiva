<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Admin\Licensing\LicenseManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lisans yönetimi için merkezi sınıf
 */
final class License
{
    private static ?self $instance = null;
    private LicenseManager $licenseManager;

    private function __construct()
    {
        $this->licenseManager = LicenseManager::instance();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Lisansın aktif olup olmadığını kontrol eder
     */
    public function isActive(): bool
    {
        return $this->licenseManager->isActive();
    }

    /**
     * Developer modunun aktif olup olmadığını kontrol eder
     */
    public function isDevMode(): bool
    {
        // Sadece otomatik developer modu (güvenli)
        return $this->licenseManager->isDevelopmentEnvironment();
    }

    /**
     * Lisans verilerini getirir
     */
    public function getLicenseData(): array
    {
        return $this->licenseManager->get();
    }

    /**
     * Lisans verilerini kaydeder
     */
    public function setLicenseData(array $data): void
    {
        $this->licenseManager->save($data);
    }

    /**
     * Lisansı temizler
     */
    public function clearLicense(): void
    {
        $this->licenseManager->save([]);
    }

    /**
     * Lisans anahtarını getirir
     */
    public function getLicenseKey(): string
    {
        return $this->licenseManager->getKey();
    }

    /**
     * Lisans durumunu getirir
     */
    public function getStatus(): string
    {
        $data = $this->getLicenseData();
        return $data['status'] ?? 'inactive';
    }

    /**
     * Lisans planını getirir
     */
    public function getPlan(): string
    {
        $data = $this->getLicenseData();
        return $data['plan'] ?? 'lite';
    }

    /**
     * Lisans sona erme tarihini getirir
     */
    public function getExpiresAt(): ?int
    {
        $data = $this->getLicenseData();
        return isset($data['expires_at']) ? (int) $data['expires_at'] : null;
    }

    /**
     * Lisansın süresi dolmuş mu kontrol eder
     */
    public function isExpired(): bool
    {
        $expires = $this->getExpiresAt();
        if ($expires === null) {
            return false;
        }
        return $expires < time();
    }

    /**
     * Lisansın geçerli olup olmadığını kontrol eder
     */
    public function isValid(): bool
    {
        if ($this->isDevMode()) {
            return true;
        }

        if (!$this->isActive()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    /**
     * Pro özelliklerin aktif olup olmadığını kontrol eder
     */
    public function hasProFeatures(): bool
    {
        return $this->isValid() || $this->isDevMode();
    }
}
