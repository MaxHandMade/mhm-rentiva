/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR A: the shared modal dialog module -- dialog semantics, inert
 * background (or aria-hidden + tabindex without inert, restored exactly),
 * focus trap, Escape, one dialog at a time, focus back to the opener.
 */
describe( 'rvs dialog', () => {
	let dialog;
	const hasInert = Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'inert' );

	const page = () => `
		<header id="head"><a href="/" id="home">Home</a><div id="tab0" tabindex="0">x</div><p id="shown" aria-hidden="false">s</p></header>
		<main id="main">
			<button type="button" id="opener">Open</button>
			<button type="button" id="opener2">Open 2</button>
			<div id="dlg" class="rvs-dialog">
				<h2 id="dlg-title">Title</h2>
				<button type="button" id="first">First</button>
				<input id="mid">
				<button type="button" id="last">Last</button>
			</div>
			<div id="dlg2"><button type="button" id="other">Other</button></div>
		</main>`;

	const key = ( k, shift ) => document.dispatchEvent( new KeyboardEvent( 'keydown', { key: k, shiftKey: !! shift, bubbles: true } ) );

	const boot = ( withInert ) => {
		jest.resetModules();
		delete window.mhmRentivaRvsDialog;
		if ( withInert ) {
			Object.defineProperty( HTMLElement.prototype, 'inert', {
				configurable: true,
				get() {
					return this.hasAttribute( 'inert' );
				},
				set( v ) {
					if ( v ) {
						this.setAttribute( 'inert', '' );
					} else {
						this.removeAttribute( 'inert' );
					}
				},
			} );
		} else if ( Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'inert' ) ) {
			delete HTMLElement.prototype.inert;
		}
		document.body.innerHTML = page();
		require( '../../assets/js/frontend/rvs-dialog.js' );
		dialog = window.mhmRentivaRvsDialog;
	};

	afterEach( () => {
		if ( dialog && dialog.current() ) {
			dialog.close( dialog.current() );
		}
		if ( ! hasInert && Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'inert' ) ) {
			delete HTMLElement.prototype.inert;
		}
	} );

	const el = ( id ) => document.getElementById( id );

	test( 'open sets dialog semantics and focuses initialFocus', () => {
		boot( true );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ), labelledBy: 'dlg-title', initialFocus: el( 'dlg-title' ) } );

		expect( el( 'dlg' ).getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( el( 'dlg' ).getAttribute( 'aria-modal' ) ).toBe( 'true' );
		expect( el( 'dlg' ).getAttribute( 'aria-labelledby' ) ).toBe( 'dlg-title' );
		expect( document.activeElement ).toBe( el( 'dlg-title' ) );
		expect( el( 'dlg-title' ).getAttribute( 'tabindex' ) ).toBe( '-1' );
		expect( dialog.current() ).toBe( el( 'dlg' ) );
	} );

	test( 'background is inert while open and restored on close', () => {
		boot( true );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );

		expect( el( 'head' ).inert ).toBe( true );
		expect( el( 'opener' ).inert ).toBe( true );
		expect( el( 'dlg2' ).inert ).toBe( true );
		expect( el( 'dlg' ).inert ).toBe( false );

		dialog.close( el( 'dlg' ) );
		expect( el( 'head' ).inert ).toBe( false );
		expect( el( 'opener' ).inert ).toBe( false );
		expect( el( 'dlg' ).hasAttribute( 'role' ) ).toBe( false );
		expect( el( 'dlg' ).hasAttribute( 'aria-modal' ) ).toBe( false );
		expect( dialog.current() ).toBeNull();
	} );

	test( 'restores pre-existing tabindex without inert', () => {
		boot( false );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );

		expect( el( 'head' ).getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( el( 'home' ).getAttribute( 'tabindex' ) ).toBe( '-1' );
		expect( el( 'tab0' ).getAttribute( 'tabindex' ) ).toBe( '-1' );

		dialog.close( el( 'dlg' ) );
		expect( el( 'head' ).hasAttribute( 'aria-hidden' ) ).toBe( false );
		expect( el( 'home' ).hasAttribute( 'tabindex' ) ).toBe( false );
		expect( el( 'tab0' ).getAttribute( 'tabindex' ) ).toBe( '0' );
		expect( el( 'shown' ).getAttribute( 'aria-hidden' ) ).toBe( 'false' );
	} );

	test( 'Tab wraps from last to first, Shift+Tab from first to last', () => {
		boot( true );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ), initialFocus: el( 'dlg-title' ) } );

		el( 'last' ).focus();
		key( 'Tab' );
		expect( document.activeElement ).toBe( el( 'first' ) );

		key( 'Tab', true );
		expect( document.activeElement ).toBe( el( 'last' ) );

		el( 'dlg-title' ).focus();
		key( 'Tab', true );
		expect( document.activeElement ).toBe( el( 'last' ) );
	} );

	test( 'Escape closes and returns focus to opener', () => {
		boot( true );
		const onClose = jest.fn();
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ), initialFocus: el( 'dlg-title' ), onClose } );

		key( 'Escape' );
		expect( dialog.current() ).toBeNull();
		expect( onClose ).toHaveBeenCalledTimes( 1 );
		expect( document.activeElement ).toBe( el( 'opener' ) );
		expect( el( 'dlg-title' ).hasAttribute( 'tabindex' ) ).toBe( false );
	} );

	test( 'second open is ignored while one is open', () => {
		boot( true );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );
		dialog.open( el( 'dlg2' ), { opener: el( 'opener2' ) } );

		expect( dialog.current() ).toBe( el( 'dlg' ) );
		expect( el( 'dlg2' ).hasAttribute( 'role' ) ).toBe( false );

		dialog.close( el( 'dlg' ) );
		expect( document.activeElement ).toBe( el( 'opener' ) );
		expect( el( 'dlg2' ).inert ).toBe( false );
	} );

	test( 'a dialog removed from the page does not block the next one', () => {
		boot( true );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );
		el( 'dlg' ).remove();

		dialog.open( el( 'dlg2' ), { opener: el( 'opener2' ) } );
		expect( dialog.current() ).toBe( el( 'dlg2' ) );
	} );

	test( 'bodyClass is added and removed', () => {
		boot( true );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ), bodyClass: 'rvs-drawer-open' } );
		expect( document.body.classList.contains( 'rvs-drawer-open' ) ).toBe( true );
		dialog.close( el( 'dlg' ) );
		expect( document.body.classList.contains( 'rvs-drawer-open' ) ).toBe( false );

		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );
		expect( document.body.classList.contains( 'rvs-dialog-open' ) ).toBe( true );
	} );

	test( 'refresh hides controls that arrived after opening (no inert)', () => {
		boot( false );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );
		const late = document.createElement( 'a' );
		late.href = '#';
		late.id = 'late';
		el( 'dlg2' ).appendChild( late );

		dialog.refresh();
		expect( el( 'late' ).getAttribute( 'tabindex' ) ).toBe( '-1' );
		dialog.close( el( 'dlg' ) );
		expect( el( 'late' ).hasAttribute( 'tabindex' ) ).toBe( false );
	} );

	test( 'close restores a pre-existing aria-labelledby', () => {
		boot( true );
		el( 'dlg' ).setAttribute( 'aria-labelledby', 'x' );
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ), labelledBy: 'dlg-title' } );
		expect( el( 'dlg' ).getAttribute( 'aria-labelledby' ) ).toBe( 'dlg-title' );
		dialog.close( el( 'dlg' ) );
		expect( el( 'dlg' ).getAttribute( 'aria-labelledby' ) ).toBe( 'x' );
	} );

	test( 'summary and contenteditable are hidden from Tab without inert', () => {
		boot( false );
		el( 'dlg2' ).innerHTML = '<details><summary id="sum">More</summary></details><div id="ce" contenteditable="true"></div>';
		dialog.open( el( 'dlg' ), { opener: el( 'opener' ) } );
		expect( el( 'sum' ).getAttribute( 'tabindex' ) ).toBe( '-1' );
		expect( el( 'ce' ).getAttribute( 'tabindex' ) ).toBe( '-1' );
		dialog.close( el( 'dlg' ) );
		expect( el( 'sum' ).hasAttribute( 'tabindex' ) ).toBe( false );
		expect( el( 'ce' ).hasAttribute( 'tabindex' ) ).toBe( false );
	} );
} );
