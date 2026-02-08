<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Admin\Frontend\Shortcodes\Account\AccountMessages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Messages Elementor Widget
 */
class MyMessagesWidget extends ElementorWidgetBase
{

    public function get_name(): string
    {
        return 'mhm_rentiva_messages';
    }

    public function get_title(): string
    {
        return __('MHM My Messages', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-mail';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content Settings', 'mhm-rentiva'),
        ]);

        $this->add_control('hide_nav', [
            'label'     => __('Hide Navigation', 'mhm-rentiva'),
            'type'      => 'switcher',
            'default'   => '',
            'label_on'  => __('Yes', 'mhm-rentiva'),
            'label_off' => __('No', 'mhm-rentiva'),
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $atts = $this->get_prepared_atts();
        $data = AccountMessages::get_data($atts);

        if (!empty($data)) {
            extract($data);
            include MHM_RENTIVA_PLUGIN_DIR . 'templates/account/messages.php';
        }
    }
}
