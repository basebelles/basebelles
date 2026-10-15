/**
 * Plays / Players tabs that start disabled and switch on once a poll brings their content.
 */

import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { boot, singleGame, restPayload } from './helpers.mjs';

const EMPTY_PLAYS = '<p class="tg-empty">No plays yet.</p>';

/** A live single game whose Plays tab is pending, as Panels::render() emits it with no plays. */
function pendingPlaysGame() {
	return singleGame( 'live', 1 )
		.replace(
			'data-tab="plays" role="tab" aria-selected="false">',
			'data-tab="plays" role="tab" aria-selected="false" data-pending-title="Plays start at first pitch" disabled title="Plays start at first pitch">'
		)
		.replace( '<div class="tg-panel" data-panel="plays">plays</div>', `<div class="tg-panel" data-panel="plays">${ EMPTY_PLAYS }</div>` );
}

describe( 'pending tabs', () => {
	test( 'the harness markup really starts with Plays disabled', () => {
		const page = boot( pendingPlaysGame() );
		const plays = page.doc.querySelector( '.tg-tab[data-tab="plays"]' );

		assert.equal( plays.disabled, true );
	} );

	test( 'Plays switches on when a poll brings plays', async () => {
		const page = boot( pendingPlaysGame() );
		page.state.payload = restPayload( 'live' );
		page.state.payload.panels.plays = '<ul class="tg-plays"><li>Single to right.</li></ul>';

		await page.poll();

		const plays = page.doc.querySelector( '.tg-tab[data-tab="plays"]' );
		assert.equal( plays.disabled, false );
		assert.equal( plays.hasAttribute( 'title' ), false );
	} );

	test( 'Plays stays disabled while the poll still has nothing', async () => {
		const page = boot( pendingPlaysGame() );
		page.state.payload = restPayload( 'live' );
		page.state.payload.panels.plays = EMPTY_PLAYS;

		await page.poll();

		const plays = page.doc.querySelector( '.tg-tab[data-tab="plays"]' );
		assert.equal( plays.disabled, true );
		assert.equal( plays.title, 'Plays start at first pitch' );
	} );

	test( 'tabs without a pending note are left alone', async () => {
		const page = boot( pendingPlaysGame() );
		page.state.payload = restPayload( 'live' );
		page.state.payload.panels.stats = EMPTY_PLAYS;

		await page.poll();

		assert.equal( page.doc.querySelector( '.tg-tab[data-tab="stats"]' ).disabled, false );
	} );
} );
