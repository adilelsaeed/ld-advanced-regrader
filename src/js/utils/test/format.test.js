/**
 * Internal dependencies
 */
import { formatDifference, formatPercent, summarize } from '../format';

describe( 'formatDifference', () => {
	it( 'prefixes positive differences with a plus sign', () => {
		expect( formatDifference( 3 ) ).toEqual( {
			label: '+3',
			tone: 'positive',
		} );
	} );

	it( 'keeps the minus sign on negative differences', () => {
		expect( formatDifference( -2 ) ).toEqual( {
			label: '-2',
			tone: 'negative',
		} );
	} );

	it( 'treats zero and invalid values as neutral', () => {
		expect( formatDifference( 0 ).tone ).toBe( 'neutral' );
		expect( formatDifference( undefined ).label ).toBe( '0' );
	} );
} );

describe( 'formatPercent', () => {
	it( 'rounds to two decimals and drops trailing zeros', () => {
		expect( formatPercent( 68.1818 ) ).toBe( '68.18%' );
		expect( formatPercent( 50 ) ).toBe( '50%' );
		expect( formatPercent( 12.5 ) ).toBe( '12.5%' );
	} );
} );

describe( 'summarize', () => {
	it( 'counts changes, direction and pass/fail flips', () => {
		const summary = summarize( [
			{ difference: 2, current_pass: false, new_pass: true },
			{ difference: -1, current_pass: true, new_pass: true },
			{ difference: 0, current_pass: true, new_pass: true },
		] );

		expect( summary ).toEqual( {
			changed: 2,
			up: 1,
			down: 1,
			passFlips: 1,
		} );
	} );
} );
