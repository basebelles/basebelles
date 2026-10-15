/**
 * The first-pitch countdown chip in blocks/today-game/today-game.js.
 */

import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { boot, gameCard } from './helpers.mjs';

/** A near-phase card whose Status panel holds the countdown, as Panels::render_countdown_timer() emits it. */
function countdownGame( firstPitch ) {
	const scorePanel = `<div class="tg-countdown" data-first-pitch="${ firstPitch.toISOString() }">
		<span class="tg-countdown-label">First pitch in</span>
		<span class="tg-countdown-value">&hellip;</span>
	</div>`;

	return `<div class="basebelles-today-game">${ gameCard( { gamePk: 1, phase: 'near', scorePanel } ) }</div>`;
}

describe( 'countdown', () => {
	test( 'fills in on load instead of waiting a second', () => {
		const page = boot( countdownGame( new Date( Date.now() + ( 79 * 60 + 35 ) * 1000 ) ) );

		assert.match( page.doc.querySelector( '.tg-countdown-value' ).textContent, /^1:19:3\d$/ );
	} );

	test( 'keeps ticking every second', () => {
		const page = boot( countdownGame( new Date( Date.now() + 600000 ) ) );

		assert.ok( page.state.intervals.some( ( entry ) => entry.ms === 1000 ) );
	} );

	test( 'the forecast goes when the countdown runs out', () => {
		const withWeather = ( html ) => html.replace( /\n\t<\/div><\/div>$/, '<div class="tg-weather">68°F</div>\n\t</div></div>' );

		const before = boot( withWeather( countdownGame( new Date( Date.now() + 600000 ) ) ) );
		assert.equal( before.doc.querySelector( '.tg-weather' ).hidden, false );

		const after = boot( withWeather( countdownGame( new Date( Date.now() - 1000 ) ) ) );
		assert.equal( after.doc.querySelector( '.tg-weather' ).hidden, true );
	} );

	test( 'a first pitch already past refreshes the card instead of ticking', () => {
		const page = boot( countdownGame( new Date( Date.now() - 1000 ) ) );

		assert.equal( page.doc.querySelector( '.tg-countdown-value' ).textContent, '0:00' );
		assert.equal( page.state.intervals.some( ( entry ) => entry.ms === 1000 ), false );
		assert.equal( page.state.fetched.length, 1 );
	} );
} );
