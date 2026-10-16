/**
 * The tab bar records which tab is open, so the CSS can keep the first-pitch forecast on Status.
 */

import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { boot, singleGame } from './helpers.mjs';

describe( 'active tab', () => {
	test( 'switching tabs records the open one on .tg-tabs', () => {
		const page = boot( singleGame( 'near', 1 ) );
		const tabs = page.doc.querySelector( '.tg-tabs' );

		page.click( '.tg-tab[data-tab="stats"]' );
		assert.equal( tabs.getAttribute( 'data-active-tab' ), 'stats' );

		page.click( '.tg-tab[data-tab="score"]' );
		assert.equal( tabs.getAttribute( 'data-active-tab' ), 'score' );
	} );
} );
