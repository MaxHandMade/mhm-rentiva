import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import TabNav        from './components/TabNav';
import GeneralTab    from './components/GeneralTab';
import SystemTab     from './components/SystemTab';
import SupportTab    from './components/SupportTab';
import DeveloperTab  from './components/DeveloperTab';

function getInitialTab() {
	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	// Keep in sync with TabNav's TABS and About.php's $allowed. A stale `features`
	// entry here would resurrect the removed comparison tab from a bookmarked URL.
	const allowed = [ 'general', 'system', 'support', 'developer' ];
	return allowed.includes( tab ) ? tab : ( window.mhmRentivaAbout?.initial_tab ?? 'general' );
}

export default function AboutPage() {
	const [ activeTab, setActiveTab ] = useState( getInitialTab );
	const [ data,      setData ]      = useState( null );
	const [ loading,   setLoading ]   = useState( true );
	const [ error,     setError ]     = useState( null );

	useEffect( () => {
		rentivaApi.about.getData()
			.then( setData )
			.catch( () => setError( __( 'Failed to load page data. Please refresh.', 'mhm-rentiva' ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const switchTab = ( id ) => {
		setActiveTab( id );
		history.pushState( null, '', `?page=mhm-rentiva-about&tab=${ id }` );
	};

	if ( loading ) {
		return (
			<div className="mhm-about-loading">
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return <p className="mhm-error notice notice-error">{ error }</p>;
	}

	return (
		<div className="mhm-about-page">
			<TabNav activeTab={ activeTab } onSwitch={ switchTab } />

			<div className="mhm-about-tab-content">
				{ activeTab === 'general'   && <GeneralTab   data={ data.general }   /> }
				{ activeTab === 'system'    && <SystemTab    data={ data.system }    /> }
				{ activeTab === 'support'   && <SupportTab   data={ data.support }   /> }
				{ activeTab === 'developer' && <DeveloperTab data={ data.developer } /> }
			</div>
		</div>
	);
}
