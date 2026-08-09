<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The storage type behind the contact form.
 *
 * `ContactForm::save_contact_message()` has written submissions into
 * `wp_posts` under this type since the shortcode existed, but nothing ever
 * called `register_post_type()` for it. An unregistered type still stores
 * rows perfectly well -- and that was the problem: each row holds a name,
 * e-mail address, phone number, company, message body, IP address and
 * user-agent, and WordPress will not list, search, open or delete rows of a
 * type it does not know about. The site owner had no way to read or erase
 * personal data the plugin had collected on their behalf, and readme.txt
 * described those records as if they could be managed.
 *
 * Registering it gives WordPress back the list table, the row actions and
 * Trash. It stays out of the front end (`public => false`, no rewrite, no
 * REST) because a contact message is never a URL; it is admin-only data.
 *
 * Capabilities map onto `post`, so only users who can already edit and
 * delete others' content reach it -- on a default install, administrators
 * and editors. Creating one by hand makes no sense (the form is the only
 * author), so `create_posts` is mapped to a capability no role holds.
 */
final class ContactMessagePostType {

    public const TYPE = 'mhmrentiva_contact';

    public static function register(): void
    {
        add_action('init', array( self::class, 'cpt' ));
    }

    public static function cpt(): void
    {
        $labels = array(
            'name'               => __('Contact Messages', 'mhm-rentiva'),
            'singular_name'      => __('Contact Message', 'mhm-rentiva'),
            'menu_name'          => __('Contact Messages', 'mhm-rentiva'),
            'edit_item'          => __('Contact Message', 'mhm-rentiva'),
            'view_item'          => __('View Contact Message', 'mhm-rentiva'),
            'search_items'       => __('Search Contact Messages', 'mhm-rentiva'),
            'not_found'          => __('No contact messages found.', 'mhm-rentiva'),
            'not_found_in_trash' => __('No contact messages found in Trash.', 'mhm-rentiva'),
            'all_items'          => __('Contact Messages', 'mhm-rentiva'),
        );

        register_post_type(
            self::TYPE,
            array(
                'labels'          => $labels,
                'public'          => false,
                'show_ui'         => true,
                // The submenu entry is added by Menu.php alongside the plugin's
                // other screens, so this must not also place one of its own.
                'show_in_menu'    => false,
                'supports'        => array( 'title', 'editor' ),
                'capability_type' => 'post',
                'capabilities'    => array(
                    // Submissions come from the form only; nothing should offer
                    // an "Add New" screen. `do_not_allow` is the capability
                    // WordPress itself uses to close a door for every role.
                    'create_posts' => 'do_not_allow',
                ),
                'map_meta_cap'    => true,
                'has_archive'     => false,
                'rewrite'         => false,
                'query_var'       => false,
                'show_in_rest'    => false,
                'menu_icon'       => 'dashicons-email-alt',
            )
        );
    }
}
