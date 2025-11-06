<?php

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSS Stil Yönetimi Sınıfı
 * 
 * Core CSS dosyasını hem admin hem frontend'te yükler.
 */
class Styles
{
    /**
     * Plugin dizin yolu
     */
    private $plugin_dir;

    /**
     * Plugin URL
     */
    private $plugin_url;

    /**
     * CSS handle adı - AssetManager ile uyumlu
     */
    const CSS_HANDLE = 'mhm-core-css';

    /**
     * Constructor
     */
    public function __construct($plugin_dir, $plugin_url)
    {
        $this->plugin_dir = $plugin_dir;
        $this->plugin_url = $plugin_url;
    }

    /**
     * CSS enqueue işlemlerini kaydet
     */
    public function register()
    {
        // Frontend'te CSS yükle
        add_action('wp_enqueue_scripts', [$this, 'enqueueCoreCss']);

        // Admin'de CSS yükle
        add_action('admin_enqueue_scripts', [$this, 'enqueueCoreCss']);
    }

    /**
     * Core CSS dosyasını yükle - AssetManager ile uyumlu
     */
    public function enqueueCoreCss()
    {
        // Eğer AssetManager zaten yüklendiyse, tekrar yükleme
        if (wp_style_is('mhm-core-css', 'enqueued') || wp_style_is('mhm-core-css', 'done')) {
            return;
        }

        $css_path = $this->plugin_dir . 'assets/css/core/core.css';
        $css_url = $this->plugin_url . 'assets/css/core/core.css';

        // AssetManager ile aynı versiyonlama sistemi
        $version = defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : '1.0.0';

        // CSS'i enqueue et - AssetManager dependency sırasına uygun
        wp_enqueue_style(
            self::CSS_HANDLE,
            $css_url,
            [], // AssetManager'da dependency yönetimi yapılıyor
            $version,
            'all'
        );

        // CSS Variables'ı da yükle
        $css_vars_url = $this->plugin_url . 'assets/css/core/css-variables.css';
        wp_enqueue_style(
            'mhm-css-variables',
            $css_vars_url,
            [],
            $version,
            'all'
        );

        // Animations'ı da yükle
        $animations_url = $this->plugin_url . 'assets/css/core/animations.css';
        wp_enqueue_style(
            'mhm-animations',
            $animations_url,
            ['mhm-css-variables'],
            $version,
            'all'
        );
    }

    /**
     * CSS handle adını döndür
     */
    public static function getCssHandle()
    {
        return self::CSS_HANDLE;
    }
}
