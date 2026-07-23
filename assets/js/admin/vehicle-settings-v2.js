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

		/**
		 * ONE matrix order drives BOTH surfaces: the stored cardOrder/detailOrder are derived
		 * from it, filtered to the fields selected for that surface (spec §6 "one order, not
		 * two"). Seeded from the stored card order, then detail-only ids, then the rest.
		 */
		state.matrixOrder = ( function () {
			var seen = {};
			var out = [];
			function push( id ) {
				if ( id && ! seen[ id ] ) {
					seen[ id ] = true;
					out.push( id );
				}
			}
			state.cardOrder.forEach( push );
			state.detailOrder.forEach( push );
			state.fields.forEach( function ( f ) { push( f.id ); } );
			return out.filter( function ( id ) {
				return state.fields.some( function ( f ) { return f.id === id; } );
			} );
		} )();

		state.filter = 'all';

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

			// Both surfaces derive from the single matrix order.
			function orderedSelection( flag ) {
				var out = [];
				state.matrixOrder.forEach( function ( id ) {
					var f = fieldById( id );
					if ( f && f.enabled && f[ flag ] ) {
						out.push( { type: f.type, key: f.key } );
					}
				} );
				return out;
			}

			return {
				selected: selected,
				comparison: comparison,
				card: orderedSelection( 'card' ),
				detail: orderedSelection( 'detail' )
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

			var children = [ label, pill ];

			if ( field.custom ) {
				var remove = h( 'button', {
					type: 'button',
					class: 'rv-vs__remove',
					title: 'Kaldır',
					text: '×'
				} );
				remove.addEventListener( 'click', function () {
					if ( ! window.confirm( '"' + field.label + '" alanı kalıcı olarak silinecek. Emin misiniz?' ) ) {
						return;
					}
					postAjax( 'mhmrentiva_remove_custom_field', {
						field_key: field.key,
						field_type: typeToCategory( field.type )
					} ).then( function ( res ) {
						if ( ! res || ! res.success ) {
							failNotice( 'Alan silinemedi.' );
							return;
						}
						// Splice the id out of EVERY reference, or a stale id is written at the
						// next Save and then silently disappears.
						state.fields = state.fields.filter( function ( f ) { return f.id !== field.id; } );
						state.cardOrder = state.cardOrder.filter( function ( id ) { return id !== field.id; } );
						state.detailOrder = state.detailOrder.filter( function ( id ) { return id !== field.id; } );
						state.notice = { type: 'ok', text: 'Alan silindi.' };
						render();
					} ).catch( function () { failNotice(); } );
				} );
				children.push( remove );
			}

			return h( 'div', {
				class: 'rv-vs__row' + ( field.enabled ? '' : ' rv-vs__row--passive' ),
				'data-field-id': field.id
			}, children );
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

			// Edit Names opens a per-group modal (one input per field) rather than a chain of
			// prompts. Renames persist immediately AND update state, so the Tab-2 live preview
			// (B-2b) reflects a rename without a reload.
			var editNames = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost rv-vs__btn--sm', text: 'İsimleri Düzenle' } );
			editNames.addEventListener( 'click', function () {
				state.renameGroup = type;
				render();
			} );

			var head = h( 'div', { class: 'rv-vs__card-head' }, [
				h( 'h2', { class: 'rv-vs__card-title', text: GROUP_TITLE[ type ] } ),
				h( 'span', { class: 'rv-vs__count', text: activeCount + ' aktif' } )
			] );
			var actions = h( 'div', { class: 'rv-vs__card-actions' }, [ selectAll, selectNone, editNames ] );

			return h( 'div', {
				class: 'rv-vs__card',
				'data-group': typeToCategory( type )
			}, [ head, actions, h( 'div', { class: 'rv-vs__rows' }, items.map( renderRow ) ) ] );
		}

		/**
		 * POST helper for the existing definition-CRUD AJAX endpoints.
		 * Definition changes (add/remove/rename) persist immediately (D8); only the
		 * selection state is deferred to Save.
		 */
		function postAjax( action, params ) {
			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', payload.nonce );
			Object.keys( params ).forEach( function ( k ) {
				var v = params[ k ];
				if ( Array.isArray( v ) ) {
					v.forEach( function ( item ) { body.append( k + '[]', item ); } );
				} else if ( v !== null && typeof v === 'object' ) {
					Object.keys( v ).forEach( function ( sub ) { body.append( k + '[' + sub + ']', v[ sub ] ); } );
				} else {
					body.append( k, v );
				}
			} );
			return window.fetch( window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} ).then( function ( r ) { return r.json(); } );
		}

		function failNotice( text ) {
			state.notice = { type: 'err', text: text || 'İşlem başarısız. Oturumunuz sona ermiş olabilir — sayfayı yenileyin.' };
			render();
		}

		// --- Add custom field (the mockup omits the type selector; D7 requires keeping it) ---
		var addForm = { label: '', group: 'detail', type: 'text', options: '' };

		function renderAddCustom() {
			var nameInput = h( 'input', {
				class: 'rv-vs__input',
				type: 'text',
				placeholder: 'Alan adı (örn. Bagaj Hacmi)',
				value: addForm.label
			} );
			nameInput.addEventListener( 'input', function ( e ) { addForm.label = e.target.value; } );

			var groupSelect = h( 'select', { class: 'rv-vs__select' } );
			[ [ 'detail', 'Detay' ], [ 'feature', 'Özellik' ], [ 'equipment', 'Ekipman' ] ].forEach( function ( o ) {
				var opt = h( 'option', { value: o[ 0 ], text: o[ 1 ] } );
				if ( addForm.group === o[ 0 ] ) { opt.selected = true; }
				groupSelect.appendChild( opt );
			} );
			groupSelect.addEventListener( 'change', function ( e ) { addForm.group = e.target.value; render(); } );

			var children = [
				h( 'span', { class: 'rv-vs__addcustom-label', text: 'Özel alan ekle:' } ),
				nameInput,
				groupSelect
			];

			// Field type + options apply to DETAILS only (that is where mhm_custom_field_meta is read).
			if ( addForm.group === 'detail' ) {
				var typeSelect = h( 'select', { class: 'rv-vs__select' } );
				[ [ 'text', 'Metin' ], [ 'number', 'Sayı' ], [ 'select', 'Seçim' ] ].forEach( function ( o ) {
					var opt = h( 'option', { value: o[ 0 ], text: o[ 1 ] } );
					if ( addForm.type === o[ 0 ] ) { opt.selected = true; }
					typeSelect.appendChild( opt );
				} );
				typeSelect.addEventListener( 'change', function ( e ) { addForm.type = e.target.value; render(); } );
				children.push( typeSelect );

				if ( addForm.type === 'select' ) {
					var optionsInput = h( 'input', {
						class: 'rv-vs__input',
						type: 'text',
						placeholder: 'Seçenekler (virgülle ayır: S, M, L)',
						value: addForm.options
					} );
					optionsInput.addEventListener( 'input', function ( e ) { addForm.options = e.target.value; } );
					children.push( optionsInput );
				}
			}

			var addBtn = h( 'button', { type: 'button', class: 'rv-vs__btn', text: 'Ekle' } );
			addBtn.addEventListener( 'click', function () {
				var label = ( addForm.label || '' ).trim();
				if ( ! label ) {
					failNotice( 'Lütfen bir alan adı girin.' );
					return;
				}
				var group = addForm.group;
				var params = { field_label: label, field_type: typeToCategory( group ) };
				if ( group === 'detail' ) {
					params.type = addForm.type;
					params.options = addForm.options;
				}
				postAjax( 'mhmrentiva_add_custom_field', params ).then( function ( res ) {
					if ( ! res || ! res.success || ! res.data || ! res.data.key ) {
						failNotice( 'Alan eklenemedi.' );
						return;
					}
					// Merge the server-generated field into state (server owns the key).
					var newField = {
						id: group + ':' + res.data.key,
						type: group,
						key: res.data.key,
						label: label,
						enabled: true,
						core: false,
						custom: true,
						meta: ( group === 'detail' && addForm.type )
							? { type: addForm.type, options: addForm.options }
							: null,
						card: false,
						detail: false,
						compare: false
					};
					state.fields.push( newField );
					addForm.label = '';
					addForm.options = '';
					// The new field is enabled in state but not yet in mhm_selected_*; Save persists it.
					markDirty();
					state.notice = { type: 'ok', text: 'Alan eklendi. Seçim durumunu kaydetmek için Kaydet\'e basın.' };
					render();
				} ).catch( function () { failNotice(); } );
			} );
			children.push( addBtn );

			return h( 'div', { class: 'rv-vs__addcustom' }, children );
		}

		function renderFieldsTab() {
			return h( 'div', {}, [
				h( 'p', {
					class: 'rv-vs__subtitle',
					text: 'Hangi alanların toplanacağını belirle. Pasif alanlar araç formlarında ve önizlemede görünmez. Kendi özel alanını da ekleyebilirsin.'
				} ),
				renderAddCustom(),
				h( 'div', { class: 'rv-vs__groups' }, GROUP_ORDER.map( renderGroupCard ) )
			] );
		}

		// ---- Tab 2: Display & Preview ----

		// Client-side only; no preset concept exists server-side (spec §6, out of scope §11).
		var PRESETS = {
			minimal: {
				card: [ 'transmission', 'fuel_type', 'seats' ],
				detail: [ 'fuel_type', 'transmission' ]
			},
			standart: {
				card: [ 'transmission', 'fuel_type', 'seats', 'price_per_day' ],
				detail: [ 'fuel_type', 'transmission', 'seats', 'mileage', 'navigation' ]
			}
		};

		var previewEl = null;
		var matrixEl = null;

		function enabledFields() {
			return state.fields.filter( function ( f ) { return f.enabled; } );
		}

		/** Enabled fields in matrix order (order is authoritative for both surfaces). */
		function matrixFields() {
			var out = [];
			state.matrixOrder.forEach( function ( id ) {
				var f = fieldById( id );
				if ( f && f.enabled ) {
					out.push( f );
				}
			} );
			enabledFields().forEach( function ( f ) {
				if ( state.matrixOrder.indexOf( f.id ) === -1 ) {
					out.push( f );
				}
			} );
			return out;
		}

		function visibleMatrixFields() {
			var all = matrixFields();
			return state.filter === 'all'
				? all
				: all.filter( function ( f ) { return f.type === state.filter; } );
		}

		function applyPreset( kind ) {
			enabledFields().forEach( function ( f ) {
				if ( kind === 'detayli' ) {
					f.card = true;
					f.detail = true;
					f.compare = true;
					return;
				}
				var p = PRESETS[ kind ];
				f.card = p.card.indexOf( f.key ) !== -1;
				f.detail = p.detail.indexOf( f.key ) !== -1;
				f.compare = true;
			} );
			markDirty();
			render(); // set-membership change -> full re-render + sortable re-init
		}

		function surfaceToggle( field, flag ) {
			var on = !! field[ flag ];
			var btn = h( 'button', {
				type: 'button',
				class: 'rv-vs__toggle' + ( on ? ' rv-vs__toggle--on' : '' ),
				text: on ? 'Açık' : 'Kapalı'
			} );
			// Mutate the row IN PLACE — never replace the node, or jquery-ui-sortable loses it.
			btn.addEventListener( 'click', function () {
				field[ flag ] = ! field[ flag ];
				btn.textContent = field[ flag ] ? 'Açık' : 'Kapalı';
				btn.classList.toggle( 'rv-vs__toggle--on', field[ flag ] );
				markDirty();
				refreshPreview();
				refreshSaveButton();
			} );
			return btn;
		}

		function renderMatrixRow( field ) {
			var label = h( 'div', { class: 'rv-vs__row-label' }, [ h( 'span', { text: field.label } ) ] );
			if ( field.core ) {
				label.appendChild( badge( 'rv-vs__badge--required', 'ZORUNLU' ) );
			}
			return h( 'div', { class: 'rv-vs__mrow', 'data-field-id': field.id }, [
				h( 'span', { class: 'rv-vs__grip', title: 'Sürükleyerek sırala', text: '⋮⋮' } ),
				h( 'div', { class: 'rv-vs__mrow-main' }, [
					label,
					h( 'div', { class: 'rv-vs__mrow-group', text: GROUP_TITLE[ field.type ] } )
				] ),
				surfaceToggle( field, 'card' ),
				surfaceToggle( field, 'detail' ),
				surfaceToggle( field, 'compare' )
			] );
		}

		function renderMatrix() {
			var head = h( 'div', { class: 'rv-vs__mhead' }, [
				h( 'span', { class: 'rv-vs__mhead-field', text: 'Alan' } ),
				h( 'span', { class: 'rv-vs__mhead-col', text: 'Kart' } ),
				h( 'span', { class: 'rv-vs__mhead-col', text: 'Detay' } ),
				h( 'span', { class: 'rv-vs__mhead-col', text: 'Karş.' } )
			] );
			var rows = visibleMatrixFields().map( renderMatrixRow );
			var body = h( 'div', { class: 'rv-vs__mbody' }, rows );
			if ( ! rows.length ) {
				body.appendChild( h( 'p', { class: 'rv-vs__empty', text: 'Bu kategoride aktif alan yok.' } ) );
			}
			matrixEl = h( 'div', { class: 'rv-vs__matrix' }, [ head, body ] );
			return matrixEl;
		}

		/** Drag end: the DOM is authoritative for order — read it back into state. */
		function initSortable() {
			if ( ! window.jQuery || ! matrixEl ) {
				return;
			}
			var $body = window.jQuery( matrixEl ).find( '.rv-vs__mbody' );
			if ( ! $body.sortable ) {
				return;
			}
			// Reordering a filtered subset cannot express a whole-list order, so drag is only
			// enabled on the unfiltered view.
			if ( state.filter !== 'all' ) {
				return;
			}
			$body.sortable( {
				handle: '.rv-vs__grip',
				axis: 'y',
				tolerance: 'pointer',
				update: function () {
					var ids = [];
					$body.children( '.rv-vs__mrow' ).each( function () {
						ids.push( this.getAttribute( 'data-field-id' ) );
					} );
					// Keep ids that are not currently shown (disabled fields) after the visible ones.
					var rest = state.matrixOrder.filter( function ( id ) {
						return ids.indexOf( id ) === -1;
					} );
					state.matrixOrder = ids.concat( rest );
					markDirty();
					refreshPreview();
					refreshSaveButton();
				}
			} );
		}

		function renderPreviewInner() {
			var cardChips = enabledFields().filter( function ( f ) { return f.card; } );
			var detailChips = enabledFields().filter( function ( f ) { return f.detail; } );
			var compareCount = enabledFields().filter( function ( f ) { return f.compare; } ).length;

			var chips = h( 'div', { class: 'rv-vs__chips' },
				cardChips.map( function ( f ) {
					return h( 'span', { class: 'rv-vs__chip', text: f.label } );
				} )
			);
			if ( ! cardChips.length ) {
				chips.appendChild( h( 'span', { class: 'rv-vs__empty-inline', text: 'Kartta gösterilecek alan seçilmedi' } ) );
			}

			var vehicleCard = h( 'div', { class: 'rv-vs__pcard' }, [
				h( 'div', { class: 'rv-vs__pimage', text: 'araç görseli' } ),
				h( 'div', { class: 'rv-vs__pbody' }, [
					h( 'div', { class: 'rv-vs__pname', text: 'Toyota Corolla Hybrid' } ),
					h( 'div', { class: 'rv-vs__pprice', text: '₺1.850 / gün' } ),
					chips,
					h( 'div', { class: 'rv-vs__plink', text: 'İncele →' } )
				] )
			] );

			var grid = h( 'div', { class: 'rv-vs__pgrid' },
				detailChips.map( function ( f ) {
					return h( 'div', { class: 'rv-vs__pgrid-item' }, [
						h( 'span', { class: 'rv-vs__pgrid-label', text: f.label } ),
						h( 'span', { class: 'rv-vs__pgrid-value', text: '—' } )
					] );
				} )
			);
			var highlights = h( 'div', { class: 'rv-vs__pcard rv-vs__pcard--flat' }, [
				h( 'div', { class: 'rv-vs__ptitle', text: 'Detay — Öne çıkanlar' } ),
				grid
			] );
			if ( ! detailChips.length ) {
				highlights.appendChild( h( 'div', { class: 'rv-vs__empty-inline', text: 'Detayda öne çıkacak alan seçilmedi' } ) );
			}

			var counts = h( 'div', { class: 'rv-vs__counts' }, [
				h( 'span', { text: 'Kartta ' + cardChips.length } ),
				h( 'span', { text: 'Detayda ' + detailChips.length } ),
				h( 'span', { text: 'Karşılaştırmada ' + compareCount } )
			] );

			return [
				h( 'div', { class: 'rv-vs__ptitle rv-vs__ptitle--section', text: 'Canlı önizleme' } ),
				vehicleCard,
				highlights,
				counts
			];
		}

		function refreshPreview() {
			if ( ! previewEl ) {
				return;
			}
			previewEl.textContent = '';
			renderPreviewInner().forEach( function ( n ) { previewEl.appendChild( n ); } );
		}

		function refreshSaveButton() {
			var existing = mount.querySelector( '.rv-vs__save' );
			if ( existing ) {
				existing.replaceWith( renderSaveButton() );
			}
		}

		function renderDisplayTab() {
			// Presets + category filter chips
			var bar = h( 'div', { class: 'rv-vs__toolbar' }, [
				h( 'span', { class: 'rv-vs__toolbar-label', text: 'Şablon:' } )
			] );
			[ [ 'minimal', 'Minimal' ], [ 'standart', 'Standart' ], [ 'detayli', 'Detaylı' ] ].forEach( function ( p ) {
				var b = h( 'button', { type: 'button', class: 'rv-vs__chipbtn', text: p[ 1 ] } );
				b.addEventListener( 'click', function () { applyPreset( p[ 0 ] ); } );
				bar.appendChild( b );
			} );
			bar.appendChild( h( 'span', { class: 'rv-vs__divider' } ) );
			[ [ 'all', 'Tümü' ], [ 'detail', 'Detaylar' ], [ 'feature', 'Özellikler' ], [ 'equipment', 'Ekipman' ] ].forEach( function ( c ) {
				var on = state.filter === c[ 0 ];
				var b = h( 'button', {
					type: 'button',
					class: 'rv-vs__chipbtn' + ( on ? ' rv-vs__chipbtn--on' : '' ),
					text: c[ 1 ]
				} );
				// Filtering only changes what is VISIBLE; it never mutates state.
				b.addEventListener( 'click', function () {
					state.filter = c[ 0 ];
					render();
				} );
				bar.appendChild( b );
			} );

			previewEl = h( 'div', { class: 'rv-vs__preview' }, renderPreviewInner() );

			var layout = h( 'div', { class: 'rv-vs__display' }, [
				h( 'div', { class: 'rv-vs__matrix-wrap' }, [ renderMatrix() ] ),
				previewEl
			] );

			var wrap = h( 'div', {}, [ bar, layout ] );
			if ( state.filter !== 'all' ) {
				bar.appendChild( h( 'span', { class: 'rv-vs__hint', text: 'Sıralama için "Tümü" görünümüne geçin' } ) );
			}
			return wrap;
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

		/** Per-group rename modal (Edit Names, D7). */
		function renderRenameModal() {
			if ( ! state.renameGroup ) {
				return null;
			}
			var type = state.renameGroup;
			var items = state.fields.filter( function ( f ) { return f.type === type; } );
			var draft = {};
			items.forEach( function ( f ) { draft[ f.key ] = f.label; } );

			var rows = items.map( function ( f ) {
				var input = h( 'input', { class: 'rv-vs__input', type: 'text', value: f.label } );
				input.addEventListener( 'input', function ( e ) { draft[ f.key ] = e.target.value; } );
				return h( 'label', { class: 'rv-vs__rename-row' }, [
					h( 'span', { class: 'rv-vs__rename-key', text: f.key } ),
					input
				] );
			} );

			function close() {
				state.renameGroup = null;
				render();
			}

			var cancel = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost', text: 'İptal' } );
			cancel.addEventListener( 'click', close );

			var confirm = h( 'button', { type: 'button', class: 'rv-vs__btn', text: 'Kaydet' } );
			confirm.addEventListener( 'click', function () {
				var labels = {};
				items.forEach( function ( f ) {
					var next = ( draft[ f.key ] || '' ).trim();
					if ( next && next !== f.label ) {
						labels[ f.key ] = next;
					}
				} );
				if ( ! Object.keys( labels ).length ) {
					close();
					return;
				}
				postAjax( 'mhmrentiva_update_field_labels', {
					type: typeToCategory( type ),
					labels: labels
				} ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						failNotice( 'İsimler güncellenemedi.' );
						return;
					}
					Object.keys( labels ).forEach( function ( key ) {
						var f = fieldById( type + ':' + key );
						if ( f ) {
							f.label = labels[ key ];
						}
					} );
					state.renameGroup = null;
					state.notice = { type: 'ok', text: 'İsimler güncellendi.' };
					render();
				} ).catch( function () { failNotice(); } );
			} );

			var dialog = h( 'div', { class: 'rv-vs__modal' }, [
				h( 'h2', { class: 'rv-vs__card-title', text: GROUP_TITLE[ type ] + ' — İsimleri Düzenle' } ),
				h( 'div', { class: 'rv-vs__rename-list' }, rows ),
				h( 'div', { class: 'rv-vs__modal-actions' }, [ cancel, confirm ] )
			] );

			var overlay = h( 'div', { class: 'rv-vs__overlay' }, [ dialog ] );
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					close();
				}
			} );
			return overlay;
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
			if ( state.tab === 'fields' ) {
				previewEl = null;
				matrixEl = null;
				mount.appendChild( renderFieldsTab() );
			} else {
				mount.appendChild( renderDisplayTab() );
				initSortable();
			}
			var modal = renderRenameModal();
			if ( modal ) {
				mount.appendChild( modal );
			}
		}

		// Reset resets the tab the user is actually on (client-side tab, not the stale ?tab= that
		// the legacy flow localized once). fields -> definitions defaults, display -> display wiped.
		function wireReset() {
			var btn = document.getElementById( 'reset-vehicle-settings' );
			if ( ! btn ) {
				return;
			}
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! window.confirm( 'Bu sekmeyi varsayılana sıfırla?' ) ) {
					return;
				}
				postAjax( 'mhmrentiva_reset_vehicle_settings', {
					tab: state.tab === 'display' ? 'display' : 'definitions'
				} ).then( function ( res ) {
					if ( res && res.success ) {
						state.dirty = false; // avoid the beforeunload prompt on the reload
						window.location.reload();
					} else {
						failNotice( 'Sıfırlanamadı.' );
					}
				} ).catch( function () { failNotice(); } );
			} );
		}

		// Dirty-state guard: warn before leaving with unsaved selection changes.
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( state.dirty && ! state.saving ) {
				e.preventDefault();
				e.returnValue = '';
				return '';
			}
		} );

		render();
		wireReset();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
