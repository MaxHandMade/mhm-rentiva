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
 * Capabilities map onto `post`, which by inheritance would let an editor --
 * and, since WooCommerce is a hard dependency, a shop manager -- read these
 * records. The screen is therefore reached through a `manage_options`
 * submenu (see Menu.php), the same bar as every other screen here, so this
 * release does not quietly widen who can read submitter PII. Creating one by
 * hand makes no sense (the form is the only author), so `create_posts` is
 * mapped to a capability no role holds.
 */
final class ContactMessagePostType {

    public const TYPE = 'mhmrentiva_contact';

    /**
     * The stored fields, in reading order: full meta key => label.
     *
     * @return array<string, string>
     */
    private static function fields(): array
    {
        return array(
            '_mhmrentiva_contact_name'           => __('Name', 'mhm-rentiva'),
            '_mhmrentiva_contact_email'          => __('Email', 'mhm-rentiva'),
            '_mhmrentiva_contact_phone'          => __('Phone', 'mhm-rentiva'),
            '_mhmrentiva_contact_company'        => __('Company', 'mhm-rentiva'),
            '_mhmrentiva_contact_type'           => __('Enquiry type', 'mhm-rentiva'),
            '_mhmrentiva_contact_vehicle_id'     => __('Vehicle', 'mhm-rentiva'),
            '_mhmrentiva_contact_preferred_date' => __('Preferred date', 'mhm-rentiva'),
            '_mhmrentiva_contact_priority'       => __('Priority', 'mhm-rentiva'),
            '_mhmrentiva_contact_rating'         => __('Rating', 'mhm-rentiva'),
            '_mhmrentiva_contact_attachment'     => __('Attachment', 'mhm-rentiva'),
            '_mhmrentiva_contact_ip_address'     => __('IP address', 'mhm-rentiva'),
            '_mhmrentiva_contact_user_agent'     => __('Browser user-agent', 'mhm-rentiva'),
            '_mhmrentiva_contact_timestamp'      => __('Submitted', 'mhm-rentiva'),
        );
    }

    public static function register(): void
    {
        add_action('init', array( self::class, 'cpt' ));
        add_action('add_meta_boxes_' . self::TYPE, array( self::class, 'add_details_box' ));
        add_filter('manage_' . self::TYPE . '_posts_columns', array( self::class, 'columns' ));
        add_action('manage_' . self::TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2);
    }

    /**
     * Read-only panel listing everything the submission stored.
     *
     * The form writes fourteen values; the post itself carries only two of
     * them (the sender's name inside the title, and the message body). The
     * rest live in underscore-prefixed meta, which `is_protected_meta()` hides
     * from the Custom Fields box -- so without this panel the site owner can
     * delete a record but cannot read the e-mail address, phone number or IP
     * it holds, which is most of what a subject-access or erasure request is
     * about.
     */
    public static function add_details_box(): void
    {
        add_meta_box(
            'mhmrentiva_contact_details',
            __('Submitted details', 'mhm-rentiva'),
            array( self::class, 'render_details_box' ),
            self::TYPE,
            'normal',
            'high'
        );
    }

    public static function render_details_box(\WP_Post $post): void
    {
        echo '<table class="widefat striped"><tbody>';

        foreach (self::fields() as $meta_key => $label) {
            $value = get_post_meta($post->ID, $meta_key, true);
            if (! is_scalar($value) || '' === (string) $value) {
                continue;
            }

            $value = (string) $value;

            echo '<tr><th scope="row" style="width:14em">' . esc_html($label) . '</th><td>';

            if ('_mhmrentiva_contact_email' === $meta_key && is_email($value)) {
                printf('<a href="%s">%s</a>', esc_url('mailto:' . $value), esc_html($value));
            } elseif ('_mhmrentiva_contact_attachment' === $meta_key) {
                printf('<a href="%s" rel="noopener">%s</a>', esc_url($value), esc_html($value));
            } elseif ('_mhmrentiva_contact_vehicle_id' === $meta_key) {
                $title = get_the_title( (int) $value);
                echo esc_html('' !== $title ? $title : $value);
            } else {
                echo esc_html($value);
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function columns(array $columns): array
    {
        $date = $columns['date'] ?? '';
        unset($columns['date']);

        $columns['mhmrentiva_email'] = __('Email', 'mhm-rentiva');
        $columns['mhmrentiva_type']  = __('Enquiry type', 'mhm-rentiva');

        if ('' !== $date) {
            $columns['date'] = $date;
        }

        return $columns;
    }

    public static function column(string $column, int $post_id): void
    {
        $meta_key = array(
            'mhmrentiva_email' => '_mhmrentiva_contact_email',
            'mhmrentiva_type'  => '_mhmrentiva_contact_type',
        )[ $column ] ?? '';

        if ('' === $meta_key) {
            return;
        }

        $value = get_post_meta($post_id, $meta_key, true);
        echo is_scalar($value) ? esc_html( (string) $value) : '';
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
