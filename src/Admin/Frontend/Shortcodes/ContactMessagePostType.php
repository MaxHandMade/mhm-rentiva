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
 * The type carries its own capability names, every one mapped to
 * `manage_options`. Inheriting `post` would have handed editors -- and,
 * because WooCommerce is a hard dependency, shop managers -- the list and
 * this panel by inheritance, and a submenu capability would not have stopped
 * them: `add_submenu_page()` does not register an entry for a user who lacks
 * its capability, so `user_can_access_admin_page()` resolves no parent, lets
 * the request through, and `edit.php` then gates on the POST TYPE's
 * `edit_posts`. Putting the gate on the type closes the list screen,
 * `post.php`'s `edit_post`, the private-status read and deletion together.
 * Creating one by hand makes no sense (the form is the only author), so
 * `create_posts` is mapped to a capability no role holds.
 */
final class ContactMessagePostType {

    public const TYPE = 'mhmrentiva_contact';

    public const TYPES      = array( 'general', 'booking', 'support', 'feedback' );
    public const PRIORITIES = array( 'low', 'medium', 'high' );

    /**
     * Human label of an enquiry type. Unknown stored values (the form did not
     * allowlist the type before 4.4.0) read as the general form's label.
     */
    public static function type_label(string $type): string
    {
        switch ($type) {
            case 'booking':
                return __('Booking Inquiry', 'mhm-rentiva');
            case 'support':
                return __('Technical Support', 'mhm-rentiva');
            case 'feedback':
                return __('Feedback', 'mhm-rentiva');
            default:
                return __('General Contact', 'mhm-rentiva');
        }
    }

    /** Translated priority label; '' for anything outside PRIORITIES. Same strings as ContactForm::get_priority_options(). */
    public static function priority_label(string $priority): string
    {
        switch ($priority) {
            case 'low':
                return __('Low', 'mhm-rentiva');
            case 'medium':
                return __('Medium', 'mhm-rentiva');
            case 'high':
                return __('High', 'mhm-rentiva');
            default:
                return '';
        }
    }

    /** Detail screen of one message in the React admin page. */
    public static function admin_detail_url(int $id): string
    {
        return admin_url('admin.php?page=mhm-rentiva-contact-messages&id=' . $id);
    }

    public static function register(): void
    {
        add_action('init', array( self::class, 'cpt' ));
        // Registered here, not with the admin page: a permanent delete through
        // REST is not an is_admin() request, and it must still take the file.
        add_action('before_delete_post', array( \MHMRentiva\Admin\ContactMessages\ContactAttachmentStore::class, 'on_before_delete_post' ), 10, 1);
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
                // The gate lives HERE, not on the menu. A submenu capability
                // does not protect a screen: `add_submenu_page()` simply does
                // not register the entry for a user who lacks it, and
                // `user_can_access_admin_page()` then resolves no parent and
                // lets the request through to `edit.php`, which gates on the
                // POST TYPE's `edit_posts`. Inheriting `post` therefore handed
                // every editor -- and, since WooCommerce is a hard dependency,
                // every shop manager -- the list and the detail panel, with
                // the sender's e-mail address and IP in them. Its own
                // capability names, all mapped to `manage_options`, close
                // every door at once: the list screen, `post.php`'s
                // `edit_post`, the private-status read and deletion.
                'capability_type' => array( 'mhmrentiva_contact', 'mhmrentiva_contacts' ),
                'capabilities'    => array(
                    'edit_posts'             => 'manage_options',
                    'edit_others_posts'      => 'manage_options',
                    'edit_private_posts'     => 'manage_options',
                    'edit_published_posts'   => 'manage_options',
                    'read_private_posts'     => 'manage_options',
                    'delete_posts'           => 'manage_options',
                    'delete_others_posts'    => 'manage_options',
                    'delete_private_posts'   => 'manage_options',
                    'delete_published_posts' => 'manage_options',
                    'publish_posts'          => 'manage_options',
                    // Submissions come from the form only; nothing should offer
                    // an "Add New" screen. `do_not_allow` is the capability
                    // WordPress itself uses to close a door for every role.
                    'create_posts'           => 'do_not_allow',
                ),
                'map_meta_cap'    => true,
                'has_archive'     => false,
                'rewrite'         => false,
                'query_var'       => false,
                'show_in_rest'    => false,
                // Tools -> Export would walk straight past every capability
                // above: wp-admin/export.php gates on the `export` capability
                // alone, export_wp() contains no capability check of its own,
                // and a shop manager holds `export`. Without this, one click
                // hands them a WXR file with each sender's e-mail address,
                // phone number, IP and user-agent in it.
                'can_export'      => false,
                'menu_icon'       => 'dashicons-email-alt',
            )
        );
    }
}
