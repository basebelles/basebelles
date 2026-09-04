/**
 * Shared jsdom harness for the today-game block script.
 *
 * The markup below mirrors what blocks/today-game/render.php emits. It is deliberately hand-built
 * rather than generated: these tests are about what today-game.js does to a DOM, and the PHP suite
 * is what proves the PHP produces that DOM. If the two drift, TodayGameBlockTest is the one that
 * should fail first.
 */

import fs from 'node:fs';
import path from 'node:path';
import { JSDOM } from 'jsdom';

const PLUGIN_DIR = path.resolve( import.meta.dirname, '../..' );
const SCRIPT_PATH = path.join( PLUGIN_DIR, 'blocks/today-game/today-game.js' );

export const script = fs.readFileSync( SCRIPT_PATH, 'utf8' );

/** The four Status/Plays/Stats/Players buttons and their panels. */
function tabsMarkup( gamePk, phase, scorePanel ) {
	const names = [ 'score', 'plays', 'stats', 'players' ];
	const labels = { score: 'Status', plays: 'Plays', stats: 'Stats', players: 'Players' };

	const bar = names
		.map(
			( name, i ) =>
				`<button type="button" class="tg-tab${ i === 0 ? ' is-active' : '' }" data-tab="${ name }" role="tab" aria-selected="${ i === 0 }">${ labels[ name ] }</button>`
		)
		.join( '' );

	const panels = names
		.map(
			( name, i ) =>
				`<div class="tg-panel${ i === 0 ? ' is-active' : '' }" data-panel="${ name }">${ name === 'score' ? scorePanel : name }</div>`
		)
		.join( '' );

	return `<div class="tg-tabs" data-game-pk="${ gamePk }" data-phase="${ phase }"><div class="tg-tab-bar" role="tablist">${ bar }</div>${ panels }</div>`;
}

/** The .game-time badge for a phase, matching Panels::render_time_label(). */
export function timeLabel( phase, delayLabel = 'Rain delay' ) {
	if ( phase === 'live' ) {
		return '<div class="game-time is-live"><span class="tg-live-dot" aria-hidden="true"></span>LIVE</div>';
	}
	if ( phase === 'delayed' ) {
		return `<div class="game-time is-delayed">${ delayLabel }</div>`;
	}
	if ( phase === 'postponed' ) {
		return '<div class="game-time is-postponed">Postponed</div>';
	}
	return '<div class="game-time">2:10 PM EDT</div>';
}

/**
 * One .game-card. Pass doubleheader:true to get the data-game-card attribute the switcher
 * needs, and hidden:true for the inactive game.
 */
export function gameCard( { gamePk, phase, doubleheader = false, hidden = false, scorePanel = 'score', headerScore = '' } ) {
	const attrs = [ 'class="game-card"' ];
	if ( doubleheader ) {
		attrs.push( `data-game-card="${ gamePk }"` );
	}
	if ( hidden ) {
		attrs.push( 'hidden' );
	}

	return `<div ${ attrs.join( ' ' ) }>
		<div class="today-game-header"><div class="game-meta">Fri 9/4<br />${ timeLabel( phase ) }${ headerScore }</div></div>
		${ tabsMarkup( gamePk, phase, scorePanel ) }
	</div>`;
}

/** A single-game block. */
export function singleGame( phase, gamePk = 1 ) {
	return `<div class="basebelles-today-game">${ gameCard( { gamePk, phase } ) }</div>`;
}

/**
 * A doubleheader block.
 *
 * @param {Array<{gamePk: number, phase: string, label: string}>} games
 * @param {number} activeIndex Which game starts visible.
 */
