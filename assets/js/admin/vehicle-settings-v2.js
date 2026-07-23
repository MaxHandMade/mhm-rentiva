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
			dirty: false,
			saving: false,
			notice: null,
			fields: payload.state.fields.map( function ( f ) {
				return Object.assign( {}, f );
			} ),
			cardOrder: ( payload.state.cardOrder || [] ).slice(),
			detailOrder: ( payload.state.detailOrder || [] ).slice()
		};

		function fieldById( id ) {
			for ( var i = 0; i < state.fields.length; i++ ) {
				if ( state.fields[ i ].id === id ) {
					return state.fields[ i ];
				}
			}
			return null;
		}

		function markDirty() {
			state.dirty = true;
			state.notice = null;
		}

		/**
		 * Build the COMPLETE save_all payload from state.
		 *
		 * save_all requires every selected_* set (an omitted key is stored as empty), and
		 * save_display_payload writes comparison_fields UNGUARDED — so comparison must be sent
		 * from state even while Tab 2's UI does not exist yet, or the stored selection is wiped.
		 * Card/detail are sent in their stored order, with any newly-flagged field appended.
		 */
		function buildSavePayload() {
			var selected = { details: [], features: [], equipment: [] };
			var comparison = { details: [], features: [], equipment: [] };

			state.fields.forEach( function ( f ) {
				var cat = typeToCategory( f.type );
				if ( ! selected[ cat ] ) {
					return; // taxonomy/unknown is out of scope (D5)
				}
				if ( f.enabled ) {
					selected[ cat ].push( f.key );
				}
				if ( f.compare ) {
					comparison[ cat ].push( f.key );
				}
			} );

			function orderedSelection( order, flag ) {
				var out = [];
				order.forEach( function ( id ) {
					var f = fieldById( id );
					if ( f && f[ flag ] ) {
						out.push( { type: f.type, key: f.key } );
					}
				} );
				state.fields.forEach( function ( f ) {
					if ( f[ flag ] && order.indexOf( f.id ) === -1 ) {
						out.push( { type: f.type, key: f.key } );
					}
				} );
				return out;
			}

			return {
				selected: selected,
				comparison: comparison,
				card: orderedSelection( state.cardOrder, 'card' ),
				detail: orderedSelection( state.detailOrder, 'detail' )
			};
		}

		function save() {
			if ( state.saving ) {
				return;
			}
			state.saving = true;
			state.notice = null;
			render();

			var p = buildSavePayload();
			var body = new URLSearchParams();
			body.append( 'action', 'mhmrentiva_save_vehicle_settings' );
			body.append( 'nonce', payload.nonce );
			body.append( 'sub_action', 'save_all' );

			[ 'details', 'features', 'equipment' ].forEach( function ( cat ) {
				p.selected[ cat ].forEach( function ( k ) {
					body.append( 'selected_' + cat + '[]', k );
				} );
				p.comparison[ cat ].forEach( function ( k ) {
					body.append( 'comparison_fields[' + cat + '][]', k );
				} );
			} );
			body.append( 'mhm_rentiva_vehicle_card_fields', JSON.stringify( p.card ) );
			body.append( 'mhm_rentiva_vehicle_detail_fields', JSON.stringify( p.detail ) );

			window.fetch( window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} ).then( function ( r ) {
				return r.json();
			} ).then( function ( res ) {
				state.saving = false;
				if ( res && res.success ) {
					state.dirty = false;
					state.notice = { type: 'ok', text: 'Ayarlar kaydedildi.' };
				} else {
					state.notice = { type: 'err', text: 'Kaydedilemedi. Oturumunuz sona ermiş olabilir — sayfayı yenileyip tekrar deneyin.' };
				}
				render();
			} ).catch( function () {
				state.saving = false;
				state.notice = { type: 'err', text: 'Kaydedilemedi. Bağlantıyı kontrol edip tekrar deneyin.' };
				render();
			} );
		}

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
			} else {
				pill.addEventListener( 'click', function () {
					field.enabled = ! field.enabled;
					markDirty();
					render();
				} );
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

			// Bulk actions apply only to non-core rows (core stays permanently active).
			function setGroupEnabled( on ) {
				items.forEach( function ( f ) {
					if ( ! f.core ) {
						f.enabled = on;
					}
				} );
				markDirty();
				render();
			}

			var selectAll = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost rv-vs__btn--sm', text: 'Tümünü Seç' } );
			selectAll.addEventListener( 'click', function () { setGroupEnabled( true ); } );
			var selectNone = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost rv-vs__btn--sm', text: 'Tümünü Kaldır' } );
			selectNone.addEventListener( 'click', function () { setGroupEnabled( false ); } );

			var head = h( 'div', { class: 'rv-vs__card-head' }, [
				h( 'h2', { class: 'rv-vs__card-title', text: GROUP_TITLE[ type ] } ),
				h( 'span', { class: 'rv-vs__count', text: activeCount + ' aktif' } )
			] );
			var actions = h( 'div', { class: 'rv-vs__card-actions' }, [ selectAll, selectNone ] );

			return h( 'div', {
				class: 'rv-vs__card',
				'data-group': typeToCategory( type )
			}, [ head, actions, h( 'div', { class: 'rv-vs__rows' }, items.map( renderRow ) ) ] );
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

		function renderSaveButton() {
			var label = state.saving ? 'Kaydediliyor…' : ( state.dirty ? 'Kaydet *' : 'Kaydet' );
			var btn = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__save', text: label } );
			btn.disabled = state.saving;
			if ( state.dirty && ! state.saving ) {
				btn.title = 'Kaydedilmemiş değişiklikler var';
			}
			btn.addEventListener( 'click', save );
			return btn;
		}

		function renderNotice() {
			if ( ! state.notice ) {
				return null;
			}
			return h( 'div', {
				class: 'rv-vs__notice rv-vs__notice--' + ( state.notice.type === 'ok' ? 'ok' : 'err' ),
				text: state.notice.text
			} );
		}

		function render() {
			mount.textContent = '';
			mount.appendChild( h( 'div', { class: 'rv-vs__head' }, [
				h( 'h1', { class: 'rv-vs__title', text: 'Araç Ayarları' } ),
				h( 'span', { class: 'rv-vs__subtitle', text: 'Alanları tanımla, nerede görüneceğini yönet' } ),
				renderSaveButton()
			] ) );
			var notice = renderNotice();
			if ( notice ) {
				mount.appendChild( notice );
			}
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
