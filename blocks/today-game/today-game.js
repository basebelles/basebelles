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
					var chosen = subtab.getAttribute( 'data-subtab' );
					setActiveSubtab( subtabsEl, chosen );
					// Remembered so a live poll's re-render (which defaults to whichever team is
					// batting) doesn't yank the view back once someone's picked a side themselves.
					subtabsEl.setAttribute( 'data-manual-subtab', chosen );
				}
			}
		} );
	}

	function setActiveGame( switcherRoot, gamePk ) {
		var tabs  = switcherRoot.querySelectorAll( '.tg-game-tab' );
		var cards = switcherRoot.querySelectorAll( '.game-card[data-game-card]' );

		for ( var i = 0; i < tabs.length; i++ ) {
			var isActive = tabs[ i ].getAttribute( 'data-game-tab' ) === gamePk;
			tabs[ i ].classList.toggle( 'is-active', isActive );
			tabs[ i ].setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		}

		for ( var j = 0; j < cards.length; j++ ) {
			// Hidden rather than detached: the inactive game keeps its .tg-tabs instance, so its
			// live polling, its countdown interval, and whichever Status/Plays/Stats/Players and
			// team sub-tab the reader picked all survive being switched away from and back.
			// Hidden content contributes no height either, so the scroll saving is the same.
			cards[ j ].hidden = cards[ j ].getAttribute( 'data-game-card' ) !== gamePk;
		}

		switcherRoot.setAttribute( 'data-active-game', gamePk );
	}

	function wireGameSwitcher( switcherRoot ) {
		switcherRoot.addEventListener( 'click', function ( e ) {
			var gameTab = e.target.closest( '.tg-game-tab' );

			if ( gameTab ) {
				setActiveGame( switcherRoot, gameTab.getAttribute( 'data-game-tab' ) );
			}
		} );
	}

	// A game can change state while the reader is looking at the other one -- Game 1 going final
	// while Game 2 is up -- so the poll refreshes that game's pill label, not just its panels.
	function updateSwitcherLabel( instance, labelHtml ) {
		if ( 'string' !== typeof labelHtml || ! labelHtml ) {
			return;
		}

		var card = instance.closest( '.game-card[data-game-card]' );

		if ( ! card ) {
			return;
		}

		var switcherRoot = card.closest( '.tg-doubleheader' );
		var tab = switcherRoot
			? switcherRoot.querySelector( '.tg-game-tab[data-game-tab="' + card.getAttribute( 'data-game-card' ) + '"]' )
			: null;

		if ( tab ) {
			tab.innerHTML = labelHtml;
		}
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

	// The whole element is swapped for the server's version rather than rebuilt here: LIVE, a
	// delay badge, and the scheduled time all differ in class and content, and only the server
	// knows a delay's cause. Falls back to leaving the badge alone if the payload omits it.
	function updateTimeLabel( instance, timeLabelHtml ) {
		if ( 'string' !== typeof timeLabelHtml || ! timeLabelHtml ) {
			return;
		}

		var card = instance.closest( '.game-card' );
		var timeEl = card ? card.querySelector( '.game-time' ) : null;

		if ( timeEl ) {
			timeEl.outerHTML = timeLabelHtml;
		}
	}

	// Live and delayed both need the 60s poll: a delay ends without warning, and the poll is the
	// only thing watching for it.
	function isPolledPhase( phase ) {
		return 'live' === phase || 'delayed' === phase;
	}

	function updateHeaderScore( instance, headerScoreHtml ) {
		var card = instance.closest( '.game-card' );
		var meta = card ? card.querySelector( '.game-meta' ) : null;

		if ( ! meta ) {
			return;
		}

		var existing = meta.querySelector( '.tg-header-score' );

		if ( ! headerScoreHtml ) {
			if ( existing ) {
				existing.remove();
			}

			return;
		}

		if ( existing ) {
			existing.outerHTML = headerScoreHtml;
		} else {
			var timeEl = meta.querySelector( '.game-time' );

			if ( timeEl ) {
				timeEl.insertAdjacentHTML( 'afterend', headerScoreHtml );
			}
		}
	}

	function applyPanelUpdate( instance, data ) {
		instance.setAttribute( 'data-phase', data.phase );
		updateTimeLabel( instance, data.time_label );
		updateHeaderScore( instance, data.header_score );
		updateSwitcherLabel( instance, data.switcher_label );

		var panelNames = [ 'score', 'plays', 'stats', 'players' ];

		for ( var i = 0; i < panelNames.length; i++ ) {
			var panel = instance.querySelector( '.tg-panel[data-panel="' + panelNames[ i ] + '"]' );

			if ( ! panel || typeof data.panels[ panelNames[ i ] ] !== 'string' ) {
				continue;
			}

			// The fresh Players HTML defaults to whichever team is batting; if someone already
			// picked a side manually, carry that choice forward instead of overriding it.
			var oldSubtabs   = panel.querySelector( '.tg-subtabs' );
			var manualSubtab = oldSubtabs ? oldSubtabs.getAttribute( 'data-manual-subtab' ) : null;

			panel.innerHTML = data.panels[ panelNames[ i ] ];

			if ( manualSubtab ) {
				var newSubtabs = panel.querySelector( '.tg-subtabs' );

				if ( newSubtabs ) {
					newSubtabs.setAttribute( 'data-manual-subtab', manualSubtab );
					setActiveSubtab( newSubtabs, manualSubtab );
				}
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

				if ( startPollingIfLive && isPolledPhase( data.phase ) ) {
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
			var phase = instance.getAttribute( 'data-phase' );

			// Postponed as well as final: a delay that gets called off would otherwise keep
			// polling all night for a game that is no longer going to be played.
			if ( 'final' === phase || 'postponed' === phase ) {
				window.clearInterval( poll );
				instance.removeAttribute( 'data-polling' );
				return;
			}

			refreshInstance( instance, false );
		}, LIVE_POLL_INTERVAL_MS );
	}

	var switchers = document.querySelectorAll( '.tg-doubleheader' );

	for ( var s = 0; s < switchers.length; s++ ) {
		wireGameSwitcher( switchers[ s ] );
	}

	var instances = document.querySelectorAll( '.tg-tabs' );

	for ( var k = 0; k < instances.length; k++ ) {
		wireTabs( instances[ k ] );
		wireCountdown( instances[ k ] );

		if ( isPolledPhase( instances[ k ].getAttribute( 'data-phase' ) ) ) {
			startLivePolling( instances[ k ] );
		}
	}
} )();
