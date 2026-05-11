/* global mhmSetupWizard */
( function () {
	var cfg          = window.mhmSetupWizard || {};
	var ajaxUrl      = cfg.ajaxUrl || '';
	var nonce        = cfg.nonce || '';
	var seedSteps    = cfg.seedSteps || [];
	var cleanupSteps = cfg.cleanupSteps || [];

	function setProgress( pct, label ) {
		document.getElementById( 'mhm-demo-progress-bar' ).style.display = 'block';
		document.getElementById( 'mhm-demo-progress-fill' ).style.width  = pct + '%';
		document.getElementById( 'mhm-demo-progress-label' ).textContent = label;
	}

	function showResult( msg ) {
		var el = document.getElementById( 'mhm-demo-result' );
		el.style.display = 'block';
		document.getElementById( 'mhm-demo-result-msg' ).textContent = msg;
	}

	function showError( msg ) {
		var el = document.getElementById( 'mhm-demo-error' );
		el.style.display = 'block';
		document.getElementById( 'mhm-demo-error-msg' ).textContent = msg;
	}

	function clearFeedback() {
		document.getElementById( 'mhm-demo-progress-bar' ).style.display  = 'none';
		document.getElementById( 'mhm-demo-result' ).style.display         = 'none';
		document.getElementById( 'mhm-demo-error' ).style.display          = 'none';
		document.getElementById( 'mhm-demo-progress-fill' ).style.width    = '0';
	}

	function runSteps( steps, action, onDone ) {
		var i = 0;
		function next() {
			if ( i >= steps.length ) {
				onDone();
				return;
			}
			var step = steps[ i++ ];
			var fd   = new FormData();
			fd.append( 'action', action );
			fd.append( 'nonce',  nonce );
			fd.append( 'step',   step );
			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						setProgress( data.data.progress, data.data.message );
						next();
					} else {
						showError( data.data && data.data.message ? data.data.message : 'Error during step: ' + step );
					}
				} )
				.catch( function () { showError( 'Network error during step: ' + step ); } );
		}
		next();
	}

	var btnSeed = document.getElementById( 'mhm-btn-seed' );
	if ( btnSeed ) {
		btnSeed.addEventListener( 'click', function () {
			clearFeedback();
			btnSeed.disabled = true;
			runSteps( seedSteps, 'mhm_rentiva_demo_seed', function () {
				showResult( cfg.msgSeeded || '' );
				btnSeed.disabled = false;
			} );
		} );
	}

	var btnCleanup = document.getElementById( 'mhm-btn-cleanup' );
	if ( btnCleanup ) {
		btnCleanup.addEventListener( 'click', function () {
			clearFeedback();
			btnCleanup.disabled = true;
			runSteps( cleanupSteps, 'mhm_rentiva_demo_cleanup', function () {
				showResult( cfg.msgCleaned || '' );
				btnCleanup.disabled = false;
			} );
		} );
	}
}() );
