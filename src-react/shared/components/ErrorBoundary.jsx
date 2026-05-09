import { Component } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

class ErrorBoundary extends Component {
	state = { hasError: false };

	static getDerivedStateFromError() {
		return { hasError: true };
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<Notice status="error" isDismissible={ false }>
					{ __( 'An error occurred. Please refresh the page.', 'mhm-rentiva' ) }
				</Notice>
			);
		}
		return this.props.children;
	}
}

export default ErrorBoundary;
