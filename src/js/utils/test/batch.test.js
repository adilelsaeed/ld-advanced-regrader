/**
 * Internal dependencies
 */
import { runBatches } from '../batch';

/**
 * Fake server that processes `size` rows per call out of `total`.
 *
 * @param {number} total Total rows.
 * @param {number} size  Batch size.
 * @return {Function} jest mock.
 */
const fakeServer = ( total, size ) =>
	jest.fn( async ( offset ) => {
		const processed = Math.max( 0, Math.min( size, total - offset ) );
		return {
			processed,
			updated: processed,
			total,
			next_offset: offset + processed,
			done: offset + processed >= total,
		};
	} );

describe( 'runBatches', () => {
	it( 'requests sequential offsets until the server is done', async () => {
		const request = fakeServer( 120, 50 );
		const onProgress = jest.fn();

		const summary = await runBatches( request, { onProgress } );

		expect( request.mock.calls.map( ( [ offset ] ) => offset ) ).toEqual( [
			0, 50, 100,
		] );
		expect( onProgress ).toHaveBeenLastCalledWith( {
			processed: 120,
			total: 120,
			updated: 120,
		} );
		expect( summary ).toEqual( {
			processed: 120,
			total: 120,
			updated: 120,
			stopped: false,
		} );
	} );

	it( 'stops before the next batch when asked', async () => {
		const request = fakeServer( 500, 50 );
		let calls = 0;

		const summary = await runBatches( request, {
			onProgress: () => calls++,
			shouldStop: () => calls >= 2,
		} );

		expect( request ).toHaveBeenCalledTimes( 2 );
		expect( summary.stopped ).toBe( true );
		expect( summary.processed ).toBe( 100 );
	} );

	it( 'does not loop forever when a batch makes no progress', async () => {
		const request = jest.fn( async () => ( {
			processed: 0,
			updated: 0,
			total: 10,
			next_offset: 0,
			done: false,
		} ) );

		await runBatches( request );

		expect( request ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'propagates request errors', async () => {
		const request = jest.fn().mockRejectedValue( new Error( 'timeout' ) );

		await expect( runBatches( request ) ).rejects.toThrow( 'timeout' );
	} );
} );
