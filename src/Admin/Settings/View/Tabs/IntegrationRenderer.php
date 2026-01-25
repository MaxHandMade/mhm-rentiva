<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;
use MHMRentiva\Admin\REST\Settings\RESTSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderer for the Integration Settings tab
 * 
 * Manages external API connectivity, key generation, and endpoint security.
 * Ported and refactored from the legacy view system for unified aesthetics.
 */
final class IntegrationRenderer extends AbstractTabRenderer
{
    public function __construct()
    {
        parent::__construct(
            __('Integration Settings', 'mhm-rentiva'),
            'integration'
        );
    }

    /**
     * @inheritDoc
     */
    public function render(): void
    {
?>
        <div class="mhm-settings-tab-header">
            <div class="mhm-settings-title-group">
                <h2><?php echo esc_html($this->label); ?></h2>
                <p class="description"><?php esc_html_e('Manage secure communication channels for mobile applications and third-party services.', 'mhm-rentiva'); ?></p>
            </div>

            <div class="mhm-settings-header-actions">
                <a href="https://maxhandmade.github.io/mhm-rentiva-docs/" target="_blank" class="button button-secondary mhm-docs-btn">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php esc_html_e('Documentation', 'mhm-rentiva'); ?>
                </a>

                <button type="button" class="button button-secondary mhm-reset-tab-settings" data-tab="integration">
                    <span class="dashicons dashicons-undo"></span>
                    <?php esc_html_e('Factory Reset API', 'mhm-rentiva'); ?>
                </button>
            </div>
        </div>
        <hr class="wp-header-end">

        <div class="mhm-integration-page-content" style="margin-top: 25px;">
            <form method="post" action="options.php" class="mhm-settings-form mhm-integration-form" id="mhm-rest-settings-form">
                <?php
                if (class_exists(RESTSettings::class)) {
                    // WordPress Settings API
                    settings_fields(RESTSettings::OPTION_NAME);

                    // Track active tab
                    echo '<input type="hidden" name="current_active_tab" value="integration">';

                    // API Limit & Rules
                    RESTSettings::render_settings_section();

                    // Detailed management sections
                    $this->render_api_keys_section();
                    $this->render_endpoints_section();
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('REST Integration core service is missing.', 'mhm-rentiva') . '</p></div>';
                }
                ?>

                <div class="submit-section" style="margin-top: 30px;">
                    <?php submit_button(__('Commit API Configuration', 'mhm-rentiva'), 'primary', 'submit', true, ['id' => 'mhm-save-integration-btn']); ?>
                </div>
            </form>
        </div>
    <?php
    }

    /**
     * Renders the Key Management UI
     */
    private function render_api_keys_section(): void
    {
    ?>
        <div class="mhm-integration-section mhm-api-keys-wrapper" style="margin-top: 50px; border-top: 1px solid #eee; padding-top: 30px;">
            <h3><?php esc_html_e('Secure API Access Tokens', 'mhm-rentiva'); ?></h3>
            <p class="description"><?php esc_html_e('Issue and revoke tokens to allow your custom apps to interact with rental data.', 'mhm-rentiva'); ?></p>

            <div id="mhm-api-keys-list-container" class="mhm-dynamic-container" style="margin: 20px 0;">
                <div id="mhm-api-keys-list">
                    <div class="mhm-placeholder-spinner" style="text-align: center; padding: 30px;">
                        <span class="dashicons dashicons-update spin"></span>
                        <p><?php esc_html_e('Synchronizing access tokens...', 'mhm-rentiva'); ?></p>
                    </div>
                </div>
            </div>

            <div class="mhm-creator-box" style="background: #fcfcfc; padding: 25px; border: 1px solid #dfdfdf; border-radius: 6px;">
                <h4 style="margin-top: 0;"><?php esc_html_e('Issue New Credentials', 'mhm-rentiva'); ?></h4>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="new_key_name"><?php esc_html_e('Client Identity', 'mhm-rentiva'); ?></label></th>
                        <td>
                            <input type="text" id="new_key_name" placeholder="<?php esc_attr_e('e.g. Android App / External Portal', 'mhm-rentiva'); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Authorization Level', 'mhm-rentiva'); ?></th>
                        <td>
                            <label><input type="checkbox" name="new_key_permissions[]" value="read" checked disabled> <span class="badge">READ</span> <?php esc_html_e('Access public/private data', 'mhm-rentiva'); ?></label><br>
                            <label><input type="checkbox" name="new_key_permissions[]" value="write"> <span class="badge">WRITE</span> <?php esc_html_e('Modify resources', 'mhm-rentiva'); ?></label><br>
                            <label><input type="checkbox" name="new_key_permissions[]" value="admin"> <span class="badge badge-critical">ADMIN</span> <?php esc_html_e('Full system control', 'mhm-rentiva'); ?></label>
                        </td>
                    </tr>
                </table>
                <div class="mhm-action-bar" style="margin-top: 15px;">
                    <button type="button" id="mhm-create-api-key-btn" class="button button-primary button-large">
                        <span class="dashicons dashicons-key"></span> <?php esc_html_e('Generate Access Key', 'mhm-rentiva'); ?>
                    </button>
                    <button type="button" id="mhm-refresh-keys-btn" class="button button-large">
                        <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh Data', 'mhm-rentiva'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Renders available endpoints for developer reference
     */
    private function render_endpoints_section(): void
    {
    ?>
        <div class="mhm-integration-section mhm-endpoints-wrapper" style="margin-top: 50px; border-top: 1px solid #eee; padding-top: 30px;">
            <h3><?php esc_html_e('Developer Endpoint Reference', 'mhm-rentiva'); ?></h3>
            <p class="description"><?php esc_html_e('Direct access URLs for custom integrations and headless implementations.', 'mhm-rentiva'); ?></p>

            <div id="mhm-endpoints-list-container" class="mhm-dynamic-container" style="margin-top: 20px;">
                <div id="mhm-endpoints-list">
                    <button type="button" id="mhm-refresh-endpoints-btn" class="button button-secondary">
                        <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Reveal API Directory', 'mhm-rentiva'); ?>
                    </button>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * This tab manages its own form logic for REST settings
     */
    public function should_wrap_with_form(): bool
    {
        return false;
    }
}
