import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import UiCoreErrorBoundary from '../../../vendor/mhm/ui-core/src-react/components/ErrorBoundary';

/**
 * Rentiva's error boundary: the shared boundary from mhm/ui-core, wearing this
 * plugin's wording.
 *
 * ui-core is a package, not a plugin, so it has no text domain and cannot ship a
 * translatable string; the fallback is supplied here, where 'mhm-rentiva' is the
 * right domain. __() is called during render, as before, not at module scope.
 *
 * @param {Object} root0          Component props.
 * @param {*}      root0.children Subtree to guard.
 * @return {JSX.Element} The guarded subtree.
 */
const ErrorBoundary = ( { children } ) => (
	<UiCoreErrorBoundary
		fallback={
			<Notice status="error" isDismissible={ false }>
				{ __(
					'An error occurred. Please refresh the page.',
					'mhm-rentiva'
				) }
			</Notice>
		}
	>
		{ children }
	</UiCoreErrorBoundary>
);

export default ErrorBoundary;
