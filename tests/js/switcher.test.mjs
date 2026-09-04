/**
 * The doubleheader game switcher in blocks/today-game/today-game.js.
 */

import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { boot, doubleheader, singleGame } from './helpers.mjs';

const twoGames = [
	{ gamePk: 777001, phase: 'final', label: 'Game 1 · Final 2–6' },
	{ gamePk: 777002, phase: 'live', label: 'Game 2 · Live' },
];

describe( 'initial render', () => {
	test( 'exactly one card is visible', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		assert.deepEqual( page.visibleCards(), [ '777002' ] );
	} );

	test( 'one pill is active and aria-selected agrees with it', () => {
		const page = boot( doubleheader( twoGames, 1 ) );
		const pills = page.doc.querySelectorAll( '.tg-game-tab' );

		assert.equal( page.doc.querySelectorAll( '.tg-game-tab.is-active' ).length, 1 );

		for ( const pill of pills ) {
			assert.equal(
				pill.getAttribute( 'aria-selected' ) === 'true',
				pill.classList.contains( 'is-active' ),
				'aria-selected must track is-active'
			);
		}
	} );

	test( 'the live pill carries a pulse dot', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		assert.ok( page.pill( 777002 ).querySelector( '.tg-live-dot' ) );
		assert.equal( page.pill( 777001 ).querySelector( '.tg-live-dot' ), null );
	} );
} );

describe( 'switching games', () => {
	test( 'clicking a pill swaps which card is visible', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777001"]' );

		assert.deepEqual( page.visibleCards(), [ '777001' ] );
	} );

	test( 'the clicked pill becomes the active one', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777001"]' );

		assert.ok( page.pill( 777001 ).classList.contains( 'is-active' ) );
		assert.equal( page.pill( 777001 ).getAttribute( 'aria-selected' ), 'true' );
		assert.ok( ! page.pill( 777002 ).classList.contains( 'is-active' ) );
		assert.equal( page.pill( 777002 ).getAttribute( 'aria-selected' ), 'false' );
	} );

	test( 'the switcher records which game is showing', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777001"]' );

		assert.equal( page.switcher().getAttribute( 'data-active-game' ), '777001' );
	} );

	test( 'clicking the already-active pill changes nothing', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777002"]' );

		assert.deepEqual( page.visibleCards(), [ '777002' ] );
		assert.equal( page.doc.querySelectorAll( '.tg-game-tab.is-active' ).length, 1 );
	} );
} );

/**
 * The reason the inactive game is hidden rather than unmounted. The handoff called for
 * unmounting and credited it for capping scroll length, but hidden content has no height
 * either -- and unmounting would throw away everything asserted here.
 */
describe( 'state survives a switch', () => {
	test( 'both games stay mounted', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777001"]' );

		assert.equal( page.switcher().querySelectorAll( '.game-card[data-game-card]' ).length, 2 );
		assert.ok( page.instance( 777001 ) );
		assert.ok( page.instance( 777002 ) );
	} );

	test( 'an inner tab selection survives switching away and back', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777001"]' );
		page.click( '.game-card[data-game-card="777001"] .tg-tab[data-tab="plays"]' );

		assert.equal(
			page.card( 777001 ).querySelector( '.tg-tab.is-active' ).getAttribute( 'data-tab' ),
			'plays'
		);

		page.click( '.tg-game-tab[data-game-tab="777002"]' );
		page.click( '.tg-game-tab[data-game-tab="777001"]' );

		assert.equal(
			page.card( 777001 ).querySelector( '.tg-tab.is-active' ).getAttribute( 'data-tab' ),
			'plays',
			'the Plays tab should still be selected'
		);
	} );

	test( 'the matching panel is the one shown', () => {
		const page = boot( doubleheader( twoGames, 1 ) );

		page.click( '.tg-game-tab[data-game-tab="777001"]' );
		page.click( '.game-card[data-game-card="777001"] .tg-tab[data-tab="stats"]' );

		const active = page.card( 777001 ).querySelectorAll( '.tg-panel.is-active' );
		assert.equal( active.length, 1 );
		assert.equal( active[ 0 ].getAttribute( 'data-panel' ), 'stats' );
	} );
} );

describe( 'multiple switchers on one page', () => {
	test( 'clicking one does not disturb the other', () => {
		const first = doubleheader( twoGames, 1 );
		const second = doubleheader(
			[
				{ gamePk: 777005, phase: 'final', label: 'Game 1 · Final 12–10' },
				{ gamePk: 777006, phase: 'final', label: 'Game 2 · Final 4–3' },
			],
			1
		);
		const page = boot( first + second );

		page.click( '.tg-game-tab[data-game-tab="777005"]' );

		const switchers = page.doc.querySelectorAll( '.tg-doubleheader' );
		assert.equal( switchers[ 1 ].getAttribute( 'data-active-game' ), '777005' );
		assert.equal( switchers[ 0 ].getAttribute( 'data-active-game' ), '777002' );
	} );
} );

describe( 'single game', () => {
	test( 'no switcher is wired and the card is not hidden', () => {
		const page = boot( singleGame( 'far', 1 ) );

		assert.equal( page.doc.querySelector( '.tg-doubleheader' ), null );
		assert.equal( page.doc.querySelector( '.game-card' ).hidden, false );
	} );

	test( 'the inner tabs still work', () => {
		const page = boot( singleGame( 'far', 1 ) );

		page.click( '.tg-tab[data-tab="stats"]' );

		assert.equal(
			page.doc.querySelector( '.tg-tab.is-active' ).getAttribute( 'data-tab' ),
			'stats'
		);
	} );
} );
