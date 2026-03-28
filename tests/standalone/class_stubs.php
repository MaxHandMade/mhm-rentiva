<?php

/**
 * Class stubs for dependent classes used by MessagesSettings.
 *
 * @package MHMRentiva\Tests
 */

namespace MHMRentiva\Admin\Core\Utilities;

if (!class_exists('MHMRentiva\Admin\Core\Utilities\SettingsHelper')) {
    class SettingsHelper
    {
        public static function add_settings_error($code, $message, $type = 'error') {}
    }
}

if (!class_exists('MHMRentiva\Admin\Core\Utilities\ErrorHandler')) {
    class ErrorHandler
    {
        public static function log($message, $context = []) {}
    }
}

namespace MHMRentiva\Admin\Messages\Core;

if (!class_exists('MHMRentiva\Admin\Messages\Core\MessageUrlHelper')) {
    class MessageUrlHelper
    {
        public static function get_message_list_url()
        {
            return '';
        }
    }
}
