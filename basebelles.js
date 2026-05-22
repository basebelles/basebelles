( function () {
	var toggle  = document.querySelector( '.bb-transactions-toggle' );
	var panel   = document.getElementById( 'bb-transactions-panel' );
	var overlay = document.getElementById( 'bb-transactions-overlay' );
	var close   = document.querySelector( '.bb-transactions-close' );

	if ( ! toggle || ! panel ) {
		return;
	}

	function openPanel() {
		panel.classList.add( 'is-open' );
		panel.setAttribute( 'aria-hidden', 'false' );
		if ( overlay ) {
			overlay.classList.add( 'is-open' );
			overlay.setAttribute( 'aria-hidden', 'false' );
		}
		toggle.setAttribute( 'aria-expanded', 'true' );
		if ( close ) {
			close.focus();
		}
	}

	function closePanel() {
		panel.classList.remove( 'is-open' );
		panel.setAttribute( 'aria-hidden', 'true' );
		if ( overlay ) {
			overlay.classList.remove( 'is-open' );
			overlay.setAttribute( 'aria-hidden', 'true' );
		}
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.focus();
	}

	toggle.addEventListener( 'click', function () {
		panel.classList.contains( 'is-open' ) ? closePanel() : openPanel();
	} );

	if ( close ) {
		close.addEventListener( 'click', closePanel );
	}

	if ( overlay ) {
		overlay.addEventListener( 'click', closePanel );
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && panel.classList.contains( 'is-open' ) ) {
			closePanel();
		}
	} );
} )();
