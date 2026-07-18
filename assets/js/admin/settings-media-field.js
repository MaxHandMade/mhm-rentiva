/**
 * Settings media (image) field — WP media modal picker.
 *
 * Extracted from the inline per-field block that SettingsHelper::render_media_field_html()
 * used to echo. The inline version relied on document.currentScript to find its own
 * wrapper; this version initializes every [data-mhm-media-field] wrapper on the page,
 * preserving identical behavior for one or many fields.
 */
( function () {
	function initMediaField( wrap ) {
		if ( ! wrap || ! window.wp || ! wp.media ) {
			return;
		}

		var config    = window.mhmMediaField || {};
		var i18n      = config.i18n || {};
		var idInput   = wrap.querySelector( '.mhm-media-id' );
		var preview   = wrap.querySelector( '.mhm-media-preview' );
		var selectBtn = wrap.querySelector( '[data-mhm-media-select]' );
		var removeBtn = wrap.querySelector( '[data-mhm-media-remove]' );
		var frame;

		if ( ! selectBtn || ! idInput || ! preview || ! removeBtn ) {
			return;
		}

		selectBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = wp.media( { title: i18n.selectImage || '', multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				idInput.value = a.id;
				preview.innerHTML = '<img src="' + a.url + '" alt="" style="max-width:200px;max-height:80px;display:block;margin-bottom:6px;">';
				removeBtn.style.display = '';
			} );
			frame.open();
		} );

		removeBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			idInput.value = '0';
			preview.innerHTML = '';
			removeBtn.style.display = 'none';
		} );
	}

	function init() {
		var wrappers = document.querySelectorAll( '[data-mhm-media-field]' );
		for ( var i = 0; i < wrappers.length; i++ ) {
			initMediaField( wrappers[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
