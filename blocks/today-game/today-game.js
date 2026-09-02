( function () {
	// Keep in step with Basebelles_API::LIVE_FEED_CACHE_TTL (helpers/class-api.php) -- polling
	// faster than that just re-fetches the same cached response.
	var LIVE_POLL_INTERVAL_MS = 60000;

	function setActiveTab( instance, tabName ) {
		var tabs   = instance.querySelectorAll( '.tg-tab' );
		var panels = instance.querySelectorAll( '.tg-panel' );

		for ( var i = 0; i < tabs.length; i++ ) {
			var isActive = tabs[ i ].getAttribute( 'data-tab' ) === tabName;
			tabs[ i ].classList.toggle( 'is-active', isActive );
			tabs[ i ].setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		}

		for ( var j = 0; j < panels.length; j++ ) {
			panels[ j ].classList.toggle( 'is-active', panels[ j ].getAttribute( 'data-panel' ) === tabName );
		}
	}

	function setActiveSubtab( subtabsEl, subtabName ) {
		var subtabs   = subtabsEl.querySelectorAll( '.tg-subtab' );
		var subpanels = subtabsEl.querySelectorAll( '.tg-subpanel' );

		for ( var i = 0; i < subtabs.length; i++ ) {
			var isActive = subtabs[ i ].getAttribute( 'data-subtab' ) === subtabName;
			subtabs[ i ].classList.toggle( 'is-active', isActive );
			subtabs[ i ].setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		}

		for ( var j = 0; j < subpanels.length; j++ ) {
			subpanels[ j ].classList.toggle( 'is-active', subpanels[ j ].getAttribute( 'data-subpanel' ) === subtabName );
		}
	}

	function wireTabs( instance ) {
		// Delegated on the instance root (not the buttons) so it keeps working after a live poll
		// replaces a .tg-panel's innerHTML -- including the Players tab's TOR/CLE sub-tabs.
		instance.addEventListener( 'click', function ( e ) {
			var tab = e.target.closest( '.tg-tab' );

			if ( tab ) {
				setActiveTab( instance, tab.getAttribute( 'data-tab' ) );
				return;
			}

			var subtab = e.target.closest( '.tg-subtab' );

			if ( subtab ) {
				var subtabsEl = subtab.closest( '.tg-subtabs' );

				if ( subtabsEl ) {
					setActiveSubtab( subtabsEl, subtab.getAttribute( 'data-subtab' ) );
				}
			}
		} );
	}

	function formatCountdown( msRemaining ) {
		if ( msRemaining <= 0 ) {
			return '0:00';
		}

		var totalSeconds = Math.floor( msRemaining / 1000 );
		var hours        = Math.floor( totalSeconds / 3600 );
		var minutes       = Math.floor( ( totalSeconds % 3600 ) / 60 );
		var seconds       = totalSeconds % 60;
		var paddedMinutes = ( hours > 0 && minutes < 10 ) ? '0' + minutes : String( minutes );
		var paddedSeconds = seconds < 10 ? '0' + seconds : String( seconds );

		return ( hours > 0 ? hours + ':' + paddedMinutes : minutes ) + ':' + paddedSeconds;
	}

	function wireCountdown( instance ) {
		var countdownEl = instance.querySelector( '.tg-countdown' );

		if ( ! countdownEl ) {
			return;
		}

		var valueEl    = countdownEl.querySelector( '.tg-countdown-value' );
		var firstPitch = new Date( countdownEl.getAttribute( 'data-first-pitch' ) ).getTime();

		if ( ! valueEl || ! firstPitch ) {
			return;
		}

		var tick = window.setInterval( function () {
			var remaining = firstPitch - Date.now();
			valueEl.textContent = formatCountdown( remaining );

			if ( remaining <= 0 ) {
				window.clearInterval( tick );
				refreshInstance( instance, true );
			}
		}, 1000 );
	}

	function applyPanelUpdate( instance, data ) {
		instance.setAttribute( 'data-phase', data.phase );

		var panelNames = [ 'score', 'plays', 'stats', 'players' ];

		for ( var i = 0; i < panelNames.length; i++ ) {
			var panel = instance.querySelector( '.tg-panel[data-panel="' + panelNames[ i ] + '"]' );

			if ( panel && typeof data.panels[ panelNames[ i ] ] === 'string' ) {
				panel.innerHTML = data.panels[ panelNames[ i ] ];
			}
		}
	}

	function refreshInstance( instance, startPollingIfLive ) {
		if ( ! window.bbTodayGame || ! window.bbTodayGame.restUrl ) {
			return;
		}

		var gamePk = instance.getAttribute( 'data-game-pk' );

		fetch( window.bbTodayGame.restUrl + gamePk )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data ) {
					return;
				}

				applyPanelUpdate( instance, data );

				if ( startPollingIfLive && 'live' === data.phase ) {
					startLivePolling( instance );
				}
			} )
			.catch( function () {
				// Network hiccup: leave the last-known content in place and try again on the next tick.
			} );
	}

	function startLivePolling( instance ) {
		if ( instance.getAttribute( 'data-polling' ) ) {
			return;
		}

		instance.setAttribute( 'data-polling', 'true' );

		var poll = window.setInterval( function () {
			if ( 'final' === instance.getAttribute( 'data-phase' ) ) {
				window.clearInterval( poll );
				instance.removeAttribute( 'data-polling' );
				return;
			}

			refreshInstance( instance, false );
		}, LIVE_POLL_INTERVAL_MS );
	}

	var instances = document.querySelectorAll( '.tg-tabs' );

	for ( var k = 0; k < instances.length; k++ ) {
		wireTabs( instances[ k ] );
		wireCountdown( instances[ k ] );

		if ( 'live' === instances[ k ].getAttribute( 'data-phase' ) ) {
			startLivePolling( instances[ k ] );
		}
	}
} )();
