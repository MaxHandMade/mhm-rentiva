<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Admin\Transfer\Frontend\TransferResults;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transfer Results Elementor Widget
 */
class TransferResultsWidget extends ElementorWidgetBase
{

    public function get_name(): string
    {
        return 'mhm_rentiva_transfer_results';
    }

    public function get_title(): string
    {
        return __('MHM Transfer Results', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content Settings', 'mhm-rentiva'),
        ]);

        $this->add_control('show_price', [
            'label'     => __('Show Price', 'mhm-rentiva'),
            'type'      => 'switcher',
            'default'   => 'yes',
            'label_on'  => __('Show', 'mhm-rentiva'),
            'label_off' => __('Hide', 'mhm-rentiva'),
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $atts = $this->get_prepared_atts();
        $data = TransferResults::get_data($atts);

        if (!empty($data)) {
            extract($data);
            include MHM_RENTIVA_PLUGIN_DIR . 'templates/shortcodes/transfer-results.php';
        }
    }
}
