/*
 * MHM Rentiva — Vehicle Settings v2 UI.
 *
 * One client-side state model hydrated ONCE from window.mhmVehicleSettings.state
 * (localized by AssetManager from VehicleSettings::build_settings_state()); the DOM
 * never feeds state back. All user-facing strings come from mhmVehicleSettings.i18n
 * (English source in PHP + TR via .po), with English fallbacks here.
 */
( function () {
	'use strict';

	var MOUNT_ID = 'rv-vs-app';
	var GROUP_ORDER = [ 'detail', 'feature', 'equipment' ];

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
		var i18n = ( payload && payload.i18n ) || {};

		/** Translated string with an English source fallback. */
		function t( key, fallback ) {
			return i18n[ key ] || fallback;
		}

		if ( ! payload || ! payload.state || ! Array.isArray( payload.state.fields ) ) {
			mount.appendChild( h( 'p', {
				class: 'rv-vs__subtitle',
				text: t( 'loadError', 'Could not load settings data. Please reload the page.' )
			} ) );
			return;
		}

		var T = {
			title: t( 'title', 'Vehicle Settings' ),
			subtitle: t( 'subtitle', 'Define fields and manage where they appear' ),
			tabFields: t( 'tabFields', '1 · Field Definitions' ),
			tabDisplay: t( 'tabDisplay', '2 · Display & Preview' ),
			fieldsHint: t( 'fieldsHint', 'Choose which fields are collected. Passive fields do not appear on vehicle forms or in the preview. You can also add your own custom field.' ),
			titleDetail: t( 'titleDetail', 'Vehicle Details' ),
			titleFeature: t( 'titleFeature', 'Vehicle Features' ),
			titleEquipment: t( 'titleEquipment', 'Vehicle Equipment' ),
			active: t( 'active', 'Active' ),
			passive: t( 'passive', 'Passive' ),
			activeLower: t( 'activeLower', 'active' ),
			coreLocked: t( 'coreLocked', 'Core fields cannot be disabled' ),
			badgeRequired: t( 'badgeRequired', 'REQUIRED' ),
			badgeCustom: t( 'badgeCustom', 'CUSTOM' ),
			removeTitle: t( 'remove', 'Remove' ),
			removeConfirm: t( 'removeConfirm', 'The field "%s" will be permanently deleted. Are you sure?' ),
			removed: t( 'removed', 'Field deleted.' ),
			removeFailed: t( 'removeFailed', 'Could not delete the field.' ),
			selectAll: t( 'selectAll', 'Select All' ),
			selectNone: t( 'selectNone', 'Deselect All' ),
			editNames: t( 'editNames', 'Edit Names' ),
			addCustom: t( 'addCustom', 'Add custom field:' ),
			fieldNamePlaceholder: t( 'fieldNamePlaceholder', 'Field name (e.g. Boot Size)' ),
			groupDetail: t( 'groupDetail', 'Detail' ),
			groupFeature: t( 'groupFeature', 'Feature' ),
			groupEquipment: t( 'groupEquipment', 'Equipment' ),
			typeText: t( 'typeText', 'Text' ),
			typeNumber: t( 'typeNumber', 'Number' ),
			typeSelect: t( 'typeSelect', 'Select' ),
			optionsPlaceholder: t( 'optionsPlaceholder', 'Options (comma separated: S, M, L)' ),
			add: t( 'add', 'Add' ),
			nameRequired: t( 'nameRequired', 'Please enter a field name.' ),
			addFailed: t( 'addFailed', 'Could not add the field.' ),
			addedOk: t( 'addedOk', 'Field added. Press Save to persist the selection state.' ),
			genericFail: t( 'genericFail', 'Operation failed. Your session may have expired — reload the page.' ),
			save: t( 'save', 'Save' ),
			saving: t( 'saving', 'Saving…' ),
			dirtyTitle: t( 'dirtyTitle', 'You have unsaved changes' ),
			savedOk: t( 'savedOk', 'Settings saved.' ),
			saveErr: t( 'saveErr', 'Could not save. Your session may have expired — reload the page and try again.' ),
			netErr: t( 'netErr', 'Could not save. Check your connection and try again.' ),
			template: t( 'template', 'Template:' ),
			presetMinimal: t( 'presetMinimal', 'Minimal' ),
			presetStandard: t( 'presetStandard', 'Standard' ),
			presetDetailed: t( 'presetDetailed', 'Detailed' ),
			filterAll: t( 'filterAll', 'All' ),
			filterDetail: t( 'filterDetail', 'Details' ),
			filterFeature: t( 'filterFeature', 'Features' ),
			filterEquipment: t( 'filterEquipment', 'Equipment' ),
			colField: t( 'colField', 'Field' ),
			colCard: t( 'colCard', 'Card' ),
			colDetail: t( 'colDetail', 'Detail' ),
			colCompare: t( 'colCompare', 'Comp.' ),
			toggleOn: t( 'toggleOn', 'On' ),
			toggleOff: t( 'toggleOff', 'Off' ),
			emptyCategory: t( 'emptyCategory', 'No active fields in this category.' ),
			dragHint: t( 'dragHint', 'Switch to "All" to reorder' ),
			gripTitle: t( 'gripTitle', 'Drag to reorder' ),
			livePreview: t( 'livePreview', 'Live preview' ),
			previewImage: t( 'previewImage', 'vehicle image' ),
			previewName: t( 'previewName', 'Toyota Corolla Hybrid' ),
			previewPrice: t( 'previewPrice', '$1,850 / day' ),
			previewLink: t( 'previewLink', 'View →' ),
			detailHighlights: t( 'detailHighlights', 'Detail — Highlights' ),
			noCard: t( 'noCard', 'No fields selected for the card' ),
			noDetail: t( 'noDetail', 'No fields highlighted in the detail view' ),
			countCard: t( 'countCard', 'Card' ),
			countDetail: t( 'countDetail', 'Detail' ),
			countCompare: t( 'countCompare', 'Comparison' ),
			cancel: t( 'cancel', 'Cancel' ),
			renameSuffix: t( 'renameSuffix', ' — Edit Names' ),
			renameSaved: t( 'renameSaved', 'Names updated.' ),
			renameFailed: t( 'renameFailed', 'Could not update names.' ),
			resetConfirm: t( 'resetConfirm', 'Reset this tab to defaults?' ),
			resetFailed: t( 'resetFailed', 'Could not reset.' )
		};

		function groupTitle( type ) {
			return { detail: T.titleDetail, feature: T.titleFeature, equipment: T.titleEquipment }[ type ] || type;
		}

		// Copy the localized payload so UI edits never mutate the global.
		var state = {
			tab: 'fields',
			dirty: false,
			dirtyEpoch: 0,
			saving: false,
			notice: null,
			fields: payload.state.fields.map( function ( f ) {
				return Object.assign( {}, f );
			} ),
			cardOrder: ( payload.state.cardOrder || [] ).slice(),
			detailOrder: ( payload.state.detailOrder || [] ).slice()
		};

		/**
		 * ONE matrix order drives BOTH surfaces (spec §6 "one order, not two"). It is persisted
		 * explicitly (mhm_rentiva_vehicle_matrix_order) and hydrated back here, so the admin's
		 * editing order round-trips exactly. When it is absent (pre-existing installs), fall back
		 * to deriving it: stored card order, then detail-only ids, then the rest.
		 */
		state.matrixOrder = ( function () {
			var known = {};
			state.fields.forEach( function ( f ) { known[ f.id ] = true; } );

			var stored = payload.state.matrixOrder;
			if ( Array.isArray( stored ) && stored.length ) {
				var out = stored.filter( function ( id ) { return known[ id ]; } );
				// Append any field missing from the stored order (e.g. newly available field).
				state.fields.forEach( function ( f ) {
					if ( out.indexOf( f.id ) === -1 ) { out.push( f.id ); }
				} );
				return out;
			}

			var seen = {};
			var derived = [];
			function push( id ) {
				if ( id && known[ id ] && ! seen[ id ] ) {
					seen[ id ] = true;
					derived.push( id );
				}
			}
			state.cardOrder.forEach( push );
			state.detailOrder.forEach( push );
			state.fields.forEach( function ( f ) { push( f.id ); } );
			return derived;
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
			state.dirtyEpoch++;
			state.notice = null;
		}

		/**
		 * Build the COMPLETE save_all payload from state.
		 *
		 * save_all requires every selected_* set (an omitted key is stored as empty), and
		 * save_display_payload writes comparison_fields UNGUARDED — so comparison must be sent
		 * from state, or the stored selection is wiped. Card/detail derive from the matrix order.
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
				// Only an ENABLED field can compare — mirror card/detail so a Passive field's
				// stale compare flag is not persisted as comparison cruft.
				if ( f.enabled && f.compare ) {
					comparison[ cat ].push( f.key );
				}
			} );

			// Both surfaces derive from the single matrix order. Append any enabled+flagged field
			// that is somehow absent from matrixOrder (defensive — matches matrixFields()), so the
			// two derivations can never silently drop a field the matrix showed.
			function orderedSelection( flag ) {
				var out = [];
				var seen = {};
				state.matrixOrder.forEach( function ( id ) {
					var f = fieldById( id );
					if ( f && f.enabled && f[ flag ] ) {
						seen[ id ] = true;
						out.push( { type: f.type, key: f.key } );
					}
				} );
				state.fields.forEach( function ( f ) {
					if ( f.enabled && f[ flag ] && ! seen[ f.id ] ) {
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
			// Snapshot the change counter: only clear `dirty` on success if no mutation happened
			// while the request was in flight, so a change made mid-save is not falsely "saved".
			var epoch = state.dirtyEpoch;
			render();

			var p = buildSavePayload();
			var body = new URLSearchParams();
			body.append( 'action', 'mhm_rentiva_save_vehicle_settings' );
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
			// Persist the single editing order so it round-trips exactly (spec §6).
			body.append( 'mhm_rentiva_vehicle_matrix_order', JSON.stringify( state.matrixOrder ) );

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
					// Keep dirty if the user changed something during the save (that change was
					// not in this request's body).
					if ( state.dirtyEpoch === epoch ) {
						state.dirty = false;
					}
					state.notice = { type: 'ok', text: T.savedOk };
				} else {
					state.notice = { type: 'err', text: T.saveErr };
				}
				render();
			} ).catch( function () {
				state.saving = false;
				state.notice = { type: 'err', text: T.netErr };
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
				label.appendChild( badge( 'rv-vs__badge--required', T.badgeRequired ) );
			}
			if ( field.custom ) {
				label.appendChild( badge( 'rv-vs__badge--custom', T.badgeCustom ) );
			}

			var pill = h( 'button', {
				type: 'button',
				'data-field-id': field.id,
				class: 'rv-vs__pill' + ( field.enabled ? ' rv-vs__pill--on' : '' ),
				text: field.enabled ? T.active : T.passive
			} );
			// Core fields are permanently active (enforced server-side too).
			if ( field.core ) {
				pill.disabled = true;
				pill.title = T.coreLocked;
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
					title: T.removeTitle,
					text: '×'
				} );
				remove.addEventListener( 'click', function () {
					if ( ! window.confirm( T.removeConfirm.replace( '%s', field.label ) ) ) {
						return;
					}
					postAjax( 'mhm_rentiva_remove_custom_field', {
						field_key: field.key,
						field_type: typeToCategory( field.type )
					} ).then( function ( res ) {
						if ( ! res || ! res.success ) {
							failNotice( T.removeFailed );
							return;
						}
						// Splice the id out of EVERY reference, or a stale id is written at the
						// next Save and then silently disappears.
						state.fields = state.fields.filter( function ( f ) { return f.id !== field.id; } );
						state.cardOrder = state.cardOrder.filter( function ( id ) { return id !== field.id; } );
						state.detailOrder = state.detailOrder.filter( function ( id ) { return id !== field.id; } );
						state.matrixOrder = state.matrixOrder.filter( function ( id ) { return id !== field.id; } );
						state.notice = { type: 'ok', text: T.removed };
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

			var selectAll = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost rv-vs__btn--sm', text: T.selectAll } );
			selectAll.addEventListener( 'click', function () { setGroupEnabled( true ); } );
			var selectNone = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost rv-vs__btn--sm', text: T.selectNone } );
			selectNone.addEventListener( 'click', function () { setGroupEnabled( false ); } );

			// Edit Names opens a per-group modal (one input per field); renames persist immediately
			// AND update state, so the Tab-2 live preview reflects a rename without a reload.
			var editNames = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost rv-vs__btn--sm', text: T.editNames } );
			editNames.addEventListener( 'click', function () {
				state.renameGroup = type;
				render();
			} );

			var head = h( 'div', { class: 'rv-vs__card-head' }, [
				h( 'h2', { class: 'rv-vs__card-title', text: groupTitle( type ) } ),
				h( 'span', { class: 'rv-vs__count', text: activeCount + ' ' + T.activeLower } )
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
			state.notice = { type: 'err', text: text || T.genericFail };
			render();
		}

		// --- Add custom field (the mockup omits the type selector; D7 requires keeping it) ---
		var addForm = { label: '', group: 'detail', type: 'text', options: '' };

		function renderAddCustom() {
			var nameInput = h( 'input', {
				class: 'rv-vs__input',
				type: 'text',
				placeholder: T.fieldNamePlaceholder,
				value: addForm.label
			} );
			nameInput.addEventListener( 'input', function ( e ) { addForm.label = e.target.value; } );

			var groupSelect = h( 'select', { class: 'rv-vs__select' } );
			[ [ 'detail', T.groupDetail ], [ 'feature', T.groupFeature ], [ 'equipment', T.groupEquipment ] ].forEach( function ( o ) {
				var opt = h( 'option', { value: o[ 0 ], text: o[ 1 ] } );
				if ( addForm.group === o[ 0 ] ) { opt.selected = true; }
				groupSelect.appendChild( opt );
			} );
			groupSelect.addEventListener( 'change', function ( e ) { addForm.group = e.target.value; render(); } );

			var children = [
				h( 'span', { class: 'rv-vs__addcustom-label', text: T.addCustom } ),
				nameInput,
				groupSelect
			];

			// Field type + options apply to DETAILS only (that is where mhm_custom_field_meta is read).
			if ( addForm.group === 'detail' ) {
				var typeSelect = h( 'select', { class: 'rv-vs__select' } );
				[ [ 'text', T.typeText ], [ 'number', T.typeNumber ], [ 'select', T.typeSelect ] ].forEach( function ( o ) {
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
						placeholder: T.optionsPlaceholder,
						value: addForm.options
					} );
					optionsInput.addEventListener( 'input', function ( e ) { addForm.options = e.target.value; } );
					children.push( optionsInput );
				}
			}

			var addBtn = h( 'button', { type: 'button', class: 'rv-vs__btn', text: T.add } );
			addBtn.addEventListener( 'click', function () {
				var label = ( addForm.label || '' ).trim();
				if ( ! label ) {
					failNotice( T.nameRequired );
					return;
				}
				var group = addForm.group;
				var params = { field_label: label, field_type: typeToCategory( group ) };
				if ( group === 'detail' ) {
					params.type = addForm.type;
					params.options = addForm.options;
				}
				postAjax( 'mhm_rentiva_add_custom_field', params ).then( function ( res ) {
					if ( ! res || ! res.success || ! res.data || ! res.data.key ) {
						failNotice( T.addFailed );
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
					state.matrixOrder.push( newField.id );
					addForm.label = '';
					addForm.options = '';
					// The new field is enabled in state but not yet in mhm_selected_*; Save persists it.
					markDirty();
					state.notice = { type: 'ok', text: T.addedOk };
					render();
				} ).catch( function () { failNotice(); } );
			} );
			children.push( addBtn );

			return h( 'div', { class: 'rv-vs__addcustom' }, children );
		}

		function renderFieldsTab() {
			return h( 'div', {}, [
				h( 'p', { class: 'rv-vs__subtitle', text: T.fieldsHint } ),
				renderAddCustom(),
				h( 'div', { class: 'rv-vs__groups' }, GROUP_ORDER.map( renderGroupCard ) )
			] );
		}

		// ---- Tab 2: Display & Preview ----

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
				text: on ? T.toggleOn : T.toggleOff
			} );
			// Mutate the row IN PLACE — never replace the node, or jquery-ui-sortable loses it.
			btn.addEventListener( 'click', function () {
				field[ flag ] = ! field[ flag ];
				btn.textContent = field[ flag ] ? T.toggleOn : T.toggleOff;
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
				label.appendChild( badge( 'rv-vs__badge--required', T.badgeRequired ) );
			}
			return h( 'div', { class: 'rv-vs__mrow', 'data-field-id': field.id }, [
				h( 'span', { class: 'rv-vs__grip', title: T.gripTitle, text: '⋮⋮' } ),
				h( 'div', { class: 'rv-vs__mrow-main' }, [
					label,
					h( 'div', { class: 'rv-vs__mrow-group', text: groupTitle( field.type ) } )
				] ),
				surfaceToggle( field, 'card' ),
				surfaceToggle( field, 'detail' ),
				surfaceToggle( field, 'compare' )
			] );
		}

		function renderMatrix() {
			var head = h( 'div', { class: 'rv-vs__mhead' }, [
				h( 'span', { class: 'rv-vs__mhead-field', text: T.colField } ),
				h( 'span', { class: 'rv-vs__mhead-col', text: T.colCard } ),
				h( 'span', { class: 'rv-vs__mhead-col', text: T.colDetail } ),
				h( 'span', { class: 'rv-vs__mhead-col', text: T.colCompare } )
			] );
			var rows = visibleMatrixFields().map( renderMatrixRow );
			var body = h( 'div', { class: 'rv-vs__mbody' }, rows );
			if ( ! rows.length ) {
				body.appendChild( h( 'p', { class: 'rv-vs__empty', text: T.emptyCategory } ) );
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
				chips.appendChild( h( 'span', { class: 'rv-vs__empty-inline', text: T.noCard } ) );
			}

			var vehicleCard = h( 'div', { class: 'rv-vs__pcard' }, [
				h( 'div', { class: 'rv-vs__pimage', text: T.previewImage } ),
				h( 'div', { class: 'rv-vs__pbody' }, [
					h( 'div', { class: 'rv-vs__pname', text: T.previewName } ),
					h( 'div', { class: 'rv-vs__pprice', text: T.previewPrice } ),
					chips,
					h( 'div', { class: 'rv-vs__plink', text: T.previewLink } )
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
				h( 'div', { class: 'rv-vs__ptitle', text: T.detailHighlights } ),
				grid
			] );
			if ( ! detailChips.length ) {
				highlights.appendChild( h( 'div', { class: 'rv-vs__empty-inline', text: T.noDetail } ) );
			}

			var counts = h( 'div', { class: 'rv-vs__counts' }, [
				h( 'span', { text: T.countCard + ' ' + cardChips.length } ),
				h( 'span', { text: T.countDetail + ' ' + detailChips.length } ),
				h( 'span', { text: T.countCompare + ' ' + compareCount } )
			] );

			return [
				h( 'div', { class: 'rv-vs__ptitle rv-vs__ptitle--section', text: T.livePreview } ),
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
				h( 'span', { class: 'rv-vs__toolbar-label', text: T.template } )
			] );
			[ [ 'minimal', T.presetMinimal ], [ 'standart', T.presetStandard ], [ 'detayli', T.presetDetailed ] ].forEach( function ( p ) {
				var b = h( 'button', { type: 'button', class: 'rv-vs__chipbtn', text: p[ 1 ] } );
				b.addEventListener( 'click', function () { applyPreset( p[ 0 ] ); } );
				bar.appendChild( b );
			} );
			bar.appendChild( h( 'span', { class: 'rv-vs__divider' } ) );
			[ [ 'all', T.filterAll ], [ 'detail', T.filterDetail ], [ 'feature', T.filterFeature ], [ 'equipment', T.filterEquipment ] ].forEach( function ( c ) {
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
				bar.appendChild( h( 'span', { class: 'rv-vs__hint', text: T.dragHint } ) );
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

			var cancel = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__btn--ghost', text: T.cancel } );
			cancel.addEventListener( 'click', close );

			var confirm = h( 'button', { type: 'button', class: 'rv-vs__btn', text: T.save } );
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
				postAjax( 'mhm_rentiva_update_field_labels', {
					type: typeToCategory( type ),
					labels: labels
				} ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						failNotice( T.renameFailed );
						return;
					}
					Object.keys( labels ).forEach( function ( key ) {
						var f = fieldById( type + ':' + key );
						if ( f ) {
							f.label = labels[ key ];
						}
					} );
					state.renameGroup = null;
					state.notice = { type: 'ok', text: T.renameSaved };
					render();
				} ).catch( function () { failNotice(); } );
			} );

			var dialog = h( 'div', { class: 'rv-vs__modal' }, [
				h( 'h2', { class: 'rv-vs__card-title', text: groupTitle( type ) + T.renameSuffix } ),
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
			var label = state.saving ? T.saving : ( state.dirty ? T.save + ' *' : T.save );
			var btn = h( 'button', { type: 'button', class: 'rv-vs__btn rv-vs__save', text: label } );
			btn.disabled = state.saving;
			if ( state.dirty && ! state.saving ) {
				btn.title = T.dirtyTitle;
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
			// Tear down the previous sortable so its jQuery data/handlers do not leak across
			// the full re-renders (preset/filter/tab switch) that replace the matrix node.
			if ( matrixEl && window.jQuery ) {
				var $prev = window.jQuery( matrixEl ).find( '.rv-vs__mbody' );
				if ( $prev.sortable && $prev.hasClass( 'ui-sortable' ) ) {
					$prev.sortable( 'destroy' );
				}
			}
			mount.textContent = '';
			mount.appendChild( h( 'div', { class: 'rv-vs__head' }, [
				h( 'h1', { class: 'rv-vs__title', text: T.title } ),
				h( 'span', { class: 'rv-vs__subtitle', text: T.subtitle } ),
				renderSaveButton()
			] ) );
			var notice = renderNotice();
			if ( notice ) {
				mount.appendChild( notice );
			}
			mount.appendChild( h( 'div', { class: 'rv-vs__tabs' }, [
				tabButton( 'fields', T.tabFields ),
				tabButton( 'display', T.tabDisplay )
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
				if ( ! window.confirm( T.resetConfirm ) ) {
					return;
				}
				postAjax( 'mhm_rentiva_reset_vehicle_settings', {
					tab: state.tab === 'display' ? 'display' : 'definitions'
				} ).then( function ( res ) {
					if ( res && res.success ) {
						state.dirty = false; // avoid the beforeunload prompt on the reload
						window.location.reload();
					} else {
						failNotice( T.resetFailed );
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
