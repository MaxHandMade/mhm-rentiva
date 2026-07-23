/*
 * MHM Rentiva — Vehicle Settings v2 UI.
 * Plan B-2a-1 slice: shell, one client-side state model, client-side tabs,
 * and a read-only render of Tab 1 (Field Definitions).
 *
 * State is hydrated ONCE from window.mhmVehicleSettings.state (localized by
 * AssetManager from VehicleSettings::build_settings_state()) and is the single
 * source of truth — nothing is ever derived from the DOM.
 */
( function () {
	'use strict';

	var MOUNT_ID = 'rv-vs-app';
	var GROUP_ORDER = [ 'detail', 'feature', 'equipment' ];
	var GROUP_TITLE = {
		detail: 'Araç Detayları',
		feature: 'Araç Özellikleri',
		equipment: 'Araç Ekipmanları'
	};

	/**
	 * The ONE singular-type -> plural-category converter. The payload uses singular
	 * types (detail/feature/equipment); stored option names and comparison categories
	 * are plural. Never do this conversion ad hoc anywhere else.
	 */
	function typeToCategory( type ) {
		return { detail: 'details', feature: 'features', equipment: 'equipment' }[ type ] || type;
	}

	/** Tiny element helper: h(tag, attrs, children). `text` sets textContent. */
	function h( tag, attrs, children ) {
		var el = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) {
				el.className = attrs[ k ];
			} else if ( k === 'text' ) {
				el.textContent = attrs[ k ];
			} else {
				el.setAttribute( k, attrs[ k ] );
			}
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c ) {
				el.appendChild( c );
			}
		} );
		return el;
	}

	function boot() {
		var mount = document.getElementById( MOUNT_ID );
		if ( ! mount ) {
			return;
		}

		var payload = window.mhmVehicleSettings;
		if ( ! payload || ! payload.state || ! Array.isArray( payload.state.fields ) ) {
			mount.appendChild( h( 'p', {
				class: 'rv-vs__subtitle',
				text: 'Ayar verisi yüklenemedi. Lütfen sayfayı yenileyin.'
			} ) );
			return;
		}

		// Copy the localized payload so UI edits never mutate the global.
		var state = {
			tab: 'fields',
			fields: payload.state.fields.map( function ( f ) {
				return Object.assign( {}, f );
			} ),
			cardOrder: ( payload.state.cardOrder || [] ).slice(),
			detailOrder: ( payload.state.detailOrder || [] ).slice()
		};

		function badge( modifier, txt ) {
			return h( 'span', { class: 'rv-vs__badge ' + modifier, text: txt } );
		}

		function renderRow( field ) {
			var label = h( 'div', { class: 'rv-vs__row-label' }, [
				h( 'span', { text: field.label } )
			] );
			if ( field.core ) {
				label.appendChild( badge( 'rv-vs__badge--required', 'ZORUNLU' ) );
			}
			if ( field.custom ) {
				label.appendChild( badge( 'rv-vs__badge--custom', 'ÖZEL' ) );
			}

			var pill = h( 'button', {
				type: 'button',
				'data-field-id': field.id,
				class: 'rv-vs__pill' + ( field.enabled ? ' rv-vs__pill--on' : '' ),
				text: field.enabled ? 'Aktif' : 'Pasif'
			} );
			// Core fields are permanently active (enforced server-side too).
			if ( field.core ) {
				pill.disabled = true;
				pill.title = 'Zorunlu alanlar kapatılamaz';
			}

			return h( 'div', {
				class: 'rv-vs__row' + ( field.enabled ? '' : ' rv-vs__row--passive' ),
				'data-field-id': field.id
			}, [ label, pill ] );
		}

		function renderGroupCard( type ) {
			var items = state.fields.filter( function ( f ) {
				return f.type === type;
			} );
			var activeCount = items.filter( function ( f ) {
				return f.enabled;
			} ).length;

			var head = h( 'div', { class: 'rv-vs__card-head' }, [
				h( 'h2', { class: 'rv-vs__card-title', text: GROUP_TITLE[ type ] } ),
				h( 'span', { class: 'rv-vs__count', text: activeCount + ' aktif' } )
			] );

			return h( 'div', {
				class: 'rv-vs__card',
				'data-group': typeToCategory( type )
			}, [ head, h( 'div', { class: 'rv-vs__rows' }, items.map( renderRow ) ) ] );
		}

		function renderFieldsTab() {
			return h( 'div', {}, [
				h( 'p', {
					class: 'rv-vs__subtitle',
					text: 'Hangi alanların toplanacağını belirle. Pasif alanlar araç formlarında ve önizlemede görünmez.'
				} ),
				h( 'div', { class: 'rv-vs__groups' }, GROUP_ORDER.map( renderGroupCard ) )
			] );
		}

		function renderDisplayTab() {
			return h( 'p', {
				class: 'rv-vs__subtitle',
				text: 'Görünüm & Önizleme — sonraki dilimde (B-2b) geliyor.'
			} );
		}

		function tabButton( key, label ) {
			var btn = h( 'button', {
				type: 'button',
				class: 'rv-vs__tab' + ( state.tab === key ? ' rv-vs__tab--active' : '' ),
				text: label
			} );
			btn.addEventListener( 'click', function () {
				if ( state.tab !== key ) {
					state.tab = key;
					render();
				}
			} );
			return btn;
		}

		function render() {
			mount.textContent = '';
			mount.appendChild( h( 'div', { class: 'rv-vs__head' }, [
				h( 'h1', { class: 'rv-vs__title', text: 'Araç Ayarları' } ),
				h( 'span', { class: 'rv-vs__subtitle', text: 'Alanları tanımla, nerede görüneceğini yönet' } )
			] ) );
			mount.appendChild( h( 'div', { class: 'rv-vs__tabs' }, [
				tabButton( 'fields', '1 · Alan Tanımları' ),
				tabButton( 'display', '2 · Görünüm & Önizleme' )
			] ) );
			mount.appendChild( state.tab === 'fields' ? renderFieldsTab() : renderDisplayTab() );
		}

		render();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
