/**
 * Delay handling and live polling in blocks/today-game/today-game.js.
 */

import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { boot, singleGame, doubleheader, restPayload } from './helpers.mjs';

describe( 'which phases poll', () => {
	const cases = [
		[ 'live', true ],
		[ 'delayed', true ],
		[ 'postponed', false ],
		[ 'far', false ],
		[ 'near', false ],
		[ 'final', false ],
	];

	for ( const [ phase, shouldPoll ] of cases ) {
		test( `${ phase } ${ shouldPoll ? 'polls' : 'does not poll' }`, () => {
			const page = boot( singleGame( phase, 1 ) );

			assert.equal( page.instance( 1 ).getAttribute( 'data-polling' ), shouldPoll ? 'true' : null );
		} );
	}

	/** A delay ends without warning, so the poll is the only thing watching for it. */
	test( 'a delayed game registers a 60s interval', () => {
		const page = boot( singleGame( 'delayed', 1 ) );

		assert.ok( page.state.intervals.some( ( entry ) => entry.ms === 60000 ) );
	} );
} );

describe( 'a live game going into a rain delay', () => {
	test( 'the badge stops claiming the game is live', async () => {
		const page = boot( singleGame( 'live', 1 ) );
		page.state.payload = restPayload( 'delayed' );

		await page.poll();

		const badge = page.card( 1 ).querySelector( '.game-time' );
		assert.match( badge.textContent, /Rain delay/ );
		assert.doesNotMatch( badge.textContent, /LIVE/ );
		assert.ok( ! badge.classList.contains( 'is-live' ) );
		assert.equal( badge.querySelector( '.tg-live-dot' ), null, 'the pulse must go' );
	} );

	test( 'the phase attribute follows', async () => {
		const page = boot( singleGame( 'live', 1 ) );
		page.state.payload = restPayload( 'delayed' );

		await page.poll();

		assert.equal( page.instance( 1 ).getAttribute( 'data-phase' ), 'delayed' );
	} );

	test( 'the score stays on screen through the delay', async () => {
		const page = boot( singleGame( 'live', 1 ) );
		page.state.payload = restPayload( 'delayed' );

		await page.poll();

		assert.ok( page.card( 1 ).querySelector( '.tg-header-score' ) );
	} );

	test( 'the score panel swaps to the delay card', async () => {
		const page = boot( singleGame( 'live', 1 ) );
		page.state.payload = restPayload( 'delayed' );

		await page.poll();

		assert.ok( page.card( 1 ).querySelector( '.tg-delay-badge' ) );
		assert.equal( page.card( 1 ).querySelector( '.tg-at-bat' ), null );
	} );

	test( 'it polls the endpoint for the right game', async () => {
		const page = boot( singleGame( 'live', 4242 ) );
		page.state.payload = restPayload( 'delayed' );

		await page.poll();

		assert.ok( page.state.fetched[ 0 ].endsWith( '/4242' ), page.state.fetched[ 0 ] );
	} );
} );

describe( 'the delay ending', () => {
	test( 'the LIVE badge and at-bat come back', async () => {
		const page = boot( singleGame( 'delayed', 1 ) );
		page.state.payload = restPayload( 'live' );

		await page.poll();

		const badge = page.card( 1 ).querySelector( '.game-time' );
		assert.ok( badge.classList.contains( 'is-live' ) );
		assert.ok( badge.querySelector( '.tg-live-dot' ) );
		assert.ok( page.card( 1 ).querySelector( '.tg-at-bat' ) );
		assert.equal( page.instance( 1 ).getAttribute( 'data-phase' ), 'live' );
	} );
} );

describe( 'stopping the poll', () => {
	test( 'a delay that gets called off stops polling', async () => {
		const page = boot( singleGame( 'delayed', 1 ) );

		// A postponement means there is no game left to wait for; polling all night would be
		// pointless.
		page.instance( 1 ).setAttribute( 'data-phase', 'postponed' );
		await page.poll();

		assert.equal( page.state.cleared.length, 1 );
		assert.equal( page.instance( 1 ).getAttribute( 'data-polling' ), null );
	} );

	test( 'a finished game stops polling', async () => {
		const page = boot( singleGame( 'live', 1 ) );

		page.instance( 1 ).setAttribute( 'data-phase', 'final' );
		await page.poll();

		assert.equal( page.state.cleared.length, 1 );
	} );

	test( 'a game still delayed keeps polling', async () => {
		const page = boot( singleGame( 'delayed', 1 ) );
		page.state.payload = restPayload( 'delayed' );

		await page.poll();

		assert.equal( page.state.cleared.length, 0 );
		assert.equal( page.instance( 1 ).getAttribute( 'data-polling' ), 'true' );
	} );
} );

describe( 'a doubleheader pill during a delay', () => {
	/**
	 * Game 1 can change state while the reader is looking at Game 2, so the poll has to refresh
	 * the pill of the game it polled -- not the visible one.
	 */
	test( 'the polled game updates its own pill', async () => {
		const page = boot(
			doubleheader(
				[
					{ gamePk: 777001, phase: 'live', label: 'Game 1 · Live' },
					{ gamePk: 777002, phase: 'far', label: 'Game 2 · 5:40 PM EDT' },
				],
				1
			)
		);

		page.state.payload = restPayload( 'delayed', { switcher_label: 'Game 1 · Delayed' } );
		await page.poll();

		assert.equal( page.pill( 777001 ).textContent.trim(), 'Game 1 · Delayed' );
		assert.equal( page.pill( 777002 ).textContent.trim(), 'Game 2 · 5:40 PM EDT', 'the other pill is untouched' );
	} );

	test( 'the hidden game can update without becoming visible', async () => {
		const page = boot(
			doubleheader(
				[
					{ gamePk: 777001, phase: 'live', label: 'Game 1 · Live' },
					{ gamePk: 777002, phase: 'far', label: 'Game 2 · 5:40 PM EDT' },
				],
				1
			)
		);

		page.state.payload = restPayload( 'delayed', { switcher_label: 'Game 1 · Delayed' } );
		await page.poll();

		assert.deepEqual( page.visibleCards(), [ '777002' ] );
	} );
} );

describe( 'bad payloads leave the page alone', () => {
	test( 'a missing time_label does not blank the badge', async () => {
		const page = boot( singleGame( 'live', 1 ) );
		const before = page.card( 1 ).querySelector( '.game-time' ).outerHTML;

		page.state.payload = { phase: 'live', header_score: '', panels: {} };
		await page.poll();

		assert.equal( page.card( 1 ).querySelector( '.game-time' ).outerHTML, before );
	} );

	test( 'a missing switcher_label leaves the pill text alone', async () => {
		const page = boot(
			doubleheader(
				[
					{ gamePk: 777001, phase: 'live', label: 'Game 1 · Live' },
					{ gamePk: 777002, phase: 'far', label: 'Game 2 · 5:40 PM EDT' },
				],
				0
			)
		);

		page.state.payload = restPayload( 'live' );
		await page.poll();

		assert.match( page.pill( 777001 ).textContent, /Game 1 · Live/ );
	} );

	test( 'a failed request leaves the last known content in place', async () => {
		const page = boot( singleGame( 'live', 1 ) );
		const before = page.card( 1 ).innerHTML;

		// payload stays null, so the stub reports ok:false.
		await page.poll();

		assert.equal( page.card( 1 ).innerHTML, before );
	} );
} );
