<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Helper Trait
 *
 * Centralizes repeated code in admin pages
 */
trait AdminHelperTrait {



	/**
	 * Admin capability check
	 *
	 * @param string $capability Required capability
	 * @return bool Capability status
	 */
	protected function check_admin_capability( string $capability = 'manage_options' ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Admin capability check and access blocking
	 *
	 * @param string $capability Required capability
	 * @throws \Exception Throws exception if no permission
	 */
	protected function require_admin_capability( string $capability = 'manage_options' ): void {
		if ( ! current_user_can( $capability ) ) {
			throw new \Exception( esc_html__( 'You do not have permission to access this page.', 'mhm-rentiva' ) );
		}
	}

	/**
	 * Start admin page wrapper
	 *
	 * @param string $title Page title
	 * @param string $class CSS class
	 */
	protected function start_admin_wrapper( string $title, string $class = 'mhm-rentiva-wrap' ): void {
		echo '<div class="wrap ' . esc_attr( $class ) . '">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
	}

	/**
	 * End admin page wrapper
	 */
	protected function end_admin_wrapper(): void {
		echo '</div>';
	}

	/**
	 * Renders a standardized admin page header with a title and action buttons.
	 *
	 * @param string $title    The page title to display in H1.
	 * @param array  $buttons  Optional array of buttons to display on the right.
	 *                         Format: [['text' => 'Label', 'url' => 'URL', 'class' => 'class', 'icon' => 'dashicons-xxx', 'id' => 'id', 'target' => '_blank', 'data' => []]]
	 * @param bool   $echo     Whether to echo the output or return it.
	 * @param string $subtitle Optional subtitle to display next to the main title.
	 * @return string The generated HTML.
	 */
	protected function render_admin_header( string $title, array $buttons = array(), bool $echo = true, string $subtitle = '' ): string {
		$allowed_tags = array(
			'span'   => array(
				'class' => array(),
			),
			'strong' => array(),
			'em'     => array(),
		);

		$html  = '<div class="mhm-admin-header">';
		$html .= '<h1>' . wp_kses( $title, $allowed_tags );
		if ( ! empty( $subtitle ) ) {
			$html .= ' <span class="mhm-subtitle">' . esc_html( $subtitle ) . '</span>';
		}
		$html .= '</h1>';

		if ( ! empty( $buttons ) ) {
			$docs_btns  = array();
			$reset_btns = array();
			$other_btns = array();

			foreach ( $buttons as $btn ) {
				// Detect type if not set
				if ( ! isset( $btn['type'] ) ) {
					if ( stripos( $btn['text'] ?? '', 'documentation' ) !== false || stripos( $btn['text'] ?? '', 'belgeler' ) !== false ) {
						$btn['type'] = 'documentation';
					} elseif ( stripos( $btn['text'] ?? '', 'reset' ) !== false || stripos( $btn['text'] ?? '', 'varsayılan' ) !== false ) {
						$btn['type'] = 'reset';
					}
				}

				// Standardize based on type
				if ( isset( $btn['type'] ) ) {
					switch ( $btn['type'] ) {
						case 'documentation':
							$btn['text']   = __( 'Documentation', 'mhm-rentiva' );
							$btn['icon']   = 'dashicons-book-alt';
							$btn['class']  = 'button button-secondary';
							$btn['target'] = '_blank';
							$docs_btns[]   = $btn;
							break;
						case 'reset':
							$btn['text']  = __( 'Reset to Defaults', 'mhm-rentiva' );
							$btn['icon']  = 'dashicons-undo';
							$btn['class'] = 'button button-link-delete'; // Red/Warning style
							$reset_btns[] = $btn;
							break;
						default:
							$other_btns[] = $btn;
							break;
					}
				} else {
					$other_btns[] = $btn;
				}
			}

			// Merge in fixed order: Documentation -> Others -> Reset
			$sorted_buttons = array_merge( $docs_btns, $other_btns, $reset_btns );

			$html .= '<div class="mhm-admin-header-actions">';
			foreach ( $sorted_buttons as $btn ) {
				$class  = $btn['class'] ?? 'button button-secondary';
				$icon   = isset( $btn['icon'] ) ? '<span class="dashicons ' . esc_attr( $btn['icon'] ) . '"></span> ' : '';
				$target = isset( $btn['target'] ) ? ' target="' . esc_attr( $btn['target'] ) . '"' : '';
				$id     = isset( $btn['id'] ) ? ' id="' . esc_attr( $btn['id'] ) . '"' : '';
				$url    = isset( $btn['url'] ) ? esc_url( $btn['url'] ) : 'javascript:void(0);';

				$extra_attrs = '';
				if ( isset( $btn['data'] ) && is_array( $btn['data'] ) ) {
					foreach ( $btn['data'] as $key => $val ) {
						if ( $key === 'onclick' ) {
							$extra_attrs .= ' onclick="' . $val . '"'; // No data- prefix for events
						} else {
							$extra_attrs .= ' data-' . esc_attr( $key ) . '="' . esc_attr( (string) $val ) . '"';
						}
					}
				}

				$html .= sprintf(
					'<a href="%s"%s%s class="%s"%s>%s%s</a>',
					$url,
					$id,
					$target,
					esc_attr( $class ),
					$extra_attrs,
					$icon,
					esc_html( $btn['text'] )
				);
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $html;
	}

	/**
	 * Render Developer Mode Banner
	 *
	 * Displays the standardized "Developer Mode Active" banner if logic applies.
	 * Wraps ProFeatureNotice::displayDeveloperModeBanner() for consistent usage.
	 *
	 * @param array $features Optional list of unlocked features to display
	 */
	protected function render_developer_mode_banner( array $features = array() ): void {
		if ( class_exists( \MHMRentiva\Admin\Core\ProFeatureNotice::class ) ) {
			\MHMRentiva\Admin\Core\ProFeatureNotice::displayDeveloperModeBanner( $features );
		}
	}

	/**
	 * Show admin notice
	 *
	 * @param string $message Message
	 * @param string $type Notice type (success, error, warning, info)
	 * @param bool   $dismissible Can be dismissed
	 */
	protected function show_admin_notice( string $message, string $type = 'info', bool $dismissible = true ): void {
		$dismissible_class = $dismissible ? 'is-dismissible' : '';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' ' . esc_attr( $dismissible_class ) . '">';
		echo '<p>' . wp_kses_post( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Create admin tab navigation
	 *
	 * @param array  $tabs Tabs ['key' => 'label']
	 * @param string $current_active Active tab
	 * @param string $base_url Base URL
	 */
	protected function render_admin_tabs( array $tabs, string $current_active, string $base_url ): void {
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$active_class = ( $key === $current_active ) ? 'nav-tab-active' : '';
			$url          = add_query_arg( 'tab', $key, $base_url );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab ' . esc_attr( $active_class ) . '">';
			echo esc_html( $label );
			echo '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Add form nonce field
	 *
	 * @param string $action Action name
	 * @param string $name Field name
	 */
	protected function add_nonce_field( string $action, string $name = '_wpnonce' ): void {
		wp_nonce_field( $action, $name );
	}

	/**
	 * Verify nonce
	 *
	 * @param string $action Action name
	 * @param string $name Field name
	 * @return bool Verification status
	 */
	protected function verify_nonce( string $action, string $name = '_wpnonce' ): bool {
		return wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $name ] ?? '' ) ), $action ) !== false;
	}

	/**
	 * Admin form submit check
	 *
	 * @param string $action Action name
	 * @param string $nonce_name Nonce field name
	 * @return bool Submit status
	 */
	protected function is_form_submitted( string $action, string $nonce_name = '_wpnonce' ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This method exists solely to verify the nonce.
		return isset( $_POST[ $nonce_name ] ) && $this->verify_nonce( $action, $nonce_name );
	}

	/**
	 * Sanitize form data
	 *
	 * @param array $data Form data
	 * @param array $fields Allowed fields
	 * @return array Sanitized data
	 */
	protected function sanitize_form_data( array $data, array $fields = array() ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			if ( ! empty( $fields ) && ! in_array( $key, $fields, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_form_data( $value, $fields );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Admin redirect
	 *
	 * @param string $url Redirect URL
	 * @param array  $query_params Query parameters
	 */
	protected function admin_redirect( string $url, array $query_params = array() ): void {
		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Send admin ajax response
	 *
	 * @param bool   $success Success status
	 * @param mixed  $data Response data
	 * @param string $message Message
	 * @param int    $status_code HTTP status code
	 */
	protected function send_ajax_response( bool $success, $data = null, string $message = '', int $status_code = 200 ): void {
		$response = array(
			'success' => $success,
			'data'    => $data,
			'message' => $message,
		);

		wp_send_json( $response, $status_code );
	}

	/**
	 * Create admin table pagination
	 *
	 * @param int    $total_items Total item count
	 * @param int    $per_page Items per page
	 * @param int    $current_page Current page
	 * @param string $base_url Base URL
	 * @param string $page_param Page parameter
	 */
	protected function render_pagination( int $total_items, int $per_page, int $current_page, string $base_url, string $page_param = 'paged' ): void {
		$total_pages = ceil( $total_items / $per_page );

		if ( $total_pages <= 1 ) {
			return;
		}

		echo '<div class="tablenav-pages">';
		echo '<span class="displaying-num">' . esc_html(
			sprintf(
				/* translators: %s placeholder. */
				_n( '%s item', '%s items', $total_items, 'mhm-rentiva' ),
				number_format_i18n( $total_items )
			)
		) . '</span>';

		echo '<span class="pagination-links">';

		// Previous page
		if ( $current_page > 1 ) {
			$prev_url = add_query_arg( $page_param, $current_page - 1, $base_url );
			echo '<a class="first-page" href="' . esc_url( $prev_url ) . '">‹‹</a>';
			echo '<a class="prev-page" href="' . esc_url( $prev_url ) . '">‹</a>';
		}

		// Page numbers
		$start = max( 1, $current_page - 2 );
		$end   = min( $total_pages, $current_page + 2 );

		for ( $i = $start; $i <= $end; $i++ ) {
			if ( $i === $current_page ) {
				echo '<span class="current">' . esc_html( (string) $i ) . '</span>';
			} else {
				$page_url = add_query_arg( $page_param, $i, $base_url );
				echo '<a href="' . esc_url( $page_url ) . '">' . esc_html( (string) $i ) . '</a>';
			}
		}

		// Next page
		if ( $current_page < $total_pages ) {
			$next_url = add_query_arg( $page_param, $current_page + 1, $base_url );
			echo '<a class="next-page" href="' . esc_url( $next_url ) . '">›</a>';
			echo '<a class="last-page" href="' . esc_url( $next_url ) . '">››</a>';
		}

		echo '</span>';
		echo '</div>';
	}

	/**
	 * Create admin table bulk actions
	 *
	 * @param array  $actions Bulk actions
	 * @param string $name Field name
	 */
	protected function render_bulk_actions( array $actions, string $name = 'bulk_action' ): void {
		echo '<select name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html__( 'Bulk Actions', 'mhm-rentiva' ) . '</option>';

		foreach ( $actions as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		echo '<input type="submit" class="button" value="' . esc_attr__( 'Apply', 'mhm-rentiva' ) . '">';
	}

	/**
	 * Show admin loading spinner
	 *
	 * @param string $message Loading message
	 */
	protected function show_loading_spinner( string $message = '' ): void {
		echo '<div class="mhm-loading-spinner">';
		echo '<div class="spinner is-active"></div>';
		if ( $message ) {
			echo '<span class="loading-message">' . esc_html( $message ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * Show admin success message
	 *
	 * @param string $message Success message
	 */
	protected function show_success_message( string $message ): void {
		$this->show_admin_notice( $message, 'success' );
	}

	/**
	 * Show admin error message
	 *
	 * @param string $message Error message
	 */
	protected function show_error_message( string $message ): void {
		$this->show_admin_notice( $message, 'error' );
	}

	/**
	 * Show admin warning message
	 *
	 * @param string $message Warning message
	 */
	protected function show_warning_message( string $message ): void {
		$this->show_admin_notice( $message, 'warning' );
	}

	/**
	 * Show admin info message
	 *
	 * @param string $message Info message
	 */
	protected function show_info_message( string $message ): void {
		$this->show_admin_notice( $message, 'info' );
	}
}