export function doubleheader( games, activeIndex = 0 ) {
	const pills = games
		.map( ( game, i ) => {
			const dot = game.phase === 'live' ? '<span class="tg-live-dot" aria-hidden="true"></span>' : '';
			return `<button type="button" class="tg-game-tab${ i === activeIndex ? ' is-active' : '' }" data-game-tab="${ game.gamePk }" role="tab" aria-selected="${ i === activeIndex }">${ dot }${ game.label }</button>`;
		} )
		.join( '' );

	const cards = games
		.map( ( game, i ) =>
			gameCard( { gamePk: game.gamePk, phase: game.phase, doubleheader: true, hidden: i !== activeIndex } )
		)
		.join( '' );

	return `<div class="basebelles-today-game has-doubleheader">
		<div class="tg-doubleheader" data-active-game="${ games[ activeIndex ].gamePk }">
			<div class="tg-dh-heading">Fri 9/4 · Tigers @ Guardians</div>
			<div class="tg-game-switcher" role="tablist" aria-label="Doubleheader games">${ pills }</div>
			<div class="tg-dh-panel">${ cards }</div>
		</div>
	</div>`;
}

/**
 * Boot a page with today-game.js running against it.
 *
 * setInterval is captured rather than left to run, so a test can drive the 60s poll by hand
 * instead of waiting for it. fetch is stubbed and its payload is settable per test.
 *
 * @param {string} body Markup for the page body.
 * @returns {object} Handles for driving and inspecting the page.
 */
export function boot( body ) {
	const dom = new JSDOM( `<!doctype html><body>${ body }</body>`, {
		runScripts: 'outside-only',
		pretendToBeVisual: true,
	} );

	const { window } = dom;
	const state = {
		intervals: [],
		cleared: [],
		fetched: [],
		payload: null,
	};

	const realSetTimeout = window.setTimeout.bind( window );

	window.setInterval = ( fn, ms ) => {
		state.intervals.push( { fn, ms } );
		return state.intervals.length;
	};
	window.clearInterval = ( id ) => {
		state.cleared.push( id );
	};

	window.fetch = ( url ) => {
		state.fetched.push( String( url ) );

		return Promise.resolve( {
			ok: state.payload !== null,
			json: () => Promise.resolve( state.payload ),
		} );
	};

	window.bbTodayGame = { restUrl: 'https://example.test/wp-json/basebelles/v1/today-game/' };
	window.eval( script );

	const doc = window.document;

	return {
		window,
		doc,
		state,

		/** Let queued promise callbacks run. */
		flush: () => new Promise( ( resolve ) => realSetTimeout( resolve, 0 ) ),

		instance: ( gamePk ) => doc.querySelector( `.tg-tabs[data-game-pk="${ gamePk }"]` ),
		card: ( gamePk ) => doc.querySelector( `.tg-tabs[data-game-pk="${ gamePk }"]` ).closest( '.game-card' ),
		switcher: () => doc.querySelector( '.tg-doubleheader' ),
		pill: ( gamePk ) => doc.querySelector( `.tg-game-tab[data-game-tab="${ gamePk }"]` ),

		click: ( selector ) => {
			doc.querySelector( selector ).dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		},

		/** game_pks of the cards currently visible. */
		visibleCards: () =>
			Array.from( doc.querySelectorAll( '.game-card[data-game-card]' ) )
				.filter( ( card ) => ! card.hidden )
				.map( ( card ) => card.getAttribute( 'data-game-card' ) ),

		/** Run the 60s poll callback for the nth polling instance. */
		poll: async ( index = 0 ) => {
			const polls = state.intervals.filter( ( entry ) => entry.ms === 60000 );
			polls[ index ].fn();
			await new Promise( ( resolve ) => realSetTimeout( resolve, 0 ) );
			await new Promise( ( resolve ) => realSetTimeout( resolve, 0 ) );
		},
	};
}

/** A REST payload shaped like features/class-today-game-rest.php returns. */
export function restPayload( phase, overrides = {} ) {
	const panels = {
		score: phase === 'delayed' ? '<div class="tg-delay"><div class="tg-delay-badge">Rain delay</div></div>' : '<div class="tg-at-bat">at bat</div>',
		plays: 'plays',
		stats: 'stats',
		players: 'players',
	};

	return {
		phase,
		time_label: timeLabel( phase ),
		header_score: '<div class="tg-header-score">3 &ndash; 1</div>',
		switcher_label: null,
		panels,
		...overrides,
	};
}
