<?php

/**
 * Transfer Export/Import Integration
 *
 * Integrates Transfer Locations, Routes and Waypoints into WordPress's native
 * export/import system. Adds a "Transfer Verileri" option to the Tools > Export page.
 *
 * @package MHMRentiva\Admin\Transfer
 * @since 4.9.8
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TransferExportImport
 *
 * Handles integration with WordPress export/import system for Transfer data.
 */
final class TransferExportImport
{

    /**
     * @var self|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Custom content type identifier
     */
    private const CONTENT_TYPE = 'mhm_rentiva_transfer';

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Add "Transfer Verileri" radio button to export page
        add_action('export_filters', [$this, 'render_export_filter']);

        // Handle export: when "Transfer Verileri" is selected
        add_action('export_wp', [$this, 'handle_export'], 10, 1);

        // Add Transfer data to any WP export via rss2_head
        add_action('rss2_head', [$this, 'export_transfer_data']);

        // Import Transfer data from XML
        add_action('import_end', [$this, 'import_transfer_data']);
    }

    // ─── TABLE RESOLUTION ────────────────────────────────────────────

    /**
     * Resolve an active table name, checking new naming first then legacy.
     *
     * @param string $new_suffix    New naming convention suffix (e.g. rentiva_transfer_locations)
     * @param string $legacy_suffix Legacy naming convention suffix (e.g. mhm_rentiva_transfer_locations)
     * @return string|null Full table name or null if neither exists
     */
    private function resolve_table(string $new_suffix, string $legacy_suffix): ?string
    {
        global $wpdb;

        $new_table = $wpdb->prefix . $new_suffix;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));

        if ($exists === $new_table) {
            return $new_table;
        }

        $legacy_table = $wpdb->prefix . $legacy_suffix;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));

        return ($exists === $legacy_table) ? $legacy_table : null;
    }

    /**
     * Get all three Transfer table names.
     *
     * @return array{locations: string|null, routes: string|null, waypoints: string|null}
     */
    private function get_tables(): array
    {
        return [
            'locations'  => $this->resolve_table('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations'),
            'routes'     => $this->resolve_table('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes'),
            'waypoints'  => $this->resolve_table('rentiva_transfer_waypoints', 'mhm_rentiva_transfer_waypoints'),
        ];
    }

    // ─── EXPORT FILTER (RADIO BUTTON) ────────────────────────────────

    /**
     * Render "Transfer Verileri" radio button on WordPress Export page.
     *
     * @since 4.9.8
     * @return void
     */
    public function render_export_filter(): void
    {
        global $wpdb;

        $tables  = $this->get_tables();
        $loc_count   = 0;
        $route_count = 0;
        $wp_count    = 0;

        if ($tables['locations'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $loc_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['locations']}");
        }
        if ($tables['routes'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $route_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['routes']}");
        }
        if ($tables['waypoints'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wp_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['waypoints']}");
        }

?>
        <p>
            <label>
                <input type="radio" name="content" value="<?php echo esc_attr(self::CONTENT_TYPE); ?>" />
                <?php esc_html_e('Transfer Verileri', 'mhm-rentiva'); ?>
            </label>
        </p>
        <ul id="mhm-transfer-export-filters" class="export-filters" style="margin-left:18px;">
            <li style="color:#666;">
                <?php
                printf(
                    /* translators: %1$d: location count, %2$d: route count, %3$d: waypoint count */
                    esc_html__('%1$d Lokasyon, %2$d Rota, %3$d Waypoint', 'mhm-rentiva'),
                    $loc_count,
                    $route_count,
                    $wp_count
                );
                ?>
            </li>
        </ul>
<?php
    }

    // ─── CUSTOM EXPORT HANDLER ───────────────────────────────────────

    /**
     * Handle export when user selects "Transfer Verileri"
     *
     * Intercepts the export request and generates a custom SQL file download.
     *
     * @since 4.9.8
     * @param array $args Export arguments from WordPress
     * @return void
     */
    public function handle_export(array $args): void
    {
        // Only act when our content type is selected
        if (!isset($args['content']) || $args['content'] !== self::CONTENT_TYPE) {
            return;
        }

        if (!current_user_can('export')) {
            wp_die(esc_html__('Yetkiniz yok.', 'mhm-rentiva'));
        }

        global $wpdb;

        $tables = $this->get_tables();
        $sql    = $this->build_export_sql($tables);

        // Send as downloadable SQL file
        $filename = 'transfer-data-' . gmdate('Y-m-d') . '.sql';

        // phpcs:ignore WordPress.Security.Headers
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Type: application/sql; charset=utf-8');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SQL export file content
        echo $sql;
        exit;
    }

    /**
     * Build SQL export string for all Transfer tables.
     *
     * @param array $tables Resolved table names
     * @return string SQL content
     */
    private function build_export_sql(array $tables): string
    {
        global $wpdb;

        $sql  = "-- MHM Rentiva Transfer Data Export\n";
        $sql .= '-- Generated: ' . gmdate('Y-m-d H:i:s') . "\n";
        $sql .= '-- Site: ' . esc_url(get_site_url()) . "\n\n";

        // ── Locations ──────────────────────────────────────
        if ($tables['locations'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $locations = $wpdb->get_results("SELECT * FROM {$tables['locations']} ORDER BY id", ARRAY_A);

            if (!empty($locations)) {
                $sql .= "-- Transfer Locations\n";
                $sql .= '-- Total: ' . count($locations) . " locations\n\n";

                foreach ($locations as $row) {
                    $sql .= $this->build_insert($tables['locations'], $row) . "\n";
                }
                $sql .= "\n";
            }
        }

        // ── Routes ─────────────────────────────────────────
        if ($tables['routes'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $routes = $wpdb->get_results("SELECT * FROM {$tables['routes']} ORDER BY id", ARRAY_A);

            if (!empty($routes)) {
                $sql .= "-- Transfer Routes\n";
                $sql .= '-- Total: ' . count($routes) . " routes\n\n";

                foreach ($routes as $row) {
                    $sql .= $this->build_insert($tables['routes'], $row) . "\n";
                }
                $sql .= "\n";
            }
        }

        // ── Waypoints ──────────────────────────────────────
        if ($tables['waypoints'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $waypoints = $wpdb->get_results("SELECT * FROM {$tables['waypoints']} ORDER BY id", ARRAY_A);

            if (!empty($waypoints)) {
                $sql .= "-- Transfer Waypoints\n";
                $sql .= '-- Total: ' . count($waypoints) . " waypoints\n\n";

                foreach ($waypoints as $row) {
                    $sql .= $this->build_insert($tables['waypoints'], $row) . "\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "-- Export Complete\n";

        return $sql;
    }

    /**
     * Build a single INSERT INTO statement for a row.
     *
     * Uses the generic prefix `wp_` so the file is portable across sites.
     *
     * @param string $table Full table name
     * @param array  $row   Associative row data
     * @return string SQL INSERT statement
     */
    private function build_insert(string $table, array $row): string
    {
        global $wpdb;

        // Replace site-specific prefix with generic wp_ for portability
        $portable_table = str_replace($wpdb->prefix, 'wp_', $table);

        $columns = implode('`, `', array_keys($row));
        $values  = implode(', ', array_map(function ($value) {
            if ($value === null) {
                return 'NULL';
            }
            return "'" . esc_sql($value) . "'";
        }, array_values($row)));

        return "INSERT INTO `{$portable_table}` (`{$columns}`) VALUES ({$values});";
    }

    // ─── RSS2 HEAD EXPORT (for "All Content" exports) ────────────────

    /**
     * Export Transfer data as custom XML in WordPress export file.
     *
     * This fires during standard "All Content" WordPress exports
     * so Transfer data is included in the XML alongside posts/pages.
     *
     * @return void
     */
    public function export_transfer_data(): void
    {
        // Only run during export
        if (!isset($_GET['download']) || !current_user_can('export')) {
            return;
        }

        global $wpdb;

        $tables = $this->get_tables();

        echo "\n<!-- MHM Rentiva Transfer Data -->\n";
        echo "<mhm_rentiva_transfer>\n";

        // Export Locations
        if ($tables['locations'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $locations = $wpdb->get_results("SELECT * FROM {$tables['locations']}", ARRAY_A);

            if (!empty($locations)) {
                echo "\t<transfer_locations>\n";
                foreach ($locations as $location) {
                    echo "\t\t<location>\n";
                    foreach ($location as $key => $value) {
                        $safe_value = esc_xml($value ?? '');
                        echo "\t\t\t<{$key}><![CDATA[{$safe_value}]]></{$key}>\n";
                    }
                    echo "\t\t</location>\n";
                }
                echo "\t</transfer_locations>\n";
            }
        }

        // Export Routes
        if ($tables['routes'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $routes = $wpdb->get_results("SELECT * FROM {$tables['routes']}", ARRAY_A);

            if (!empty($routes)) {
                echo "\t<transfer_routes>\n";
                foreach ($routes as $route) {
                    echo "\t\t<route>\n";
                    foreach ($route as $key => $value) {
                        $safe_value = esc_xml($value ?? '');
                        echo "\t\t\t<{$key}><![CDATA[{$safe_value}]]></{$key}>\n";
                    }
                    echo "\t\t</route>\n";
                }
                echo "\t</transfer_routes>\n";
            }
        }

        // Export Waypoints
        if ($tables['waypoints'] !== null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $waypoints = $wpdb->get_results("SELECT * FROM {$tables['waypoints']}", ARRAY_A);

            if (!empty($waypoints)) {
                echo "\t<transfer_waypoints>\n";
                foreach ($waypoints as $waypoint) {
                    echo "\t\t<waypoint>\n";
                    foreach ($waypoint as $key => $value) {
                        $safe_value = esc_xml($value ?? '');
                        echo "\t\t\t<{$key}><![CDATA[{$safe_value}]]></{$key}>\n";
                    }
                    echo "\t\t</waypoint>\n";
                }
                echo "\t</transfer_waypoints>\n";
            }
        }

        echo "</mhm_rentiva_transfer>\n";
        echo "<!-- End MHM Rentiva Transfer Data -->\n\n";
    }

    // ─── IMPORT ──────────────────────────────────────────────────────

    /**
     * Import Transfer data from WordPress import file
     *
     * This hook fires after WordPress import completes.
     *
     * @return void
     */
    public function import_transfer_data(): void
    {
        if (!current_user_can('import')) {
            return;
        }

        add_action('admin_notices', function (): void {
            echo '<div class="notice notice-info">';
            echo '<p><strong>MHM Rentiva:</strong> ';
            echo esc_html__('Transfer verisi tespit edildi. Araçlar → Transfer İçe Aktar ile aktarımı tamamlayın.', 'mhm-rentiva');
            echo '</p></div>';
        });
    }

    /**
     * Parse and import Transfer data from XML content.
     *
     * @param string $xml_content XML content from import file
     * @return bool Success status
     */
    public function parse_and_import_xml(string $xml_content): bool
    {
        global $wpdb;

        $xml = simplexml_load_string($xml_content);

        if ($xml === false) {
            return false;
        }

        if (!isset($xml->mhm_rentiva_transfer)) {
            return false;
        }

        $transfer_data = $xml->mhm_rentiva_transfer;
        $tables        = $this->get_tables();

        // Import Locations (must be first for referential integrity)
        if (isset($transfer_data->transfer_locations->location) && $tables['locations'] !== null) {
            foreach ($transfer_data->transfer_locations->location as $location) {
                $loc_data = [];
                foreach ($location->children() as $field) {
                    $loc_data[$field->getName()] = (string) $field;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert($tables['locations'], $loc_data);
            }
        }

        // Import Routes
        if (isset($transfer_data->transfer_routes->route) && $tables['routes'] !== null) {
            foreach ($transfer_data->transfer_routes->route as $route) {
                $route_data = [];
                foreach ($route->children() as $field) {
                    $route_data[$field->getName()] = (string) $field;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert($tables['routes'], $route_data);
            }
        }

        // Import Waypoints
        if (isset($transfer_data->transfer_waypoints->waypoint) && $tables['waypoints'] !== null) {
            foreach ($transfer_data->transfer_waypoints->waypoint as $waypoint) {
                $wp_data = [];
                foreach ($waypoint->children() as $field) {
                    $wp_data[$field->getName()] = (string) $field;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert($tables['waypoints'], $wp_data);
            }
        }

        return true;
    }
}

// Initialize
TransferExportImport::instance();
