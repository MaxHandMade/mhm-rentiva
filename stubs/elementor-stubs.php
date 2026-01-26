<?php

/**
 * Elementor Stubs for IDE support
 * 
 * This file provides class and method declarations for Elementor core classes
 * that are used in MHM Rentiva widgets. This helps IDEs like Intelephense
 * to recognize these symbols without needing the full Elementor source code.
 */

namespace Elementor {
    abstract class Widget_Base
    {
        public function start_controls_section(string $section_id, array $args = []): void {}
        public function add_control(string $id, array $args = [], array $options = []): bool
        {
            return true;
        }
        public function end_controls_section(): void {}
        public function get_settings_for_display(?string $setting_key = null): array
        {
            return [];
        }
        public function add_group_control(string $type, array $args = [], array $options = []): void {}
    }

    class Controls_Manager
    {
        const TAB_CONTENT = 'content';
        const TAB_STYLE = 'style';
        const TAB_ADVANCED = 'advanced';
        const TEXT = 'text';
        const SELECT = 'select';
        const SELECT2 = 'select2';
        const NUMBER = 'number';
        const SWITCHER = 'switcher';
        const HEADING = 'heading';
    }

    class Group_Control_Typography
    {
        public static function get_type()
        {
            return 'typography';
        }
    }

    class Group_Control_Border
    {
        public static function get_type()
        {
            return 'border';
        }
    }

    class Group_Control_Box_Shadow
    {
        public static function get_type()
        {
            return 'box-shadow';
        }
    }

    class Group_Control_Background
    {
        public static function get_type()
        {
            return 'background';
        }
    }

    class Plugin
    {
        /** @var self */
        public static $instance;
        public static function instance()
        {
            return self::$instance;
        }
        /** @var \Elementor\Widgets_Manager */
        public $widgets_manager;
        /** @var \Elementor\Elements_Manager */
        public $elements_manager;
    }

    class Widgets_Manager
    {
        public function register($widget_instance) {}
    }

    class Elements_Manager
    {
        public function add_category($category_name, array $args, $position = null) {}
    }
}
