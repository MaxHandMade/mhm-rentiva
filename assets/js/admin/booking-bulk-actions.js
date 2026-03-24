/**
 * Overrides WP bulk-action validation to avoid false "no selection" when layout shifts.
 */
(function ($) {
	'use strict';

	function getStrings() {
		const defaults = {
			no_items_selected: 'Please select at least one item to perform this action on.',
			confirm_bulk_trash: '',
			confirm_bulk_delete: '',
			confirm_single_trash: '',
			confirm_single_delete: '',
			confirm_empty_trash: ''
		};
		if (window.mhmBookingBulkActions && window.mhmBookingBulkActions.strings) {
			return Object.assign( defaults, window.mhmBookingBulkActions.strings );
		}
		return defaults;
	}

	function getCheckedItems() {
		// Prefer native name used by core, but fallback to any checked row checkbox
		let $items = $( '.wp-list-table input[name="post[]"]:checked' );
		if ($items.length === 0) {
			$items = $( '.wp-list-table tbody th.check-column input[type="checkbox"]:checked' );
		}
		return $items;
	}

	$(
		function () {
			const isBookingsList = (typeof pagenow !== 'undefined' && pagenow === 'edit.php')
			&& (typeof typenow !== 'undefined' && typenow === 'vehicle_booking');
			if ( ! isBookingsList) {
				return;
			}

			const strings      = getStrings();
			const $bulkButtons = $( '#doaction, #doaction2' );

			// Remove WP's default click handler to avoid early alert
			$bulkButtons.off( 'click' );

			$bulkButtons.on(
				'click.mhmBookingBulk',
				function (event) {
					const isTopButton = this.id === 'doaction';
					const $selector   = isTopButton ? $( '#bulk-action-selector-top' ) : $( '#bulk-action-selector-bottom' );

					if ( ! $selector.length) {
						return;
					}

					const action = $selector.val();
					if ( ! action || action === '-1') {
						return;
					}

					const selectedItems = getCheckedItems();
					const selectedCount = selectedItems.length;

					if (action === 'delete_all') {
						if (strings.confirm_empty_trash && ! window.confirm( strings.confirm_empty_trash )) {
							event.preventDefault();
							return false;
						}
						return;
					}

					const requiresSelection = /^(trash|untrash|delete|spam|unspam|restore|edit)/.test( action );

					if (requiresSelection && selectedCount === 0) {
						event.preventDefault();
						window.alert( strings.no_items_selected );
						return false;
					}

					if (action === 'trash' && selectedCount > 0 && strings.confirm_bulk_trash) {
						if ( ! window.confirm( strings.confirm_bulk_trash.replace( '%d', selectedCount ) )) {
							event.preventDefault();
							return false;
						}
					}

					if (action === 'delete' && selectedCount > 0 && strings.confirm_bulk_delete) {
						if ( ! window.confirm( strings.confirm_bulk_delete.replace( '%d', selectedCount ) )) {
							event.preventDefault();
							return false;
						}
					}

					// Bypass WP's legacy alert by submitting ourselves
					event.preventDefault();
					event.stopImmediatePropagation();
					const $form = $( this ).closest( 'form' );
					// Ensure the action select mirrors to the hidden 'action' input WP expects
					if (isTopButton) {
						$form.find( 'select[name="action"]' ).val( action );
					} else {
						$form.find( 'select[name="action2"]' ).val( action );
					}

					// Ensure selected post IDs are submitted as post[]
					// Remove previously added hidden inputs
					$form.find( 'input.mhm-bulk-post' ).remove();
					selectedItems.each(
						function () {
							const id = $( this ).val();
							$(
								'<input>',
								{
									type: 'hidden',
									name: 'post[]',
									value: id,
									class: 'mhm-bulk-post'
								}
							).appendTo( $form );
						}
					);
					$form.trigger( 'submit' );
					return false;
				}
			);

			const $deleteAll = $( '#delete_all' );
			if ($deleteAll.length) {
				$deleteAll.off( 'click' ).on(
					'click.mhmBookingBulk',
					function (event) {
						if (strings.confirm_empty_trash && ! window.confirm( strings.confirm_empty_trash )) {
							event.preventDefault();
							return false;
						}
					}
				);
			}

			$( '.row-actions .trash a' ).off( 'click.mhmBookingBulk' ).on(
				'click.mhmBookingBulk',
				function (event) {
					if (strings.confirm_single_trash && ! window.confirm( strings.confirm_single_trash )) {
						event.preventDefault();
						return false;
					}
				}
			);

			$( '.row-actions .delete a' ).off( 'click.mhmBookingBulk' ).on(
				'click.mhmBookingBulk',
				function (event) {
					if (strings.confirm_single_delete && ! window.confirm( strings.confirm_single_delete )) {
						event.preventDefault();
						return false;
					}
				}
			);
		}
	);
})( jQuery );
